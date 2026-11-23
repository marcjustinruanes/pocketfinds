<?php
declare(strict_types=1);
use App\Models\{Shipment,Order,DeliveryAssignment};
use App\Services\{LogisticsAccess,GcashLocks,GcashWorkflow};
use Illuminate\Support\Facades\{DB,RateLimiter};
use Illuminate\Support\Str;

/** Seller-to-origin-center pickup, separate from final buyer delivery. */
final class MobilePickup {
    public function __construct(private Identity $identity) {}
    public function handle(string $action,array $data): array {
        $uid=$this->identity->approved('rider'); $rider=$this->identity->user;
        if ($action==='list') {
            $available=app(LogisticsAccess::class)->openParcelsForRider($rider,'pickup')->whereHas('order',fn($q)=>$q->whereNotIn('status',['cancelled','returned','completed']))->with('order.seller')->get();
            $mine=Shipment::where('pickup_rider_id',$uid)->with('order.seller')->latest('updated_at')->get();
            return ['available'=>$available->map(fn($s)=>$this->row($s,false))->all(),'mine'=>$mine->map(fn($s)=>$this->row($s,true))->all()];
        }
        validateData($data,['shipment_id'=>'required|uuid','request_id'=>'required|uuid']);
        ensure(in_array($action,['accept','arrive','confirm'],true),'Unknown pickup action.',404);
        $rate='mobile-pickup:'.$uid; ensure(!RateLimiter::tooManyAttempts($rate,30),'Too many requests. Wait a minute.',429); RateLimiter::hit($rate,60);
        return DB::transaction(function() use($action,$data,$uid,$rider) {
            GcashLocks::shipment($data['shipment_id']);
            $s=Shipment::whereKey($data['shipment_id'])->lockForUpdate()->first(); ensure($s!==null,'Pickup not found.',404);
            $order=Order::lockFresh($s->order_id);
            $hash=hash_hmac('sha256',json_encode([$action,$data],JSON_THROW_ON_ERROR),(string)config('app.key'));
            $event=DB::table('delivery_events')->where('shipment_id',$s->id)->where('request_id',$data['request_id'])->first();
            if ($event) {
                ensure((int)$event->actor_id===$uid && $event->action==='seller-pickup-'.$action && hash_equals($event->payload_hash,$hash),'Request ID already used.',409);
                ensure((int)$s->pickup_rider_id===$uid,'Pickup no longer assigned to you.',403);
                return $this->row($s,true);
            }
            ensure(!in_array($order->status,['cancelled','returned','completed'],true),'This order no longer permits pickup.');
            $hub=app(LogisticsAccess::class)->hub($rider);
            ensure($s->logistics_company===$rider->business_name && app(LogisticsAccess::class)->matches($hub,$s->origin_hub,$s->origin_province),'Pickup is outside your authorized service area.',403);
            if ($action!=='accept') app(GcashWorkflow::class)->assertOrderOperation($order,true);
            if ($action==='accept') {
                if ((int)$s->pickup_rider_id!==$uid) {
                    ensure($s->pickup_rider_id===null && $s->shipping_status==='ready_for_pickup' && $s->pickup_approved_at,'This pickup was already accepted or is unavailable.');
                    $s->update(['pickup_rider_id'=>$uid]);
                    DeliveryAssignment::updateOrCreate(['shipment_id'=>$s->id,'leg'=>'pickup'],['courier_id'=>$uid,'status'=>'accepted','requested_at'=>now(),'accepted_at'=>now(),'expires_at'=>null,'declined_at'=>null]);
                    $s->refresh();
                }
            } else {
                ensure((int)$s->pickup_rider_id===$uid,'Pickup is not assigned to you.',403);
                ensure($s->shipping_status==='ready_for_pickup' && !$s->rider_confirmed_pickup_at,'Pickup has already been confirmed or moved on.');
                if ($action==='confirm') {
                ensure($this->arrived($s),'Record arrival at the seller before confirming receipt.');
                validateData($data,['parcel_code'=>'required|string|max:255']);
                ensure($s->tracking_number && hash_equals($s->tracking_number,trim($data['parcel_code'])) && Shipment::where('tracking_number',trim($data['parcel_code']))->count()===1,'Tracking ID does not match this parcel.');
                $s->rider_confirmed_pickup_at=now(); $s->save();
                // Native observers require the seller's independent handover
                // confirmation before picked_up. Hub staff record arrival.
                Order::notifyUser($order->seller_id,'Rider confirmed pickup','Confirm physical handover for order #'.$order->order_number.'.','order_status',$order->id);
                }
            }
            $s->refresh();
            DB::table('delivery_events')->insert(['id'=>(string)Str::uuid(),'shipment_id'=>$s->id,'actor_id'=>$uid,'action'=>'seller-pickup-'.$action,'request_id'=>$data['request_id'],'payload_hash'=>$hash,'status'=>$s->shipping_status,'created_at'=>now()]);
            return $this->row($s,true);
        },3);
    }
    private function arrived(Shipment $s): bool {
        return DB::table('delivery_events')->where('shipment_id',$s->id)->where('actor_id',$s->pickup_rider_id)->where('action','seller-pickup-arrive')->exists();
    }
    private function row(Shipment $s,bool $assigned): array {
        $seller=$s->order?->seller;
        $active=$assigned && $s->shipping_status==='ready_for_pickup' && !$s->rider_confirmed_pickup_at && !in_array($s->order?->status,['cancelled','returned','completed'],true);
        $arrived=$assigned && $this->arrived($s);
        $address=$seller?array_filter([$seller->house_no,$seller->street,$seller->barangay,$seller->municipality,$seller->province]):[];
        return ['id'=>$s->id,'order_id'=>$s->order_id,'order_number'=>$s->order?->order_number,'status'=>$s->shipping_status,'tracking_number'=>$s->tracking_number,
            'seller_name'=>$seller?->business_name?:trim(($seller?->given_names??'').' '.($seller?->last_name??'')),
            'seller_address'=>$assigned?implode(', ',$address):trim(($seller?->municipality??'').', '.($seller?->province??'')),
            'seller_contact'=>$assigned?$seller?->contact_no:null,'seller_id'=>$assigned?$seller?->id:null,
            'center'=>trim($s->origin_hub.', '.$s->origin_province),'rider_confirmed_at'=>$s->rider_confirmed_pickup_at?->toIso8601String(),
            'picked_up_at'=>$s->picked_up_at?->toIso8601String(),'created_at'=>$s->created_at?->toIso8601String(),
            'pickup_stage'=>!$assigned?'Assigned':($s->at_sorting_center_at?'Received at sorting center':($s->picked_up_at?'Picked up':($s->rider_confirmed_pickup_at?'Awaiting seller handover confirmation':($arrived?'Arrived at seller':'Accepted')))),
            'can_arrive'=>$active && !$arrived,
            'can_confirm'=>$active && $arrived];
    }
}
