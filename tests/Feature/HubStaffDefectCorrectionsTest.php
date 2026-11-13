<?php

namespace Tests\Feature;

use App\Models\{CompanyVehicle, LogisticsHub, ShipmentHubLeg};
use Illuminate\Support\Facades\{DB, Storage};
use PHPUnit\Framework\Attributes\DataProvider;

class HubStaffDefectCorrectionsTest extends OperationalRecoveryTest
{
    private function proofUrl(): string
    {
        Storage::disk('sensitive_documents')->put('delivery_proofs/hub-qa.png', 'Private fixture');
        $this->shipment->update(['delivery_proof_file'=>'delivery_proofs/hub-qa.png']);

        return route('delivery-proofs.show',$this->shipment->id);
    }

    public function test_private_proof_preserves_admin_participant_and_route_hub_access(): void
    {
        $url=$this->proofUrl();
        foreach ([$this->admin,$this->staff,$this->seller] as $actor) $this->actingAs($actor)->get($url)->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $destination=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Pila','province'=>'Laguna','is_operational'=>true]);
        $middle=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Pagsanjan','province'=>'Laguna','is_operational'=>true]);
        DB::table('shipments')->where('id',$this->shipment->id)->update(['destination_hub'=>'Pila']);
        ShipmentHubLeg::create(['shipment_id'=>$this->shipment->id,'sequence'=>1,'leg_type'=>'direct','from_hub'=>'Santa Cruz','from_province'=>'Laguna','to_hub'=>'Pagsanjan','to_province'=>'Laguna','status'=>'completed']);
        foreach ([$destination,$middle] as $hub) $this->actingAs($this->person('hub_staff',$hub->id))->get($url)->assertOk();
    }

    public function test_private_proof_denies_unrelated_same_named_foreign_closed_and_revoked_staff(): void
    {
        $url=$this->proofUrl();
        foreach ([['Alpha','Pila','Laguna'],['Alpha','Santa Cruz','Quezon'],['Other','Santa Cruz','Laguna']] as [$company,$town,$province]) {
            $hub=LogisticsHub::create(['company_name'=>$company,'municipality'=>$town,'province'=>$province,'is_operational'=>true]);
            $this->actingAs($this->person('hub_staff',$hub->id,$company))->get($url)->assertNotFound();
        }
        DB::table('logistics_hubs')->where('id',$this->hub->id)->update(['is_operational'=>false]);
        $this->actingAs($this->staff)->get($url)->assertNotFound();
        DB::table('logistics_hubs')->where('id',$this->hub->id)->update(['is_operational'=>true]);
        DB::table('users')->where('id',$this->staff->id)->update(['status'=>'suspended']);
        $this->actingAs($this->staff)->get($url)->assertNotFound();
    }

    private function onRoad(): void
    {
        $this->delivery();
        $this->actingAs($this->rider)->patch(route('rider.deliveries.advance',$this->shipment->id),['from'=>'assigned_to_rider'])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function failureData(): array
    {
        return ['status'=>'delivery_failed','reason'=>'Recipient unavailable during actual delivery','attempt_token'=>$this->shipment->fresh()->failedDeliveryToken()];
    }

    public function test_staff_failure_counts_once_and_preserves_custody_and_financial_snapshots(): void
    {
        $this->onRoad();$data=$this->failureData();$snapshots=DB::table('logistics_compensations')->orderBy('id')->get()->toJson();
        $this->actingAs($this->staff)->patchJson(route('logistics.status.update',$this->shipment->id),$data)->assertStatus(303)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shipments',['id'=>$this->shipment->id,'shipping_status'=>'delivery_failed','delivery_attempts'=>1,'courier_id'=>$this->rider->id,'returned_at'=>null]);
        $this->assertSame('out_for_delivery',$this->assignment->fresh()->status);
        $this->assertSame($snapshots,DB::table('logistics_compensations')->orderBy('id')->get()->toJson());
        $this->assertSame(0,DB::table('logistics_financial_transactions')->count());
        $notifications=DB::table('notifications')->count();
        $this->patchJson(route('logistics.status.update',$this->shipment->id),$data)->assertConflict();
        $this->assertSame(1,DB::table('order_status_history')->where('status','delivery_failed')->count());
        $this->assertSame($notifications,DB::table('notifications')->count());
    }

    public function test_staff_can_record_existing_precollection_failure_without_inventing_handover(): void
    {
        $this->delivery();
        $this->actingAs($this->staff)->patchJson(route('logistics.status.update',$this->shipment->id),$this->failureData())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1,(int)$this->shipment->fresh()->delivery_attempts);
        $this->assertNull($this->shipment->fresh()->out_for_delivery_at);
        $this->assertSame('assigned_to_rider',$this->assignment->fresh()->status);
    }

    public function test_third_staff_recorded_failure_blocks_retry_without_returning_or_settling(): void
    {
        $this->onRoad();
        for ($attempt=1;$attempt<=3;$attempt++) {
            $this->actingAs($this->staff)->patchJson(route('logistics.status.update',$this->shipment->id),$this->failureData())->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($attempt,(int)$this->shipment->fresh()->delivery_attempts);
            $response=$this->patchJson(route('logistics.status.update',$this->shipment->id),['status'=>'assigned_to_rider','reason'=>'Buyer confirmed another delivery attempt']);
            if ($attempt===3) $response->assertUnprocessable();
            else {
                $response->assertRedirect()->assertSessionHasNoErrors();
                $this->actingAs($this->rider)->patch(route('rider.deliveries.advance',$this->shipment->id),['from'=>'assigned_to_rider'])->assertRedirect()->assertSessionHasNoErrors();
            }
        }
        $this->assertSame('delivery_failed',$this->shipment->fresh()->shipping_status);
        $this->assertNull($this->shipment->fresh()->returned_at);
        $this->assertSame(3,DB::table('order_status_history')->where('status','delivery_failed')->count());
        $this->assertSame(0,DB::table('logistics_financial_transactions')->count());
        $html=$this->actingAs($this->staff)->get(route('logistics.monitor'))->assertOk()->getContent();
        $this->assertStringNotContainsString('action="'.route('logistics.status.update',$this->shipment->id).'"',$html);
    }

    public function test_old_staff_failure_token_cannot_consume_a_later_attempt(): void
    {
        $this->onRoad();$old=$this->failureData();
        $this->actingAs($this->staff)->patchJson(route('logistics.status.update',$this->shipment->id),$old)->assertRedirect()->assertSessionHasNoErrors();
        $this->patchJson(route('logistics.status.update',$this->shipment->id),['status'=>'assigned_to_rider','reason'=>'Buyer confirmed a fresh delivery attempt'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->rider)->patch(route('rider.deliveries.advance',$this->shipment->id),['from'=>'assigned_to_rider'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->patchJson(route('logistics.status.update',$this->shipment->id),$old)->assertConflict();
        $this->assertSame(1,(int)$this->shipment->fresh()->delivery_attempts);
        $this->assertSame('out_for_delivery',$this->shipment->fresh()->shipping_status);
    }

    public function test_failure_requires_valid_token_and_current_assignment_evidence(): void
    {
        $this->onRoad();$data=$this->failureData();
        $this->actingAs($this->staff)->patchJson(route('logistics.status.update',$this->shipment->id),array_diff_key($data,['attempt_token'=>true]))->assertUnprocessable();
        $this->patchJson(route('logistics.status.update',$this->shipment->id),array_replace($data,['attempt_token'=>str_repeat('0',64)]))->assertConflict();
        DB::table('delivery_assignments')->where('id',$this->assignment->id)->update(['status'=>'cancelled']);
        $this->patchJson(route('logistics.status.update',$this->shipment->id),$data)->assertConflict();
        $this->assertSame(0,(int)$this->shipment->fresh()->delivery_attempts);
        $this->assertSame(0,DB::table('order_status_history')->where('status','delivery_failed')->count());
    }

    public function test_failure_audit_error_rolls_back_counter_history_notifications_and_status(): void
    {
        $this->onRoad();$notifications=DB::table('notifications')->count();
        DB::unprepared("CREATE TRIGGER fail_staff_failure BEFORE INSERT ON logistics_activity_logs WHEN NEW.action='logistics.status.update' BEGIN SELECT RAISE(ABORT,'audit unavailable'); END");
        $this->actingAs($this->staff)->patchJson(route('logistics.status.update',$this->shipment->id),$this->failureData())->assertServerError();
        $this->assertSame(0,(int)$this->shipment->fresh()->delivery_attempts);
        $this->assertSame('out_for_delivery',$this->shipment->fresh()->shipping_status);
        $this->assertSame(0,DB::table('order_status_history')->where('status','delivery_failed')->count());
        $this->assertSame($notifications,DB::table('notifications')->count());
    }

    private function vehicle(array $attributes=[]): CompanyVehicle
    {
        return CompanyVehicle::create(array_replace(['company_name'=>'Alpha','logistics_hub_id'=>$this->hub->id,'vehicle_type'=>'two_wheels','platform_status'=>'approved','status'=>'approved','is_available'=>true,'plate_number'=>'HUB-'.bin2hex(random_bytes(4))],$attributes));
    }

    public function test_explicit_vehicle_condition_is_idempotent_in_both_directions(): void
    {
        $vehicle=$this->vehicle();$url=route('logistics.hub.vehicles.toggle',$vehicle->id);
        foreach ([0,0,1,1] as $target) {
            $this->actingAs($this->staff)->patch($url,['is_available'=>$target])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame((bool)$target,$vehicle->fresh()->is_available);
        }
        $this->assertSame('approved',$vehicle->fresh()->platform_status);
        $this->assertSame('approved',$vehicle->fresh()->status);
        $this->patchJson($url,[])->assertUnprocessable();
        $this->patchJson($url,['is_available'=>'invalid'])->assertUnprocessable();
        $this->assertTrue($vehicle->fresh()->is_available);
    }

    public static function invalidVehicles(): array { return ['company'=>['company'],'hub'=>['hub'],'pending'=>['pending']]; }

    #[DataProvider('invalidVehicles')]
    public function test_vehicle_scope_and_platform_approval_cannot_be_bypassed(string $case): void
    {
        $attributes=[];
        if ($case==='company') $attributes['company_name']='Other';
        if ($case==='hub') $attributes['logistics_hub_id']=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Pila','province'=>'Laguna','is_operational'=>true])->id;
        if ($case==='pending') $attributes['platform_status']='pending';
        $vehicle=$this->vehicle($attributes);
        $this->actingAs($this->staff)->patchJson(route('logistics.hub.vehicles.toggle',$vehicle->id),['is_available'=>false])->assertNotFound();
        $this->assertTrue($vehicle->fresh()->is_available);
        if ($case==='company') $this->get(route('logistics.hub.vehicles'))->assertOk()->assertDontSee($vehicle->plate_number);
    }

    public function test_vehicle_update_rechecks_approval_and_rolls_back_on_activity_failure(): void
    {
        $vehicle=$this->vehicle();$url=route('logistics.hub.vehicles.toggle',$vehicle->id);
        $this->actingAs($this->admin)->patchJson($url,['is_available'=>false])->assertForbidden();
        DB::unprepared("CREATE TRIGGER fail_vehicle_audit BEFORE INSERT ON logistics_activity_logs WHEN NEW.action='logistics.hub.vehicles.toggle' BEGIN SELECT RAISE(ABORT,'audit unavailable'); END");
        $this->actingAs($this->staff)->patchJson($url,['is_available'=>false])->assertServerError();
        $this->assertTrue($vehicle->fresh()->is_available);
        DB::table('users')->where('id',$this->staff->id)->update(['status'=>'suspended']);
        $this->actingAs($this->staff->fresh())->patchJson($url,['is_available'=>false])->assertForbidden();
        $this->assertTrue($vehicle->fresh()->is_available);
    }

    public function test_monitor_hides_wrong_destination_actions_but_preserves_route_visibility_and_admin(): void
    {
        $this->onRoad();$other=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Pila','province'=>'Laguna','is_operational'=>true]);
        DB::table('shipments')->where('id',$this->shipment->id)->update(['destination_hub'=>'Pila']);
        $url=route('logistics.status.update',$this->shipment->id);$data=$this->failureData();
        $html=$this->actingAs($this->staff)->get(route('logistics.monitor'))->assertOk()->assertSee($this->shipment->tracking_number)->getContent();
        $this->assertStringNotContainsString('action="'.$url.'"',$html);
        $this->patchJson($url,$data)->assertForbidden();
        foreach ([$this->admin,$this->person('hub_staff',$other->id)] as $actor) {
            $html=$this->actingAs($actor)->get(route('logistics.monitor'))->assertOk()->getContent();
            $this->assertStringContainsString('action="'.$url.'"',$html);
            $this->assertStringContainsString('name="attempt_token"',$html);
        }
    }

    public function test_staff_pickup_alert_links_to_existing_hub_page_and_admin_keeps_review_link(): void
    {
        DB::table('shipments')->where('id',$this->shipment->id)->update(['pickup_approved_at'=>null,'pickup_rider_id'=>null]);
        foreach ([$this->staff,$this->admin] as $actor) {
            DB::table('users')->where('id',$actor->id)->update(['notify_new_requests'=>true]);
            $html=$this->actingAs($actor->fresh())->get(route('logistics.dashboard'))->assertOk()->getContent();
            if ($actor->isHubStaff()) {
                $this->assertStringNotContainsString('href="'.route('logistics.requests').'"',$html);
                $this->assertStringContainsString('awaiting Logistics Admin approval.',$html);
                $this->get(route('logistics.requests'))->assertForbidden();
            } else $this->assertStringContainsString('href="'.route('logistics.requests').'"',$html);
        }
        DB::table('users')->where('id',$this->staff->id)->update(['notify_new_requests'=>false]);
        $this->actingAs($this->staff->fresh())->get(route('logistics.dashboard'))->assertOk()->assertDontSee('awaiting Logistics Admin approval.');
    }
}
