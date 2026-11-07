<?php

namespace App\Services;

use App\Models\{Order,Shipment,User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Schema,Validator};
use Illuminate\Support\Str;

/** Explicit accounting for actual buyer refunds; original orders/collections are immutable. */
class CodRefundSettlement
{
    public function installed(): bool { return Schema::hasTable('cod_refund_reconciliations'); }
    private function fail(string $why): never { app(CommissionLedger::class)->fail($why); }
    private function locked(User $actor,string $orderId,bool $platform=false): array
    {
        abort_unless($this->installed(),503,'Refund settlement installation is required.');
        $peek=Order::findOrFail($orderId);$shipment=Shipment::where('order_id',$orderId)->firstOrFail();
        GcashLocks::operation([$shipment->id],[],[$actor->id,$peek->seller_id]);
        $actor=$platform?app(CommissionPayments::class)->admin($actor):app(LogisticsTreasury::class)->admin($actor);
        abort_unless($platform || $shipment->logistics_company===$actor->business_name,403);
        $order=Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
        $commission=DB::table('commission_obligations')->where('order_id',$orderId)->where('method','cod')->lockForUpdate()->first();
        abort_unless($commission,422,'Only an enrolled COD order can use residual settlement. Historical orders retain their existing review path.');
        return [$actor,$order,$shipment,$commission];
    }
    public function fingerprint(object $r): string
    {
        return hash('sha256',json_encode([$r->id,$r->order_id,$r->status,app(CommissionLedger::class)->cents($r->amount),strtoupper(trim($r->refund_reference??'')),json_decode($r->item_keys,true),$r->refund_destination]));
    }
    public function approved(string $orderId,object $return): ?object
    {
        if(!$this->installed())return null;
        $r=DB::table('cod_refund_reconciliations')->where('return_id',$return->id)->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->first();
        return $r && $r->status==='verified' && $r->source_hash===$this->fingerprint($return)?$r:null;
    }
    private function balance(string $account,?string $exceptRefund=null): int
    {
        $balance=(int)DB::table('logistics_treasury_movements')->where('account_id',$account)->where('bucket','cod')->lockForUpdate()->get(['delta_cents'])->sum('delta_cents');
        $reserved=(int)DB::table('logistics_seller_payouts')->where('treasury_account_id',$account)->whereIn('status',['prepared','verified'])->lockForUpdate()->get(['product_cents'])->sum('product_cents');
        $refunds=(int)DB::table('cod_refund_reconciliations')->where('account_id',$account)->where('status','awaiting_remittance')->when($exceptRefund,fn($q)=>$q->where('id','!=',$exceptRefund))->lockForUpdate()->get(['amount_cents'])->sum('amount_cents');
        return $balance-$reserved-$refunds;
    }
    private function noPayout(Order $order): void
    {
        if(DB::table('logistics_seller_payouts')->where('order_id',$order->id)->where('status','!=','cancelled')->lockForUpdate()->first())$this->fail('Cancel any unsent Seller payout first. Paid orders cannot create a second residual payout.');
    }
    public function reconcile(User $actor,string $orderId,array $input,UploadedFile $file): void
    {
        $d=Validator::make($input+['proof'=>$file],['return_id'=>'required|uuid','account_id'=>'required|uuid','payer'=>'required|in:company,seller,platform','amount'=>'required|string','reference'=>['required','regex:/^[A-Za-z0-9-]{6,64}$/D'],'buyer_name'=>'required|string|max:150','buyer_number'=>['required','regex:/^09[0-9]{9}$/D'],'platform_account_id'=>'required_if:payer,platform|nullable|uuid','confirmed'=>'required|accepted','reason'=>'required|string|min:3|max:2000','proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120'])->validate();
        DB::transaction(function()use($actor,$orderId,$d,$file){
            [$actor,$order,$shipment,$commission]=$this->locked($actor,$orderId);$this->noPayout($order);
            $return=DB::table('commerce_returns')->where('id',$d['return_id'])->where('order_id',$orderId)->lockForUpdate()->firstOrFail();
            if($return->status!=='refunded' || !$return->refund_reference)$this->fail('Complete and document the actual buyer refund through the existing return workflow first.');
            if(DB::table('cod_refund_reconciliations')->where('return_id',$return->id)->lockForUpdate()->first())$this->fail('This buyer refund was already reconciled.');
            $ledger=app(CommissionLedger::class);$amount=$ledger->cents($d['amount']);$ref=strtoupper(trim($d['reference']));
            if($amount<=0 || $amount!==$ledger->cents($return->amount) || $ref!==strtoupper(trim($return->refund_reference)))$this->fail('Confirm the exact actual buyer refund amount and its recorded reference.');
            if(preg_match('/^GCash: (.+) \/ (09[0-9]{9})$/uD',$return->refund_destination,$destination) && (trim($destination[1])!==trim($d['buyer_name']) || $destination[2]!==$d['buyer_number']))$this->fail('The buyer refund destination must match the approved return instructions.');
            if(!in_array($order->status,['delivered','completed']) || !in_array($shipment->shipping_status,['delivered','completed']))$this->fail('Residual settlement requires delivered COD.');
            $ob=DB::table('logistics_shipping_obligations')->where('order_id',$orderId)->where('company_name',$shipment->logistics_company)->lockForUpdate()->firstOrFail();
            $collection=DB::table('logistics_financial_transactions')->where('collection_order_id',$orderId)->where('collection_shipment_id',$shipment->id)->where('company_name',$actor->business_name)->where('kind','cod_collection')->where('status','recorded')->lockForUpdate()->firstOrFail();
            $total=$ledger->cents($order->total);$gross=$total-(int)$ob->amount_cents;
            if($ob->collection_mode!=='cod' || (int)$collection->amount_cents!==$total || app(LogisticsCashDesk::class)->balances($collection,true)['verified']!==$total)$this->fail('The entire original COD collection must be verified.');
            if($amount>$gross)$this->fail('Refund exceeds the original product funds. An additional funding exception must be resolved first.');
            $keys=json_decode($return->item_keys,true);$all=DB::table('commission_lines')->where('obligation_id',$commission->id)->get();
            if(!$keys || count(array_unique($keys))!==count($keys) || count(array_intersect($keys,$all->pluck('line_key')->all()))!==count($keys))$this->fail('Refunded item lines do not match the original commission snapshot.');
            $ledger->reverse($orderId,'return:'.$return->id,$keys,'Recorded buyer refund reconciled');
            $retained=(int)$all->reject(fn($line)=>in_array($line->line_key,$keys))->sum('basis_cents');
            $shippingFunding=json_decode($ob->voucher_funding??'[]',true);$sellerShipping=(int)collect($shippingFunding)->where('source','seller')->sum('amount_cents');
            $remaining=$gross-$amount;$subsidy=max(0,$retained-$sellerShipping-$remaining);
            $t=app(LogisticsTreasury::class);$account=$t->account($d['account_id'],$actor->business_name);$t->requireGcash($account);
            $id=(string)Str::uuid();app(LogisticsShipping::class)->reserveReference($ref,'cod_buyer_refund',$id);$t->evidence($actor,'cod_buyer_refund',$id,$file);
            $platform=null;if($d['payer']==='platform')$platform=app(CommissionAccounts::class)->details(DB::table('commission_accounts')->where('id',$d['platform_account_id'])->firstOrFail());
            if($d['payer']==='company'){
                if($this->balance($account->id)<$amount)$this->fail('Insufficient unreserved verified COD funds for this actual company-funded refund.');
                $t->move($actor,$account,'cod',-$amount,'cod_buyer_refund',$id,$ref);
            }
            DB::table('cod_refund_reconciliations')->insert(['id'=>$id,'return_id'=>$return->id,'order_id'=>$orderId,'company_name'=>$actor->business_name,'account_id'=>$account->id,'payer'=>$d['payer'],'amount_cents'=>$amount,'remaining_cents'=>$remaining,'seller_reimbursement_cents'=>$d['payer']==='seller'?$amount:0,'required_subsidy_cents'=>$subsidy,'source_hash'=>$this->fingerprint($return),'reference'=>$ref,'buyer_destination'=>json_encode(['name'=>$d['buyer_name'],'number'=>$d['buyer_number']]),'platform_account_id'=>$platform?->id,'platform_destination'=>$platform?json_encode(['name'=>$platform->account_name,'number'=>$platform->account_number]):null,'status'=>$platform?'awaiting_remittance':'verified','reason'=>$d['reason'],'reviewed_by'=>$actor->id,'created_at'=>now(),'updated_at'=>now()]);
            $t->event($actor,'cod_refund_reconciled','cod_refund',$id,['order_id'=>$orderId,'amount_cents'=>$amount,'payer'=>$d['payer'],'remaining_cents'=>$remaining,'seller_reimbursement_cents'=>$d['payer']==='seller'?$amount:0,'required_subsidy_cents'=>$subsidy,'reason'=>$d['reason']]);
        },3);
    }
    public function reimburse(User $actor,string $id,array $input,UploadedFile $file): void
    {
        $d=Validator::make($input+['proof'=>$file],['revision'=>'required|integer|min:1','amount'=>'required|string','reference'=>['required','regex:/^[A-Za-z0-9-]{6,64}$/D'],'sent'=>'required|accepted','reason'=>'required|string|min:3|max:2000','proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120'])->validate();
        DB::transaction(function()use($actor,$id,$d,$file){
            $peek=DB::table('cod_refund_reconciliations')->find($id);abort_unless($peek,404);[$actor,$order]=$this->locked($actor,$peek->order_id);$this->noPayout($order);
            $r=DB::table('cod_refund_reconciliations')->where('id',$id)->lockForUpdate()->first();abort_unless($r->status==='awaiting_remittance' && (int)$r->revision===(int)$d['revision'],409);
            if(app(CommissionLedger::class)->cents($d['amount'])!==(int)$r->amount_cents)$this->fail('Send the exact actual buyer-refund reimbursement to the recorded platform account.');
            $t=app(LogisticsTreasury::class);$account=$t->account($r->account_id,$actor->business_name);
            if($this->balance($account->id,$id)<(int)$r->amount_cents)$this->fail('Insufficient unreserved COD funds.');
            $ref=$t->reference($account,$d['reference'],'cod_refund_reimburse',$id);$t->evidence($actor,'cod_refund_reimburse',$id,$file);
            $t->move($actor,$account,'cod',-(int)$r->amount_cents,'cod_refund_reimburse',$id,$ref);$t->move($actor,$account,'refund_transit',(int)$r->amount_cents,'cod_refund_reimburse',$id);
            DB::table('cod_refund_reconciliations')->where('id',$id)->update(['status'=>'in_transit','reimbursement_reference'=>$ref,'revision'=>$r->revision+1,'updated_at'=>now()]);
            $t->event($actor,'cod_refund_reimbursement_sent','cod_refund',$id,['reference'=>$ref,'reason'=>$d['reason'],'amount_cents'=>$r->amount_cents]);
        },3);
    }
    public function verify(User $actor,string $id,array $input): void
    {
        $d=Validator::make($input,['revision'=>'required|integer|min:1','amount'=>'required|string','reference'=>'required|string|max:64','received'=>'required|accepted','reason'=>'required|string|min:3|max:2000'])->validate();
        DB::transaction(function()use($actor,$id,$d){
            $peek=DB::table('cod_refund_reconciliations')->find($id);abort_unless($peek,404);[$actor]=$this->locked($actor,$peek->order_id,true);
            $r=DB::table('cod_refund_reconciliations')->where('id',$id)->lockForUpdate()->first();abort_unless($r->status==='in_transit' && (int)$r->revision===(int)$d['revision'],409);
            if(app(CommissionLedger::class)->cents($d['amount'])!==(int)$r->amount_cents || strtoupper(trim($d['reference']))!==$r->reimbursement_reference)$this->fail('Verify the exact platform refund reimbursement receipt.');
            $t=app(LogisticsTreasury::class);$account=$t->account($r->account_id,$r->company_name);$t->move($actor,$account,'refund_transit',-(int)$r->amount_cents,'cod_refund_received',$id);
            DB::table('cod_refund_reconciliations')->where('id',$id)->update(['status'=>'verified','platform_verified_by'=>$actor->id,'platform_verified_at'=>now(),'revision'=>$r->revision+1,'updated_at'=>now()]);
            app(LogisticsFinance::class)->event($r->company_name,'cod_refund_reimbursement_received','cod_refund',$id,['amount_cents'=>$r->amount_cents,'reason'=>$d['reason']],$actor->id);
        },3);
    }
    public function attach(string $payoutId,Order $order): void
    {
        if(!$this->installed())return;$r=DB::table('cod_refund_reconciliations')->where('order_id',$order->id)->where('status','verified')->first();if(!$r)return;
        $gross=app(CommissionLedger::class)->cents($order->total)-(int)DB::table('logistics_shipping_obligations')->where('order_id',$order->id)->value('amount_cents');
        DB::table('cod_residual_settlements')->updateOrInsert(['payout_id'=>$payoutId],['reconciliation_id'=>$r->id,'original_product_cents'=>$gross,'buyer_refund_cents'=>$r->amount_cents,'remaining_product_cents'=>$r->remaining_cents,'seller_reimbursement_cents'=>$r->seller_reimbursement_cents]);
    }
}
