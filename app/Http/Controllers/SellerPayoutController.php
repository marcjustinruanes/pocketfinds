<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CodSellerPayouts;
use App\Services\LogisticsTreasury;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SellerPayoutController extends LogisticsController
{
    public function index(Request $request,LogisticsTreasury $treasury,CodSellerPayouts $flow)
    {
        abort_unless($treasury->installed(),503,'The seller payout migration is required.');
        $actor=$request->user()->fresh();$seller=$actor->account_type==='seller';
        if($seller)app(\App\Services\GcashWorkflow::class)->eligible($actor,'seller');else $actor=$treasury->admin($actor);
        $payouts=DB::table('logistics_seller_payouts')->when($seller,fn($q)=>$q->where('seller_id',$actor->id),fn($q)=>$q->where('company_name',$actor->business_name))
            ->orderByDesc('created_at')->paginate(15);
        $orders=$seller?collect():Order::whereNull('gcash_purchase_id')->whereIn('status',['delivered','completed'])
            ->whereHas('shipment',fn($q)=>$q->where('logistics_company',$actor->business_name))
            ->where(fn($q)=>$q->whereIn('payment_method',['COD','Cash on Delivery'])->orWhereHas('paymentMethod',fn($m)=>$m->where('type','cod')))
            ->orderByDesc('created_at')->paginate(15,['*'],'orders_page');
        $eligibility=[];foreach($orders as $order)$eligibility[$order->id]=$flow->eligibility($order,$actor->business_name);
        $existingPayouts=$seller?collect():DB::table('logistics_seller_payouts')->where('company_name',$actor->business_name)->whereIn('order_id',$orders->pluck('id'))->pluck('status','order_id');
        $payoutEligibility=[];if(!$seller)foreach($payouts as $p)if(in_array($p->status,['prepared','verified']))$payoutEligibility[$p->id]=$flow->eligibility(Order::findOrFail($p->order_id),$actor->business_name);
        $accounts=$seller?collect():DB::table('logistics_treasury_accounts')->where('company_name',$actor->business_name)->get();
        $balances=[];foreach($accounts as $account)$balances[$account->id]=['cod'=>$treasury->balance($account->id,'cod'),'adjustment'=>$treasury->balance($account->id,'adjustment'),'unallocated'=>$treasury->balance($account->id,'unallocated'),
            'cod_available'=>$treasury->available($account->id,'cod'),'adjustment_available'=>$treasury->available($account->id,'adjustment')];
        $hubs=$seller?collect():DB::table('logistics_hubs')->where('company_name',$actor->business_name)->where('is_operational',true)->get();
        $transfers=$seller?collect():DB::table('logistics_cash_handovers')->where('company_name',$actor->business_name)->where('kind','treasury')->orderByDesc('created_at')->paginate(10,['*'],'treasury_page');
        $sellerIds=$seller?[$actor->id]:array_unique(array_merge($payouts->pluck('seller_id')->all(),$orders->pluck('seller_id')->all()));
        $destinations=DB::table('logistics_payout_destinations')->whereIn('seller_id',$sellerIds)->orderByDesc('created_at')->get()->keyBy('id');
        $people=DB::table('users')->whereIn('id',$sellerIds)->pluck('username','id');
        $evidence=DB::table('logistics_finance_evidence')->where('source_type','seller_payout')->whereIn('source_id',$payouts->pluck('id'))->get()->keyBy('source_id');
        $history=DB::table('logistics_financial_events')->where('subject_type','seller_payout')->whereIn('subject_id',$payouts->pluck('id'))->orderBy('id')->get()->groupBy('subject_id');
        $treasuryEvidence=$seller?collect():DB::table('logistics_finance_evidence')->where('company_name',$actor->business_name)->where('source_type','treasury_receipt')->whereIn('source_id',$transfers->pluck('id'))->get()->keyBy('source_id');
        $treasuryReceipts=$seller?collect():DB::table('logistics_treasury_movements')->whereIn('account_id',$accounts->pluck('id'))->where('source_type','treasury_receipt')->where('bucket','cod')->whereIn('source_id',$transfers->pluck('id'))->get()->keyBy('source_id');
        $fundingMovements=$seller?collect():DB::table('logistics_treasury_movements')->whereIn('account_id',$accounts->pluck('id'))->where('source_type','adjustment_funding')->orderByDesc('id')->limit(30)->get();
        $fundingEvidence=$seller?collect():DB::table('logistics_finance_evidence')->where('company_name',$actor->business_name)->where('source_type','adjustment_funding')->whereIn('source_id',$fundingMovements->pluck('source_id'))->get()->keyBy('source_id');
        return response()->view('logistics.seller-payouts',array_merge($seller?[]:$this->sidebarCounts(),compact('seller','actor','payouts','orders','eligibility','existingPayouts','payoutEligibility','accounts','balances','hubs','transfers','destinations','people','evidence','history','treasuryEvidence','treasuryReceipts','fundingMovements','fundingEvidence')))
            ->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff');
    }

    private function run(callable $callback)
    {
        try{$callback();}catch(\Illuminate\Database\QueryException $e){
            if(($e->errorInfo[1]??null)===1062 || (($e->errorInfo[1]??null)===19 && str_contains(strtolower($e->getMessage()),'unique constraint failed')))abort(409,'This financial record or reference already exists. Refresh before retrying.');
            throw $e;
        }
        return back()->with('success','Financial action recorded.');
    }
    public function treasuryAccount(Request $r,LogisticsTreasury $t){return $this->run(fn()=>$t->createAccount($r->user(),$r->all()));}
    public function destination(Request $r,LogisticsTreasury $t){return $this->run(fn()=>$t->destination($r->user(),$r->all()));}
    public function transfer(Request $r,LogisticsTreasury $t){return $this->run(fn()=>$t->transfer($r->user(),$r->all()));}
    public function release(Request $r,string $id,LogisticsTreasury $t){$d=$r->validate(['amount'=>'required|string']);return $this->run(fn()=>$t->release($r->user(),$id,$d['amount']));}
    public function receipt(Request $r,string $id,LogisticsTreasury $t){$r->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);return $this->run(fn()=>$t->receipt($r->user(),$id,$r->all(),$r->file('proof')));}
    public function funding(Request $r,LogisticsTreasury $t){$r->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);return $this->run(fn()=>$t->adjustmentFunding($r->user(),$r->all(),$r->file('proof')));}
    public function prepare(Request $r,CodSellerPayouts $f){return $this->run(fn()=>$f->prepare($r->user(),$r->all()));}
    public function action(Request $r,string $id,CodSellerPayouts $f){return $this->run(fn()=>$f->action($r->user(),$id,$r->all()));}
    public function pay(Request $r,string $id,CodSellerPayouts $f){$r->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);return $this->run(fn()=>$f->pay($r->user(),$id,$r->all(),$r->file('proof')));}
    public function acknowledge(Request $r,string $id,CodSellerPayouts $f){$r->validate(['received'=>'required|accepted']);return $this->run(fn()=>$f->acknowledge($r->user(),$id));}

    public function evidence(Request $r,string $id,LogisticsTreasury $t)
    {
        abort_unless($t->installed(),503);
        $row=DB::table('logistics_finance_evidence')->where('id',$id)->firstOrFail();$actor=$r->user()->fresh();
        if($actor->account_type==='rider'){
            app(\App\Services\RiderCompensation::class)->rider($actor);
            abort_unless($row->source_type==='rider_payout' && DB::table('logistics_rider_payouts')->where('id',$row->source_id)->where('rider_id',$actor->id)->exists(),403);
        }elseif($actor->account_type==='seller'){

            app(\App\Services\GcashWorkflow::class)->eligible($actor,'seller');
            abort_unless($row->source_type==='seller_payout' && DB::table('logistics_seller_payouts')->where('id',$row->source_id)->where('seller_id',$actor->id)->exists(),403);
        }elseif($actor->isHubStaff()){
            abort_unless($actor->status==='approved' && $actor->business_name,403);
            // Hub Staff may view their own salary payment evidence or existing issue/recovery evidence.
            if($row->source_type==='staff_payout'){
                abort_unless(DB::table('logistics_staff_payouts')->where('id',$row->source_id)->where('staff_id',$actor->id)->where('company_name',$actor->business_name)->exists() && $row->company_name===$actor->business_name,403);
            }else{
                abort_unless(in_array($row->source_type,['parcel_recovery','parcel_issue']) && $row->company_name===$actor->business_name,403);
                $hub=app(\App\Services\LogisticsAccess::class)->hub($actor);
                abort_unless(DB::table('logistics_issues')->where('id',$row->source_id)->where('company_name',$actor->business_name)->where('hub_id',$hub->id)->when($row->source_type==='parcel_recovery',fn($q)=>$q->whereNotNull('recovery'))->whereIn('shipment_id',app(\App\Services\LogisticsAccess::class)->shipments($actor)->select('shipments.id'))->exists(),403);
            }
        }else{$actor=$t->admin($actor);abort_unless($row->company_name===$actor->business_name,403);}
        abort_unless(preg_match('#^logistics-finance-proofs/[A-Za-z0-9]+\.(jpg|jpeg|png|webp|pdf)$#D',$row->path) && Storage::disk('local')->exists($row->path),404);
        return response()->file(Storage::disk('local')->path($row->path),['Content-Type'=>$row->mime,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"default-src 'none'; sandbox"])->setPrivate();
    }
}
