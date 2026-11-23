<?php
declare(strict_types=1);
use App\Models\{User,Shipment,Order};
use App\Services\LogisticsAccess;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;

final class MobileRider {
    public function __construct(private Identity $identity) {}
    public static function installed(): bool { return Illuminate\Support\Facades\Schema::hasTable('mobile_rider_presence'); }
    public static function countJobs(int $id): int {
        return app(App\Services\RiderDeliveryOffers::class)->activeJobs($id);
    }
    public static function eligible(User $rider): bool {
        return app(App\Services\RiderDeliveryOffers::class)->candidates((string)$rider->business_name)->where('users.id',$rider->id)->exists();
    }
    public static function audit(int $actor,string $action,string $subject,mixed $old,mixed $new): void {
        DB::table('mobile_rider_audit')->insert(['id'=>(string)Str::uuid(),'actor_id'=>$actor,'action'=>$action,'subject_id'=>$subject,'old_value'=>$old===null?null:json_encode($old),'new_value'=>json_encode($new),'created_at'=>now()]);
    }
    public static function offline(int $id): void {
        User::whereKey($id)->update(['is_available'=>false,'availability_updated_at'=>now()]);
    }
    public static function sweep(): void {
        // Availability and offer expiration follow the web; an app heartbeat must
        // never take a rider who is using the website offline.
        app(App\Services\RiderDeliveryOffers::class)->expireOutstanding();
        self::reminders();
    }
    private static function reminders(): void {
        $minutes=max(1,(int)(getenv('RIDER_REMINDER_MINUTES')?:60));
        foreach(Shipment::whereIn('shipping_status',['ready_for_pickup','assigned_to_rider','out_for_delivery'])->where('updated_at','<=',now()->subMinutes($minutes))->get(['id']) as $candidate) {
            DB::transaction(function() use($candidate) {
                $s=Shipment::whereKey($candidate->id)->lockForUpdate()->first();
                $rider=$s->shipping_status==='ready_for_pickup'?$s->pickup_rider_id:$s->courier_id;
                if(!$rider || !in_array($s->shipping_status,['ready_for_pickup','assigned_to_rider','out_for_delivery'],true))return;
                $title=$s->shipping_status==='out_for_delivery'?'Delivery reminder':'Pickup reminder';
                if(DB::table('notifications')->where('user_id',$rider)->where('reference_id',$s->id)->where('title',$title)->where('created_at','>=',now()->startOfDay())->exists())return;
                Order::notifyUser($rider,$title,'Tracking '.$s->tracking_number.' still needs your next update.','rider_task',$s->id);
            },3);
        }
    }
    public static function accept(User $rider,string $shipment,string $leg): void {
        $rider=User::whereKey($rider->id)->lockForUpdate()->firstOrFail();
        app(App\Services\RiderDeliveryOffers::class)->accept($rider,$shipment,$leg);
    }
    public function handle(string $action,array $data): mixed {
        $uid=$this->identity->approved();
        if(in_array($action,['confirm-remittance','vehicle-decision','assign','staff-overview','dispatch-point'],true)) return $this->staff($action,$data);
        $this->identity->approved('rider');
        if(in_array($action,['availability','presence'],true)) {
            // Legacy presence requests remain accepted during the APK upgrade.
            $data['is_available']=$data['is_available']??$data['online']??null;
            validateData($data,['is_available'=>'required|boolean']);
            $this->identity->user->forceFill(['is_available'=>(bool)$data['is_available'],'availability_updated_at'=>now()])->save();
            if($data['is_available'])$this->dispatch();
            return ['is_available'=>(bool)$data['is_available']];
        }
        if(in_array($action,['assignments','offers'],true)) {
            self::sweep();$this->dispatch();
            return cleanRows(DB::table('delivery_assignments')->where('courier_id',$uid)->where('status','offered')->get());
        }
        if($action==='decline') {
            validateData($data,['id'=>'required|uuid']);
            $assignment=App\Models\DeliveryAssignment::where('courier_id',$uid)->findOrFail($data['id']);
            app(App\Services\RiderDeliveryOffers::class)->decline($this->identity->user,$assignment->shipment_id,$assignment->leg);
            return ['declined'=>true];
        }
        if($action==='earnings') {
            self::sweep();
            $rows=cleanRows(DB::table('mobile_rider_pay as p')->join('shipments as s','s.id','=','p.shipment_id')->where('p.rider_id',$uid)->select('p.*','s.tracking_number','s.shipping_status')->orderByDesc('p.created_at')->get());
            $collected=(float)DB::table('mobile_rider_pay')->where('rider_id',$uid)->sum('cod_amount');
            $remitted=(float)DB::table('mobile_rider_remittances')->where('rider_id',$uid)->where('status','confirmed')->sum('amount');
            return app(App\Services\RiderPayouts::class)->summary($uid)+['today_credited'=>(float)DB::table('mobile_rider_pay')->where('rider_id',$uid)->where('created_at','>=',now()->startOfDay())->sum('amount'),'jobs'=>$rows,'collected'=>$collected,'remitted'=>$remitted,'outstanding'=>round(max(0,$collected-$remitted),2),
                'remittances'=>cleanRows(DB::table('mobile_rider_remittances')->where('rider_id',$uid)->latest()->get())];
        }
        if($action==='remit') {
            validateData($data,['request_id'=>'required|uuid','amount'=>'required|numeric|min:0.01','reference'=>'required|string|max:150']);
            return MobileRequests::run('remit:'.$uid,$data['request_id'],$data,function() use($uid,$data) {
                User::whereKey($uid)->lockForUpdate()->firstOrFail();
                $balance=(float)DB::table('mobile_rider_pay')->where('rider_id',$uid)->sum('cod_amount')-(float)DB::table('mobile_rider_remittances')->where('rider_id',$uid)->whereIn('status',['pending','confirmed'])->sum('amount');
                ensure($data['amount']<=$balance,'The amount exceeds COD available to remit.');
                DB::table('mobile_rider_remittances')->insert(['id'=>$data['request_id'],'rider_id'=>$uid,'company'=>$this->identity->user->business_name,'amount'=>$data['amount'],'reference'=>$data['reference'],'created_at'=>now()]);
                $this->notifyStaff('COD remittance pending','A rider submitted COD for confirmation.',$data['request_id']);
                return ['id'=>$data['request_id'],'status'=>'pending'];
            });
        }
        if($action==='profile') return ['user'=>cleanRow($this->identity->user->getAttributes()),'requests'=>cleanRows(DB::table('mobile_rider_vehicle_requests')->where('rider_id',$uid)->latest()->get())];
        if($action==='history') {
            self::sweep();$rows=[];
            foreach(Shipment::where(fn($q)=>$q->where('courier_id',$uid)->orWhere('pickup_rider_id',$uid))->get() as $s) {
                foreach(['pickup','delivery'] as $leg) {
                    if(($leg==='pickup'?(int)$s->pickup_rider_id:(int)$s->courier_id)!==$uid)continue;
                    $pay=DB::table('mobile_rider_pay')->where('shipment_id',$s->id)->where('leg',$leg)->first();
                    $attempts=DB::table('delivery_attempts')->where('shipment_id',$s->id)->where('rider_id',$uid)->count();
                    if(!$pay && !$attempts && !$s->picked_up_at && !in_array($s->shipping_status,['returned','return_in_transit','delivered','completed','cancelled'],true) && $s->order?->status!=='cancelled')continue;
                    $rows[]=['shipment_id'=>$s->id,'leg'=>$leg,'tracking_number'=>$s->tracking_number,'result'=>$leg==='pickup'&&$s->picked_up_at?'picked_up':$s->shipping_status,'amount'=>(float)($pay?->amount??0),'attempt_count'=>$leg==='delivery'?$attempts:0,'created_at'=>$pay?->created_at??$s->updated_at?->toIso8601String()];
                    $last=count($rows)-1;
                    if($s->order?->status==='cancelled')$rows[$last]['result']='cancelled';
                    $rows[$last]['events']=cleanRows(DB::table('delivery_events')->where('shipment_id',$s->id)->where('actor_id',$uid)->whereIn('action',$leg==='pickup'?['seller-pickup-accept','seller-pickup-arrive','seller-pickup-confirm']:['assignment-accept','arrive','pickup','start','verify-otp','fail','reschedule'])->orderBy('created_at')->get(['action','status','created_at']));
                }
            }
            usort($rows,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));return $rows;
        }
        if($action==='vehicle') {
            foreach(['plate_number','license_number'] as $field)if(isset($data[$field]))$data[$field]=strtoupper(trim($data[$field]));
            validateData($data,['request_id'=>'required|uuid','vehicle_type'=>'required|in:two_wheels,four_wheels','vehicle_brand'=>'required|string|max:100','vehicle_model'=>'required|string|max:100',
                'plate_number'=>'required|string|max:20','license_number'=>['required','regex:/^[A-Z][0-9]{2}-[0-9]{2}-[0-9]{6}$/D'],'license_expiry'=>'required|date|after_or_equal:today','license_file'=>'required|string','or_cr_file'=>'required|string']);
            return MobileRequests::run('vehicle:'.$uid,$data['request_id'],$data,function() use($uid,$data) {
                User::whereKey($uid)->lockForUpdate()->firstOrFail();
                ensure(!DB::table('mobile_rider_vehicle_requests')->where('rider_id',$uid)->where('status','pending')->exists(),'Your previous vehicle change is awaiting verification.');
                foreach(['plate_number','license_number'] as $field) {
                    ensure(!User::where('id','!=',$uid)->where($field,$data[$field])->exists(),'This '.str_replace('_',' ',$field).' is already registered.');
                    DB::table('mobile_rider_identifiers')->insertOrIgnore(['kind'=>$field,'value'=>$data[$field],'rider_id'=>$uid]);
                    ensure((int)DB::table('mobile_rider_identifiers')->where('kind',$field)->where('value',$data[$field])->lockForUpdate()->value('rider_id')===$uid,'This identifier is reserved for another rider verification.');
                }
                foreach(['license_file','or_cr_file'] as $field) self::document($data[$field],$uid);
                DB::table('mobile_rider_vehicle_requests')->insert(['id'=>$data['request_id'],'rider_id'=>$uid,'payload'=>json_encode($data),'created_at'=>now()]);
                $this->notifyStaff('Vehicle re-verification','A rider submitted updated vehicle documents.',$data['request_id']);
                return ['status'=>'pending'];
            });
        }
        throw new ApiError('Unknown rider action.',404);
    }
    public static function document(string $path,int $uid): void {
        ensure(str_starts_with($path,$uid.'/') && !str_contains($path,'..'),'Use your own uploaded document.',403);
        ensure(Storage::disk('local')->exists('rider-documents/'.$path),'Upload your document first.');
    }
    private function notifyStaff(string $title,string $body,string $id): void {
        foreach(User::where('status','approved')->where('business_name',$this->identity->user->business_name)->where(function($q){$q->where('is_logistics',true)->orWhere('is_admin',true);})->pluck('id') as $staff) Order::notifyUser($staff,$title,$body,'rider_admin',$id);
    }
    public function dispatch(): void {
        $offers=app(App\Services\RiderDeliveryOffers::class);
        $offers->expireOutstanding();
        Shipment::where('logistics_company',$this->identity->user->business_name)->where('shipping_status','ready_for_pickup')->whereNotNull('pickup_approved_at')->whereNull('pickup_rider_id')->get()->each(fn($s)=>$offers->offer($s,'pickup'));
        Shipment::where('logistics_company',$this->identity->user->business_name)->where('shipping_status','sorted')->whereNull('courier_id')->get()->each(fn($s)=>$offers->offer($s,'delivery'));
        MobilePush::assignmentNotifications();
    }
    private function staff(string $action,array $data): array {
        $actor=$this->identity->user;
        ensure($actor->is_logistics || $actor->is_admin,'Logistics authorization required.',403);
        if($action==='staff-overview') {
            self::sweep();$users=User::where('account_type','rider')->where('business_name',$actor->business_name)->get();$riders=[];
            foreach($users as $r){$p=DB::table('mobile_rider_presence')->where('rider_id',$r->id)->first();$collected=(float)DB::table('mobile_rider_pay')->where('rider_id',$r->id)->sum('cod_amount');$paid=(float)DB::table('mobile_rider_remittances')->where('rider_id',$r->id)->where('status','confirmed')->sum('amount');
                $riders[]=['id'=>$r->id,'name'=>trim($r->given_names.' '.$r->last_name),'online'=>self::eligible($r),'presence'=>cleanRow($p),'active_jobs'=>self::countJobs($r->id),'cod_outstanding'=>round(max(0,$collected-$paid),2)];}
            return ['riders'=>$riders,'remittances'=>cleanRows(DB::table('mobile_rider_remittances')->where('company',$actor->business_name)->latest()->get()),'vehicle_requests'=>cleanRows(DB::table('mobile_rider_vehicle_requests')->whereIn('rider_id',$users->pluck('id'))->latest()->get()),'unassigned'=>cleanRows(Shipment::where('logistics_company',$actor->business_name)->where(fn($q)=>$q->where(fn($s)=>$s->where('shipping_status','sorted')->whereNull('courier_id'))->orWhere(fn($s)=>$s->where('shipping_status','ready_for_pickup')->whereNull('pickup_rider_id')))->get(['id','tracking_number','shipping_status']))];
        }
        if($action==='dispatch-point') {
            validateData($data,['hub_id'=>'required|integer','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180']);
            $hub=App\Models\LogisticsHub::findOrFail($data['hub_id']);ensure($actor->is_admin || $hub->company_name===$actor->business_name,'Another company owns this hub.',403);
            DB::table('mobile_rider_dispatch_points')->updateOrInsert(['hub_id'=>$hub->id],['latitude'=>$data['latitude'],'longitude'=>$data['longitude']]);
            self::audit($actor->id,$action,(string)$hub->id,null,$data);return ['saved'=>true];
        }
        validateData($data,['id'=>'required|uuid']);
        return DB::transaction(function() use($action,$data,$actor) {
            if($action==='confirm-remittance') {
                $q=DB::table('mobile_rider_remittances')->where('id',$data['id']);$r=(clone $q)->lockForUpdate()->first();
                ensure($r && ($actor->is_admin || $r->company===$actor->business_name),'Remittance not found.',404);
                ensure($r->rider_id!=$actor->id,'You cannot confirm your own remittance.',403);
                if($r->status==='confirmed')return ['status'=>'confirmed'];
                $q->update(['status'=>'confirmed','confirmed_by'=>$actor->id,'confirmed_at'=>now()]);self::audit($actor->id,$action,$r->id,(array)$r,['status'=>'confirmed']);
                Order::notifyUser($r->rider_id,'COD remittance confirmed','Your logistics company confirmed the submitted amount. Remaining COD stays outstanding.','rider_earnings',$r->id);
                return ['status'=>'confirmed'];
            }
            if($action==='vehicle-decision') {
                validateData($data,['approve'=>'required|boolean','reason'=>'required|string|min:10|max:300']);
                $q=DB::table('mobile_rider_vehicle_requests')->where('id',$data['id']);$r=(clone $q)->lockForUpdate()->first();ensure($r!==null,'Request not found.',404);
                $rider=User::whereKey($r->rider_id)->lockForUpdate()->firstOrFail();ensure($actor->is_admin || $rider->business_name===$actor->business_name,'Another company owns this rider.',403);
                if($r->status===($data['approve']?'approved':'rejected'))return ['status'=>$r->status];
                ensure($r->status==='pending','This request was already decided.');$payload=json_decode($r->payload,true);
                if($data['approve']) {
                    foreach(['plate_number','license_number'] as $f)ensure(!User::where('id','!=',$rider->id)->where($f,$payload[$f])->exists(),'Identifier already registered.');
                    ensure(strtotime($payload['license_expiry'].' 23:59:59')>=time(),'The submitted license expired. Request a valid replacement.');
                    $rider->forceFill(array_intersect_key($payload,array_flip(['vehicle_type','vehicle_brand','vehicle_model','plate_number','license_number','license_expiry'])))->save();
                }
                $status=$data['approve']?'approved':'rejected';$q->update(['status'=>$status,'reason'=>$data['reason'],'decided_by'=>$actor->id,'decided_at'=>now()]);
                foreach(['plate_number','license_number'] as $field)DB::table('mobile_rider_identifiers')->where('rider_id',$rider->id)->where('kind',$field)->where('value','!=',(string)$rider->$field)->delete();
                self::audit($actor->id,$action,$r->id,(array)$r,['status'=>$status,'reason'=>$data['reason']]);
                Order::notifyUser($rider->id,'Vehicle verification '.$status,$data['reason'],'rider_profile',$r->id);return ['status'=>$status];
            }
            validateData($data,['rider_id'=>'required|integer','leg'=>'required|in:pickup,delivery','reason'=>'required|string|min:10|max:300']);
            $assignment=app(App\Services\RiderDeliveryOffers::class)->propose($actor,$data['id'],(int)$data['rider_id'],$data['leg']);
            return ['assigned'=>false,'status'=>$assignment->status,'assignment_id'=>$assignment->id];
        },3);
    }
}
