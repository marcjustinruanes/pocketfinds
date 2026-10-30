<?php
namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\User;
use App\Services\HubStaffSalary;
use App\Services\StaffAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HubSalaryAttendanceTest extends StaffSalaryTest
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_30_000004_add_hub_salary_and_staff_attendance.php'))->up();
    }
    private function attendance(): StaffAttendance { return app(StaffAttendance::class); }
    private function hubSalary(string $monthly='300.01',int $revision=0,?int $hub=null): void
    {
        app(HubStaffSalary::class)->configure($this->admin,['hub_id'=>$hub??$this->hub->id,'monthly'=>$monthly,'revision'=>$revision]);
    }
    private function correction(?object $row=null,array $extra=[]): object
    {
        $this->attendance()->requestCorrection($this->staff,array_replace(['attendance_id'=>$row?->id,'business_date'=>'2026-10-01','requested_in'=>'2026-10-01T08:00','requested_out'=>'2026-10-01T09:00','reason'=>'Forgot to record my shift times'],$extra));
        return DB::table('logistics_staff_attendance_corrections')->where('staff_id',$this->staff->id)->where('status','pending')->where('pending_key',$this->staff->id.':'.($row?->id??'2026-10-01'))->firstOrFail();
    }
    private function approve(object $request): void { $this->attendance()->decide($this->admin,$request->id,['decision'=>'approve','reason'=>'Verified with hub attendance evidence']); }

    public function test_hub_defaults_are_prospective_and_missing_current_rate_is_not_assumed(): void
    {
        $this->hubSalary();
        $this->assertSame('2026-10-16',DB::table('logistics_hub_salary_rates')->value('effective_period'));
        $this->invalid(fn()=>$this->generatePeriod());
        $this->travelTo(CarbonImmutable::parse('2026-10-16 09:00','Asia/Manila'));
        $period=$this->generatePeriod(2026,10,2);
        $this->assertSame(15001,(int)$period->amount_cents);
        $this->hubSalary('500.03',1);
        $this->assertSame(15001,(int)DB::table('logistics_staff_salary_periods')->where('id',$period->id)->value('amount_cents'));
        $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00','Asia/Manila'));
        $this->assertSame(25001,(int)$this->generatePeriod(2026,11,1)->amount_cents);
    }

    public function test_individual_override_is_preserved_and_can_return_to_hub_prospectively(): void
    {
        $this->configure(60000);$this->hubSalary();
        $this->assertSame(1,DB::table('logistics_staff_salaries')->where('staff_id',$this->staff->id)->count());
        $this->assertSame(30000,(int)$this->generatePeriod()->amount_cents);
        app(HubStaffSalary::class)->useHub($this->admin,['staff_id'=>$this->staff->id,'revision'=>1,'confirmed'=>'1']);
        $this->assertSame('hub',DB::table('logistics_staff_salaries')->where('staff_id',$this->staff->id)->orderByDesc('revision')->value('salary_source'));
        $this->travelTo(CarbonImmutable::parse('2026-10-16 09:00','Asia/Manila'));
        $this->assertSame(15001,(int)$this->generatePeriod(2026,10,2)->amount_cents);
    }

    public function test_approval_and_transfer_hook_keeps_old_hub_snapshots(): void
    {
        $this->hubSalary();
        $other=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Pila','province'=>'Laguna','is_operational'=>true]);
        $this->hubSalary('500.01',0,$other->id);
        $this->travelTo(CarbonImmutable::parse('2026-10-16 09:00','Asia/Manila'));
        $member=$this->person('hub_staff');
        $this->assertSame(30001,(int)DB::table('logistics_staff_salaries')->where('staff_id',$member->id)->value('monthly_cents'));
        $old=$this->flow->generatePeriod($this->admin,['staff_id'=>$member->id,'period_year'=>2026,'period_month'=>10,'period_half'=>2]);
        $member->update(['logistics_hub_id'=>$other->id]);
        $this->assertSame(15001,(int)DB::table('logistics_staff_salary_periods')->where('id',$old->id)->value('amount_cents'));
        $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00','Asia/Manila'));
        $new=$this->flow->generatePeriod($this->admin,['staff_id'=>$member->id,'period_year'=>2026,'period_month'=>11,'period_half'=>1]);
        $this->assertSame(25000,(int)$new->amount_cents);
        $this->assertDatabaseHas('logistics_financial_events',['event'=>'staff_salary_hub_changed','subject_id'=>(string)$member->id]);
    }

    public function test_transfer_to_unconfigured_hub_keeps_history_and_requires_salary_review(): void
    {
        $this->hubSalary();$this->travelTo(CarbonImmutable::parse('2026-10-16 09:00','Asia/Manila'));$old=$this->generatePeriod(2026,10,2);
        $before=(object)$this->staff->getAttributes();
        $newHub=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'No Rate','province'=>'Laguna','is_operational'=>true]);
        DB::table('users')->where('id',$this->staff->id)->update(['logistics_hub_id'=>$newHub->id]);$this->staff->refresh();
        DB::transaction(fn()=>app(HubStaffSalary::class)->membershipChanged($this->staff,$before));
        $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00','Asia/Manila'));
        $this->invalid(fn()=>$this->generatePeriod(2026,11,1));
        $this->assertSame(15001,(int)DB::table('logistics_staff_salary_periods')->where('id',$old->id)->value('amount_cents'));
    }

    public function test_attendance_clock_uses_server_time_and_duplicate_actions_do_not_duplicate_shifts(): void
    {
        $this->actingAs($this->staff)->post(route('logistics.my-attendance.clock',['action'=>'in']),['time_in'=>'1990-01-01','staff_id'=>$this->otherStaff->id])->assertRedirect();
        $row=DB::table('logistics_staff_attendance')->first();
        $this->assertSame($this->staff->id,(int)$row->staff_id);
        $this->assertSame('2026-10-01 01:00:00',$row->time_in);
        $this->invalid(fn()=>$this->attendance()->clock($this->staff,'in'));
        $this->travel(8)->hours();$this->attendance()->clock($this->staff,'out');
        $this->invalid(fn()=>$this->attendance()->clock($this->staff,'out'));
        $this->assertDatabaseCount('logistics_staff_attendance',1);
        $this->assertDatabaseCount('logistics_staff_attendance_events',2);
    }

    public function test_overnight_shift_stays_on_start_date_and_open_shift_requires_explicit_review(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 22:00','Asia/Manila'));$this->attendance()->clock($this->staff,'in');
        $this->travelTo(CarbonImmutable::parse('2026-10-02 06:00','Asia/Manila'));
        $row=DB::table('logistics_staff_attendance')->first();$this->assertSame('working',$this->attendance()->status($row));
        $this->attendance()->review($this->admin,$row->id,['revision'=>1,'action'=>'missing_timeout','reason'=>'Shift handover needs physical review']);
        $this->assertSame('missing_timeout',$this->attendance()->status(DB::table('logistics_staff_attendance')->first()));
        $this->attendance()->clock($this->staff,'out');$row=DB::table('logistics_staff_attendance')->first();
        $this->assertSame('2026-10-01',$row->business_date);$this->assertSame('completed',$this->attendance()->status($row));
        $this->assertSame(480,(int)CarbonImmutable::parse($row->time_in)->diffInMinutes(CarbonImmutable::parse($row->time_out)));
    }

    public function test_corrections_preserve_original_timestamps_require_review_and_reject_stale_requests(): void
    {
        $this->attendance()->clock($this->staff,'in');$row=DB::table('logistics_staff_attendance')->first();
        $this->travel(2)->hours();$request=$this->correction($row,['requested_in'=>'2026-10-01T09:00','requested_out'=>'2026-10-01T10:00']);
        $this->invalid(fn()=>$this->correction($row));
        $this->approve($request);$changed=DB::table('logistics_staff_attendance')->first();
        $this->assertSame($row->original_in,$changed->original_in);$this->assertNull($changed->original_out);
        $this->assertTrue((bool)$changed->corrected);$this->assertSame('2026-10-01 02:00:00',$changed->time_out);
        $this->assertDatabaseHas('logistics_staff_attendance_events',['event'=>'correction_applied']);
        $this->attendance()->clock($this->staff,'in');$active=DB::table('logistics_staff_attendance')->whereNotNull('active_staff_id')->first();
        $request=$this->correction($active,['requested_in'=>'2026-10-01T11:00','requested_out'=>null]);
        $this->travel(1)->hours();$this->attendance()->clock($this->staff,'out');
        $this->invalid(fn()=>$this->approve($request));
        $this->attendance()->decide($this->admin,$request->id,['decision'=>'reject','reason'=>'Staff already clocked out; request is stale']);
        $this->assertDatabaseHas('logistics_staff_attendance_corrections',['id'=>$request->id,'status'=>'rejected','pending_key'=>null]);
    }

    public function test_forgotten_time_in_correction_and_leave_absence_rules(): void
    {
        $request=$this->correction();$this->approve($request);
        $row=DB::table('logistics_staff_attendance')->first();$this->assertNull($row->original_in);$this->assertTrue((bool)$row->corrected);
        $this->invalid(fn()=>$this->attendance()->mark($this->admin,['staff_id'=>$this->staff->id,'business_date'=>'2026-10-02','kind'=>'absence','reason'=>'No schedule evidence']));
        $this->attendance()->mark($this->admin,['staff_id'=>$this->staff->id,'business_date'=>'2026-10-02','kind'=>'leave','reason'=>'Approved leave documented by Admin']);
        $this->travelTo(CarbonImmutable::parse('2026-10-02 09:00','Asia/Manila'));
        $this->invalid(fn()=>$this->attendance()->clock($this->staff,'in'));
        $this->invalid(fn()=>$this->attendance()->mark($this->admin,['staff_id'=>$this->staff->id,'business_date'=>'2026-10-02','kind'=>'absence','scheduled_confirmed'=>'1','reason'=>'Must not override approved leave']));
        $leave=DB::table('logistics_staff_attendance')->where('kind','leave')->first();
        $this->attendance()->review($this->admin,$leave->id,['revision'=>1,'action'=>'void','reason'=>'Leave cancelled after documented review']);
        $this->attendance()->mark($this->admin,['staff_id'=>$this->staff->id,'business_date'=>'2026-10-02','kind'=>'absence','scheduled_confirmed'=>'1','reason'=>'Confirmed scheduled workday and no approved leave']);
        $this->assertDatabaseHas('logistics_staff_attendance',['kind'=>'absence']);
    }

    public function test_attendance_authorization_audit_rollback_and_payroll_separation(): void
    {
        $this->configure();$period=$this->generatePeriod();$this->fund();$this->prepare();$this->verify();
        $events=DB::table('logistics_financial_events')->count();
        $this->attendance()->clock($this->staff,'in');$this->travel(1)->hours();$this->attendance()->clock($this->staff,'out');
        $summary=$this->attendance()->summary('Alpha',[$this->staff->id],'2026-10-01','2026-10-15');
        $this->assertSame(1,$summary[$this->staff->id]['workdays']);$this->assertSame(0,$summary[$this->staff->id]['absence']);
        $this->assertSame($events,DB::table('logistics_financial_events')->count());
        $this->assertSame(15000,(int)DB::table('logistics_staff_salary_periods')->where('id',$period->id)->value('amount_cents'));
        $this->pay();$this->assertSame('paid',$this->payout()->status);
        $this->actingAs($this->otherStaff)->get(route('logistics.my-attendance.show',DB::table('logistics_staff_attendance')->value('id')))->assertNotFound();
        $foreign=$this->person('admin','Beta');
        $this->actingAs($foreign)->get(route('logistics.attendance.show',DB::table('logistics_staff_attendance')->value('id')))->assertNotFound();
        $this->actingAs($this->staff)->post(route('logistics.attendance.mark'),[])->assertForbidden();
        DB::statement("CREATE TRIGGER fail_attendance_audit BEFORE INSERT ON logistics_staff_attendance_events BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        try{$this->attendance()->clock($this->staff,'in');$this->fail('Expected audit failure');}catch(\Illuminate\Database\QueryException $e){}
        $this->assertSame(0,DB::table('logistics_staff_attendance')->whereNotNull('active_staff_id')->count());
    }

    public function test_attendance_pages_filters_exports_and_500_staff_are_paginated(): void
    {
        for($i=0;$i<500;$i++) $this->person('hub_staff');
        $response=$this->actingAs($this->admin)->get(route('logistics.attendance',['status'=>'no_record']));
        $response->assertOk()->assertSee('No Record')->assertSee('Export filtered attendance CSV');
        $this->assertSame(25,$response->viewData('records')->count());$this->assertSame(502,$response->viewData('records')->total());
        $this->actingAs($this->staff)->get(route('logistics.my-attendance'))->assertOk()->assertSee('Time In')->assertSee('Request attendance correction');
        $this->attendance()->clock($this->staff,'in');
        $this->actingAs($this->admin)->get(route('logistics.attendance',['q'=>(string)$this->staff->id,'status'=>'working']))->assertOk()->assertSee('Working');
        $csv=$this->get(route('logistics.attendance.export',['staff_id'=>$this->staff->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString('recorded_minutes_not_payroll',$csv);
        $this->get(route('logistics.finance.staff'))->assertOk()->assertSee('Hub salary defaults')->assertSee('Attendance review');
    }
    public function test_missing_hub_rate_is_filterable_and_has_no_invented_installment(): void
    {
        $member=$this->person('hub_staff');
        $r=$this->actingAs($this->admin)->get(route('logistics.finance.staff',['blocker'=>'missing_salary','q'=>(string)$member->id]));
        $r->assertOk()->assertSee('Missing Salary Configuration')->assertSee('No salary snapshot');
        $this->assertSame(1,$r->viewData('staff')->total());
        $this->assertSame(0,DB::table('logistics_staff_salary_periods')->where('staff_id',$member->id)->count());
        $this->actingAs($member)->get(route('logistics.my-salary'))->assertOk()->assertSee('Missing Salary Configuration');
    }

    public function test_hub_configuration_company_scope_revision_and_audit_rollback(): void
    {
        $foreign=$this->person('admin','Beta');
        $this->actingAs($foreign)->post(route('logistics.finance.staff.hub'),['hub_id'=>$this->hub->id,'monthly'=>'300','revision'=>0])->assertNotFound();
        $this->hubSalary();
        $this->actingAs($this->admin)->post(route('logistics.finance.staff.hub'),['hub_id'=>$this->hub->id,'monthly'=>'400','revision'=>0])->assertStatus(409);
        DB::statement("CREATE TRIGGER fail_hub_audit BEFORE INSERT ON logistics_financial_events BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        try{$this->hubSalary('500',1);$this->fail('Expected audit rollback');}catch(\Illuminate\Database\QueryException $e){}
        $this->assertDatabaseCount('logistics_hub_salary_rates',1);
        $this->assertSame(30001,(int)DB::table('logistics_staff_salaries')->where('staff_id',$this->staff->id)->value('monthly_cents'));
    }

    public function test_suspended_staff_future_and_foreign_corrections_are_denied(): void
    {
        $this->actingAs($this->otherStaff)->post(route('logistics.my-attendance.correction'),['attendance_id'=>(string)\Illuminate\Support\Str::uuid(),'business_date'=>'2026-10-01','requested_in'=>'2026-10-01T08:00','reason'=>'Wrong account record'])->assertNotFound();
        $this->invalid(fn()=>$this->correction(null,['requested_in'=>'2026-10-02T08:00','business_date'=>'2026-10-02']));
        DB::table('users')->where('id',$this->staff->id)->update(['status'=>'suspended']);
        $this->actingAs($this->staff)->post(route('logistics.my-attendance.clock',['action'=>'in']))->assertForbidden();
        $this->assertDatabaseCount('logistics_staff_attendance',0);
    }

    public function test_overlapping_correction_cannot_replace_another_shift_or_documented_leave(): void
    {
        $this->attendance()->clock($this->staff,'in');$first=DB::table('logistics_staff_attendance')->first();
        $this->travel(1)->hours();$this->attendance()->clock($this->staff,'out');
        $this->travel(1)->hours();$this->attendance()->clock($this->staff,'in');
        $request=$this->correction($first,['requested_in'=>'2026-10-01T09:00','requested_out'=>null]);
        $this->invalid(fn()=>$this->approve($request));
        $this->assertSame('pending',DB::table('logistics_staff_attendance_corrections')->where('id',$request->id)->value('status'));
        $this->assertSame(1,DB::table('logistics_staff_attendance')->whereNotNull('active_staff_id')->count());
    }

    public function test_reopened_corrected_shift_retains_its_first_original_clock_out(): void
    {
        $this->attendance()->clock($this->staff,'in');$this->travel(1)->hours();$this->attendance()->clock($this->staff,'out');
        $first=DB::table('logistics_staff_attendance')->first();$this->travel(1)->hours();
        $request=$this->correction($first,['requested_in'=>'2026-10-01T09:00','requested_out'=>null]);$this->approve($request);
        $this->travel(1)->hours();$this->attendance()->clock($this->staff,'out');
        $current=DB::table('logistics_staff_attendance')->first();
        $this->assertSame($first->original_in,$current->original_in);
        $this->assertSame($first->original_out,$current->original_out);
        $this->assertSame('2026-10-01 04:00:00',$current->time_out);
        $this->assertSame(2,DB::table('logistics_staff_attendance_events')->where('event','time_out')->count());
    }

    public function test_attendance_detail_shows_readable_names_seconds_and_duration_without_raw_json(): void
    {
        $this->attendance()->clock($this->staff,'in');$this->travel(3)->seconds();$this->attendance()->clock($this->staff,'out');
        $id=DB::table('logistics_staff_attendance')->value('id');
        $this->actingAs($this->staff)->get(route('logistics.my-attendance.show',$id))->assertOk()
            ->assertSee('Staff Test')->assertSee('Santa Cruz')->assertSee('9:00:03 AM')->assertSee('3s')
            ->assertSee('Timed in')->assertSee('Timed out')->assertDontSee('server_time')->assertDontSee('Original clock timestamps:');
        $this->actingAs($this->admin)->get(route('logistics.attendance.show',$id))->assertOk()->assertSee('Recorded duration');
    }

}
