<?php
namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB,Schema};

/** Read-only reporting. Sales cohort, dated money activity and current reservations are distinct. */
class PaymentReporting
{
    public function snapshot(?int $sellerId,?Carbon $from=null,?Carbon $to=null): array
    {
        if(DB::getDriverName()==='mysql' && DB::transactionLevel()===0)DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        return DB::transaction(fn()=>$this->build($sellerId,$from,$to));
    }

    private function build(?int $sellerId,?Carbon $from,?Carbon $to): array
    {
        $dated=fn($q,$field)=>$q->when($from,fn($q)=>$q->where($field,'>=',$from))->when($to,fn($q)=>$q->where($field,'<=',$to));
        $scope=fn($q,$field='seller_id')=>$q->when($sellerId!==null,fn($q)=>$q->where($field,$sellerId));
        $orders=$dated($scope(Order::query())->where('status','completed'),'updated_at')->orderByDesc('updated_at')->get();
        $cents=fn($v)=>app(LogisticsCashDesk::class)->amount((string)$v);
        $m=['item_sales'=>(int)$orders->sum(fn($o)=>$cents($o->subtotal)),'quoted_shipping'=>(int)$orders->sum(fn($o)=>$cents($o->shipping_amount)),
            'discounts'=>(int)$orders->sum(fn($o)=>$cents($o->discount_amount)),'buyer_order_value'=>(int)$orders->sum(fn($o)=>$cents($o->total)),
            'buyer_gcash_verified'=>0,'cod_collected'=>0,'cod_verified'=>0,'seller_cod_paid'=>0,'shipping_gcash_paid'=>0,'cod_shipping_retained'=>0,
            'pending_cod_payouts'=>0,'held_cod_payouts'=>0,'unverified_platform_claims'=>0];
        $tables=['gcash_purchases','logistics_financial_transactions','logistics_cash_allocations','logistics_cash_handovers','logistics_seller_payouts','logistics_shipping_obligations'];
        $available=collect($tables)->every(fn($t)=>Schema::hasTable($t));
        if($available){
            $orderIds=$scope(DB::table('orders'))->select('id');
            // Verified receipt remains history even if a later cancellation requires manual resolution.
            $m['buyer_gcash_verified']=(int)$dated($scope(DB::table('gcash_purchases'))->whereNotNull('verified_at')->whereNotNull('payment_reference'),'verified_at')->get(['verified_amount'])->sum(fn($p)=>$cents($p->verified_amount));
            $transactions=$dated(DB::table('logistics_financial_transactions')->whereIn('order_id',$orderIds)->where('status','recorded'),'recorded_at')->get();
            $m['cod_collected']=(int)$transactions->where('kind','cod_collection')->sum('amount_cents');
            $m['shipping_gcash_paid']=(int)$transactions->where('kind','gcash_shipping')->where('verification_state','verified')->sum('amount_cents');
            $m['cod_verified']=(int)$dated(DB::table('logistics_cash_allocations as a')->join('logistics_cash_handovers as h','h.id','=','a.handover_id')->join('logistics_financial_transactions as c','c.id','=','a.collection_id')->whereIn('c.order_id',$scope(DB::table('orders'))->select('id'))
                ->where('c.kind','cod_collection')->whereNotNull('h.counted_at'),'h.counted_at')->sum('a.verified_cents');
            $payouts=$scope(DB::table('logistics_seller_payouts'))->get();
            $paid=$payouts->where('status','paid')->filter(fn($p)=>$p->paid_at && (!$from || Carbon::parse($p->paid_at)->gte($from)) && (!$to || Carbon::parse($p->paid_at)->lte($to)));
            $m['seller_cod_paid']=(int)$paid->sum(fn($p)=>(int)$p->product_cents+(int)$p->adjustment_cents);
            $m['cod_shipping_retained']=(int)$paid->sum('shipping_cents');
            $pending=$payouts->whereIn('status',['prepared','verified']);
            $m['pending_cod_payouts']=(int)$pending->sum(fn($p)=>(int)$p->product_cents+(int)$p->adjustment_cents);
            $m['held_cod_payouts']=(int)$pending->filter(fn($p)=>filled($p->hold_reason))->sum(fn($p)=>(int)$p->product_cents+(int)$p->adjustment_cents);
            $m['unverified_platform_claims']=(int)$scope(DB::table('logistics_shipping_obligations'))->where('claim_status','unverified')->sum('platform_claim_cents');
        }
        return ['orders'=>$orders,'metrics'=>$m,'available'=>$available,'from'=>$from,'to'=>$to,'snapshot_at'=>now()];
    }
}
