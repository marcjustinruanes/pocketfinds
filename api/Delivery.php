<?php
declare(strict_types=1);
require_once __DIR__.'/Assignments.php';

use App\Models\{Shipment,Order,DeliveryAssignment};
use App\Services\{LogisticsAccess,GcashLocks};
use Illuminate\Support\Facades\{DB,Hash,Mail,Crypt,RateLimiter,Storage};
use Illuminate\Support\Str;

/** Mobile transport and pickup audit; final delivery uses the website controller. */
final class MobileDelivery {
    public static bool $writing=false;
    public const REASONS=['buyer_unavailable','buyer_unreachable','incorrect_address','refused','otp_failed','other'];
    private static bool $guardRegistered=false;
    public function __construct(private Identity $identity) {}
    public static function registerGuard(): void {
        if (!self::$guardRegistered) { Shipment::saving([self::class,'guard']); self::$guardRegistered=true; }
    }
    public static function setting(string $name,int $default): int {
        return max(1,(int)(getenv($name)?:env($name,$default)));
    }
    public static function guard(Shipment $s): void {
        if (self::$writing || !$s->exists || !$s->isDirty('shipping_status') || !$s->getOriginal('courier_id')) return;
        $from=$s->getOriginal('shipping_status'); $to=$s->shipping_status;
        if ($from==='delivered' && $to==='completed') return; // existing buyer confirmation
        $managed=in_array($from,['assigned_to_rider','out_for_delivery','delivery_failed','rescheduled','return_in_transit','delivered','completed','returned'],true)
            || ($from==='picked_up' && DB::table('delivery_verifications')->where('shipment_id',$s->id)->whereNotNull('pickup_verified_at')->exists());
        if ($managed) throw Illuminate\Validation\ValidationException::withMessages(['status'=>'Use verified delivery actions: parcel scan, delivery OTP, or sorting-center return receipt.']);
    }
    public function handle(string $action,array $data): array {
        if(in_array($action,['assigned-list','assignment-accept','assignment-reject','arrive'],true)) return (new MobileAssignments($this->identity))->handle($action,$data);
        $uid=$this->identity->approved();
        $rate='delivery:'.$uid.':'.$action;
        ensure(!RateLimiter::tooManyAttempts($rate,30),'Too many delivery requests. Please wait a minute.',429);
        RateLimiter::hit($rate,60);
        if ($action==='history') {
            $this->identity->approved('rider');
            return cleanRows(Shipment::where('courier_id',$uid)->where(function($q) {
                $q->whereIn('shipping_status',['delivered','completed','delivery_failed','rescheduled','return_in_transit','returned'])
                    ->orWhereExists(function($attempt) { $attempt->selectRaw('1')->from('delivery_attempts')->whereColumn('delivery_attempts.shipment_id','shipments.id'); });
            })->select('shipments.*')->selectSub(DB::table('delivery_attempts')->selectRaw('COUNT(*)')->whereColumn('shipment_id','shipments.id'),'attempt_count')->orderByDesc('created_at')->toBase()->get());
        }
        validateData($data,['shipment_id'=>'required|uuid']);
        if ($action==='details') return $this->details($data['shipment_id']);
        if ($action==='label') return $this->label($data['shipment_id']);
        if ($action==='capture') {
            $s=Shipment::findOrFail($data['shipment_id']);$this->rider($s);ensure($s->shipping_status==='out_for_delivery','Start delivery before capturing handover proof.');
            $token=RiderWebsiteDelivery::capture($this->identity->id(),$s->id);
            return ['token'=>$token];
        }
        validateData($data,['request_id'=>'required|uuid']);
        $hash=hash_hmac('sha256',json_encode([$action,$data],JSON_THROW_ON_ERROR),(string)config('app.key'));
        $result=DB::transaction(function () use($action,$data,$uid,$hash) {
            GcashLocks::shipment($data['shipment_id']);
            $s=Shipment::whereKey($data['shipment_id'])->lockForUpdate()->first();
            ensure($s!==null,'Parcel not found.',404);
            $order=Order::lockFresh($s->order_id);
            if ($action==='receive-return') $this->staff($s);
            elseif ($action==='confirm-seller-return') { $this->identity->approved('seller'); ensure((int)$order->seller_id===$uid,'Seller access denied.',403); }
            else $this->rider($s);
            $event=DB::table('delivery_events')->where('shipment_id',$s->id)->where('request_id',$data['request_id'])->lockForUpdate()->first();
            if ($event) {
                ensure((int)$event->actor_id===$uid && $event->action===$action && hash_equals($event->payload_hash,$hash),'This request ID was already used for another action.',409);
                if ($event->result_error) return ['error'=>$event->result_error];
                return ['ok'=>true,'status'=>$s->shipping_status,'delivery_status'=>$s->shipping_status,'order_status'=>$order->status,'replayed'=>true];
            }
            ensure(!in_array($order->status,['cancelled','completed','returned'],true),'This order no longer permits delivery actions.');
            DB::table('delivery_verifications')->insertOrIgnore(['shipment_id'=>$s->id]);
            $v=DB::table('delivery_verifications')->where('shipment_id',$s->id)->lockForUpdate()->first();
            if (!in_array($action,['receive-return','confirm-seller-return'],true) && $v->pickup_verified_at) {
                ensure((int)$v->pickup_rider_id===$uid,'Pickup was verified by another rider. Contact your sorting center for an authorized handover.',403);
            }
            $status=$s->shipping_status;
            if ($action==='pickup') {
                $assignment=DB::table('delivery_assignments')->where('shipment_id',$s->id)->where('courier_id',$uid)->where('leg','delivery')->first();
                ensure(!$assignment || $assignment->status!=='requested','Accept this assignment before collecting the parcel.');
                if($order->platform_payment || DB::table('delivery_events')->where('shipment_id',$s->id)->where('actor_id',$uid)->where('action','assignment-accept')->exists()) ensure(DB::table('delivery_events')->where('shipment_id',$s->id)->where('actor_id',$uid)->where('action','arrive')->exists(),'Record arrival at pickup before confirming this parcel.');
                ensure($status==='assigned_to_rider','Only an accepted parcel can be picked up.');
                validateData($data,['parcel_code'=>'required|string|max:4096']);
                $code=trim($data['parcel_code']);
                if (str_starts_with($code,'PF1:')) {
                    try { $label=json_decode(Crypt::decryptString(substr($code,4)),true,8,JSON_THROW_ON_ERROR); }
                    catch(Throwable) { throw new ApiError('The parcel code is invalid.'); }
                    ensure(($label['purpose']??'')==='delivery-pickup' && ($label['shipment_id']??'')===$s->id && ($label['expires']??0)>time(),'This parcel code is incorrect or expired.');
                } else {
                    ensure(!empty($s->tracking_number) && hash_equals($s->tracking_number,$code)
                        && Shipment::where('tracking_number',$code)->count()===1,'The tracking ID does not match this parcel.');
                }
                ensure(!$v->pickup_verified_at,'Pickup was already verified.');
                DB::table('delivery_verifications')->where('shipment_id',$s->id)->update(['pickup_verified_at'=>now(),'pickup_rider_id'=>$uid]);
                // The web delivery leg stays assigned_to_rider until out_for_delivery.
                $status='assigned_to_rider';
            } elseif ($action==='start') {
                if($status==='rescheduled') {
                    // Preserve already scheduled app retries, then rejoin the web
                    // delivery stages before generating a shared code.
                    $scheduled=DB::table('delivery_attempts')->where('shipment_id',$s->id)->orderByDesc('attempt_number')->value('scheduled_at');
                    ensure($v->pickup_verified_at && (!$scheduled || strtotime($scheduled)<=time()),'This delivery is scheduled for a later time.');
                    $retry=clone $s;$retry->shipping_status='delivery_failed';
                    app(App\Services\RiderDeliveryOffers::class)->assertRetryEligible($retry);
                    self::$writing=true;
                    try {
                        $s->shipping_status='assigned_to_rider';$s->save();
                        DeliveryAssignment::where('shipment_id',$s->id)->where('leg','delivery')->where('courier_id',$uid)->update(['status'=>'assigned_to_rider']);
                        $order->status='assigned_to_rider';$order->save();
                    } finally {self::$writing=false;}
                    $status=$s->shipping_status;
                }
                ensure($status==='assigned_to_rider','Only an accepted delivery can be started.');
                ensure($v->pickup_verified_at && (int)$v->pickup_rider_id===$uid,'Scan or enter this parcel barcode before starting delivery.');
                RiderWebsiteDelivery::advance($this->identity,$s->id,['from'=>$status]);
                $s->refresh();$order->refresh();$status=$s->shipping_status;
            } elseif ($action==='request-otp') {
                // The website creates and notifies the buyer of the code on start.
                // Reuse that code; never generate an independent mobile OTP.
                ensure($status==='out_for_delivery' && $s->delivery_otp_hash && $s->delivery_otp_ciphertext,'Start delivery to generate the buyer code.');
                ensure(!$s->delivery_otp_locked_until || !$s->delivery_otp_locked_until->isFuture(),'Delivery OTP is locked. Wait before trying again.',429);
            } elseif (in_array($action,['complete','verify-otp'],true)) {
                ensure($status==='out_for_delivery','This parcel is not eligible for delivery verification.');
                // Accept legacy draft keys during app upgrades; all new clients use web names.
                $data['delivery_proof']=$data['delivery_proof']??$data['photo']??null;
                validateData($data,['delivery_proof'=>'required|string|max:255']);
                validateData($data,['latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180']);
                ensure($v->pickup_verified_at && (int)$v->pickup_rider_id===$uid,'Verify this parcel before completing delivery.');
                $proof=RiderWebsiteDelivery::proof($uid,$data['delivery_proof'],$s->id);
                $input=['from'=>$status,'cod_amount'=>(string)($data['cod_amount']??''),'cod_confirmed'=>$data['cod_confirmed']??false,'collection_key'=>$data['collection_key']??$data['request_id']];
                RiderWebsiteDelivery::advance($this->identity,$s->id,$input,$proof);
                DB::table('delivery_verifications')->where('shipment_id',$s->id)->update(['delivery_latitude'=>$data['latitude'],'delivery_longitude'=>$data['longitude'],'location_recorded_at'=>now()]);
                $s->refresh();$order->refresh();$status=$s->shipping_status;
                $proofPath=$data['delivery_proof'];
                DB::afterCommit(function()use($uid,$proofPath){
                    Storage::disk('sensitive_documents')->delete('delivery_proofs/pending/'.$proofPath);
                    Illuminate\Support\Facades\Cache::forget(RiderWebsiteDelivery::captureKey($uid,pathinfo($proofPath,PATHINFO_FILENAME)));
                });
            } elseif ($action==='fail') {
                ensure($status==='out_for_delivery','Only an active delivery attempt can fail.');
                validateData($data,['reason'=>'required|in:'.implode(',',self::REASONS),'notes'=>'nullable|string|min:10|max:300']);
                ensure($data['reason']!=='other' || trim($data['notes']??'')!=='','Explain the other failure reason.');
                $attempt=DB::table('delivery_attempts')->where('shipment_id',$s->id)->lockForUpdate()->get()->count()+1;
                ensure($attempt<=self::setting('DELIVERY_MAX_ATTEMPTS',3),'The delivery attempt limit has been reached.');
                DB::table('delivery_attempts')->insert(['id'=>(string)Str::uuid(),'shipment_id'=>$s->id,'rider_id'=>$uid,'attempt_number'=>$attempt,'reason'=>$data['reason'],'notes'=>$data['notes']??null,'attempted_at'=>now(),'decision'=>'pending']);
                $s->delivery_failed_reason=str_replace('_',' ',$data['reason']).(!empty($data['notes'])?': '.$data['notes']:'');
                $s->delivery_failed_at=now(); $status='delivery_failed';
                $s->forceFill(['delivery_otp_hash'=>null,'delivery_otp_ciphertext'=>null])->save();
                // Logistics decides the next operational step after a failed attempt.
                // Recording a failure alone must not initiate a parcel return.
            } elseif ($action==='reschedule') {
                ensure($status==='delivery_failed','Only a failed attempt can be rescheduled.');
                ensure(DB::table('delivery_attempts')->where('shipment_id',$s->id)->lockForUpdate()->get()->count()<self::setting('DELIVERY_MAX_ATTEMPTS',3),'Retry limit reached. Return the parcel to the sorting center.');
                validateData($data,['scheduled_at'=>'required|date|after_or_equal:now']);
                $this->decision($s,'rescheduled',$data['scheduled_at']); $status='rescheduled';
            } elseif ($action==='request-return') {
                ensure($status==='delivery_failed','Record the failed attempt before requesting return.');
                $this->decision($s,'return_in_transit'); $status='return_in_transit';
                DB::table('delivery_verifications')->where('shipment_id',$s->id)->update(['return_requested_at'=>now()]);
            } elseif ($action==='receive-return') {
                ensure($status==='return_in_transit','Only a parcel physically returning to this center can be received.');
                ensure(!$v->return_received_at,'This center already recorded the return receipt.');
                validateData($data,['tracking_number'=>'required|string']);
                ensure(hash_equals((string)$s->tracking_number,trim($data['tracking_number'])),'Scan or enter the matching parcel tracking number.');
                DB::table('delivery_verifications')->where('shipment_id',$s->id)->update(['return_received_at'=>now(),'return_received_by'=>$uid]);
                $this->decision($s,'received_at_center');
                foreach (array_unique([$order->buyer_id,$order->seller_id]) as $recipient) Order::notifyUser($recipient,'Return at sorting center','Order #'.$order->order_number.': the center received the parcel; return to the seller is still in progress.','order_status',$order->id);
                // Hub receipt is not seller stock receipt: do not restock goods at the hub.
            } elseif ($action==='confirm-seller-return') {
                ensure($status==='return_in_transit' && $v->return_received_at,'Sorting-center receipt must be recorded before the seller receives the return.');
                validateData($data,['tracking_number'=>'required|string']);
                ensure($s->tracking_number && hash_equals($s->tracking_number,trim($data['tracking_number'])),'Scan or enter the matching returned parcel.');
                $this->decision($s,'returned'); $s->returned_at=now(); $status='returned';
            } else throw new ApiError('Unknown delivery action.',404);
            if ($status!==$s->shipping_status) {
                self::$writing=true;
                try {
                    $s->shipping_status=$status;
                    if ($status==='out_for_delivery') $s->out_for_delivery_at=now();
                    if ($status==='delivered') $s->delivered_at=now();
                    $s->save(); $order->status=$status; $order->save();
                    if($status==='delivered') Order::notifyUser($uid,'Delivery successfully confirmed','The required handover proof was accepted. Earnings remain subject to the completion and payout policy.','rider_task',$s->id);
                    DeliveryAssignment::where('shipment_id',$s->id)->where('leg','delivery')->update(array_filter(['status'=>$status,'delivered_at'=>$status==='delivered'?now():null]));
                    $order->logStatus($status,$uid,$s->delivery_failed_reason);
                    foreach (array_unique([$order->buyer_id,$order->seller_id]) as $recipient) Order::notifyUser($recipient,'Delivery update','Order #'.$order->order_number.': '.str_replace('_',' ',$status).($status==='delivery_failed'?'. '.$s->delivery_failed_reason:''),'order_status',$order->id);
                    if(MobileRider::installed()) foreach(App\Models\User::where('business_name',$s->logistics_company)->where('status','approved')->where('is_logistics',true)->pluck('id') as $staff) Order::notifyUser($staff,'Delivery update','Tracking '.$s->tracking_number.': '.str_replace('_',' ',$status),'rider_task',$s->id);
                } finally { self::$writing=false; }
            }
            DB::table('delivery_events')->insert(['id'=>(string)Str::uuid(),'shipment_id'=>$s->id,'actor_id'=>$uid,'action'=>$action,'request_id'=>$data['request_id'],'payload_hash'=>$hash,'status'=>$status,'created_at'=>now()]);
            return ['ok'=>true,'status'=>$status,'delivery_status'=>$status,'order_status'=>$order->status,'sent'=>$action==='request-otp'];
        },$action==='request-otp'?1:3);
        ensure(!isset($result['error']),$result['error']??'Verification failed.');
        return $result;
    }
    private function decision(Shipment $s,string $decision,?string $at=null): void {
        $attempt=DB::table('delivery_attempts')->where('shipment_id',$s->id)->orderByDesc('attempt_number')->lockForUpdate()->first();
        ensure($attempt!==null,'No delivery attempt is recorded.');
        DB::table('delivery_attempts')->where('id',$attempt->id)->update(['decision'=>$decision,'scheduled_at'=>$at?\Carbon\Carbon::parse($at):null]);
    }
    private function rider(Shipment $s): void {
        $uid=$this->identity->approved('rider'); $r=$this->identity->user;
        ensure((int)$s->courier_id===$uid,'This task is not assigned to you.',403);
        $hub=app(LogisticsAccess::class)->hub($r);
        ensure($s->logistics_company===$r->business_name && app(LogisticsAccess::class)->matches($hub,$s->destination_hub,$s->destination_province),'This parcel is outside your authorized service area.',403);
    }
    private function staff(Shipment $s): void {
        $user=$this->identity->user;
        ensure($user && $user->account_type==='logistics' && $user->isHubStaff(),'Sorting-center staff access required.',403);
        ensure(app(LogisticsAccess::class)->shipments($user)->whereKey($s->id)->exists(),'Parcel access denied.',403);
        app(LogisticsAccess::class)->requireLocation($user,$s->destination_hub,$s->destination_province);
    }
    public function details(string $id): array {
        $s=Shipment::find($id); ensure($s!==null,'Parcel not found.',404); $this->rider($s);
        $v=DB::table('delivery_verifications')->where('shipment_id',$id)->first();
        $attempts=cleanRows(DB::table('delivery_attempts')->where('shipment_id',$id)->orderBy('attempt_number')->get());
        $order=$s->order; $method=$order->paymentMethod;
        $scheduled=$attempts?end($attempts)['scheduled_at']:null;
        $remaining=max(0,5-(int)$s->delivery_otp_attempts);
        $locked=$s->delivery_otp_locked_until?->toDateTimeString();

        $cooldown=0;
        return ['status'=>$s->shipping_status,'delivery_status'=>$s->shipping_status==='completed'?'delivered':$s->shipping_status,'order_status'=>$order->status,'tracking_number'=>$s->tracking_number,'pickup_location'=>trim($s->destination_hub.', '.$s->destination_province),
            'otp_attempts_remaining'=>$remaining,'otp_resend_after_seconds'=>$cooldown,
            'otp_locked_until'=>$locked,
            'can_verify_otp'=>$s->shipping_status==='out_for_delivery' && (bool)$v?->pickup_verified_at,
            'pickup_verified_at'=>$v->pickup_verified_at??null,'delivery_verified_at'=>$s->delivered_at?->toDateTimeString(),
            'delivery_location'=>isset($v->delivery_latitude,$v->delivery_longitude)?['latitude'=>(float)$v->delivery_latitude,'longitude'=>(float)$v->delivery_longitude,'recorded_at'=>$v->location_recorded_at]:null,
            'assignment_accepted_at'=>DB::table('delivery_assignments')->where('shipment_id',$s->id)->where('courier_id',$this->identity->id())->where('leg','delivery')->value('accepted_at'),
            'arrived_at'=>DB::table('delivery_events')->where('shipment_id',$s->id)->where('actor_id',$this->identity->id())->where('action','arrive')->value('created_at'),
            'return_received_at'=>$v->return_received_at??null,
            'otp_sent_at'=>$s->out_for_delivery_at?->toDateTimeString(),'otp_expires_at'=>null,
            'scheduled_at'=>$scheduled,'can_start'=>in_array($s->shipping_status,['assigned_to_rider','rescheduled'],true) && (!$scheduled || strtotime($scheduled)<=time()),
            'can_reschedule'=>$s->shipping_status==='delivery_failed' && count($attempts)<self::setting('DELIVERY_MAX_ATTEMPTS',3),
            'max_attempts'=>self::setting('DELIVERY_MAX_ATTEMPTS',3),'attempts'=>$attempts,
            'payment_method'=>$order->payment_method,
            'cod_amount'=>($method?->buyerCheckoutKind()==='cod' || in_array(strtolower((string)$order->payment_method),['cod','cash on delivery'],true))?(float)$order->total:null,
            'events'=>cleanRows(DB::table('delivery_events')->where('shipment_id',$id)->orderBy('created_at')->get(['action','status','created_at']))];
    }
    public function label(string $id): array {
        $s=Shipment::find($id); ensure($s!==null,'Parcel not found.',404); $this->staff($s);
        if (!$s->tracking_number) { $s->tracking_number='PF-'.strtoupper(bin2hex(random_bytes(12))); $s->save(); }
        return ['tracking_number'=>$s->tracking_number,'parcel_code'=>'PF1:'.Crypt::encryptString(json_encode(['purpose'=>'delivery-pickup','shipment_id'=>$s->id,'expires'=>time()+86400]))];
    }
}
