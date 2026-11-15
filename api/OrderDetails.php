<?php
declare(strict_types=1);
use App\Models\{Order,PaymentMethod};
use Illuminate\Support\Facades\{DB,Schema};

/** Buyer-owned presentation of persisted website records. Never reprice purchased items. */
final class MobileOrderDetails {
    private static function iso(mixed $value): ?string {return $value?\Illuminate\Support\Carbon::parse($value)->toIso8601String():null;}
    public static function read(Order $order,int $buyer): array {
        ensure((int)$order->buyer_id===$buyer,'Order access denied.',403);
        $s=$order->shipment;
        $history=cleanRows(DB::table('order_status_history')->where('order_id',$order->id)->orderBy('created_at')->get(['status','notes','created_at']));
        $events=array_map(fn($e)=>['label'=>str_replace('_',' ',$e['status']),'at'=>$e['created_at'],'notes'=>$e['notes']],$history);
        if($s){
            foreach(['picked_up_at'=>'Collected from seller','at_sorting_center_at'=>'Received at origin hub','assigned_at'=>'Final rider assigned','out_for_delivery_at'=>'Out for delivery','delivered_at'=>'Delivered'] as $field=>$label)
                if($s->$field)$events[]=['label'=>$label,'at'=>(string)$s->$field,'notes'=>null];
            foreach(DB::table('shipment_hub_legs')->where('shipment_id',$s->id)->orderBy('sequence')->get() as $leg){
                foreach(['started_at'=>'Transfer dispatched','rider_confirmed_pickup_at'=>'Transfer collected','completed_at'=>'Destination hub arrival'] as $field=>$label)
                    if($leg->$field)$events[]=['label'=>$label,'at'=>$leg->$field,'notes'=>$leg->from_hub.' → '.$leg->to_hub];
            }
        }
        usort($events,fn($a,$b)=>strtotime($a['at'])<=>strtotime($b['at']));
        foreach($events as &$event)$event['at']=self::iso($event['at']);unset($event);
        $snapshot=$order->commerce_snapshot??[];
        $shippingDiscount=0;$productDiscount=0;
        foreach($snapshot['vouchers']??[] as $v){if(($v['kind']??'')==='shipping')$shippingDiscount+=(float)$v['amount'];else $productDiscount+=(float)$v['amount'];}
        $known=isset($snapshot['vouchers']) && abs($productDiscount+$shippingDiscount-(float)$order->discount_amount)<0.01;
        $return=DB::table('commerce_returns')->where('order_id',$order->id)->first();
        $related=[];
        $checkout=DB::table('commerce_checkouts')->where('buyer_id',$buyer)->whereJsonContains('result->ids',$order->gcash_purchase_id?:$order->id)->first();
        if($checkout){
            $result=json_decode($checkout->result,true);
            $orders=Order::where('buyer_id',$buyer)->whereIn(($result['kind']??'')==='gcash'?'gcash_purchase_id':'id',$result['ids']??[])->with('seller')->get();
            foreach($orders as $sibling)$related[]=['id'=>$sibling->id,'order_number'=>$sibling->order_number,'seller'=>$sibling->seller?->business_name?:$sibling->seller?->given_names,'shipping_amount'=>$sibling->shipping_amount];
        }
        $completed=DB::table('order_status_history')->where('order_id',$order->id)->where('status','completed')->max('created_at');
        $buyerConfirmed=DB::table('order_status_history')->where('order_id',$order->id)->where('status','completed')->where('changed_by',$buyer)->max('created_at');
        $r=cleanRow($return);
        if($r){
            $r['approved_amount']=in_array($return->status,['approved','received','refunded'],true)?$return->amount:null;
            $r['repayment_recorded_at']=$return->status==='refunded'?self::iso($return->updated_at):null;
        }
        $cancellationRefund=Schema::hasTable('cancellation_refunds')?DB::table('cancellation_refunds')->where('order_id',$order->id)->where('buyer_id',$buyer)->first():null;
        $refund=$cancellationRefund?['status'=>$cancellationRefund->status,'amount'=>$cancellationRefund->amount,'reference'=>$cancellationRefund->repayment_reference,'recorded_at'=>self::iso($cancellationRefund->completed_at)]:null;
        if(!$refund && $order->platform_payment && in_array($order->status,['cancelled','refunded'],true)){
            $p=DB::table('payment_verifications')->where('order_id',$order->id)->first();
            if($p && in_array($p->status,['paid','refunded'],true))$refund=['status'=>$p->status==='refunded'?'refunded':'pending','amount'=>$p->amount_paid,'reference'=>$p->status==='refunded'?$p->refund_reference:null,'recorded_at'=>$p->status==='refunded'?self::iso($p->refunded_at):null];
        }
        return ['order_number'=>$order->order_number,'created_at'=>$order->created_at?->toIso8601String(),'status'=>$order->status,
            'seller_name'=>$order->seller?->business_name?:$order->seller?->given_names,
            'items'=>$order->items,'address'=>$order->shipping_address,'related_orders'=>$related,
            'pricing'=>['subtotal'=>$order->subtotal,'shipping'=>$order->shipping_amount,'discount'=>$order->discount_amount,'total'=>$order->total,
                'discount_split_known'=>$known,'product_discount'=>$known?$productDiscount:null,'shipping_discount'=>$known?$shippingDiscount:null],
            'payment'=>self::payment($order),
            'estimated_delivery'=>['from'=>self::iso($order->delivery_guaranteed_from),'by'=>self::iso($order->delivery_guaranteed_by)],
            'shipping'=>$s?['id'=>$s->id,'courier_id'=>$s->courier_id,'company'=>$s->logistics_company,'tracking_number'=>$s->tracking_number,'status'=>$s->shipping_status,
                'estimated_from'=>$order->delivery_guaranteed_from,'estimated_by'=>$order->delivery_guaranteed_by,
                'delivered_at'=>$s->delivered_at?->toIso8601String(),'proof'=>$s->delivery_proof_file]:null,
            'events'=>$events,'receipt'=>['confirmed'=>$buyerConfirmed!==null,'completed'=>$order->status==='completed' || $completed!==null,'recorded_at'=>self::iso($completed),'buyer_confirmed_at'=>self::iso($buyerConfirmed)],
            'cancellation'=>['reason'=>$order->cancellation_reason,'notes'=>$order->cancellation_note],'return'=>$r,
            'cancellation_refund'=>$refund];
    }
    private static function payment(Order $order): array {
        $method=$order->payment_method_id?PaymentMethod::find($order->payment_method_id):null;
        $name=$order->payment_method?:$method?->name;
        $paid=0;$at=null;$ref=null;$status='unpaid';
        if($order->gcash_purchase_id){
            $p=\App\Models\GcashPurchase::find($order->gcash_purchase_id);
            $at=$p?->verified_at?->toIso8601String();$paid=$at && $p->verified_amount===null?null:(float)($p?->verified_amount??0);$ref=$at?$p?->payment_reference:null;
            $status=match($p?->payment_state){'order_placed','verified'=>$at?'paid':'pending','proof_submitted'=>'pending_verification',default=>$p?->payment_state??'pending'};
        }elseif($order->platform_payment){
            $p=DB::table('payment_verifications')->where('order_id',$order->id)->first();$status=$p?->status??'pending';
            if(in_array($status,['paid','refunded'],true)){$paid=(float)$p->amount_paid;$at=$p->reviewed_at;$ref=$p->reference_number;}
        }elseif(app(\App\Services\CodCollection::class)->isCod($order)){
            $p=DB::table('logistics_financial_transactions')->where('order_id',$order->id)->where('kind','cod_collection')->where('status','recorded')->first();
            if($p){$paid=(int)$p->amount_cents/100;$at=$p->recorded_at;$status='collected';}else $status='due_on_delivery';
        }else{
            $p=DB::table('payment_verifications')->where('order_id',$order->id)->first();$status=$p?->status??'unknown';
            if($p && in_array($status,['paid','confirmed'],true)){$paid=isset($p->amount_paid)?(float)$p->amount_paid:null;$at=$p->reviewed_at??null;$ref=$p->reference_number;}
        }
        return ['method'=>$name,'status'=>$status,'total'=>$order->total,'paid'=>$paid,'outstanding'=>$paid===null?null:round(max(0,(float)$order->total-$paid),2),
            'reference'=>$ref,'verified_at'=>self::iso($at),'cancelled'=>$order->status==='cancelled'];
    }
}
