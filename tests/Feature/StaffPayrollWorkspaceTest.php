<?php

namespace Tests\Feature;

use App\Services\StaffPayrollWorkspace;
use App\Models\LogisticsHub;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffPayrollWorkspaceTest extends StaffSalaryTest
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_30_000003_index_staff_payroll_workspace.php'))->up();
    }

    private function workspace(): StaffPayrollWorkspace { return app(StaffPayrollWorkspace::class); }

    private function selection(array $ids,string $mode='generate'): array
    {
        return ['ids'=>$ids,'mode'=>$mode,'account_id'=>$this->account->id,'period_year'=>2026,'period_month'=>10,'period_half'=>1];
    }

    protected function population(int $count): array
    {
        $otherHub=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Other Hub','province'=>'Laguna','is_operational'=>true]);
        $ids=[];$rates=[];$destinations=[];
        for($i=0;$i<$count;$i++){
            $person=$this->person('hub_staff','Alpha',$i%2?$otherHub->id:$this->hub->id);
            DB::table('users')->where('id',$person->id)->update(['given_names'=>'Alex','last_name'=>'Same','status'=>$i===0?'suspended':'approved']);
            $ids[]=$person->id;
            if($i!==1) $rates[]=['id'=>(string)Str::uuid(),'company_name'=>'Alpha','staff_id'=>$person->id,'monthly_cents'=>30001,'revision'=>1,'set_by'=>$this->admin->id,'effective_from'=>now(),'created_at'=>now(),'updated_at'=>now()];
            if($i!==2) $destinations[]=['id'=>(string)Str::uuid(),'staff_id'=>$person->id,'channel'=>'gcash','institution'=>'GCash','account_name'=>'Alex Same','account_number'=>'09'.str_pad((string)$i,9,'0',STR_PAD_LEFT),'created_at'=>now()];
        }
        foreach(array_chunk($rates,100) as $rows) DB::table('logistics_staff_salaries')->insert($rows);
        foreach(array_chunk($destinations,100) as $rows) DB::table('logistics_staff_destinations')->insert($rows);
        return $ids;
    }

    public function test_workspace_handles_100_and_500_staff_with_bounded_queries_and_stable_pages(): void
    {
        $ids=$this->population(100);
        DB::enableQueryLog();DB::flushQueryLog();
        $response=$this->actingAs($this->admin)->get(route('logistics.finance.staff'));
        $response->assertOk();$firstQueries=count(DB::getQueryLog());
        $this->assertSame(25,$response->viewData('staff')->count());
        $this->assertSame(102,$response->viewData('staff')->total());
        $this->population(400);
        DB::flushQueryLog();
        $response=$this->get(route('logistics.finance.staff',['page'=>2]));
        $response->assertOk();
        $this->assertSame(502,$response->viewData('staff')->total());
        $this->assertSame(25,$response->viewData('staff')->count());
        $this->assertLessThanOrEqual($firstQueries+3,count(DB::getQueryLog()));
        $idsPage2=$response->viewData('staff')->pluck('id')->all();
        $this->assertSame($idsPage2,$this->get(route('logistics.finance.staff',['page'=>2]))->viewData('staff')->pluck('id')->all());
        DB::disableQueryLog();
        foreach(['Alex Same',(string)$ids[5]] as $q) $this->get(route('logistics.finance.staff',['q'=>$q]))->assertOk()->assertSee('Alex Same');
        $this->assertSame(1,$this->get(route('logistics.finance.staff',['q'=>(string)$ids[5]]))->viewData('staff')->total());
        $this->get(route('logistics.finance.staff',['blocker'=>'suspended']))->assertOk()->assertSee('Suspended Staff');
        $this->get(route('logistics.finance.staff',['blocker'=>'missing_salary']))->assertOk()->assertSee('Missing salary configuration');
        $this->get(route('logistics.finance.staff',['blocker'=>'missing_gcash']))->assertOk()->assertSee('Missing or invalid GCash account');
        $this->get(route('logistics.finance.staff',['hub'=>$this->hub->id,'q'=>'Alex']))->assertOk();
    }

    public function test_bulk_generation_preview_execution_and_retry_with_itemized_exclusions(): void
    {
        $ids=$this->population(5);
        $preview=$this->workspace()->preview($this->admin,$this->selection($ids));
        $this->assertSame(2,collect($preview['items'])->where('state','skipped')->count());
        $this->assertDatabaseCount('logistics_staff_salary_periods',0);
        $results=$this->workspace()->execute($this->admin,$preview['token']);
        $this->assertSame(3,collect($results['items'])->where('state','created')->count());
        $this->workspace()->execute($this->admin,$preview['token']);
        $this->assertDatabaseCount('logistics_staff_salary_periods',3);
        $again=$this->workspace()->preview($this->admin,$this->selection($ids));
        $this->assertSame(3,collect($again['items'])->where('state','existing')->count());
    }

    public function test_bulk_funding_shortfall_never_prepares_a_subset_and_replay_is_safe(): void
    {
        $ids=$this->population(5);
        $gen=$this->workspace()->preview($this->admin,$this->selection($ids));$this->workspace()->execute($this->admin,$gen['token']);
        $this->fund('150');
        $preview=$this->workspace()->preview($this->admin,$this->selection($ids,'prepare'));
        $this->assertSame(30000,$preview['total']); // one generated Staff lacks GCash
        $this->assertSame(15000,$preview['shortfall']);
        $this->invalid(fn()=>$this->workspace()->execute($this->admin,$preview['token']));
        $this->assertDatabaseCount('logistics_staff_payouts',0);
        $this->fund('150','STAFFUND002');
        $results=$this->workspace()->execute($this->admin,$preview['token']);
        $this->assertSame(2,collect($results['items'])->where('state','prepared')->count());
        $this->assertSame(0,$this->flow->available($this->account->id));
        $this->workspace()->execute($this->admin,$preview['token']);
        $this->assertDatabaseCount('logistics_staff_payouts',2);
        $this->assertSame(30000,$this->treasury->balance($this->account->id,'staff_payroll'));
        $this->assertSame(0,$this->treasury->balance($this->account->id,'cod'));
    }

    public function test_bulk_revalidates_salary_destination_staff_and_returns_partial_failures(): void
    {
        $ids=$this->population(5);
        $preview=$this->workspace()->preview($this->admin,$this->selection([$ids[3],$ids[4]]));
        DB::table('users')->where('id',$ids[3])->update(['status'=>'suspended']);
        $results=$this->workspace()->execute($this->admin,$preview['token']);
        $this->assertSame(['failed','created'],array_column($results['items'],'state'));
        $this->assertDatabaseCount('logistics_staff_salary_periods',1);
        $this->fund();
        $prepare=$this->workspace()->preview($this->admin,$this->selection([$ids[4]],'prepare'));
        $this->travel(1)->seconds();
        $this->flow->destination(\App\Models\User::find($ids[4]),['channel'=>'gcash','institution'=>'GCash','account_name'=>'Changed Destination','account_number'=>'09991112223','confirmed'=>'1']);
        $result=$this->workspace()->execute($this->admin,$prepare['token']);
        $this->assertSame('failed',$result['items'][0]['state']);
        $this->assertDatabaseCount('logistics_staff_payouts',0);
    }

    public function test_preview_detects_changed_historical_snapshot_before_generation(): void
    {
        $this->configure();
        $preview=$this->workspace()->preview($this->admin,$this->selection([$this->staff->id]));
        // A revision effective at period start changes the prospective snapshot.
        $this->travelTo(CarbonImmutable::parse('2026-10-01 00:00','Asia/Manila'));
        $this->flow->configure($this->admin,['staff_id'=>$this->staff->id,'monthly'=>'500','revision'=>1]);
        $result=$this->workspace()->execute($this->admin,$preview['token']);
        $this->assertSame('failed',$result['items'][0]['state']);
        $this->assertDatabaseCount('logistics_staff_salary_periods',0);
    }

    public function test_period_filters_overdue_and_focused_links_work_without_history_pagination(): void
    {
        $this->configure();$this->generatePeriod();$this->fund();$this->prepare();
        $p=$this->payout();
        $this->travelTo(CarbonImmutable::parse('2026-11-01','Asia/Manila'));
        $f=['period_year'=>2026,'period_month'=>10,'period_half'=>1,'blocker'=>'overdue'];
        $response=$this->actingAs($this->admin)->get(route('logistics.finance.staff',$f));
        $response->assertOk()->assertSee('Overdue salary')->assertSee('/finance/staff/payouts/'.$p->id);
        $this->get(route('logistics.finance.staff.show',['id'=>$p->id,'payouts_page'=>999]))->assertOk()->assertSee('Staff salary payment')->assertSee('PHP 150.00');
        $this->get(route('logistics.finance.staff.history',['staff'=>$this->staff->id]))->assertOk()->assertSee('Revision 1');
        $this->get(route('logistics.finance.staff',['status'=>'prepared','period_year'=>2026,'period_month'=>10,'period_half'=>1]))->assertOk()->assertSee('View payout');
        $this->get(route('logistics.finance.staff',['period_year'=>2026,'period_month'=>11,'period_half'=>1]))->assertOk()->assertSee('Not generated');
    }

    public function test_csv_is_period_based_masked_authorized_and_formula_safe(): void
    {
        $this->configure();$this->generatePeriod();$this->addDestination();
        DB::table('users')->where('id',$this->staff->id)->update(['given_names'=>'=FORMULA']);
        $response=$this->actingAs($this->admin)->get(route('logistics.finance.staff.export',['period_year'=>2026,'period_month'=>10,'period_half'=>1,'q'=>(string)$this->staff->id]));
        $response->assertOk()->assertHeader('Cache-Control','no-store, private');
        $csv=$response->streamedContent();
        $this->assertStringContainsString('15000',$csv);$this->assertStringContainsString('*******3221',$csv);$this->assertStringNotContainsString('09554433221',$csv);$this->assertStringContainsString("'=FORMULA",$csv);
        $this->assertDatabaseHas('logistics_financial_events',['event'=>'staff_payroll_exported','company_name'=>'Alpha']);
        $this->fund();
        $this->flow->prepare($this->admin,['period_id'=>$this->period()->id,'account_id'=>$this->account->id,'destination_id'=>DB::table('logistics_staff_destinations')->where('staff_id',$this->staff->id)->value('id')]);
        $this->travel(1)->seconds();
        $this->flow->destination($this->staff,['channel'=>'gcash','institution'=>'GCash','account_name'=>'New Receiving Account','account_number'=>'09998887776','confirmed'=>'1']);
        $snapshotCsv=$this->get(route('logistics.finance.staff.export',['q'=>(string)$this->staff->id]))->streamedContent();
        $this->assertStringContainsString('*******3221',$snapshotCsv);
        $this->assertStringNotContainsString('*******7776',$snapshotCsv);
        $this->actingAs($this->staff)->get(route('logistics.finance.staff.export'))->assertForbidden();
        $other=$this->person('admin','Beta');
        $this->actingAs($other)->get(route('logistics.finance.staff.show','00000000-0000-0000-0000-000000000001'))->assertNotFound();
    }

    public function test_bulk_http_flow_preserves_filters_and_blocks_foreign_oversized_and_tampered_selection(): void
    {
        $this->configure();
        $data=$this->selection([$this->staff->id])+['q'=>(string)$this->staff->id,'page'=>2];
        $this->actingAs($this->admin)->post(route('logistics.finance.staff.bulk.preview'),$data)->assertRedirect(route('logistics.finance.staff.bulk.preview'));
        $this->get(route('logistics.finance.staff.bulk.preview'))->assertOk()->assertSee('Generate obligations');
        $preview=session('staff_payroll_preview');
        $this->post(route('logistics.finance.staff.bulk.execute'),['token'=>$preview['token'],'confirmed'=>'1'])->assertRedirect(route('logistics.finance.staff',$preview['filters']));
        $this->post(route('logistics.finance.staff.bulk.execute'),['token'=>'tampered','confirmed'=>'1'])->assertStatus(422);
        $foreign=$this->person('hub_staff','Beta');
        $this->post(route('logistics.finance.staff.bulk.preview'),$this->selection([$foreign->id]))->assertForbidden();
        $this->post(route('logistics.finance.staff.bulk.preview'),$this->selection(range(1,26)))->assertSessionHasErrors('ids');
        $this->actingAs($this->staff)->post(route('logistics.finance.staff.bulk.preview'),$data)->assertForbidden();
    }
    public function test_partial_audit_failure_rolls_back_only_the_failed_item(): void
    {
        $ids=$this->population(5);
        $preview=$this->workspace()->preview($this->admin,$this->selection([$ids[3],$ids[4]]));
        $staffId=(int)$ids[3];
        DB::statement("CREATE TRIGGER fail_one_bulk_audit BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'staff_period_generated' AND json_extract(NEW.details, '$.staff_id') = ".$staffId." BEGIN SELECT RAISE(ABORT, 'synthetic item audit failure'); END");
        $result=$this->workspace()->execute($this->admin,$preview['token']);
        $this->assertSame(['failed','created'],array_column($result['items'],'state'));
        $this->assertDatabaseMissing('logistics_staff_salary_periods',['staff_id'=>$ids[3]]);
        $this->assertDatabaseHas('logistics_staff_salary_periods',['staff_id'=>$ids[4]]);
        DB::statement('DROP TRIGGER fail_one_bulk_audit');
        $result=$this->workspace()->execute($this->admin,$preview['token']);
        $this->assertSame(['created','existing'],array_column($result['items'],'state'));
        $this->assertDatabaseCount('logistics_staff_salary_periods',2);
    }

    public function test_ambiguous_gcash_is_excluded_and_expired_preview_cannot_execute(): void
    {
        $this->configure();$this->generatePeriod();$this->fund();
        $this->addDestination();$this->addDestination();
        $preview=$this->workspace()->preview($this->admin,$this->selection([$this->staff->id],'prepare'));
        $this->assertSame('skipped',$preview['items'][0]['state']);
        $this->assertStringContainsString('identical saved timestamps',$preview['items'][0]['reason']);
        $payload=json_decode(\Illuminate\Support\Facades\Crypt::decryptString($preview['token']),true);
        $payload['expires']=time()-1;
        try{$this->workspace()->execute($this->admin,\Illuminate\Support\Facades\Crypt::encryptString(json_encode($payload)));$this->fail('Expired preview accepted');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}
        $this->assertDatabaseCount('logistics_staff_payouts',0);
    }

    public function test_filters_and_pagination_survive_individual_validation_errors(): void
    {
        $url=route('logistics.finance.staff',['q'=>(string)$this->staff->id,'hub'=>$this->hub->id,'period_year'=>2026,'period_month'=>10,'period_half'=>1,'page'=>2]);
        $this->actingAs($this->admin)->from($url)->post(route('logistics.finance.staff.configure'),['staff_id'=>$this->staff->id,'monthly'=>'invalid','revision'=>0])
            ->assertRedirect($url)->assertSessionHasErrors();
        $this->get(route('logistics.finance.staff',['status'=>'invented']))->assertSessionHasErrors('status');
        $this->get(route('logistics.finance.staff',['blocker'=>'not_eligible']))->assertOk();
    }

    public function test_history_does_not_replace_selected_period_snapshots_or_show_foreign_staff(): void
    {
        $this->configure(30001);$period=$this->generatePeriod();
        $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00','Asia/Manila'));
        $this->flow->configure($this->admin,['staff_id'=>$this->staff->id,'monthly'=>'500','revision'=>1]);
        $foreign=$this->person('hub_staff','Beta');
        $f=['period_year'=>2026,'period_month'=>10,'period_half'=>1,'q'=>(string)$this->staff->id];
        $response=$this->actingAs($this->admin)->get(route('logistics.finance.staff',$f));
        $response->assertOk()->assertSee('Period basis: PHP 300.01');
        $this->assertSame($period->id,$response->viewData('staff')->first()->period_id);
        $this->get(route('logistics.finance.staff.history',['staff'=>$foreign->id]))->assertNotFound();
        $this->get(route('logistics.finance.staff',['q'=>(string)$foreign->id]))->assertOk()->assertSee('No Staff match');
    }

    public function test_selected_period_queries_remain_bounded_with_a_year_of_500_staff_history(): void
    {
        $this->population(500);
        $rates=DB::table('logistics_staff_salaries')->where('company_name','Alpha')->get();
        $rows=[];
        foreach($rates as $rate){
            for($month=1;$month<=12;$month++) for($half=1;$half<=2;$half++) {
                $rows[]=['id'=>(string)Str::uuid(),'company_name'=>'Alpha','staff_id'=>$rate->staff_id,'salary_id'=>$rate->id,'period_year'=>2025,'period_month'=>$month,'period_half'=>$half,
                    'pay_date'=>$this->flow->payDate(2025,$month,$half)->toDateString(),'amount_cents'=>$this->flow->halfCents((int)$rate->monthly_cents,$half),'status'=>'pending','created_at'=>'2025-01-01 00:00:00','updated_at'=>'2025-01-01 00:00:00'];
            }
        }
        foreach(array_chunk($rows,100) as $chunk) DB::table('logistics_staff_salary_periods')->insert($chunk);
        $this->assertGreaterThan(11000,DB::table('logistics_staff_salary_periods')->count());
        $current=$this->actingAs($this->admin)->get(route('logistics.finance.staff'));
        $current->assertOk();$this->assertSame(25,$current->viewData('staff')->count());
        $this->assertSame(0,(int)$current->viewData('summary')->outstanding);
        $historical=$this->get(route('logistics.finance.staff',['period_year'=>2025,'period_month'=>3,'period_half'=>1]));
        $historical->assertOk();$this->assertSame(25,$historical->viewData('staff')->count());
        $this->assertSame(499*15000,(int)$historical->viewData('summary')->outstanding);
        $this->assertSame(0,(int)$historical->viewData('summary')->paid);
    }

    public function test_partial_preparation_failure_keeps_funds_and_allows_safe_retry(): void
    {
        $ids=$this->population(5);$chosen=[$ids[3],$ids[4]];
        $gen=$this->workspace()->preview($this->admin,$this->selection($chosen));$this->workspace()->execute($this->admin,$gen['token']);
        $this->fund();$preview=$this->workspace()->preview($this->admin,$this->selection($chosen,'prepare'));
        $periodId=$preview['items'][0]['period_id'];
        DB::statement("CREATE TRIGGER fail_prepare_item BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'staff_payout_prepared' AND json_extract(NEW.details, '$.period_id') = '".$periodId."' BEGIN SELECT RAISE(ABORT, 'synthetic preparation audit failure'); END");
        $result=$this->workspace()->execute($this->admin,$preview['token']);
        $this->assertSame(['failed','prepared'],array_column($result['items'],'state'));
        $this->assertDatabaseCount('logistics_staff_payouts',1);
        $this->assertSame(15000,$this->flow->available($this->account->id));
        DB::statement('DROP TRIGGER fail_prepare_item');
        $result=$this->workspace()->execute($this->admin,$preview['token']);
        $this->assertSame(['prepared','existing'],array_column($result['items'],'state'));
        $this->assertDatabaseCount('logistics_staff_payouts',2);
        $this->assertSame(0,$this->flow->available($this->account->id));
        $this->assertSame(30000,$this->treasury->balance($this->account->id,'staff_payroll'));
    }

}
