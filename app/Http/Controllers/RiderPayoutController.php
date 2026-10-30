<?php

namespace App\Http\Controllers;

use App\Services\RiderCompensation;
use App\Services\LogisticsTreasury;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderPayoutController extends LogisticsController
{
    public function index(Request $r,RiderCompensation $flow)
    {
        abort_unless($flow->installed(),503,'The rider compensation migration is required.');
        $actor=$r->user()->fresh();$rider=$actor->account_type==='rider';
        $actor=$rider?$flow->rider($actor):app(LogisticsTreasury::class)->admin($actor);
        $scope=fn($q)=>$rider?$q->where('rider_id',$actor->id):$q->where('company_name',$actor->business_name);
        $earnings=$scope(DB::table('logistics_compensations'))->orderByDesc('created_at')->paginate(20,['*'],'earnings_page');
        $payouts=$scope(DB::table('logistics_rider_payouts'))->orderByDesc('created_at')->paginate(15,['*'],'payouts_page');
        $totals=['earned'=>(int)$scope(DB::table('logistics_compensations'))->where('status','earned')->sum('amount_cents'),
            'paid'=>(int)$scope(DB::table('logistics_rider_payouts'))->where('status','paid')->sum('amount_cents'),
            'reserved'=>(int)$scope(DB::table('logistics_rider_payouts'))->whereIn('status',['prepared','verified'])->sum('amount_cents')];
        $ids=$earnings->pluck('rider_id')->merge($payouts->pluck('rider_id'))->push($actor->id)->unique();
        $people=DB::table('users')->whereIn('id',$ids)->pluck('username','id');
        $destinations=DB::table('logistics_rider_destinations')->whereIn('rider_id',$ids)->get()->keyBy('id');
        $accounts=$rider?collect():DB::table('logistics_treasury_accounts')->where('company_name',$actor->business_name)->get();
        $balances=[];foreach($accounts as $a)$balances[$a->id]=['balance'=>app(LogisticsTreasury::class)->balance($a->id,'rider_payroll'),'available'=>$flow->available($a->id)];
        $existing=$scope(DB::table('logistics_rider_payouts'))->whereIn('compensation_id',$earnings->pluck('id'))->get()->keyBy('compensation_id');
        $evidence=DB::table('logistics_finance_evidence')->where('source_type','rider_payout')->whereIn('source_id',$payouts->pluck('id'))->get()->keyBy('source_id');
        $history=DB::table('logistics_financial_events')->where('subject_type','rider_payout')->whereIn('subject_id',$payouts->pluck('id'))->orderBy('id')->get()->groupBy('subject_id');
        $funding=$rider?collect():DB::table('logistics_treasury_movements')->whereIn('account_id',$accounts->pluck('id'))->where('source_type','rider_funding')->orderByDesc('id')->limit(30)->get();
        $fundingEvidence=$rider?collect():DB::table('logistics_finance_evidence')->where('source_type','rider_funding')->whereIn('source_id',$funding->pluck('source_id'))->get()->keyBy('source_id');
        return response()->view('logistics.rider-payouts',array_merge($rider?[]:$this->sidebarCounts(),compact('rider','actor','earnings','payouts','totals','people','destinations','accounts','balances','existing','evidence','history','funding','fundingEvidence')))
            ->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff');
    }
    private function run(callable $fn)
    {
        try{$fn();}catch(\Illuminate\Database\QueryException $e){
            if(($e->errorInfo[1]??null)===1062 || (($e->errorInfo[1]??null)===19 && str_contains(strtolower($e->getMessage()),'unique constraint failed')))abort(409,'This financial record already exists. Refresh before retrying.');
            throw $e;
        }
        return back()->with('success','Rider financial action recorded.');
    }
    public function recognize(Request $r,string $id,RiderCompensation $f){return $this->run(function()use($r,$id,$f){if(!$f->recognize($id,$r->user()))app(LogisticsTreasury::class)->fail('No new earning recognized. The snapshot needs matching authoritative completion evidence or is already processed.');});}
    public function destination(Request $r,RiderCompensation $f){return $this->run(fn()=>$f->destination($r->user(),$r->all()));}
    public function funding(Request $r,RiderCompensation $f){$r->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);return $this->run(fn()=>$f->funding($r->user(),$r->all(),$r->file('proof')));}
    public function prepare(Request $r,RiderCompensation $f){return $this->run(fn()=>$f->prepare($r->user(),$r->all()));}
    public function action(Request $r,string $id,RiderCompensation $f){return $this->run(fn()=>$f->action($r->user(),$id,$r->all()));}
    public function pay(Request $r,string $id,RiderCompensation $f){$r->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);return $this->run(fn()=>$f->pay($r->user(),$id,$r->all(),$r->file('proof')));}
    public function acknowledge(Request $r,string $id,RiderCompensation $f){$r->validate(['received'=>'required|accepted']);return $this->run(fn()=>$f->acknowledge($r->user(),$id));}
}
