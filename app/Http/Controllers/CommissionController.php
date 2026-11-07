<?php

namespace App\Http\Controllers;

use App\Services\{CommissionLedger, CommissionPayments, CommissionReporting, LogisticsReconciliation, CommissionRecovery, CodRefundSettlement};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage, Schema};

class CommissionController extends Controller
{
    private function historyFilter(Request $r,$q,string $kind,string $prefix='')
    {
        $statuses=$kind==='credits'?'pending,paid':'draft,pending,review,rejected,verified,cancelled';
        $d=$r->validate([$prefix.'from'=>'nullable|date_format:Y-m-d',$prefix.'to'=>'nullable|date_format:Y-m-d|after_or_equal:'.$prefix.'from',$prefix.'status'=>'nullable|in:'.$statuses]);
        $column=$kind==='credits'?'c.':'';
        return $q->when($d[$prefix.'from']??null,fn($q,$v)=>$q->whereDate($column.'created_at','>=',$v))->when($d[$prefix.'to']??null,fn($q,$v)=>$q->whereDate($column.'created_at','<=',$v))->when($d[$prefix.'status']??null,fn($q,$v)=>$q->where($column.'status',$v));
    }
    private function access(Request $r)
    {
        abort_unless($r->user(), 401);
        $actor = app(CommissionPayments::class)->actor($r->user());
        abort_unless(app(CommissionLedger::class)->installed(), 503, 'Commission installation is required.');
        return $actor;
    }

    private function query(Request $r, $actor)
    {
        $d = $r->validate(['seller_id' => 'nullable|integer|min:1', 'company' => 'nullable|string|max:190', 'method' => 'nullable|in:cod,gcash', 'date_basis' => 'nullable|in:created,earned', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'balance' => 'nullable|in:due,overdue']);
        $dateField=($d['date_basis']??'created')==='earned'?'earned_at':'created_at';
        $q = app(CommissionReporting::class)->query($actor)->when($d['seller_id'] ?? null, fn($q, $id) => $q->where('seller_id', $id))->when($d['company'] ?? null, fn($q,$v)=>$q->where('company_name',$v))->when($d['method'] ?? null, fn($q, $v) => $q->where('method', $v))->when($d['from'] ?? null, fn($q, $v) => $q->whereDate($dateField, '>=', $v))->when($d['to'] ?? null, fn($q, $v) => $q->whereDate($dateField, '<=', $v));
        if ($d['balance'] ?? null) {
            $q = DB::query()->fromSub($q, 'balances')->where('due_cents', '>', 0);
            if ($d['balance'] === 'overdue') $q->where('due_at', '<', now());
        }
        return $q;
    }

    public function index(Request $r)
    {
        $actor = $this->access($r); $q = $this->query($r, $actor);
        $totals = app(CommissionReporting::class)->totals(clone $q);
        $obligations = $q->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $scopePayments = fn() => app(CommissionPayments::class)->scoped(DB::table('commission_payments'), $actor);
        $payments = $this->historyFilter($r,$scopePayments(),'payments','payment_')->orderByDesc('created_at')->orderByDesc('id')->paginate(20, ['*'], 'payments_page')->withQueryString();
        $unapplied = (int) $scopePayments()->where('status', 'verified')->sum('unapplied_cents');
        $verifiedReceipts = (int) $scopePayments()->where('status', 'verified')->sum('amount_cents');
        $creditsQuery = DB::table('commission_credits as c')->join('commission_obligations as o', 'o.id', '=', 'c.obligation_id');
        if (!$actor->is_admin) $actor->account_type === 'seller' ? $creditsQuery->where('o.seller_id', $actor->id) : $creditsQuery->where('o.company_name', $actor->business_name)->where('o.method', 'cod');
        $repayments = (int) (clone $creditsQuery)->where('c.status', 'paid')->sum('c.amount_cents');
        $credits = $this->historyFilter($r,$creditsQuery,'credits','credit_')->select('c.*', 'o.seller_id', 'o.company_name', 'o.order_id')->where('c.amount_cents', '>', 0)->orderByDesc('c.created_at')->orderByDesc('c.id')->paginate(20, ['*'], 'credits_page')->withQueryString();
        $accounts = DB::table('commission_accounts')->orderByDesc('created_at')->get();
        $lines = DB::table('commission_lines')->whereIn('obligation_id', $obligations->pluck('id'))->orderBy('line_key')->get()->groupBy('obligation_id');
        $events = DB::table('commission_events')->whereIn('obligation_id', $obligations->pluck('id'))->orderBy('id')->get()->groupBy('obligation_id');
        $allocations = DB::table('commission_allocations')->whereIn('payment_id', $payments->pluck('id'))->get()->groupBy('payment_id');
        $pendingRegistrations = DB::table('users')->where('status', 'pending')->count();
        $openDisputes = DB::table('complaints')->where('status', '!=', 'resolved')->count();
        $destinations = DB::table('logistics_payout_destinations')->whereIn('seller_id', $credits->pluck('seller_id')->merge($payments->pluck('seller_id'))->filter())->where('channel','gcash')->get()->groupBy('seller_id');
        $recoveries=null;$recoveryEvents=collect();$recoveryRefunds=collect();$recoveryPriorPayments=collect();$refundCases=null;$refundAccounts=collect();
        if(app(CommissionRecovery::class)->installed() && ($actor->is_admin || $actor->account_type==='seller')){
            $rq=DB::table('commission_recoveries')->when(!$actor->is_admin,fn($q)=>$q->where('seller_id',$actor->id));
            $unapplied+=(int)(clone $rq)->where('status','verified')->sum('unapplied_cents');$verifiedReceipts+=(int)(clone $rq)->where('status','verified')->sum('amount_cents');
            $repayments+=(int)DB::table('commission_recovery_refunds')->whereIn('recovery_id',(clone $rq)->select('id'))->sum('amount_cents');
            $recoveries=$this->historyFilter($r,$rq,'payments','recovery_')->orderByDesc('created_at')->orderByDesc('id')->paginate(20,['*'],'recoveries_page')->withQueryString();
            $recoveryEvents=DB::table('commission_recovery_events')->whereIn('recovery_id',$recoveries->pluck('id'))->orderBy('id')->get()->groupBy('recovery_id');
            $recoveryRefunds=DB::table('commission_recovery_refunds')->whereIn('recovery_id',$recoveries->pluck('id'))->orderBy('paid_at')->get()->groupBy('recovery_id');
            if($actor->is_admin)$recoveryPriorPayments=DB::table('commission_payments')->whereIn('reference',$recoveries->pluck('reference'))->get()->keyBy('reference');
            $destinations=DB::table('logistics_payout_destinations')->whereIn('seller_id',$credits->pluck('seller_id')->merge($payments->pluck('seller_id'))->merge($recoveries->pluck('seller_id'))->filter())->where('channel','gcash')->get()->groupBy('seller_id');
        }
        if(app(CodRefundSettlement::class)->installed() && ($actor->is_admin || $actor->account_type!=='seller')){
            $refundCases=DB::table('commerce_returns as r')->join('commission_obligations as o','o.order_id','=','r.order_id')->join('shipments as s','s.order_id','=','r.order_id')->leftJoin('cod_refund_reconciliations as f','f.return_id','=','r.id')->where('o.method','cod')->where('r.status','refunded')->when(!$actor->is_admin,fn($q)=>$q->where('s.logistics_company',$actor->business_name))->select('r.id as return_id','r.order_id','r.amount','r.refund_reference','s.logistics_company as company_name','f.id as reconciliation_id','f.status as reconciliation_status','f.revision','f.payer','f.platform_destination','f.reimbursement_reference','f.remaining_cents','f.seller_reimbursement_cents')->orderByDesc('r.created_at')->paginate(20,['*'],'refunds_page')->withQueryString();
            if(!$actor->is_admin)$refundAccounts=DB::table('logistics_treasury_accounts')->where('company_name',$actor->business_name)->get();
        }
        return response()->view('commissions.index', compact('actor', 'totals', 'obligations', 'payments', 'credits', 'accounts', 'lines', 'events', 'allocations', 'unapplied', 'verifiedReceipts', 'repayments', 'pendingRegistrations','openDisputes','destinations','recoveries','recoveryEvents','recoveryRefunds','recoveryPriorPayments','refundCases','refundAccounts'))->header('Cache-Control', 'private, no-store');
    }

    private function run(Request $r, callable $fn)
    {
        $this->access($r);
        try { $fn(); } catch (\Illuminate\Database\QueryException $e) {
            if (in_array($e->errorInfo[1] ?? null, [1062, 19])) abort(409, 'Financial reference or submission already exists. Refresh before retrying.');
            throw $e;
        }
        return back()->with('success', 'Commission action recorded.');
    }

    public function account(Request $r, CommissionPayments $p) { return $this->run($r, fn() => $p->account($r->user(), $r->all())); }
    public function recoverySubmit(Request $r,CommissionRecovery $p){$r->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);return $this->run($r,fn()=>$p->submit($r->user(),$r->all(),$r->file('proof')));}
    public function recoveryReview(Request $r,string $id,CommissionRecovery $p){return $this->run($r,fn()=>$p->review($r->user(),$id,$r->all()));}
    public function recoveryAllocate(Request $r,string $id,CommissionRecovery $p){return $this->run($r,fn()=>$p->allocate($r->user(),$id,$r->all()));}
    public function recoveryRefund(Request $r,string $id,CommissionRecovery $p){$r->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);return $this->run($r,fn()=>$p->refund($r->user(),$id,$r->all(),$r->file('proof')));}
    public function codRefund(Request $r,string $id,CodRefundSettlement $p){$r->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);return $this->run($r,fn()=>$p->reconcile($r->user(),$id,$r->all(),$r->file('proof')));}
    public function codReimburse(Request $r,string $id,CodRefundSettlement $p){$r->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);return $this->run($r,fn()=>$p->reimburse($r->user(),$id,$r->all(),$r->file('proof')));}
    public function codVerify(Request $r,string $id,CodRefundSettlement $p){return $this->run($r,fn()=>$p->verify($r->user(),$id,$r->all()));}
    public function submit(Request $r, CommissionPayments $p) { $r->validate(['proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']); return $this->run($r, fn() => $p->submit($r->user(), $r->all(), $r->file('proof'))); }
    public function prepareRemittance(Request $r, CommissionPayments $p) { return $this->run($r,fn()=>$p->prepareRemittance($r->user(),$r->all())); }
    public function sendRemittance(Request $r,string $id,CommissionPayments $p) { return $this->run($r,fn()=>$p->sendRemittance($r->user(),$id,$r->all(),$r->file('proof'))); }
    public function review(Request $r, string $id, CommissionPayments $p) { return $this->run($r, fn() => $p->review($r->user(), $id, $r->all())); }
    public function repay(Request $r, string $id, CommissionPayments $p) { $r->validate(['proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']); return $this->run($r, fn() => $p->repay($r->user(), $id, $r->all(), $r->file('proof'))); }
    public function refundCredit(Request $r, string $id, CommissionPayments $p) { $r->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']); return $this->run($r,fn()=>$p->refundUnapplied($r->user(),$id,$r->all(),$r->file('proof'))); }
    public function allocate(Request $r, string $id, CommissionPayments $p) { $d = $r->validate(['obligation_id' => 'required|uuid', 'amount' => 'required|string']); return $this->run($r, fn() => $p->allocateCredit($r->user(), $id, $d['obligation_id'], $d['amount'])); }

    public function evidence(Request $r, string $kind, string $id)
    {
        $actor = $this->access($r);
        if(in_array($kind,['buyer-refund','refund-reimbursement'])){
            $case=DB::table('cod_refund_reconciliations')->where('id',$id)->firstOrFail();
            abort_unless($actor->is_admin || ($actor->account_type==='logistics' && $actor->business_name===$case->company_name),403);
            $proof=DB::table('logistics_finance_evidence')->where('source_id',$id)->where('company_name',$case->company_name)->where('source_type',$kind==='buyer-refund'?'cod_buyer_refund':'cod_refund_reimburse')->firstOrFail();
            abort_unless(preg_match('#^logistics-finance-proofs/[A-Za-z0-9]+\.(jpg|jpeg|png|webp|pdf)$#D',$proof->path) && Storage::disk('local')->exists($proof->path),404);
            return response()->file(Storage::disk('local')->path($proof->path),['Content-Type'=>$proof->mime,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"default-src 'none'; sandbox"]);
        }
        if(in_array($kind,['recovery','recovery-refund'])){
            $row=DB::table($kind==='recovery'?'commission_recoveries':'commission_recovery_refunds')->where('id',$id)->firstOrFail();
            $owner=$kind==='recovery'?$row:DB::table('commission_recoveries')->find($row->recovery_id);
            abort_unless($actor->is_admin || ($actor->account_type==='seller' && (int)$owner->seller_id===$actor->id),403);
        }elseif ($kind === 'payment') $row = app(CommissionPayments::class)->scoped(DB::table('commission_payments'), $actor)->where('id', $id)->firstOrFail();
        else {
            abort_unless($kind === 'credit', 404);
            $row = DB::table('commission_credits')->where('id', $id)->firstOrFail();
            abort_unless(app(CommissionPayments::class)->scoped(DB::table('commission_obligations'), $actor)->where('id', $row->obligation_id)->exists(), 403);
        }
        abort_unless($row->proof_path && preg_match('#^commission-proofs/[A-Za-z0-9]+\.(jpg|jpeg|png|webp|pdf)$#D', $row->proof_path) && Storage::disk('local')->exists($row->proof_path), 404);
        return response()->file(Storage::disk('local')->path($row->proof_path), ['Content-Type' => $row->proof_mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }

    public function export(Request $r)
    {
        $actor = $this->access($r); $q = $this->query($r, $actor);
        abort_if((clone $q)->count() > 10000, 422, 'Narrow the filters to at most 10,000 obligations.');
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['Obligation', 'Order', 'Seller', 'Method', 'Product basis PHP', 'Rate percent', 'Original commission PHP', 'Reversed PHP', 'Collected allocated PHP', 'Outstanding PHP', 'Due at'], ',', '"', '');
        foreach ($q->orderBy('id')->cursor() as $o) fputcsv($stream, array_map([LogisticsReconciliation::class, 'csvCell'], [$o->id, $o->order_id, $o->seller_id, $o->method, LogisticsReconciliation::money($o->basis_cents), '10', LogisticsReconciliation::money($o->commission_cents), LogisticsReconciliation::money($o->reversed_cents), LogisticsReconciliation::money($o->collected_cents), LogisticsReconciliation::money($o->due_cents), $o->due_at]), ',', '"', '');
        rewind($stream); $csv = stream_get_contents($stream); fclose($stream);
        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="commission-statement.csv"', 'Cache-Control' => 'private, no-store']);
    }

    public function moneyExport(Request $r, string $kind)
    {
        $actor=$this->access($r);abort_unless(in_array($kind,['payments','credits','recoveries']),404);
        if($kind==='recoveries'){
            abort_unless($actor->is_admin || $actor->account_type==='seller',403);
            $q=DB::table('commission_recoveries')->when(!$actor->is_admin,fn($q)=>$q->where('seller_id',$actor->id));
            $header=['Recovery','Seller','Submitted PHP','Unapplied PHP','Status','Reference','Submitted at','Verified at'];
            $row=fn($p)=>[$p->id,$p->seller_id,LogisticsReconciliation::money($p->amount_cents),LogisticsReconciliation::money($p->unapplied_cents),$p->status,$p->reference,$p->created_at,$p->verified_at];
        }elseif($kind==='payments'){
            $q=app(CommissionPayments::class)->scoped(DB::table('commission_payments'),$actor);
            $header=['Payment','Method','Seller','Company','Submitted PHP','Unapplied PHP','Status','Reference','Submitted at','Verified at'];
            $row=fn($p)=>[$p->id,$p->method,$p->seller_id,$p->company_name,LogisticsReconciliation::money($p->amount_cents),LogisticsReconciliation::money($p->unapplied_cents),$p->status,$p->reference,$p->created_at,$p->verified_at];
        }else{
            $q=DB::table('commission_credits as c')->join('commission_obligations as o','o.id','=','c.obligation_id')->select('c.*','o.seller_id','o.company_name');
            if(!$actor->is_admin)$actor->account_type==='seller'?$q->where('o.seller_id',$actor->id):$q->where('o.company_name',$actor->business_name)->where('o.method','cod');
            $header=['Credit','Obligation','Seller','Company','Payer','Kind','Amount PHP','Status','Reference','Paid at'];
            $row=fn($p)=>[$p->id,$p->obligation_id,$p->seller_id,$p->company_name,$p->payer,$p->kind,LogisticsReconciliation::money($p->amount_cents),$p->status,$p->reference,$p->paid_at];
        }
        $q=$this->historyFilter($r,$q,$kind);
        abort_if((clone $q)->count()>10000,422,'Narrow date and status filters to at most 10,000 rows.');
        $stream=fopen('php://temp','w+');fputcsv($stream,$header,',','"','');
        foreach($q->orderBy($kind==='credits'?'c.id':'id')->cursor() as $p)fputcsv($stream,array_map([LogisticsReconciliation::class,'csvCell'],$row($p)),',','"','');
        rewind($stream);$csv=stream_get_contents($stream);fclose($stream);
        return response($csv,200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="commission-'.$kind.'.csv"','Cache-Control'=>'private, no-store']);
    }
}
