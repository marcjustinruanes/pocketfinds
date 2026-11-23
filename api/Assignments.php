<?php
declare(strict_types=1);
use App\Models\{Shipment,Order,User};
use Illuminate\Support\Facades\DB;

final class MobileAssignments {
    public function __construct(private Identity $identity) {}
    public function handle(string $action,array $data): array {
        $uid=$this->identity->approved('rider');
        if($action==='assigned-list') {
            $previous=DB::table('delivery_assignments')->where('courier_id',$uid)->where('leg','delivery')->pluck('shipment_id');
            if(MobileRider::installed()) $previous=$previous->merge(DB::table('mobile_rider_audit')->whereIn('action',['assign','disabled-rider-release'])->where('old_value->rider_id',$uid)->pluck('subject_id'));
            return Shipment::where(fn($q)=>$q->where('courier_id',$uid)->orWhereIn('id',$previous))->with(['order.seller','order.buyer'])->latest()->limit(200)->get()->map(function($s) use($uid) {
                $current=(int)$s->courier_id===$uid;
                $o=$s->order;
                $a=DB::table('delivery_assignments')->where('shipment_id',$s->id)->where('courier_id',$uid)->where('leg','delivery')->orderByDesc('requested_at')->first();
                $offered=$a && $a->status==='offered';
                $section=$offered?'new':(!$current || in_array($s->shipping_status,['cancelled','returned'],true)?'cancelled':(in_array($s->shipping_status,['delivered','completed'],true)?'completed':($s->shipping_status==='assigned_to_rider' && !$a?->accepted_at?'new':'active')));
                return ['id'=>$s->id,'order_number'=>$o?->order_number,'status'=>$offered?'offered':($current?$s->shipping_status:'reassigned'),'section'=>$section,
                    'parcel_verified'=>$current && DB::table('delivery_verifications')->where('shipment_id',$s->id)->where('pickup_rider_id',$uid)->whereNotNull('pickup_verified_at')->exists(),
                    'seller_name'=>$o?->seller?->business_name,'pickup_location'=>($current || $offered)?trim($s->destination_hub.', '.$s->destination_province):null,
                    'buyer_name'=>$current?trim($o?->buyer?->given_names.' '.$o?->buyer?->last_name):null,
                    'address'=>$current?$o?->shipping_address:null,'item_count'=>array_sum(array_column($o?->items??[],'qty')),
                    'delivery_fee'=>$current?$o?->shipping_amount:null,'assigned_at'=>$a?->requested_at??$s->assigned_at,
                    'shipment'=>$current?cleanRow($s->attributesToArray()):null,'order'=>$current?['id'=>$o?->id,'order_number'=>$o?->order_number,'items'=>$o?->items,'total'=>$o?->total,'shipping_address'=>$o?->shipping_address,'buyer_id'=>$o?->buyer_id,'shipping_amount'=>$o?->shipping_amount]:null];
            })->all();
        }
        validateData($data,['shipment_id'=>'required|uuid','request_id'=>'required|uuid']);
        ensure(app(App\Services\PlatformPayments::class)->available(),'Install the app platform workflow schema before changing assignments.',503);
        return MobileRequests::run('assignment:'.$uid,$data['request_id'],[$action,$data],function() use($uid,$action,$data) {
            if(in_array($action,['assignment-accept','assignment-reject'],true)) {
                $offers=app(App\Services\RiderDeliveryOffers::class);
                if($action==='assignment-accept')$offers->accept($this->identity->user,$data['shipment_id'],'delivery');
                else $offers->decline($this->identity->user,$data['shipment_id'],'delivery');
                return ['ok'=>true];
            }
            App\Services\GcashLocks::shipment($data['shipment_id']);
            $s=Shipment::whereKey($data['shipment_id'])->lockForUpdate()->firstOrFail();
            ensure((int)$s->courier_id===$uid,'This delivery is not assigned to you.',403);
            ensure($s->shipping_status==='assigned_to_rider','This assignment no longer permits that action.');
            $a=DB::table('delivery_assignments')->where('shipment_id',$s->id)->where('leg','delivery')->where('courier_id',$uid)->lockForUpdate()->first();
            ensure($a!==null,'The assignment record is unavailable. Refresh or contact your sorting center.');
            if($action==='arrive') {
                ensure((bool)$a->accepted_at,'Accept the assignment before marking arrival.');
                ensure(!DB::table('delivery_events')->where('shipment_id',$s->id)->where('action','arrive')->exists(),'Arrival was already recorded.',409);
            } else throw new ApiError('Unknown assignment action.',404);
            if($action!=='assignment-reject') DB::table('delivery_events')->insert(['id'=>(string)Illuminate\Support\Str::uuid(),'shipment_id'=>$s->id,'actor_id'=>$uid,'action'=>$action,'request_id'=>$data['request_id'],'payload_hash'=>hash('sha256',json_encode($data)),'status'=>$s->shipping_status,'created_at'=>now()]);
            app(App\Services\PlatformPayments::class)->audit($s->id,$uid,$action,['assignment_id'=>$a->id]);
            return ['ok'=>true];
        });
    }
}
