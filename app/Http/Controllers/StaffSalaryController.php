<?php

namespace App\Http\Controllers;

use App\Services\LogisticsTreasury;
use App\Services\StaffSalary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffSalaryController extends LogisticsController
{
    // ── Admin: Hub Staff Salaries page ────────────────────────────────────────

    public function index(Request $r, StaffSalary $flow, \App\Services\StaffPayrollWorkspace $workspace)
    {
        $actor=$flow->admin($r->user());
        $filters=$workspace->filters($r->query());
        $funds=$workspace->funds($actor->business_name);
        $query=$workspace->query($actor->business_name,$filters);
        $summary=DB::query()->fromSub(clone $query,'payroll')->selectRaw("COUNT(*) staff_count, SUM(CASE WHEN period_status != 'paid' THEN installment_cents ELSE 0 END) outstanding, SUM(CASE WHEN period_status = 'paid' THEN installment_cents ELSE 0 END) paid, SUM(CASE WHEN period_id IS NULL THEN 1 ELSE 0 END) ungenerated")->first();
        $staff=$query->orderBy('u.last_name')->orderBy('u.given_names')->orderBy('u.id')->paginate(25)->withQueryString();
        $staff->setCollection($staff->getCollection()->map(fn($row)=>$workspace->decorate($row,$filters,$funds)));
        $hubs=DB::table('logistics_hubs')->where('company_name',$actor->business_name)->orderBy('municipality')->get();
        $accounts=$workspace->accounts($actor->business_name);
        // History is restricted to the selected period and filters, not all-time company payroll.
        $selected=$workspace->query($actor->business_name,$filters)->reorder()->select('u.id');
        $allPayouts=DB::table('logistics_staff_payouts as po')->join('logistics_staff_salary_periods as p','p.id','=','po.period_id')
            ->join('users as u','u.id','=','po.staff_id')->where('po.company_name',$actor->business_name)->whereIn('u.id',$selected)
            ->where('p.period_year',$filters['period_year'])->where('p.period_month',$filters['period_month'])->where('p.period_half',$filters['period_half'])
            ->select('po.*','u.given_names','u.last_name')->orderByDesc('po.created_at')->orderBy('po.id')->paginate(20,['*'],'payouts_page')->withQueryString();
        $funding=DB::table('logistics_treasury_movements')->whereIn('account_id',$accounts->pluck('id'))->where('source_type','staff_funding')->orderByDesc('id')->paginate(10,['*'],'funding_page')->withQueryString();
        $fundingEvidence=DB::table('logistics_finance_evidence')->where('company_name',$actor->business_name)->where('source_type','staff_funding')->whereIn('source_id',$funding->pluck('source_id'))->get()->keyBy('source_id');
        $hubSalaryInstalled=app(\App\Services\HubStaffSalary::class)->installed();
        $hubRates=$hubSalaryInstalled?DB::table('logistics_hub_salary_rates')->where('company_name',$actor->business_name)->whereRaw('revision = (SELECT MAX(hr.revision) FROM logistics_hub_salary_rates hr WHERE hr.hub_id = logistics_hub_salary_rates.hub_id)')->get()->keyBy('hub_id'):collect();
        $hubStaffCounts=DB::table('users')->where('business_name',$actor->business_name)->where('logistics_role','hub_staff')->where('is_logistics',true)->where('status','approved')->selectRaw('logistics_hub_id, COUNT(*) total')->groupBy('logistics_hub_id')->pluck('total','logistics_hub_id');
        $periodStart=\Carbon\CarbonImmutable::create($filters['period_year'],$filters['period_month'],$filters['period_half']==1?1:16,0,0,0,'Asia/Manila');
        $periodEnd=$filters['period_half']==1?$periodStart->setDay(15):$periodStart->endOfMonth();
        $attendanceSummary=app(\App\Services\StaffAttendance::class)->summary($actor->business_name,$staff->pluck('id')->all(),$periodStart->toDateString(),$periodEnd->toDateString());
        return response()->view('logistics.staff-salary',array_merge($this->sidebarCounts(),compact('actor','filters','funds','summary','staff','hubs','accounts','allPayouts','funding','fundingEvidence','hubSalaryInstalled','hubRates','hubStaffCounts','attendanceSummary','periodStart','periodEnd')))->header('Cache-Control','private, no-store');
    }

    public function show(Request $r,string $id,StaffSalary $flow)
    {
        $actor=$flow->admin($r->user());
        $p=DB::table('logistics_staff_payouts')->where('company_name',$actor->business_name)->where('id',$id)->firstOrFail();
        $member=DB::table('users')->where('id',$p->staff_id)->firstOrFail();
        $period=DB::table('logistics_staff_salary_periods')->where('id',$p->period_id)->firstOrFail();
        $accounts=DB::table('logistics_treasury_accounts')->where('company_name',$actor->business_name)->get();
        $destination=DB::table('logistics_staff_destinations')->where('id',$p->destination_id)->first();
        $evidence=DB::table('logistics_finance_evidence')->where('company_name',$actor->business_name)->where('source_type','staff_payout')->where('source_id',$id)->first();
        $history=DB::table('logistics_financial_events')->where('company_name',$actor->business_name)->where('subject_type','staff_payout')->where('subject_id',$id)->orderByDesc('id')->paginate(20,['*'],'audit_page');
        $filters=app(\App\Services\StaffPayrollWorkspace::class)->filters($r->query());
        return response()->view('logistics.staff-salary-detail',array_merge($this->sidebarCounts(),compact('actor','p','member','period','accounts','destination','evidence','history','filters')))->header('Cache-Control','private, no-store');
    }

    public function salaryHistory(Request $r,int $staff,StaffSalary $flow)
    {
        $actor=$flow->admin($r->user());
        $member=DB::table('users')->where('business_name',$actor->business_name)->where('logistics_role','hub_staff')->where('id',$staff)->firstOrFail();
        $history=DB::table('logistics_staff_salaries')->where('company_name',$actor->business_name)->where('staff_id',$staff)->orderByDesc('revision')->paginate(20,['*'],'history_page')->withQueryString();
        $filters=app(\App\Services\StaffPayrollWorkspace::class)->filters($r->query());
        return response()->view('logistics.staff-salary-history',array_merge($this->sidebarCounts(),compact('member','history','filters')))->header('Cache-Control','private, no-store');
    }

    public function bulkPreview(Request $r,\App\Services\StaffPayrollWorkspace $workspace)
    {
        if($r->isMethod('post')){
            $preview=$workspace->preview($r->user(),$r->all());
            $r->session()->put('staff_payroll_preview',$preview);
            return redirect()->route('logistics.finance.staff.bulk.preview');
        }
        app(StaffSalary::class)->admin($r->user());
        $preview=$r->session()->get('staff_payroll_preview');
        abort_unless($preview && $preview['actor']===$r->user()->id && $preview['company']===$r->user()->business_name && $preview['expires']>=time(),409,'Preview expired. Select Staff again.');
        return response()->view('logistics.staff-salary-preview',array_merge($this->sidebarCounts(),compact('preview')))->header('Cache-Control','private, no-store');
    }

    public function bulkExecute(Request $r,\App\Services\StaffPayrollWorkspace $workspace)
    {
        $d=$r->validate(['token'=>'required|string|max:65536','confirmed'=>'required|accepted']);
        $result=$workspace->execute($r->user(),$d['token']);
        return redirect()->route('logistics.finance.staff',$result['filters'])->with('payroll_results',$result['items'])->with('success','Bulk action finished. Review the result for each Staff member.');
    }

    public function export(Request $r,\App\Services\StaffPayrollWorkspace $workspace)
    {
        $actor=app(StaffSalary::class)->admin($r->user());$company=$actor->business_name;
        $filters=$workspace->filters($r->query());
        // Bounded, selected-period worklist; no complete financial reconciliation scan.
        $rows=$workspace->query($company,$filters)->orderBy('u.last_name')->orderBy('u.given_names')->orderBy('u.id')->limit(1001)->get();
        abort_if($rows->count()>1000,422,'Narrow the worklist to at most 1,000 Staff.');
        DB::transaction(function()use($actor,$company,$filters,$rows){
            $fresh=app(StaffSalary::class)->admin($actor);abort_unless($fresh->business_name===$company,403);
            app(\App\Services\LogisticsFinance::class)->event($company,'staff_payroll_exported','report',(string)\Illuminate\Support\Str::uuid(),['filters'=>$filters,'rows'=>$rows->count()],$actor->id);
        });
        return response()->streamDownload(function()use($rows,$filters){
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['account_id','staff_name','hub','staff_status','configured_monthly_cents','snapshot_monthly_cents','installment_cents','period_year','period_month','period_half','due_date','payment_status','acknowledged_at','gcash_masked'],',','"','');
            foreach($rows as $row){$date=$row->pay_date??app(StaffSalary::class)->payDate((int)$filters['period_year'],(int)$filters['period_month'],(int)$filters['period_half'])->toDateString();
                $values=[$row->id,$row->given_names.' '.$row->last_name,$row->hub_name,$row->staff_status,$row->monthly_cents>0?$row->monthly_cents:null,$row->snapshot_monthly_cents,$row->installment_cents,$filters['period_year'],$filters['period_month'],$filters['period_half'],$date,$row->period_status??'not_generated',$row->acknowledged_at,$row->account_number?'*******'.substr($row->account_number,-4):''];
                fputcsv($out,array_map([\App\Services\LogisticsReconciliation::class,'csvCell'],$values),',','"','');
            }fclose($out);
        },'staff-payroll-'.$filters['period_year'].'-'.$filters['period_month'].'-'.$filters['period_half'].'.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    public function myIndex(Request $r, StaffSalary $flow)
    {
        abort_unless($flow->installed(), 503, 'The staff salary migration is required.');
        $actor = $flow->staff($r->user());

        $obligations = DB::table('logistics_staff_salary_periods')->where('staff_id', $actor->id)->where('company_name', $actor->business_name)->get();
        $salary = DB::table('logistics_staff_salaries')
            ->where('staff_id', $actor->id)
            ->where('company_name', $actor->business_name)
            ->whereNull('superseded_at')
            ->first();

        $salaryHistory = DB::table('logistics_staff_salaries')
            ->where('staff_id', $actor->id)
            ->where('company_name', $actor->business_name)
            ->orderByDesc('revision')->get();

        $periods = DB::table('logistics_staff_salary_periods')
            ->where('staff_id', $actor->id)
            ->where('company_name', $actor->business_name)
            ->orderByDesc('period_year')->orderByDesc('period_month')->orderByDesc('period_half')
            ->paginate(20, ['*'], 'periods_page');

        $payouts = DB::table('logistics_staff_payouts')
            ->where('staff_id', $actor->id)
            ->where('company_name', $actor->business_name)
            ->orderByDesc('created_at')->paginate(15, ['*'], 'payouts_page');

        $destinations = DB::table('logistics_staff_destinations')
            ->where('staff_id', $actor->id)->get();

        $evidence = DB::table('logistics_finance_evidence')
            ->where('source_type', 'staff_payout')
            ->whereIn('source_id', $payouts->pluck('id'))
            ->get()->keyBy('source_id');

        $periodPayouts = DB::table('logistics_staff_payouts')
            ->whereIn('period_id', $periods->pluck('id'))
            ->get()->keyBy('period_id');

        [$year, $month, $half] = $flow->currentPeriod();
        $upcomingPayDate = $flow->payDate($year, $month, $half);
        if ($upcomingPayDate->endOfDay()->isPast()) $upcomingPayDate = $upcomingPayDate->addMonthNoOverflow()->startOfMonth()->setDay(15);
        return response()
            ->view('logistics.my-salary', array_merge($this->sidebarCounts(), compact(
                'actor', 'obligations', 'upcomingPayDate', 'salary', 'salaryHistory', 'periods', 'payouts',
                'destinations', 'evidence', 'periodPayouts'
            )))
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    // ── Shared run helper ─────────────────────────────────────────────────────

    private function run(callable $fn)
    {
        try {
            $fn();
        } catch (\Illuminate\Database\QueryException $e) {
            $code = $e->errorInfo[1] ?? null;
            if ($code === 1062 || ($code === 19 && str_contains(strtolower($e->getMessage()), 'unique constraint failed'))) {
                abort(409, 'This financial record already exists. Refresh before retrying.');
            }
            throw $e;
        }
        return back()->with('success', 'Staff salary action recorded.');
    }

    // ── Admin actions ─────────────────────────────────────────────────────────

    public function hubHistory(Request $r,int $hub,StaffSalary $flow)
    {
        $actor=$flow->admin($r->user());abort_unless(app(\App\Services\HubStaffSalary::class)->installed(),503);
        $record=DB::table('logistics_hubs')->where('company_name',$actor->business_name)->where('id',$hub)->firstOrFail();
        $member=(object)['id'=>$record->id,'given_names'=>$record->municipality,'last_name'=>$record->province];
        $history=DB::table('logistics_hub_salary_rates')->where('hub_id',$hub)->where('company_name',$actor->business_name)->select('*','created_at as effective_from','effective_period as period_effective')->orderByDesc('revision')->paginate(20,['*'],'history_page')->withQueryString();
        $filters=app(\App\Services\StaffPayrollWorkspace::class)->filters($r->query());$hubHistory=true;
        return response()->view('logistics.staff-salary-history',array_merge($this->sidebarCounts(),compact('member','history','filters','hubHistory')))->header('Cache-Control','private, no-store');
    }

    public function configureHub(Request $r, \App\Services\HubStaffSalary $flow) { return $this->run(fn()=>$flow->configure($r->user(),$r->all())); }
    public function useHub(Request $r, \App\Services\HubStaffSalary $flow) { return $this->run(fn()=>$flow->useHub($r->user(),$r->all())); }

    public function configure(Request $r, StaffSalary $flow)
    {
        return $this->run(fn () => $flow->configure($r->user(), $r->all()));
    }

    public function generatePeriod(Request $r, StaffSalary $flow)
    {
        return $this->run(fn () => $flow->generatePeriod($r->user(), $r->all()));
    }

    public function funding(Request $r, StaffSalary $flow)
    {
        $r->validate(['proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        return $this->run(fn () => $flow->funding($r->user(), $r->all(), $r->file('proof')));
    }

    public function prepare(Request $r, StaffSalary $flow)
    {
        return $this->run(fn () => $flow->prepare($r->user(), $r->all()));
    }

    public function action(Request $r, string $id, StaffSalary $flow)
    {
        return $this->run(fn () => $flow->action($r->user(), $id, $r->all()));
    }

    public function pay(Request $r, string $id, StaffSalary $flow)
    {
        $r->validate(['proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        return $this->run(fn () => $flow->pay($r->user(), $id, $r->all(), $r->file('proof')));
    }

    // ── Hub Staff actions ─────────────────────────────────────────────────────

    public function destination(Request $r, StaffSalary $flow)
    {
        return $this->run(fn () => $flow->destination($r->user(), $r->all()));
    }

    public function acknowledge(Request $r, string $id, StaffSalary $flow)
    {
        $r->validate(['received' => 'required|accepted']);
        return $this->run(fn () => $flow->acknowledge($r->user(), $id));
    }
}
