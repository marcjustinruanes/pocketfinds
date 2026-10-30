<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only operational reconciliation. Movement balances and ledger mirrors are never added together. */
class LogisticsReconciliation
{
    public const DATASETS = ['summary'=>'Reconciliation summary','transactions'=>'Financial transactions','collections'=>'COD collections','handovers'=>'Remittances and cash transfers','shipping'=>'Shipping obligations and vouchers',
        'seller_payouts'=>'Seller settlements','earnings'=>'Rider earnings','rider_payouts'=>'Rider payouts','staff_payouts'=>'Hub Staff salary payouts','treasury'=>'Treasury movements','audit'=>'Audit history','exceptions'=>'Manual review'];

    public function installed(): bool
    {
        foreach(['logistics_financial_transactions','logistics_cash_handovers','logistics_cash_allocations','logistics_cash_movements','logistics_shipping_obligations',
            'logistics_shipping_payments','logistics_treasury_accounts','logistics_treasury_movements','logistics_seller_payouts','logistics_rider_payouts','logistics_compensations'] as $table)if(!Schema::hasTable($table))return false;
        return Schema::hasColumn('logistics_compensations','earned_source_key');
    }

    public function snapshot(User $actor,string $from,string $to): array
    {
        abort_unless($this->installed(),503,'Finance migrations through Phase 6 are required. Existing financial pages remain available.');
        // No locking reads: every source shares the same committed read view while operational writes continue.
        if(DB::getDriverName()==='mysql' && DB::transactionLevel()===0)DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        return DB::transaction(function()use($actor,$from,$to){
            $actor=app(LogisticsFinance::class)->admin($actor);
            return $this->build($actor,$from,$to);
        });
    }

    private function build(User $actor,string $from,string $to): array
    {
        $company=$actor->business_name;$now=CarbonImmutable::now();$start=CarbonImmutable::parse($from,'Asia/Manila')->startOfDay()->setTimezone(config('app.timezone'));
        $end=CarbonImmutable::parse($to,'Asia/Manila')->endOfDay()->setTimezone(config('app.timezone'));
        $scoped=fn($table)=>DB::table($table)->where('company_name',$company)->get();
        $tx=$scoped('logistics_financial_transactions');$handovers=$scoped('logistics_cash_handovers')->keyBy('id');
        $collections=$tx->where('kind','cod_collection')->where('status','recorded')->keyBy('id');
        $allocations=DB::table('logistics_cash_allocations')->whereIn('handover_id',$handovers->keys())->get();
        $cash=DB::table('logistics_cash_movements')->whereIn('handover_id',$handovers->keys())->get();
        $hubs=$scoped('logistics_hubs')->keyBy('id');$accounts=$scoped('logistics_treasury_accounts')->keyBy('id');
        $treasury=DB::table('logistics_treasury_movements')->whereIn('account_id',$accounts->keys())->get();
        $obligations=$scoped('logistics_shipping_obligations')->keyBy('id');$shippingPayments=DB::table('logistics_shipping_payments')->whereIn('obligation_id',$obligations->keys())->get();
        $sellers=$scoped('logistics_seller_payouts');$riders=$scoped('logistics_rider_payouts');$earnings=$scoped('logistics_compensations');
        $staff = Schema::hasTable('logistics_staff_payouts') ? $scoped('logistics_staff_payouts') : collect();
        $openStaffByAccount=$staff->whereIn('status',['prepared','verified'])->groupBy('treasury_account_id');
        $staffById=$staff->keyBy('id');
        $shipments=DB::table('shipments')->where('logistics_company',$company)->get()->keyBy('id');
        $orders=Order::whereIn('id',$shipments->pluck('order_id'))->with('paymentMethod')->get()->keyBy('id');
        $audit=DB::table('logistics_financial_events')->where('company_name',$company)->whereBetween('created_at',[$start,$end])->orderByDesc('id')->limit(20001)->get();
        $proofs=$scoped('logistics_finance_evidence');
        // Build indexes once. Repeated collection scans made dense counted-cash history quadratic.
        $allocationsByCollection=$allocations->groupBy('collection_id');$allocationsByHandover=$allocations->groupBy('handover_id');
        $cashByHub=$cash->groupBy('hub_id');$cashByHandover=$cash->groupBy('handover_id');
        $treasuryByAccount=$treasury->groupBy('account_id');$treasuryBySource=$treasury->groupBy(fn($v)=>$v->source_type.'|'.$v->source_id);
        $sellerById=$sellers->keyBy('id');$riderById=$riders->keyBy('id');$earningsById=$earnings->keyBy('id');
        $openSellersByAccount=$sellers->whereIn('status',['prepared','verified'])->groupBy('treasury_account_id');
        $openRidersByAccount=$riders->whereIn('status',['prepared','verified'])->groupBy('treasury_account_id');
        $evidenceSources=$proofs->keyBy(fn($p)=>$p->source_type.'|'.$p->source_id);
        $ledgerBySellerPayout=$tx->whereNotNull('seller_payout_id')->groupBy('seller_payout_id');
        $ledgerByRiderPayout=$tx->whereNotNull('rider_payout_id')->groupBy('rider_payout_id');
        $collectionsByOrder=$collections->groupBy('collection_order_id')->map(fn($group)=>$group->first());
        $collectionsByShipment=$collections->keyBy('collection_shipment_id');
        $obligationsByOrder=$obligations->keyBy('order_id');$obligationsByShipment=$obligations->keyBy('shipment_id');
        $gcash=$tx->where('kind','gcash_shipping')->where('status','recorded')->where('verification_state','verified');$gcashByObligation=$gcash->groupBy('shipping_obligation_id');
        $qualifiedReceived=[];$qualifiedReleased=[];
        foreach($handovers as $h){
            if($h->kind==='remittance' && $h->counted_at)$qualifiedReceived[$h->hub_id]=($qualifiedReceived[$h->hub_id]??0)+(int)$allocationsByHandover->get($h->id,collect())->sum('verified_cents');
            if($h->kind==='transfer' && $h->counted_at)$qualifiedReceived[$h->destination_hub_id]=($qualifiedReceived[$h->destination_hub_id]??0)+(int)$h->cod_received_cents;
            if($h->dispatched_at)$qualifiedReleased[$h->hub_id]=($qualifiedReleased[$h->hub_id]??0)+($h->cod_released_cents===null?(int)$h->expected_cents:(int)$h->cod_released_cents);
        }
        $issues=[];$rows=array_fill_keys(array_keys(self::DATASETS),[]);
        $issue=function(string $code,string $id,string $message,?int $amount=null)use(&$issues){$issues[]=['code'=>$code,'source_id'=>$id,'message'=>$message,'amount_cents'=>$amount];};
        $history=function(string $set,array $row)use(&$rows,$start,$end){
            $at=$row['at']??null;if($at && CarbonImmutable::parse($at,config('app.timezone'))->betweenIncluded($start,$end))$rows[$set][]=$row;
        };
        $m=['cod_collected'=>0,'cod_verified'=>0,'cod_reserved'=>0,'cod_outstanding'=>0,'cod_overdue'=>0,'remittance_cash'=>0,'remittance_overages'=>0,
            'hub_cash'=>0,'in_transit'=>0,'transfer_variance'=>0,'gcash_receipts'=>0,'gcash_pending_proofs'=>0,'gcash_unpaid_obligations'=>0,
            'cod_shipping_open'=>0,'cod_shipping_retained'=>0,'seller_vouchers'=>0,'platform_claims'=>0,'seller_paid'=>0,'seller_product_unpaid'=>0,
            'seller_eligible_unpaid'=>0,'seller_adjustment_reserved'=>0,'rider_earned'=>0,'rider_pending'=>0,'rider_paid'=>0,'rider_unpaid'=>0,
            'held_seller'=>0,'held_rider'=>0,'discrepancies_open'=>0,'discrepancies_resolved'=>0,'legacy_shipments'=>0,'legacy_delivered_cod'=>0];
        $cutoff=DB::table('logistics_finance_settings')->where('company_name',$company)->value('remittance_cutoff')??'23:59';
        $collectionBalances=[];
        foreach($collections as $c){
            $a=$allocationsByCollection->get($c->id,collect());$verified=0;$reserved=0;
            foreach($a as $allocation){$h=$handovers[$allocation->handover_id];
                if($h->kind!=='remittance' || (int)$h->hub_id!==(int)$c->hub_id || (int)$h->actor_id!==(int)$c->actor_id)$issue('allocation_scope',$c->id,'Collection allocation does not match its recorded rider/hub.');
                if($h->counted_at)$verified+=(int)$allocation->verified_cents;
                elseif($h->status==='submitted')$reserved+=(int)$allocation->expected_cents;
                elseif((int)$allocation->verified_cents!==0)$issue('uncounted_allocation',$c->id,'Verified allocation lacks a physical count.');
            }
            $collectionBalances[$c->id]=$verified;
            $out=(int)$c->amount_cents-$verified;$m['cod_collected']+=(int)$c->amount_cents;$m['cod_verified']+=$verified;$m['cod_reserved']+=$reserved;$m['cod_outstanding']+=$out;
            if($verified<0 || $reserved<0 || $verified+$reserved>(int)$c->amount_cents)$issue('collection_overallocated',$c->id,'Verified and reserved amounts exceed the COD collection.',$verified+$reserved-(int)$c->amount_cents);
            $state=$out===0?'verified':($verified>0?'partial':'pending');if($state!==$c->verification_state)$issue('collection_state',$c->id,'Stored verification state differs from counted allocations.');
            $due=CarbonImmutable::parse($c->recorded_at,config('app.timezone'))->setTimezone('Asia/Manila')->setTimeFromTimeString($cutoff);
            if($out>0 && $now->greaterThan($due))$m['cod_overdue']+=$out;
            $history('collections',['id'=>$c->id,'at'=>$c->recorded_at,'status'=>$state,'amount_cents'=>(int)$c->amount_cents,'verified_cents'=>$verified,'reserved_cents'=>$reserved,'outstanding_cents'=>$out,'order_id'=>$c->order_id,'hub_id'=>$c->hub_id,'rider_id'=>$c->actor_id]);
        }
        foreach($allocations as $a)if(!isset($collections[$a->collection_id]))$issue('allocation_collection',$a->handover_id,'Allocation has no matching company recorded COD collection.');
        $hubRows=[];
        foreach($hubs as $h){$balance=(int)$cashByHub->get($h->id,collect())->sum('delta_cents');$m['hub_cash']+=$balance;
            $received=$qualifiedReceived[$h->id]??0;$released=$qualifiedReleased[$h->id]??0;
            $hubRows[]=['id'=>$h->id,'name'=>$h->municipality.', '.$h->province,'operational'=>(bool)$h->is_operational,'cash_cents'=>$balance,'qualified_cod_cents'=>max(0,min($balance,$received-$released))];
            if($balance<0)$issue('hub_negative',(string)$h->id,'Hub movement balance is negative.',$balance);
        }
        foreach($cash as $movement)if(!isset($hubs[$movement->hub_id]))$issue('cash_scope',(string)$movement->id,'Cash movement points outside company hubs.');
        foreach($handovers as $h){
            $moves=$cashByHandover->get($h->id,collect());$released=-(int)$moves->filter(fn($v)=>$v->delta_cents<0)->sum('delta_cents');
            if(!isset($hubs[$h->hub_id]) || ($h->destination_hub_id && !isset($hubs[$h->destination_hub_id])))$issue('handover_scope',$h->id,'Handover hub ownership requires review.');
            if($h->kind==='remittance'){
                $actual=(int)$h->counted_cents;
                if($h->counted_at){$m['remittance_cash']+=$actual;$allocated=(int)$allocationsByHandover->get($h->id,collect())->sum('verified_cents');
                    $m['remittance_overages']+=max(0,$actual-(int)$h->expected_cents);
                    if((int)$moves->where('kind','cash_received')->sum('delta_cents')!==$actual)$issue('remittance_movement',$h->id,'Physical remittance count differs from its custody credit.');
                    if($allocated!==min($actual,(int)$h->expected_cents))$issue('remittance_allocation',$h->id,'Counted cash is not allocated exactly up to the expected amount.');
                }elseif($moves->isNotEmpty())$issue('uncounted_movement',$h->id,'Uncounted remittance has cash movements.');
            }else{
                if($h->dispatched_at && !$h->counted_at)$m['in_transit']+=$released;
                if($h->dispatched_at && $released!==(int)$h->expected_cents)$issue('release_movement',$h->id,'Authorized/dispatched amount differs from custody release.');
                if(!$h->dispatched_at && $released!==0)$issue('undispatched_movement',$h->id,'Cash release has no dispatch confirmation.');
                if($h->counted_at){$m['transfer_variance']+=(int)$h->counted_cents-$released;
                    if($h->kind==='transfer' && (int)$moves->where('kind','transfer_received')->sum('delta_cents')!==(int)$h->counted_cents)$issue('transfer_movement',$h->id,'Destination cash count differs from custody receipt.');
                    if($h->kind==='treasury'){
                        $credits=$treasuryBySource->get('treasury_receipt|'.$h->id,collect());
                        if((int)$credits->sum('delta_cents')!==(int)$h->counted_cents || (int)$credits->where('bucket','cod')->sum('delta_cents')!==(int)$h->cod_received_cents)$issue('treasury_receipt',$h->id,'Treasury receipt differs from actual counted/qualified COD cash.');
                    }
                }
                if($h->dispatched_at && $h->cod_released_cents===null)$issue('legacy_cash_classification',$h->id,'Legacy release has no qualified COD classification; do not infer funding.');
            }
            if($h->status==='discrepancy'){$m[$h->resolved_at?'discrepancies_resolved':'discrepancies_open']++;$issue($h->resolved_at?'discrepancy_resolved':'discrepancy_open',$h->id,$h->resolved_at?'Investigation documented; original count difference remains.':'Physical cash discrepancy needs investigation.',$h->counted_at?(int)$h->counted_cents-(int)$h->expected_cents:null);}
            $history('handovers',['id'=>$h->id,'at'=>$h->counted_at??$h->dispatched_at??$h->created_at,'status'=>$h->status,'kind'=>$h->kind,'amount_cents'=>(int)$h->expected_cents,'counted_cents'=>$h->counted_cents===null?null:(int)$h->counted_cents,'released_cents'=>$released,'hub_id'=>$h->hub_id,'destination_hub_id'=>$h->destination_hub_id,'counted_at'=>$h->counted_at,'dispatched_at'=>$h->dispatched_at,'resolved_at'=>$h->resolved_at]);
        }
        $accountRows=[];$bucketTotals=array_fill_keys(['cod','adjustment','rider_payroll','staff_payroll','unallocated'],0);$reservationTotals=$bucketTotals;
        foreach($accounts as $a){$b=array_fill_keys(array_keys($bucketTotals),0);$r=$b;foreach($b as $bucket=>$_)$b[$bucket]=(int)$treasuryByAccount->get($a->id,collect())->where('bucket',$bucket)->sum('delta_cents');
            $openS=$openSellersByAccount->get($a->id,collect());$openR=$openRidersByAccount->get($a->id,collect());
            $r['cod']=(int)$openS->sum('product_cents');$r['adjustment']=(int)$openS->sum('adjustment_cents');$r['rider_payroll']=(int)$openR->sum('amount_cents');$r['staff_payroll']=(int)$openStaffByAccount->get($a->id,collect())->sum('amount_cents');
            foreach($b as $bucket=>$amount){$bucketTotals[$bucket]+=$amount;$reservationTotals[$bucket]+=$r[$bucket];if($amount<0 || $amount<$r[$bucket])$issue('treasury_balance',$a->id,'Negative or over-reserved '.$bucket.' treasury balance.',$amount-$r[$bucket]);}
            $accountRows[]=['id'=>$a->id,'name'=>$a->institution.' / '.$a->account_name,'balances'=>$b,'reserved'=>$r];
        }
        foreach($treasury as $v){
            if(!in_array($v->source_type,['treasury_receipt','adjustment_funding','rider_funding','staff_funding','seller_payout','rider_payout','staff_payout']))$issue('movement_source',(string)$v->id,'Unknown treasury source requires review.');
            if(in_array($v->source_type,['treasury_receipt','adjustment_funding','rider_funding','staff_funding']) && (int)$v->delta_cents<0)$issue('funding_sign',(string)$v->id,'Funding/receipt movement is negative.');
            if(in_array($v->source_type,['adjustment_funding','rider_funding','staff_funding']) && $v->bucket!==match($v->source_type){'rider_funding'=>'rider_payroll','staff_funding'=>'staff_payroll',default=>'adjustment'})$issue('funding_bucket',(string)$v->id,'Contribution was credited to an incorrect bucket.');
            if(!isset($evidenceSources[$v->source_type.'|'.$v->source_id]))$issue('missing_evidence',(string)$v->id,'Treasury source has no matching company private evidence record.');
            if(in_array($v->source_type,['seller_payout','rider_payout','staff_payout'])){$source=match($v->source_type){'seller_payout'=>$sellerById,'staff_payout'=>$staffById,default=>$riderById};if(!isset($source[$v->source_id]))$issue('orphan_debit',(string)$v->id,'Treasury debit has no company payout.');}
            if(!array_key_exists($v->bucket,$bucketTotals))$issue('unknown_bucket',(string)$v->id,'Unknown treasury bucket requires reconciliation.');
            $history('treasury',['id'=>(string)$v->id,'at'=>$v->created_at,'status'=>'recorded','bucket'=>$v->bucket,'amount_cents'=>(int)$v->delta_cents,'source_type'=>$v->source_type,'source_id'=>$v->source_id,'account_id'=>$v->account_id,'reference'=>$v->reference]);
        }
        foreach($staff as $p){
            $moves=$treasuryBySource->get('staff_payout|'.$p->id,collect());
            if($p->status==='paid' && ((int)$moves->sum('delta_cents')!==-(int)$p->amount_cents || (int)$moves->where('bucket','staff_payroll')->sum('delta_cents')!==-(int)$p->amount_cents))$issue('staff_payment',$p->id,'Staff payment debit or funding bucket differs from its obligation.');
            if($p->status!=='paid' && $moves->isNotEmpty())$issue('staff_payment',$p->id,'Unpaid staff instruction has a treasury debit.');
            $history('staff_payouts',['id'=>$p->id,'at'=>$p->paid_at??$p->created_at,'status'=>$p->status,'staff_id'=>$p->staff_id,'period_id'=>$p->period_id,'amount_cents'=>(int)$p->amount_cents,'reference'=>$p->reference,'acknowledged_at'=>$p->acknowledged_at]);
        }
        foreach($tx as $t){$details=json_decode($t->details,true);
            $history('transactions',['id'=>$t->id,'at'=>$t->recorded_at,'status'=>$t->status,'kind'=>$t->kind,'amount_cents'=>(int)$t->amount_cents,'verification_state'=>$t->verification_state,'order_id'=>$t->order_id,'reference'=>$details['reference']??null]);
            if(in_array($t->kind,['seller_payout','rider_payout'])){$source=$t->kind==='seller_payout'?$sellerById:$riderById;$col=$t->kind.'_id';if(!$t->{$col} || !isset($source[$t->{$col}]))$issue('orphan_payment',$t->id,'Payment ledger mirror has no company payout.');}
        }
        $gcash=$tx->where('kind','gcash_shipping')->where('status','recorded')->where('verification_state','verified');
        $m['gcash_receipts']=(int)$gcash->sum('amount_cents');$m['gcash_pending_proofs']=(int)$shippingPayments->where('status','submitted')->sum('amount_cents');
        $paidProducts=$sellers->where('status','paid')->keyBy('order_id');$eligible=app(CodSellerPayouts::class);
        foreach($obligations as $o){$s=$shipments[$o->shipment_id]??null;$order=$orders[$o->order_id]??null;
            if(!$s || !$order || $s->order_id!==$o->order_id)$issue('obligation_source',$o->id,'Shipping obligation lacks matching company shipment/order evidence.');
            $receipts=$gcashByObligation->get($o->id,collect());$funding=json_decode($o->voucher_funding,true);$sellerFunding=0;$platformFunding=0;
            if(!is_array($funding))$issue('voucher_funding',$o->id,'Voucher funding snapshot is not readable.');
            else foreach($funding as $f){if(($f['source']??null)==='seller')$sellerFunding+=(int)($f['amount_cents']??0);elseif(($f['source']??null)==='platform')$platformFunding+=(int)($f['amount_cents']??0);else $issue('voucher_funding',$o->id,'Voucher funding source is unknown.');}
            if((int)$o->platform_claim_cents>0)$issue('platform_claim',$o->id,'Platform-funded voucher claim remains separate, unverified and unpaid.',(int)$o->platform_claim_cents);
            $m['seller_vouchers']+=$sellerFunding;$m['platform_claims']+=(int)$o->platform_claim_cents;
            if($platformFunding!==(int)$o->platform_claim_cents || $sellerFunding+$platformFunding>(int)$o->amount_cents)$issue('voucher_amount',$o->id,'Voucher funding does not reconcile to the gross shipping snapshot.');
            if(!in_array($o->collection_mode,['gcash','cod']))$issue('unsupported_collection_mode',$o->id,'Shipping obligation uses an unsupported settlement mode and needs manual review.');
            if($o->collection_mode==='gcash'){
                if($o->status==='verified' && ($receipts->count()!==1 || (int)$receipts->sum('amount_cents')!==(int)$o->amount_cents))$issue('shipping_receipt',$o->id,'Verified GCash shipping obligation lacks one exact verified receipt.');
                if($o->status!=='verified')$m['gcash_unpaid_obligations']+=(int)$o->amount_cents;
            }elseif($o->collection_mode==='cod'){
                $paid=$paidProducts[$o->order_id]??null;
                if($paid){$m['cod_shipping_retained']+=(int)$paid->shipping_cents;if($o->status!=='cod_retained' || (int)$paid->shipping_cents!==(int)$o->amount_cents)$issue('shipping_retention',$o->id,'Paid seller shipping retention differs from the obligation.');}
                else {$m['cod_shipping_open']+=(int)$o->amount_cents;if($o->status==='cod_retained')$issue('shipping_retention',$o->id,'COD retention has no actual recorded seller payout.');}
                $c=$collectionsByOrder->get($o->order_id);
                if($c){$product=(int)$c->amount_cents-(int)$o->amount_cents;if($product<0)$issue('negative_seller_basis',$o->id,'COD cannot cover gross shipping; a funding exception is required.',$product);
                    else {$unpaid=$product-(int)($paid?->product_cents??0);$m['seller_product_unpaid']+=$unpaid;
                        if(!$paid && $order){$e=$eligible->eligibility($order,$company,false);if($e['eligible'])$m['seller_eligible_unpaid']+=$product;else $issue('seller_eligibility',$o->order_id,$e['reason'],$product);}
                    }
                }
            }
            if($s && ($s->shipping_status==='cancelled' || ($order && in_array($order->status,['cancelled','refunded']))))$issue('cancelled_financial_review',$o->id,'Cancelled/refunded shipment retains financial history; refunds are not inferred.',(int)$o->amount_cents);
            $history('shipping',['id'=>$o->id,'at'=>$o->created_at,'status'=>$o->status,'mode'=>$o->collection_mode,'amount_cents'=>(int)$o->amount_cents,'seller_voucher_cents'=>$sellerFunding,'platform_claim_cents'=>(int)$o->platform_claim_cents,'claim_status'=>$o->claim_status,'order_id'=>$o->order_id,'shipment_id'=>$o->shipment_id]);
        }
        foreach($collections as $c)if(!isset($obligationsByOrder[$c->collection_order_id]))$issue('unknown_seller_basis',$c->id,'Recorded COD lacks a validated shipping snapshot; seller proceeds cannot be calculated.');
        if(Schema::hasTable('complaints'))foreach(DB::table('complaints')->whereIn('order_id',$orders->keys())->where(fn($q)=>$q->where('status','!=','resolved')->orWhereNull('status'))->get() as $d)$issue('active_dispute',$d->order_id,'Active order dispute requires review, including any previously recorded payment.');
        if(Schema::hasTable('commerce_returns'))foreach(DB::table('commerce_returns')->whereIn('order_id',$orders->keys())->get() as $r){if($r->status!=='rejected' || !$r->decided_at || CarbonImmutable::parse($r->decided_at,config('app.timezone'))->addDays(3)->isFuture())$issue('active_return',$r->order_id,'Return/refund or appeal protection requires financial review; no remaining payable or refund is inferred.');}
        foreach($gcash as $g)if(!isset($obligations[$g->shipping_obligation_id]))$issue('shipping_orphan',$g->id,'Verified shipping ledger receipt has no company obligation.');
        foreach(['seller'=>$sellers,'rider'=>$riders] as $type=>$payouts)foreach($payouts as $p){
            $amount=$type==='seller'?(int)$p->product_cents+(int)$p->adjustment_cents:(int)$p->amount_cents;
            if($type==='rider'){$basis=$earningsById->get($p->compensation_id);if(!$basis || $basis->status!=='earned' || (int)$basis->rider_id!==(int)$p->rider_id || (int)$basis->amount_cents!==$amount)$issue('rider_payout_basis',$p->id,'Rider payout does not match the company earned snapshot.');}
            else {$basis=$collections[$p->collection_id]??null;if(!$basis || (int)$basis->amount_cents!==(int)$p->product_cents+(int)$p->shipping_cents || ($p->status!=='cancelled' && ($collectionBalances[$p->collection_id]??0)!==(int)$basis->amount_cents))$issue('seller_payout_basis',$p->id,'Seller instruction lacks full verified collection or an exact cash/shipping basis.');}
            $debits=$treasuryBySource->get($type.'_payout|'.$p->id,collect());$ledger=($type==='seller'?$ledgerBySellerPayout:$ledgerByRiderPayout)->get($p->id,collect());
            if($p->status==='paid'){
                $m[$type.'_paid']+=$amount;
                if((int)$debits->sum('delta_cents')!==-$amount || $ledger->count()!==1 || (int)$ledger->sum('amount_cents')!==$amount || $ledger->first()?->status!=='recorded' || $ledger->first()?->verification_state!=='verified')$issue('payout_mirror',$p->id,'Paid payout does not match exactly one ledger payment and its treasury debit.',$amount);
                $expected=$type==='seller'?['cod'=>(int)$p->product_cents,'adjustment'=>(int)$p->adjustment_cents]:['rider_payroll'=>$amount];
                foreach($expected as $bucket=>$value)if(-(int)$debits->where('bucket',$bucket)->sum('delta_cents')!==$value)$issue('payout_bucket',$p->id,'Payout used an incorrect funding bucket.');
            }elseif($debits->isNotEmpty() || $ledger->isNotEmpty())$issue('unpaid_debit',$p->id,'Unpaid/cancelled instruction already has a debit or payment ledger record.');
            if($p->hold_reason && in_array($p->status,['prepared','verified']))$m['held_'.$type]+=$amount;
            if($type==='seller' && in_array($p->status,['prepared','verified']))$m['seller_adjustment_reserved']+=(int)$p->adjustment_cents;
            if(!isset($accounts[$p->treasury_account_id]))$issue('payout_account',$p->id,'Payout is not linked to a company treasury account.');
            $history($type.'_payouts',['id'=>$p->id,'at'=>$p->paid_at??$p->verified_at??$p->created_at,'status'=>$p->status,'amount_cents'=>$amount,'recipient_id'=>$type==='seller'?$p->seller_id:$p->rider_id,'reference'=>$p->reference,'paid_at'=>$p->paid_at,'acknowledged_at'=>$p->acknowledged_at,'hold_reason'=>$p->hold_reason]);
        }
        $assignmentSources=DB::table('delivery_assignments')->whereIn('id',$earnings->where('source_type','assignment')->pluck('source_id'))->get()->keyBy('id');
        $batchSources=DB::table('hub_transfer_batches')->whereIn('id',$earnings->where('source_type','transfer_batch')->pluck('source_id'))->get()->keyBy('id');
        foreach($earnings as $c){
            $source=$c->source_type==='assignment'?($assignmentSources[$c->source_id]??null):($batchSources[$c->source_id]??null);
            if(!$source)$issue('missing_assignment',$c->id,'Compensation snapshot source is missing; do not invent completion.');
            elseif($c->status==='pending' && in_array($source->status,['completed','delivered']))$issue('pending_completed_snapshot',$c->id,'Completed source has a pending snapshot; review authoritative evidence in Rider Earnings & Payouts.');
if($c->status==='earned'){$m['rider_earned']+=(int)$c->amount_cents;if(!$c->earned_at || $c->earned_source_key!==$c->source_type.'|'.$c->source_id)$issue('earned_evidence',$c->id,'Earned compensation lacks completion timestamp/source key.');}
            elseif($c->status==='pending')$m['rider_pending']+=(int)$c->amount_cents;
            $history('earnings',['id'=>$c->id,'at'=>$c->earned_at??$c->created_at,'status'=>$c->status,'amount_cents'=>(int)$c->amount_cents,'rider_id'=>$c->rider_id,'leg'=>$c->leg,'source_type'=>$c->source_type,'source_id'=>$c->source_id,'rate_revision'=>$c->settings_revision]);
        }
        $m['rider_unpaid']=$m['rider_earned']-$m['rider_paid'];if($m['rider_unpaid']<0)$issue('rider_liability','company','Rider payments exceed recorded earned compensation.',$m['rider_unpaid']);
        foreach($shipments as $s){$order=$orders[$s->order_id]??null;if((int)$s->shipping_finance_version===0){$m['legacy_shipments']++;if(!isset($obligationsByShipment[$s->id]))$issue('legacy_shipping_basis',$s->id,'Historical shipment has no approved financial obligation; no shipping payment or revenue is inferred.');if($order && app(CodCollection::class)->isCod($order) && in_array($s->shipping_status,['delivered','completed']) && !isset($collectionsByShipment[$s->id])){$m['legacy_delivered_cod']++;$issue('legacy_cod',$s->id,'Historical delivered COD has no recorded collection. Do not infer payment.');}}
            elseif(!isset($obligationsByShipment[$s->id]))$issue('missing_obligation',$s->id,'New-policy shipment is missing its approved shipping obligation.');
        }
        foreach($audit as $event)$rows['audit'][]=['id'=>(string)$event->id,'at'=>$event->created_at,'status'=>$event->event,'source_type'=>$event->subject_type,'source_id'=>$event->subject_id,'actor_id'=>$event->actor_id];
        if($audit->count()>20000)$issue('audit_limit','company','More than 20,000 audit events in the selected period; narrow dates for the audit export.');
        $sellerCodOut=-(int)$treasury->where('bucket','cod')->where('source_type','seller_payout')->sum('delta_cents');
        $expected=$m['remittance_cash']+$m['transfer_variance']-$sellerCodOut;
        $actual=$m['hub_cash']+$m['in_transit']+$bucketTotals['cod']+$bucketTotals['unallocated'];
        $residual=$actual-$expected;if($residual!==0)$issue('cash_conservation','company','Hub cash, transit and COD treasury do not reconcile to counted cash, transfer differences and seller COD disbursements.',$residual);
        foreach($m as $key=>$value)$rows['summary'][]=['metric'=>$key,'value'=>$value,'unit'=>in_array($key,['discrepancies_open','discrepancies_resolved','legacy_shipments','legacy_delivered_cod'])?'count':'centavos'];
        foreach($bucketTotals as $bucket=>$value){$rows['summary'][]=['metric'=>$bucket.'_treasury_balance','value'=>$value,'unit'=>'centavos'];$rows['summary'][]=['metric'=>$bucket.'_reserved','value'=>$reservationTotals[$bucket],'unit'=>'centavos'];}
        $rows['summary'][]=['metric'=>'cash_conservation_residual','value'=>$residual,'unit'=>'centavos'];
        $rows['exceptions']=$issues;
        foreach($rows as $set=>&$dataset)if(!in_array($set,['exceptions','summary']))usort($dataset,fn($a,$b)=>strcmp($b['at'],$a['at']));unset($dataset);
        return ['company'=>$company,'generated_at'=>$now->toIso8601String(),'from'=>$from,'to'=>$to,'metrics'=>$m,'buckets'=>$bucketTotals,'reserved'=>$reservationTotals,
            'hubs'=>$hubRows,'accounts'=>$accountRows,'issues'=>$issues,'rows'=>$rows,'conservation'=>['expected_cents'=>$expected,'actual_cents'=>$actual,'residual_cents'=>$residual]];
    }

    public static function money(int $cents): string { return ($cents<0?'-':'').intdiv(abs($cents),100).'.'.str_pad((string)(abs($cents)%100),2,'0',STR_PAD_LEFT); }
    public static function csvCell(mixed $value): string
    {
        if(is_int($value))return (string)$value;
        $value=(string)($value??'');return preg_match('/^(?:\s|\x{FEFF})*[=+@-]|^[\t\r\n]/u',$value)?"'".$value:$value;
    }
}
