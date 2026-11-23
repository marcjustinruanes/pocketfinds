<?php
declare(strict_types=1);
use App\Models\{Order,Shipment,Product,CartItem,User,LogisticsIssue,LogisticsHub};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BuyerOrderSupport {
    public static function canCancel(Order $order,?Shipment $shipment): bool {
        return in_array($order->status,array_merge(Order::CANCELLABLE_STATUSES,['pending_payment','pending_payment_verification']),true)
            && (!$shipment || (in_array($shipment->shipping_status,['pending','ready_for_pickup'],true)
                && !$shipment->picked_up_at && !$shipment->rider_confirmed_pickup_at));
    }
    public static function snapshot(Order $order,int $uid): array {
        $s=$order->shipment;
        $issue=$s?LogisticsIssue::where('shipment_id',$s->id)->where('reported_by',$uid)->latest()->orderByDesc('id')->first():null;
        $payment=$order->gcash_purchase_id?\App\Models\GcashPurchase::find($order->gcash_purchase_id):null;
        return ['status'=>$order->status,'created_at'=>$order->created_at?->toIso8601String(),
            'timeline'=>self::timeline($order),
            'payment_verification'=>$order->platform_payment?app(App\Services\PlatformPayments::class)->detail(User::findOrFail($uid),$order->id):null,
            'history'=>cleanRows(DB::table('order_status_history')->where('order_id',$order->id)->orderBy('created_at')->get(['status','notes','created_at'])),
            'can_cancel'=>self::canCancel($order,$s),'cancellation_unavailable_reason'=>'Cancellation is unavailable once the seller marks Ready for Pickup or delivery begins.',
            'prepaid'=>(bool)$order->gcash_purchase_id || (bool)$order->platform_payment,'payment_state'=>$order->platform_payment?DB::table('payment_verifications')->where('order_id',$order->id)->value('status'):$payment?->payment_state,
            'refund_notice'=>$order->platform_payment?'Cancelling does not issue a refund. PocketFinds admin must reconcile any transfer and record actual repayment.':($order->gcash_purchase_id?'Cancelling does not issue a refund. Contact support for payment reconciliation and confirmed repayment.':null),
            'seller_id'=>$order->seller_id,'seller_name'=>$order->seller?->business_name?:trim($order->seller?->given_names.' '.$order->seller?->last_name),
            'can_report_issue'=>$s && filled($s->logistics_company) && in_array($order->status,['delivered','completed'],true),
            'issue'=>$issue?->only(['id','kind','description','status','resolution','created_at']),
            'can_confirm'=>$order->status==='delivered' && (!$issue || $issue->status==='resolved') && !WebsiteAftercare::open($order->id),
            'can_review'=>$order->status==='completed' && MobileAftercare::eventDate($order,'completed')->gte(now()->subDays(30)) && !$order->review()->exists() && DB::table('mobile_buyer_reviews')->where('order_id',$order->id)->count()<count($order->items??[]),'review'=>$order->review?->only(['rating','comment']),
            'buyer_note'=>$order->buyer_note,'cancellation_reason'=>$order->cancellation_reason,'cancellation_note'=>$order->cancellation_note];
    }
    public static function timeline(Order $order): array {
        $history=DB::table('order_status_history')->where('order_id',$order->id)->orderBy('created_at')->get();
        $stages=[
            'Payment Verification'=>['pending_payment_verification'], 'Payment Confirmed'=>['payment_confirmed'],
            'Preparing Order'=>['preparing'], 'Ready for Pickup'=>['ready_for_pickup'],
            'Rider Assigned'=>['assigned_to_rider'], 'Picked Up'=>['picked_up'],
            'Out for Delivery'=>['out_for_delivery'], 'Delivered'=>['delivered'], 'Completed'=>['completed'],
        ];
        $rows=[];
        foreach($stages as $label=>$states) {
            $event=$history->last(fn($e)=>in_array($e->status,$states,true));
            $at=$event?->created_at;
            if($label==='Rider Assigned') {
                // Pickup acceptance is recorded on its assignment, whereas
                // shipment.assigned_at belongs to the later delivery leg.
                $pickupAccepted=$order->shipment
                    ? DB::table('delivery_assignments')->where('shipment_id',$order->shipment->id)
                        ->where('leg','pickup')->whereNotNull('accepted_at')
                        ->orderBy('accepted_at')->value('accepted_at')
                    : null;
                $at=$pickupAccepted??$at??$order->shipment?->assigned_at?->toIso8601String();
            }
            $purchase=$order->gcash_purchase_id?\App\Models\GcashPurchase::find($order->gcash_purchase_id):null;
            if($purchase && $label==='Payment Verification')$at=DB::table('gcash_purchase_history')->where('purchase_id',$purchase->id)->where('event','proof_submitted')->latest('id')->value('created_at');
            if($purchase && $label==='Payment Confirmed')$at=$purchase->verified_at?->toIso8601String();
            $notRequired=in_array($label,['Payment Verification','Payment Confirmed'],true) && !$order->platform_payment && !$purchase;
            $rows[]=['label'=>$label,'recorded_at'=>$at,'state'=>$notRequired?'not_required':($at?'recorded':'upcoming')];
        }
        return $rows;
    }
    public static function issue(Order $order,int $uid,array $data): array {
        validateData($data,['request_id'=>'required|uuid','kind'=>'required|in:not_received,damaged','description'=>'required|string|min:10|max:1800']);
        return DB::transaction(function() use($order,$uid,$data) {
            $order=Order::lockFresh($order->id);
            $s=Shipment::where('order_id',$order->id)->lockForUpdate()->first();
            ensure($s && filled($s->logistics_company) && in_array($order->status,['delivered','completed'],true),'Report a delivery issue after a parcel is marked delivered.');
            $hash=hash('sha256',json_encode(['buyer-issue',$order->id,$data['kind'],$data['description']]));
            $retry=DB::table('mobile_checkouts')->where('buyer_id',$uid)->where('checkout_id',$data['request_id'])->lockForUpdate()->first();
            if ($retry) { ensure(hash_equals($retry->request_hash,$hash),'Request ID already used.',409); return json_decode($retry->result,true); }
            ensure(!LogisticsIssue::where('shipment_id',$s->id)->where('reported_by',$uid)->where('status','!=','resolved')->exists(),'Your open delivery issue is already being handled.');
            $hub=LogisticsHub::where('company_name',$s->logistics_company)->whereRaw('LOWER(municipality) = ?',[mb_strtolower(trim((string)$s->destination_hub))])->whereRaw('LOWER(province) = ?',[mb_strtolower(trim((string)$s->destination_province))])->first();
            ensure($hub!==null,'The destination sorting center is unavailable. Contact the seller for help.');
            $description=($data['kind']==='not_received'?'Buyer reports parcel not received: ':'Buyer reports damaged parcel: ').trim($data['description']);
            $issue=LogisticsIssue::create(['shipment_id'=>$s->id,'company_name'=>$s->logistics_company,'hub_id'=>$hub->id,'reported_by'=>$uid,
                'kind'=>$data['kind']==='damaged'?'damaged':'other','description'=>$description,'status'=>'open']);
            DB::table('logistics_issue_events')->insert(['issue_id'=>$issue->id,'actor_id'=>$uid,'status'=>'open','assigned_to'=>null,'note'=>$description,'created_at'=>now()]);
            $staff=User::where('account_type','logistics')->where('status','approved')->where('business_name',$s->logistics_company)
                ->where(fn($q)=>$q->where('logistics_hub_id',$hub->id)->orWhere('logistics_role','admin'))->pluck('id')->all();
            foreach (array_unique([$order->seller_id,...$staff]) as $recipient) Order::notifyUser($recipient,'Buyer delivery issue','Order #'.$order->order_number.': '.$description,'order_status',$order->id);
            $result=['reported'=>true,'issue_id'=>$issue->id,'status'=>'open'];
            DB::table('mobile_checkouts')->insert(['buyer_id'=>$uid,'checkout_id'=>$data['request_id'],'request_hash'=>$hash,'result'=>json_encode($result),'created_at'=>now()]);
            return $result;
        },3);
    }
    /** Rebuild cart selections using current native inventory, never old order prices. */
    public static function buyAgain(Order $order,int $uid): array {
        $added=0; $skipped=0;
        foreach (collect($order->items??[])->sortBy('product_id') as $item) {
            $p=Product::whereKey($item['product_id']??'')->where('status','active')->sellerApproved()->lockForUpdate()->first();
            if (!$p || $p->seller->is_on_vacation) { $skipped++; continue; }
            $group=$item['variation_group']??'';
            $value=filled($item['variation_value']??null)?$item['variation_value']:(filled($item['size']??null)?$item['size']:($item['color']??''));
            if ($p->variant_axes) {
                $group=$group?:((str_starts_with($item['color']??'','variant:'))?$item['color']:'');
                $option=$p->combinationOption($group);
                if (!$option) { $skipped++; continue; } $value=$option['value'];
            } elseif ($p->variations && !$group) {
                foreach ($p->variations as $axis) foreach ($axis['options']??[] as $option) if (($option['value']??null)===$value) $group=$axis['name'];
            }
            try { $price=MobileCommerce::price($p,$group,$value); $stock=$p->availableStock($group?:null,$value?:null); }
            catch(ApiError) { $skipped++; continue; }
            if ($stock<1) { $skipped++; continue; }
            $cart=CartItem::where('buyer_id',$uid)->where('product_id',$p->id)->where('variation_group',$group)->where('variation_value',$value)->lockForUpdate()->first();
            if ($cart && $cart->qty>=min(99,$stock)) { $skipped++; continue; }
            $qty=min(99,$stock,max(1,(int)($item['qty']??1))+($cart?->qty??0));
            $values=['qty'=>$qty,'price'=>$price,'name'=>$p->name,'img'=>$p->image??'','seller'=>$p->seller->business_name?:trim($p->seller->given_names.' '.$p->seller->last_name),'seller_slug'=>$p->seller->username];
            if ($cart) $cart->update($values); else CartItem::create(['buyer_id'=>$uid,'product_id'=>$p->id,'variation_group'=>$group,'variation_value'=>$value,...$values]);
            $added++;
        }
        ensure($added>0,'No items could be added. They may be unavailable or already at the available quantity in your cart.');
        return ['ok'=>true,'added'=>$added,'skipped'=>$skipped,'message'=>"Added $added item(s) at current prices.".($skipped?" Skipped $skipped unavailable item(s).":'')];
    }
}
