<?php

namespace Tests\Feature;

use App\Models\{DeliveryAssignment, LogisticsHub, Order, Shipment, User};
use App\Services\{AssignmentReassignment, AssignmentWork, LogisticsFinance};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class OperationalRecoveryTest extends TestCase
{
    use WorkflowSchema;
    protected User $admin; protected User $staff; protected User $rider; protected User $replacement; protected User $seller;
    protected LogisticsHub $hub; protected Shipment $shipment; protected DeliveryAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();$this->createWorkflowSchema();
        foreach(['2026_10_14_000004_add_gcash_purchases','2026_10_16_000001_add_registration_business_details','2026_10_16_000002_add_logistics_registration_details',
            '2026_10_17_000001_add_buyer_commerce_features','2026_10_28_000001_add_logistics_financial_foundation','2026_10_28_000002_add_logistics_financial_transactions',
            '2026_10_29_000001_add_logistics_cash_desk','2026_10_29_000002_add_logistics_shipping_settlement','2026_10_29_000003_add_cod_seller_payouts','2026_10_29_000004_add_rider_compensation_payouts','2026_10_29_000005_add_logistics_operational_recovery'] as $m)(require database_path('migrations/'.$m.'.php'))->up();
        Storage::fake('local');Storage::fake('sensitive_documents');
        $this->hub=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Santa Cruz','province'=>'Laguna','is_operational'=>true]);
        $this->admin=$this->person('admin');$this->staff=$this->person('hub_staff');$this->seller=$this->person('seller');$this->rider=$this->person('rider');$this->replacement=$this->person('rider');$buyer=$this->person('buyer');
        app(\App\Services\LogisticsShipping::class)->publish($this->admin,['region'=>'Test Region','revision'=>0,'first_kg'=>'100','additional_kg'=>'0']);
        $order=Order::create(['buyer_id'=>$buyer->id,'seller_id'=>$this->seller->id,'status'=>'preparing','payment_method'=>'Cash on Delivery','subtotal'=>'1000','shipping_amount'=>'100','discount_amount'=>'0','total'=>'1100','commerce_snapshot'=>['vouchers'=>[]],
            'items'=>[['name'=>'Parcel','qty'=>1,'price'=>1000,'weight_grams'=>1000]],'shipping_address'=>['region'=>'Test Region','municipality'=>'Santa Cruz','province'=>'Laguna']]);
        $this->shipment=Shipment::create(['tracking_number'=>'RECOVERY-'.Str::uuid(),'order_id'=>$order->id,'logistics_company'=>'Alpha','shipping_status'=>'ready_for_pickup','origin_hub'=>'Santa Cruz','origin_province'=>'Laguna','destination_hub'=>'Santa Cruz','destination_province'=>'Laguna']);
        DB::table('shipments')->where('id',$this->shipment->id)->update(['pickup_approved_at'=>now(),'pickup_rider_id'=>$this->rider->id]);
        $this->shipment->refresh();
        $this->assignment=DeliveryAssignment::create(['shipment_id'=>$this->shipment->id,'courier_id'=>$this->rider->id,'leg'=>'pickup','status'=>'accepted','accepted_at'=>now()]);
    }

    protected function person(string $role,?int $hub=null,string $company='Alpha'): User
    {
        return User::create(['username'=>(string)Str::uuid(),'account_type'=>in_array($role,['admin','hub_staff'])?'logistics':$role,'business_name'=>$company,'status'=>'approved',
            'is_admin'=>false,'is_logistics'=>in_array($role,['admin','hub_staff']),'logistics_role'=>in_array($role,['admin','hub_staff'])?$role:null,'logistics_hub_id'=>$hub??$this->hub->id,'vehicle_type'=>'two_wheels','vehicle_ownership'=>'own','municipality'=>'Santa Cruz','province'=>'Laguna','given_names'=>'Test','last_name'=>'Recovery']);
    }
    protected function requestData(): array { return ['key'=>(string)Str::uuid(),'revision'=>$this->assignment->fresh()->revision,'old_rider_id'=>$this->assignment->fresh()->courier_id,'replacement_rider_id'=>$this->replacement->id,'reason'=>'Rider unavailable before collection']; }
    protected function confirmData(): array { $a=$this->assignment->fresh();return ['key'=>$a->reassignment['key'],'revision'=>$a->revision,'action'=>'confirm','confirmed'=>'1']; }
    protected function requestChange(): void { $this->actingAs($this->admin)->postJson(route('logistics.assignments.reassign',$this->assignment->id),$this->requestData())->assertRedirect(); }

    public function test_classification_is_read_only_and_receipt_prospectively_closes_pickup(): void
    {
        $work=app(AssignmentWork::class);$this->assertSame('active',$work->classify($this->assignment));
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'picked_up','seller_confirmed_pickup_at'=>now(),'rider_confirmed_pickup_at'=>now(),'picked_up_at'=>now()]);
        $original=$this->assignment->getRawOriginal();
        $this->actingAs($this->staff)->patchJson('/logistics/scan/'.$this->shipment->id.'/receive')->assertOk();
        $this->assertSame('completed',$this->assignment->fresh()->status);
        $this->assertSame($original['accepted_at'],$this->assignment->fresh()->getRawOriginal('accepted_at'));
        $this->assertSame('completed',$work->classify($this->assignment->fresh()));
        $this->assertSame(1,DB::table('logistics_compensations')->where('source_id',$this->assignment->id)->count());
        $this->assertSame(2500,(int)DB::table('logistics_compensations')->where('source_id',$this->assignment->id)->value('amount_cents'));
    }

    public function test_reassignment_requires_seller_possession_and_preserves_original_snapshot_on_return(): void
    {
        $this->requestChange();$stale=$this->confirmData();$this->assertSame($this->rider->id,$this->shipment->fresh()->pickup_rider_id);
        $this->actingAs($this->admin)->postJson(route('logistics.assignments.decide',$this->assignment->id),$this->confirmData())->assertForbidden();
        $this->actingAs($this->rider)->patchJson(route('rider.my-pickups.confirm',$this->shipment->id))->assertConflict();
        $this->actingAs($this->seller)->postJson(route('seller.assignments.decide',$this->assignment->id),$this->confirmData())->assertRedirect();
        $this->assertSame($this->replacement->id,$this->shipment->fresh()->pickup_rider_id);
        $this->assertDatabaseHas('logistics_compensations',['source_id'=>$this->assignment->id,'rider_id'=>$this->rider->id,'status'=>'cancelled','amount_cents'=>2500]);
        DB::table('logistics_finance_settings')->where('company_name','Alpha')->update(['pickup_cents'=>5000,'revision'=>2]);
        $old=$this->rider;$this->replacement=$old;$this->requestChange();
        $this->actingAs($this->seller)->postJson(route('seller.assignments.decide',$this->assignment->id),$this->confirmData())->assertRedirect();
        $this->assertDatabaseHas('logistics_compensations',['source_id'=>$this->assignment->id,'rider_id'=>$old->id,'status'=>'pending','amount_cents'=>2500]);
        $this->assertSame(1,DeliveryAssignment::whereKey($this->assignment->id)->count());
        $this->postJson(route('seller.assignments.decide',$this->assignment->id),$stale)->assertConflict();
    }

    public function test_handover_stale_duplicate_and_wrong_hub_requests_are_rejected(): void
    {
        $data=$this->requestData();$this->actingAs($this->admin)->postJson(route('logistics.assignments.reassign',$this->assignment->id),$data)->assertRedirect();
        $this->postJson(route('logistics.assignments.reassign',$this->assignment->id),$data)->assertRedirect();
        $stale=$this->confirmData();$this->actingAs($this->seller)->postJson(route('seller.assignments.decide',$this->assignment->id),$stale)->assertRedirect();
        $this->postJson(route('seller.assignments.decide',$this->assignment->id),$stale)->assertConflict();
        $this->assertSame(1,DB::table('logistics_financial_events')->where('event','assignment_reassigned_confirmed')->count());
        DB::table('shipments')->where('id',$this->shipment->id)->update(['rider_confirmed_pickup_at'=>now()]);
        $this->replacement=$this->rider;
        $this->actingAs($this->admin)->postJson(route('logistics.assignments.reassign',$this->assignment->id),$this->requestData())->assertUnprocessable();
    }
    protected function delivery(): void
    {
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'assigned_to_rider','courier_id'=>$this->rider->id,'sorted_at'=>now(),'at_sorting_center_at'=>now(),'picked_up_at'=>now(),'seller_confirmed_pickup_at'=>now(),'rider_confirmed_pickup_at'=>now()]);
        DB::table('orders')->where('id',$this->shipment->order_id)->update(['status'=>'assigned_to_rider']);
        $this->assignment=DeliveryAssignment::create(['shipment_id'=>$this->shipment->id,'courier_id'=>$this->rider->id,'leg'=>'delivery','status'=>'assigned_to_rider','accepted_at'=>now()]);
        $this->shipment->refresh();
    }
    protected function collected(): object
    {
        $this->delivery();
        $this->actingAs($this->rider)->patch(route('rider.deliveries.advance',$this->shipment->id),['from'=>'assigned_to_rider'])->assertRedirect();
        $this->patch(route('rider.deliveries.advance',$this->shipment->id),['from'=>'out_for_delivery','cod_amount'=>'1100','cod_confirmed'=>'1','collection_key'=>(string)Str::uuid()])->assertRedirect();
        DB::table('users')->where('id',$this->rider->id)->update(['status'=>'suspended']);
        return DB::table('logistics_financial_transactions')->where('kind','cod_collection')->first();
    }
    protected function recoveryData(): array
    {
        return ['key'=>(string)Str::uuid(),'rider_id'=>$this->rider->id,'leg'=>'delivery','tracking_numbers'=>$this->shipment->tracking_number,'condition'=>'Parcel sealed and undamaged','observed_at'=>now()->setTimezone('Asia/Manila')->toIso8601String(),'reason'=>'Rider suspended during delivery','reference'=>'HUB-CUSTODY-RECOVERY-001','confirmed'=>'1'];
    }
    public function test_final_delivery_reassignment_needs_destination_staff_and_blocks_old_rider_release(): void
    {
        $this->delivery();$this->requestChange();
        $this->actingAs($this->admin)->postJson(route('logistics.assignments.decide',$this->assignment->id),$this->confirmData())->assertForbidden();
        $this->actingAs($this->rider)->patchJson(route('rider.deliveries.advance',$this->shipment->id),['from'=>'assigned_to_rider'])->assertConflict();
        $this->actingAs($this->staff)->postJson(route('logistics.assignments.decide',$this->assignment->id),$this->confirmData())->assertRedirect();
        $this->assertSame($this->replacement->id,$this->shipment->fresh()->courier_id);
        $this->assertNotNull($this->shipment->fresh()->picked_up_at); // Earlier pickup leg evidence is retained.
        $this->actingAs($this->rider)->patchJson(route('rider.deliveries.advance',$this->shipment->id),['from'=>'assigned_to_rider'])->assertNotFound();
        $this->actingAs($this->replacement)->patchJson(route('rider.deliveries.advance',$this->shipment->id),['from'=>'assigned_to_rider'])->assertRedirect();
    }
    public function test_delivery_release_wins_before_reassignment_and_cannot_be_cleared(): void
    {
        $this->delivery();$this->actingAs($this->rider)->patch(route('rider.deliveries.advance',$this->shipment->id),['from'=>'assigned_to_rider'])->assertRedirect();
        $this->actingAs($this->admin)->postJson(route('logistics.assignments.reassign',$this->assignment->id),$this->requestData())->assertUnprocessable();
        $this->assertNotNull($this->shipment->fresh()->out_for_delivery_at);
    }
    public function test_stale_authenticated_rider_cannot_confirm_pickup_after_suspension(): void
    {
        DB::table('users')->where('id',$this->rider->id)->update(['status'=>'suspended']);
        $this->actingAs($this->rider)->patchJson(route('rider.my-pickups.confirm',$this->shipment->id))->assertForbidden();
        $this->assertNull($this->shipment->fresh()->rider_confirmed_pickup_at);
    }
    public function test_pickup_handover_first_and_wrong_company_or_hub_are_protected(): void
    {
        $otherHub=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Other City','province'=>'Laguna','is_operational'=>true]);
        $staff=$this->person('hub_staff',$otherHub->id);
        $this->actingAs($staff)->postJson(route('logistics.assignments.reassign',$this->assignment->id),$this->requestData())->assertForbidden();
        $foreign=$this->person('admin',null,'Other');
        $this->actingAs($foreign)->postJson(route('logistics.assignments.reassign',$this->assignment->id),$this->requestData())->assertNotFound();
        $this->actingAs($this->rider)->patch(route('rider.my-pickups.confirm',$this->shipment->id))->assertRedirect();
        $this->actingAs($this->admin)->postJson(route('logistics.assignments.reassign',$this->assignment->id),$this->requestData())->assertUnprocessable();
    }
    public function test_reassignment_audit_failure_rolls_back_owner_revision_and_snapshot(): void
    {
        $this->requestChange();$revision=$this->assignment->fresh()->revision;
        DB::statement("CREATE TRIGGER fail_reassignment BEFORE INSERT ON logistics_financial_events WHEN NEW.event='assignment_reassigned_confirmed' BEGIN SELECT RAISE(ABORT,'audit failed'); END");
        $this->actingAs($this->seller)->postJson(route('seller.assignments.decide',$this->assignment->id),$this->confirmData())->assertStatus(500);
        $this->assertSame($this->rider->id,$this->shipment->fresh()->pickup_rider_id);
        $this->assertSame($revision,$this->assignment->fresh()->revision);
        $this->assertNotNull($this->assignment->fresh()->reassignment);
        $this->assertSame(1,DB::table('logistics_compensations')->count());
        $this->assertDatabaseHas('logistics_compensations',['rider_id'=>$this->rider->id,'status'=>'pending']);
    }
    public function test_cancellation_preserves_assignment_and_stale_request_cannot_reappear(): void
    {
        $data=$this->requestData();$this->actingAs($this->admin)->postJson(route('logistics.assignments.reassign',$this->assignment->id),$data)->assertRedirect();
        $cancel=$this->confirmData();unset($cancel['confirmed']);$cancel['action']='cancel';$cancel['reason']='Seller cannot confirm current possession';
        $this->actingAs($this->seller)->postJson(route('seller.assignments.decide',$this->assignment->id),$cancel)->assertRedirect();
        $this->assertSame($this->rider->id,$this->shipment->fresh()->pickup_rider_id);
        $this->actingAs($this->admin)->postJson(route('logistics.assignments.reassign',$this->assignment->id),$data)->assertConflict();
    }
    public function test_staff_cash_recovery_is_separate_from_count_and_preserves_shortages_and_overages(): void
    {
        $c=$this->collected();$d=['key'=>(string)Str::uuid(),'rider_id'=>$this->rider->id,'allocations'=>[$c->id=>'600'],'reason'=>'Suspended rider remittance recovery','reference'=>'CASH-CUSTODY-01'];
        $snapshots=DB::table('logistics_compensations')->get()->toJson();
        $this->actingAs($this->admin)->postJson(route('logistics.cash-desk.recovery'),$d)->assertForbidden();
        $this->actingAs($this->staff)->postJson(route('logistics.cash-desk.recovery'),$d)->assertRedirect();
        $this->postJson(route('logistics.cash-desk.recovery'),$d)->assertRedirect();
        $h=DB::table('logistics_cash_handovers')->first();
        $this->assertSame($this->rider->id,(int)$h->actor_id);$this->assertSame($this->staff->id,(int)$h->submitted_by);
        $this->assertNull($h->counted_at);$this->assertSame(0,app(\App\Services\LogisticsCashDesk::class)->custody($this->hub->id));
        $this->postJson(route('logistics.cash-desk.count',$h->id),['counted'=>'500','allocations'=>[$c->id=>'500'],'notes'=>'Actual count has a shortage'])->assertRedirect();
        $this->assertSame(60000,app(\App\Services\LogisticsCashDesk::class)->balances($c)['outstanding']);
        $d['key']=(string)Str::uuid();$d['reference']='CASH-CUSTODY-02';$this->postJson(route('logistics.cash-desk.recovery'),$d)->assertRedirect();
        $last=DB::table('logistics_cash_handovers')->where('id','!=',$h->id)->first();
        $this->postJson(route('logistics.cash-desk.count',$last->id),['counted'=>'650','allocations'=>[$c->id=>'600'],'notes'=>'Actual overage remains unallocated'])->assertRedirect();
        $this->assertSame(0,app(\App\Services\LogisticsCashDesk::class)->balances($c)['outstanding']);
        $this->assertSame(115000,app(\App\Services\LogisticsCashDesk::class)->custody($this->hub->id));
        $this->assertSame($snapshots,DB::table('logistics_compensations')->get()->toJson());
        $report=app(\App\Services\LogisticsReconciliation::class)->snapshot($this->admin,now()->subDay()->toDateString(),now()->addDay()->toDateString());
        $this->assertSame(0,$report['conservation']['residual_cents']);
        $this->assertSame(2,DB::table('logistics_financial_events')->where('event','remittance_submitted')->count());
        $this->actingAs($this->staff)->get(route('logistics.cash-desk'))->assertOk()->assertSee('Submitted by:');
    }
    public function test_physical_recovery_preserves_parcel_finances_and_holds_redispatch_with_private_evidence(): void
    {
        $this->delivery();$this->actingAs($this->rider)->patch(route('rider.deliveries.advance',$this->shipment->id),['from'=>'assigned_to_rider'])->assertRedirect();
        DB::table('users')->where('id',$this->rider->id)->update(['status'=>'suspended']);
        $d=$this->recoveryData();$proof=\Illuminate\Http\UploadedFile::fake()->createWithContent('recovery.pdf',"%PDF-1.4\nPrivate recovery evidence");
        $this->actingAs($this->admin)->postJson(route('logistics.issues.recovery'),$d)->assertForbidden();
        $before=$this->shipment->fresh()->getRawOriginal();$snapshots=DB::table('logistics_compensations')->get()->toJson();
        $this->actingAs($this->staff)->post(route('logistics.issues.recovery'),$d+['proof'=>$proof])->assertRedirect();
        $this->assertSame($before,$this->shipment->fresh()->getRawOriginal());
        $this->assertSame($snapshots,DB::table('logistics_compensations')->get()->toJson());
        $this->assertSame(0,DB::table('logistics_financial_transactions')->count());
        $issue=\App\Models\LogisticsIssue::first();$this->assertSame($this->staff->id,$issue->recovery['received_by']);
        $e=DB::table('logistics_finance_evidence')->first();$this->get(route('finance.evidence',$e->id))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $retry=$d;$retry['key']=(string)Str::uuid();$this->postJson(route('logistics.issues.recovery'),$retry)->assertUnprocessable();
        $otherHub=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Other City','province'=>'Laguna','is_operational'=>true]);
        $other=$this->person('hub_staff',$otherHub->id);$this->actingAs($other)->get(route('finance.evidence',$e->id))->assertForbidden();
        DB::table('users')->where('id',$this->rider->id)->update(['status'=>'approved']);
        $this->actingAs($this->rider->fresh())->patchJson(route('rider.deliveries.advance',$this->shipment->id),['from'=>'out_for_delivery','cod_amount'=>'1100','cod_confirmed'=>'1','collection_key'=>(string)Str::uuid()])->assertUnprocessable();
        $this->assertSame('ambiguous',app(AssignmentWork::class)->classify($this->assignment->fresh()));
        $pickup=DeliveryAssignment::where('shipment_id',$this->shipment->id)->where('leg','pickup')->firstOrFail();
        $this->assertSame('completed',app(AssignmentWork::class)->classify($pickup));
        $this->actingAs($this->staff)->get(route('logistics.issues'))->assertOk()->assertSee('Redispatch remains held');
    }
    public function test_ineligible_replacement_and_earned_sources_cannot_be_reassigned(): void
    {
        DB::table('users')->where('id',$this->replacement->id)->update(['vehicle_type'=>'four_wheels']);
        $this->actingAs($this->admin)->postJson(route('logistics.assignments.reassign',$this->assignment->id),$this->requestData())->assertUnprocessable();
        DB::table('users')->where('id',$this->replacement->id)->update(['vehicle_type'=>'two_wheels','status'=>'suspended']);
        $this->postJson(route('logistics.assignments.reassign',$this->assignment->id),$this->requestData())->assertUnprocessable();
        DB::table('users')->where('id',$this->replacement->id)->update(['status'=>'approved']);
        DB::table('logistics_compensations')->where('source_id',$this->assignment->id)->update(['status'=>'earned']);
        $this->postJson(route('logistics.assignments.reassign',$this->assignment->id),$this->requestData())->assertUnprocessable();
        $this->assertSame(0,$this->assignment->fresh()->revision);$this->assertNull($this->assignment->fresh()->reassignment);
    }

    public function test_reassignment_pages_render_scoped_possession_and_decision_fields(): void
    {
        $this->actingAs($this->admin)->get(route('logistics.assignments.review'))->assertOk()->assertSee('Replacement rider')->assertSee('Request possession review');
        $this->requestChange();
        $this->actingAs($this->seller)->get(route('seller.assignments.review'))->assertOk()->assertSee('I still physically hold this parcel')->assertSee('Cancellation reason');
        $this->actingAs($this->staff)->get(route('logistics.assignments.review'))->assertOk()->assertDontSee('I still physically hold this parcel');
        $foreign=$this->person('seller',null,'Other');$this->actingAs($foreign)->get(route('seller.assignments.review'))->assertOk()->assertDontSee($this->shipment->tracking_number);
    }

    public function test_historical_evidence_classification_never_rewrites_or_recognizes_earnings(): void
    {
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'sorted','seller_confirmed_pickup_at'=>now(),'rider_confirmed_pickup_at'=>now(),'picked_up_at'=>now(),'at_sorting_center_at'=>now()]);
        $before=$this->assignment->fresh()->getRawOriginal();
        $this->assertSame('completed',app(AssignmentWork::class)->classify($this->assignment->fresh()));
        $this->assertSame($before,$this->assignment->fresh()->getRawOriginal());
        $this->assertDatabaseHas('logistics_compensations',['source_id'=>$this->assignment->id,'status'=>'pending']);
        $this->delivery();
        DB::table('delivery_assignments')->where('id',$this->assignment->id)->update(['status'=>'delivered','delivered_at'=>now()]);
        $this->assertSame('ambiguous',app(AssignmentWork::class)->classify($this->assignment->fresh()));
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'delivered','delivered_at'=>now()]);
        $this->assertSame('completed',app(AssignmentWork::class)->classify($this->assignment->fresh()));
        $this->assertSame(0,DB::table('logistics_compensations')->where('status','earned')->count());
    }

    public function test_recovery_submission_rejects_active_holder_wrong_hub_and_rolls_back_audit_failure(): void
    {
        $c=$this->collected();$d=['key'=>(string)Str::uuid(),'rider_id'=>$this->rider->id,'allocations'=>[$c->id=>'1100'],'reason'=>'Supervised suspended cash recovery','reference'=>'CONTROLLED-RECOVERY'];
        $otherHub=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Other City','province'=>'Laguna','is_operational'=>true]);
        $other=$this->person('hub_staff',$otherHub->id);
        $this->actingAs($other)->postJson(route('logistics.cash-desk.recovery'),$d)->assertForbidden();
        DB::table('users')->where('id',$this->rider->id)->update(['status'=>'approved']);
        $this->actingAs($this->staff)->postJson(route('logistics.cash-desk.recovery'),$d)->assertForbidden();
        DB::table('users')->where('id',$this->rider->id)->update(['status'=>'suspended']);
        DB::statement("CREATE TRIGGER fail_recovery_submit BEFORE INSERT ON logistics_financial_events WHEN NEW.event='remittance_submitted' BEGIN SELECT RAISE(ABORT,'audit failed'); END");
        try { app(\App\Services\LogisticsCashDesk::class)->submitRecovery($this->staff,$d);$this->fail('Audit failure must abort recovery submission.'); } catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('audit failed',$e->getMessage()); }
        $this->assertSame(0,DB::table('logistics_cash_handovers')->count());
        $this->assertSame(0,DB::table('logistics_cash_allocations')->count());
        $this->assertSame(0,app(\App\Services\LogisticsCashDesk::class)->custody($this->hub->id));
        $this->assertSame(110000,app(\App\Services\LogisticsCashDesk::class)->balances($c)['outstanding']);
    }

    public function test_recovery_audit_failure_removes_rolled_back_proof_and_issue(): void
    {
        $this->delivery();$this->actingAs($this->rider)->patch(route('rider.deliveries.advance',$this->shipment->id),['from'=>'assigned_to_rider'])->assertRedirect();
        DB::table('users')->where('id',$this->rider->id)->update(['status'=>'suspended']);
        DB::statement("CREATE TRIGGER fail_parcel_recovery BEFORE INSERT ON logistics_financial_events WHEN NEW.event='parcel_recovery_received' BEGIN SELECT RAISE(ABORT,'audit failed'); END");
        $proof=\Illuminate\Http\UploadedFile::fake()->createWithContent('recovery.pdf',"%PDF-1.4\nRollback private recovery proof");
        $this->actingAs($this->staff)->post(route('logistics.issues.recovery'),$this->recoveryData()+['proof'=>$proof])->assertServerError();
        $this->assertSame(0,DB::table('logistics_issues')->count());
        $this->assertSame(0,DB::table('logistics_finance_evidence')->count());
        $this->assertSame([],Storage::disk('local')->allFiles('logistics-finance-proofs'));
        $this->assertSame('out_for_delivery',$this->shipment->fresh()->shipping_status);
    }

    public function test_recovered_transfer_cannot_be_resumed_by_reactivated_rider(): void
    {
        $dest=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Other City','province'=>'Laguna','is_operational'=>true]);
        $b=\App\Models\HubTransferBatch::create(['company_name'=>'Alpha','origin_hub_id'=>$this->hub->id,'destination_hub_id'=>$dest->id,'status'=>'in_transit','rider_id'=>$this->rider->id,'requested_by'=>$this->staff->id,'requested_at'=>now(),'dispatched_at'=>now()]);
        $leg=\App\Models\ShipmentHubLeg::create(['shipment_id'=>$this->shipment->id,'sequence'=>1,'from_hub'=>'Santa Cruz','from_province'=>'Laguna','to_hub'=>'Other City','to_province'=>'Laguna','status'=>'in_transit','rider_id'=>$this->rider->id,'transfer_batch_id'=>$b->id,'started_at'=>now()]);
        \App\Models\HubTransferMember::create(['batch_id'=>$b->id,'leg_id'=>$leg->id,'shipment_id'=>$this->shipment->id,'tracking_number'=>$this->shipment->tracking_number]);
        DeliveryAssignment::create(['shipment_id'=>$this->shipment->id,'courier_id'=>$this->rider->id,'leg'=>'hub_transfer','status'=>'hub_transfer','accepted_at'=>now()]);
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'hub_transfer','hub_transfer_rider_id'=>$this->rider->id]);
        DB::table('users')->where('id',$this->rider->id)->update(['status'=>'suspended']);
        $d=$this->recoveryData();$d['leg']='hub_transfer';
        $this->actingAs($this->staff)->postJson(route('logistics.issues.recovery'),$d)->assertRedirect();
        DB::table('users')->where('id',$this->rider->id)->update(['status'=>'approved']);
        $this->actingAs($this->rider->fresh())->patchJson('/rider/hub-transfers/'.$leg->id.'/confirm')->assertUnprocessable();
        $this->assertNull($leg->fresh()->rider_confirmed_pickup_at);
        $this->assertSame('in_transit',$b->fresh()->status);
        $this->assertSame(0,DB::table('logistics_compensations')->where('status','earned')->count());
    }

    public function test_normal_receipt_for_suspended_rider_reuses_existing_path(): void
    {
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'picked_up','seller_confirmed_pickup_at'=>now(),'rider_confirmed_pickup_at'=>now(),'picked_up_at'=>now()]);
        DB::table('users')->where('id',$this->rider->id)->update(['status'=>'suspended']);
        $d=$this->recoveryData();$d['leg']='pickup';
        $this->actingAs($this->staff)->postJson(route('logistics.issues.recovery'),$d)->assertUnprocessable();
        $this->patchJson('/logistics/scan/'.$this->shipment->id.'/receive')->assertOk();
        $this->assertSame('completed',$this->assignment->fresh()->status);
        $this->assertDatabaseHas('logistics_compensations',['source_id'=>$this->assignment->id,'rider_id'=>$this->rider->id,'status'=>'earned','amount_cents'=>2500]);
    }
}
