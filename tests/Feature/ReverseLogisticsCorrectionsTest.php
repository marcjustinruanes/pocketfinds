<?php

namespace Tests\Feature;

use App\Models\{LogisticsHub, LogisticsIssue, Shipment};
use App\Services\{LogisticsParcelNotifications, LogisticsRecovery, LogisticsRts, ParcelCustody, RiderDeliveryOffers};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReverseLogisticsCorrectionsTest extends OperationalRecoveryTest
{
    public function test_stabilization_manual_failure_persists_the_submitted_reason(): void
    {
        $this->onRoad();
        $reason='Recipient unavailable; verified by destination Hub Staff';
        $this->actingAs($this->staff)->patch($this->retryUrl(),['status'=>'delivery_failed','reason'=>$reason,'attempt_token'=>$this->shipment->fresh()->failedDeliveryToken()])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($reason,$this->shipment->fresh()->delivery_failed_reason);
        $this->assertDatabaseHas('order_status_history',['order_id'=>$this->shipment->order_id,'status'=>'delivery_failed','notes'=>$reason]);
    }

    public function test_stabilization_missing_return_receipt_event_blocks_release(): void
    {
        (require database_path('migrations/2026_10_29_000007_add_issue_rts_custody.php'))->up();
        $data=$this->onRoad();$this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $flow=app(LogisticsRts::class);$flow->act($this->staff,$this->shipment->id,$this->rtsData('request',0));
        $flow->act($this->rider,$this->shipment->id,$this->rtsData('to_hub',1));$issue=$flow->act($this->staff,$this->shipment->id,$this->rtsData('hub_received',2));
        DB::table('logistics_issue_events')->where('issue_id',$issue->id)->delete();
        $this->actingAs($this->staff)->postJson(route('logistics.rts',$this->shipment->id),$this->rtsData('hub_released',3))->assertUnprocessable();
        $this->assertSame('at_hub',$issue->fresh()->rts['state']);
        $this->assertSame(0,DB::table('logistics_issue_events')->where('issue_id',$issue->id)->count());
    }

    public function test_stabilization_missing_return_transport_event_blocks_seller_completion(): void
    {
        (require database_path('migrations/2026_10_29_000007_add_issue_rts_custody.php'))->up();
        $data=$this->onRoad();$this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $flow=app(LogisticsRts::class);$flow->act($this->staff,$this->shipment->id,$this->rtsData('request',0));
        $issue=$flow->act($this->rider,$this->shipment->id,$this->rtsData('to_seller',1));
        DB::table('logistics_issue_events')->where('issue_id',$issue->id)->delete();
        $this->actingAs($this->seller)->postJson(route('seller.orders.rts',$this->shipment->id),$this->rtsData('seller_received',2))->assertUnprocessable();
        $this->assertNull($this->shipment->fresh()->returned_at);
        $this->assertSame('delivery_failed',$this->shipment->fresh()->shipping_status);
    }

    public function test_stabilization_incomplete_legacy_return_renders_as_review_required(): void
    {
        (require database_path('migrations/2026_10_29_000007_add_issue_rts_custody.php'))->up();
        $data=$this->onRoad();$this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $issue=$this->issue('return_to_sender');$issue->update(['rts_shipment_id'=>$this->shipment->id,'rts'=>['revision'=>1]]);
        $this->actingAs($this->staff)->get('/logistics/issues')->assertOk()->assertSee('needs evidence review');
        $this->patchJson(route('logistics.issues.update',$issue->id),['status'=>'in_progress','revision'=>0])->assertConflict();
    }

    public function test_stabilization_unverified_legacy_seller_receipt_cannot_unlock_retry(): void
    {
        (require database_path('migrations/2026_10_29_000007_add_issue_rts_custody.php'))->up();
        $data=$this->onRoad();$this->coverage();$this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $issue=$this->issue('return_to_sender');$issue->update(['rts_shipment_id'=>$this->shipment->id,'rts'=>['state'=>'seller_received','revision'=>1,'actor_id'=>$this->seller->id]]);
        $this->actingAs($this->staff)->patchJson($this->retryUrl(),$this->retryData())->assertConflict();
        $this->assertSame('delivery_failed',$this->shipment->fresh()->shipping_status);
    }

    public function test_stabilization_completed_return_with_inconsistent_parcel_status_cannot_unlock_retry(): void
    {
        (require database_path('migrations/2026_10_29_000007_add_issue_rts_custody.php'))->up();
        $data=$this->onRoad();$this->coverage();$this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $flow=app(LogisticsRts::class);$flow->act($this->staff,$this->shipment->id,$this->rtsData('request',0));
        $flow->act($this->rider,$this->shipment->id,$this->rtsData('to_seller',1));$flow->act($this->seller,$this->shipment->id,$this->rtsData('seller_received',2));
        // Simulate inconsistent legacy persistence, without changing its existing witness.
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'delivery_failed','returned_at'=>null]);
        DB::table('orders')->where('id',$this->shipment->order_id)->update(['status'=>'delivery_failed']);
        $this->actingAs($this->staff)->patchJson($this->retryUrl(),$this->retryData())->assertConflict();
        $this->assertSame('delivery_failed',$this->shipment->fresh()->shipping_status);
    }

    public function test_stabilization_scanner_does_not_offer_assignment_for_a_held_parcel(): void
    {
        \Illuminate\Support\Facades\Schema::create('unserviceable_areas',function($table){$table->id();$table->string('municipality');$table->text('note')->nullable();});
        $this->onRoad();$this->coverage();
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'sorted','courier_id'=>null]);
        DB::table('orders')->where('id',$this->shipment->order_id)->update(['status'=>'sorted']);
        $this->issue('damaged');
        $this->actingAs($this->staff)->postJson('/logistics/scan/lookup',['code'=>$this->shipment->tracking_number])->assertOk()
            ->assertJsonPath('stage','none')->assertJsonCount(0,'area_riders')->assertJsonPath('custody.label','Parcel investigation hold');
    }

    public function test_stabilization_incomplete_recovery_record_renders_as_review_required(): void
    {
        $issue=$this->issue('other');$issue->update(['recovery'=>['shipment_ids'=>[$this->shipment->id]]]);
        $this->actingAs($this->staff)->get('/logistics/issues')->assertOk()->assertSee('Recovery custody needs evidence review');
        $this->assertSame(['shipment_ids'=>[$this->shipment->id]],$issue->fresh()->recovery);
        $issue->update(['recovery'=>['shipment_ids'=>[$this->shipment->id],'rider_id'=>$this->rider->id,'leg'=>'delivery',
            'hub_id'=>$this->hub->id,'received_by'=>$this->staff->id,'observed_at'=>now()->toIso8601String(),'recorded_at'=>now()->toIso8601String(),
            'condition'=>'Sealed fixture parcel','reference'=>'QA recovery reference']]);
        $this->get('/logistics/issues')->assertOk()->assertSee('Recovered custody')->assertSee('QA recovery reference')->assertDontSee('Recovery custody needs evidence review');
    }

    private function onRoad(int $attempts = 0): array
    {
        $this->delivery();
        DB::table('shipments')->where('id', $this->shipment->id)->update(['shipping_status'=>'out_for_delivery', 'out_for_delivery_at'=>now(), 'delivery_attempts'=>$attempts]);
        DB::table('orders')->where('id', $this->shipment->order_id)->update(['status'=>'out_for_delivery']);
        DB::table('delivery_assignments')->where('id', $this->assignment->id)->update(['status'=>'out_for_delivery']);
        return ['reason'=>'buyer_not_home', 'attempt_token'=>$this->shipment->fresh()->failedDeliveryToken()];
    }

    private function failUrl(): string { return route('rider.deliveries.failed', $this->shipment->id); }
    private function retryUrl(): string { return route('logistics.status.update', $this->shipment->id); }
    private function retryData(): array { return ['status'=>'assigned_to_rider', 'reason'=>'Buyer confirmed another delivery attempt']; }

    private function coverage(): void
    {
        (require database_path('migrations/2026_11_11_000001_add_rider_barangay_routes.php'))->up();
        $order=$this->shipment->order;
        DB::table('orders')->where('id',$order->id)->update(['shipping_address'=>json_encode(array_merge($order->shipping_address,['barangay'=>'Central']))]);
        DB::table('users')->where('id',$this->rider->id)->update(['is_available'=>true]);
        DB::table('rider_barangay_routes')->insert(['rider_id'=>$this->rider->id,'hub_id'=>$this->hub->id,'barangay_code'=>'TEST001','barangay_name'=>'Central','barangay_key'=>'central','created_at'=>now(),'updated_at'=>now()]);
    }

    private function rtsData(string $action, int $revision): array
    {
        return ['action'=>$action,'revision'=>$revision,'key'=>(string)Str::uuid(),'reason'=>'Authenticated physical observation for fixture','confirmed'=>1];
    }

    private function issue(string $kind): LogisticsIssue
    {
        return LogisticsIssue::create(['shipment_id'=>$this->shipment->id,'company_name'=>'Alpha','hub_id'=>$this->hub->id,'reported_by'=>$this->admin->id,'kind'=>$kind,'description'=>'Synthetic parcel condition investigation','status'=>'open']);
    }

    public function test_reverse_refusal_and_exhaustion_do_not_assert_seller_receipt(): void
    {
        $data=$this->onRoad(2);$data['reason']='refused';
        $this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('shipments',['id'=>$this->shipment->id,'shipping_status'=>'delivery_failed','delivery_attempts'=>3,'returned_at'=>null]);
        $this->assertDatabaseHas('orders',['id'=>$this->shipment->order_id,'status'=>'delivery_failed']);
        $this->assertSame(0,DB::table('order_status_history')->where('status','returned')->count());
        $this->assertSame(0,DB::table('logistics_financial_transactions')->where('kind','cod_collection')->count());
        $this->actingAs($this->staff)->patchJson($this->retryUrl(),$this->retryData())->assertUnprocessable();
        $this->get('/logistics/issues')->assertOk()->assertSee('Return custody review required')->assertDontSee('Retry Delivery');
    }

    public function test_reverse_duplicate_and_conflicting_failure_requests_have_one_effect(): void
    {
        $data=$this->onRoad();
        $this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $notifications=DB::table('notifications')->count();
        $this->patchJson($this->failUrl(),$data)->assertConflict();
        $data['reason']='refused';$this->patchJson($this->failUrl(),$data)->assertConflict();
        $this->assertSame(1,$this->shipment->fresh()->delivery_attempts);
        $this->assertSame('buyer_not_home',$this->shipment->fresh()->delivery_failed_reason);
        $this->assertSame(1,DB::table('order_status_history')->where('status','delivery_failed')->count());
        $this->assertSame($notifications,DB::table('notifications')->count());
    }

    public function test_reverse_old_form_cannot_fail_a_later_attempt(): void
    {
        $data=$this->onRoad();$this->coverage();
        $this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $response=$this->actingAs($this->staff)->patch($this->retryUrl(),$this->retryData());
        // A successful PATCH redirects with GET semantics for the workstation scanner.
        $this->assertSame(303,$response->status(),$response->getContent());
        $response->assertSessionHasNoErrors();
        $this->assertSame('assigned_to_rider',$this->shipment->fresh()->shipping_status);
        $this->actingAs($this->rider)->patch(route('rider.deliveries.advance',$this->shipment->id),['from'=>'assigned_to_rider'])->assertRedirect()->assertSessionHasNoErrors();
        $this->patchJson($this->failUrl(),$data)->assertConflict();
        $this->assertSame('out_for_delivery',$this->shipment->fresh()->shipping_status);
        $this->assertSame(1,$this->shipment->fresh()->delivery_attempts);
    }

    public function test_reverse_failure_validation_and_wrong_rider_do_not_mutate(): void
    {
        $data=$this->onRoad();
        $this->actingAs($this->rider)->get(route('rider.deliveries.show',$this->shipment->id))->assertOk()->assertSee('name="attempt_token"',false)->assertSee($data['attempt_token']);
        $this->actingAs($this->replacement)->patchJson($this->failUrl(),$data)->assertForbidden();
        $this->actingAs($this->rider)->patchJson($this->failUrl(),['reason'=>'refused'])->assertUnprocessable();
        $this->patchJson($this->failUrl(),array_merge($data,['reason'=>'invalid']))->assertUnprocessable();
        $this->assertSame(0,$this->shipment->fresh()->delivery_attempts);
        $this->assertSame('out_for_delivery',$this->shipment->fresh()->shipping_status);
        DB::table('users')->where('id',$this->rider->id)->update(['status'=>'suspended']);$this->rider->refresh();
        $this->patchJson($this->failUrl(),$data)->assertRedirect(route('login'))->assertSessionHasErrors('email');
    }

    public function test_reverse_retry_reuses_barangay_online_and_vehicle_eligibility(): void
    {
        $data=$this->onRoad();$this->coverage();
        $this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        DB::table('users')->where('id',$this->rider->id)->update(['is_available'=>false]);
        $this->actingAs($this->staff)->patchJson($this->retryUrl(),$this->retryData())->assertUnprocessable();
        DB::table('users')->where('id',$this->rider->id)->update(['is_available'=>true,'vehicle_type'=>'four_wheels']);
        $this->patchJson($this->retryUrl(),$this->retryData())->assertUnprocessable();
        DB::table('users')->where('id',$this->rider->id)->update(['vehicle_type'=>'two_wheels']);
        DB::table('rider_barangay_routes')->update(['barangay_key'=>'another barangay']);
        $this->patchJson($this->retryUrl(),$this->retryData())->assertUnprocessable();
        DB::table('rider_barangay_routes')->update(['barangay_key'=>'central']);
        $this->patch($this->retryUrl(),$this->retryData())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('assigned_to_rider',$this->shipment->fresh()->shipping_status);
        $this->assertSame($this->rider->id,$this->assignment->fresh()->courier_id);
    }

    public function test_reverse_retry_capacity_counts_pickup_and_delivery_together(): void
    {
        $data=$this->onRoad();$this->coverage();
        $this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        foreach(['ready_for_pickup','picked_up','out_for_delivery'] as $state){
            DB::table('shipments')->insert(['id'=>(string)Str::uuid(),'tracking_number'=>'CAP-'.Str::uuid(),'order_id'=>$this->shipment->order_id,'logistics_company'=>'Alpha','shipping_status'=>$state,'courier_id'=>$state==='out_for_delivery'?$this->rider->id:null,'pickup_rider_id'=>$state==='out_for_delivery'?null:$this->rider->id]);
        }
        $this->actingAs($this->staff)->patchJson($this->retryUrl(),$this->retryData())->assertUnprocessable();
        $this->assertSame('delivery_failed',$this->shipment->fresh()->shipping_status);
        $this->assertSame(3,app(RiderDeliveryOffers::class)->activeJobs($this->rider->id));
    }

    public function test_reverse_failure_notifications_are_scoped_and_repeat_safe(): void
    {
        $data=$this->onRoad();
        $otherHub=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Pila','province'=>'Laguna','is_operational'=>true]);
        $wrong=$this->person('hub_staff',$otherHub->id);$foreign=$this->person('admin',null,'Beta');$pending=$this->person('hub_staff');$pending->update(['status'=>'pending']);
        $this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $alerts=DB::table('notifications')->where('notification_type','delivery_failed');
        $this->assertEqualsCanonicalizing([$this->admin->id,$this->staff->id,$this->seller->id,$this->shipment->order->buyer_id],(clone $alerts)->pluck('user_id')->all());
        $this->assertSame(0,(clone $alerts)->whereIn('user_id',[$wrong->id,$foreign->id,$pending->id])->count());
        $count=(clone $alerts)->count();$event=DB::table('order_status_history')->where('status','delivery_failed')->value('id');
        app(LogisticsParcelNotifications::class)->failed($this->shipment->fresh(),$event);
        $this->assertSame($count,(clone $alerts)->count());
        $id=(clone $alerts)->where('user_id',$this->staff->id)->value('id');
        $this->actingAs($this->staff)->get(route('account.notifications.open',$id))->assertRedirect(route('logistics.issues',['shipment'=>$this->shipment->id]).'#parcel-'.$this->shipment->id);
        $this->get(route('logistics.issues',['shipment'=>$this->shipment->id]))->assertOk()->assertSee('parcel-'.$this->shipment->id);
        $sellerId=(clone $alerts)->where('user_id',$this->seller->id)->value('id');
        $this->actingAs($this->seller)->get(route('seller.notifications.open',$sellerId))->assertRedirect(route('seller.orders',['q'=>$this->shipment->order->order_number]));
        $this->actingAs($wrong)->get(route('account.notifications.open',$id))->assertNotFound();
    }

    public function test_reverse_notification_target_rechecks_changed_hub_before_marking_read(): void
    {
        $data=$this->onRoad();$this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $id=DB::table('notifications')->where('user_id',$this->staff->id)->where('notification_type','delivery_failed')->value('id');
        $other=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Pila','province'=>'Laguna','is_operational'=>true]);
        $this->staff->update(['logistics_hub_id'=>$other->id]);
        $this->actingAs($this->staff)->get(route('account.notifications.open',$id))->assertForbidden();
        $this->assertDatabaseHas('notifications',['id'=>$id,'is_read'=>false]);
    }

    public function test_reverse_lost_damaged_and_recovered_holds_cannot_be_cleared_by_status_only(): void
    {
        $data=$this->onRoad();$this->coverage();$this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $issue=$this->issue('lost');
        $this->actingAs($this->staff)->patchJson($this->retryUrl(),$this->retryData())->assertUnprocessable();
        $issue->update(['status'=>'resolved','investigation'=>['outcome'=>'escalated']]);
        $this->patchJson($this->retryUrl(),$this->retryData())->assertUnprocessable();
        $issue->update(['kind'=>'damaged','investigation'=>['outcome'=>'damage_documented']]);
        $this->patchJson($this->retryUrl(),$this->retryData())->assertUnprocessable();
        $issue->update(['kind'=>'other','recovery'=>['shipment_ids'=>[$this->shipment->id]]]);
        $this->patchJson($this->retryUrl(),$this->retryData())->assertUnprocessable();
        $this->assertSame('Recovery hold',app(ParcelCustody::class)->describe($this->shipment->fresh(),$this->staff)['label']);
    }

    public function test_reverse_rts_custody_requires_matching_events_and_preserves_final_receipt(): void
    {
        (require database_path('migrations/2026_10_29_000007_add_issue_rts_custody.php'))->up();
        $data=$this->onRoad();$this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $flow=app(LogisticsRts::class);$view=app(ParcelCustody::class);
        $flow->act($this->staff,$this->shipment->id,$this->rtsData('request',0));
        $this->assertSame('RTS custody review',$view->describe($this->shipment->fresh(),$this->staff)['label']);
        $flow->act($this->rider,$this->shipment->id,$this->rtsData('to_hub',1));
        $this->assertSame('Return transport to hub',$view->describe($this->shipment->fresh(),$this->staff)['label']);
        $flow->act($this->staff,$this->shipment->id,$this->rtsData('hub_received',2));
        $this->assertSame('Received at return hub',$view->describe($this->shipment->fresh(),$this->staff)['label']);
        $flow->act($this->staff,$this->shipment->id,$this->rtsData('hub_released',3));
        $this->assertSame('Awaiting return rider pickup',$view->describe($this->shipment->fresh(),$this->staff)['label']);
        $flow->act($this->rider,$this->shipment->id,$this->rtsData('to_seller',4));
        $last=$this->rtsData('seller_received',5);$issue=$flow->act($this->seller,$this->shipment->id,$last);
        $this->assertSame('Seller receipt confirmed',$view->describe($this->shipment->fresh(),$this->staff)['label']);
        $buyer=\App\Models\User::findOrFail($this->shipment->order->buyer_id);
        $alert=DB::table('notifications')->where('user_id',$buyer->id)->where('notification_type','rts_custody')->first();
        $this->assertNotNull($alert);
        $this->actingAs($buyer)->get(route('buyer.notifications.open',$alert->id))->assertRedirect(route('buyer.orders'));
        $riderAlert=DB::table('notifications')->where('user_id',$this->rider->id)->where('notification_type','rts_custody')->first();
        $this->actingAs($this->rider)->get(route('account.notifications.open',$riderAlert->id))->assertRedirect(route('rider.deliveries.show',$this->shipment->id));
        $count=DB::table('notifications')->where('notification_type','rts_custody')->count();
        $flow->act($this->seller,$this->shipment->id,$last);
        $this->assertSame($count,DB::table('notifications')->where('notification_type','rts_custody')->count());
        $this->assertSame(6,DB::table('logistics_issue_events')->where('issue_id',$issue->id)->count());
        DB::table('logistics_issue_events')->where('issue_id',$issue->id)->delete();
        $this->assertSame('RTS custody review',$view->describe($this->shipment->fresh(),$this->staff)['label']);
    }

    public function test_reverse_damaged_parcel_can_be_received_at_hub_but_not_released_or_restocked(): void
    {
        (require database_path('migrations/2026_10_29_000007_add_issue_rts_custody.php'))->up();
        $data=$this->onRoad();$this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->issue('damaged');$flow=app(LogisticsRts::class);
        $flow->act($this->staff,$this->shipment->id,$this->rtsData('request',0));$flow->act($this->rider,$this->shipment->id,$this->rtsData('to_hub',1));$flow->act($this->staff,$this->shipment->id,$this->rtsData('hub_received',2));
        $this->actingAs($this->staff)->postJson(route('logistics.rts',$this->shipment->id),$this->rtsData('hub_released',3))->assertUnprocessable();
        $this->assertSame('at_hub',LogisticsIssue::where('rts_shipment_id',$this->shipment->id)->firstOrFail()->rts['state']);
        $this->assertNull($this->shipment->fresh()->returned_at);
    }

    public function test_reverse_failure_audit_error_rolls_back_attempt_history_and_notifications(): void
    {
        $data=$this->onRoad();$start=DB::table('notifications')->count();
        DB::unprepared("CREATE TRIGGER failed_attempt_audit BEFORE INSERT ON logistics_activity_logs BEGIN SELECT RAISE(ABORT, 'failed attempt audit'); END");
        $this->withoutExceptionHandling();
        try{$this->actingAs($this->rider)->patch($this->failUrl(),$data);$this->fail('Audit failure was ignored');}
        catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('failed attempt audit',$e->getMessage());}
        $this->assertSame('out_for_delivery',$this->shipment->fresh()->shipping_status);$this->assertSame(0,$this->shipment->fresh()->delivery_attempts);
        $this->assertSame(0,DB::table('order_status_history')->where('status','delivery_failed')->count());$this->assertSame($start,DB::table('notifications')->count());
    }

    public function test_reverse_condition_hold_allows_actual_origin_receipt_without_automatic_delivery_offer(): void
    {
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'picked_up','seller_confirmed_pickup_at'=>now()->subMinutes(3),'rider_confirmed_pickup_at'=>now()->subMinutes(3),'picked_up_at'=>now()->subMinutes(2)]);
        DB::table('orders')->where('id',$this->shipment->order_id)->update(['status'=>'picked_up']);
        $this->issue('damaged');
        $this->actingAs($this->staff)->patchJson('/logistics/scan/'.$this->shipment->id.'/receive')->assertOk();
        $this->assertSame('sorted',$this->shipment->fresh()->shipping_status);
        $this->assertNotNull($this->shipment->fresh()->at_sorting_center_at);
        $this->assertSame(0,DB::table('delivery_assignments')->where('shipment_id',$this->shipment->id)->where('leg','delivery')->count());
        $this->assertDatabaseHas('logistics_compensations',['source_id'=>$this->assignment->id,'amount_cents'=>3000,'status'=>'earned']);
    }

    public function test_reverse_ui_hides_cross_hub_transport_and_held_release_actions(): void
    {
        (require database_path('migrations/2026_10_29_000007_add_issue_rts_custody.php'))->up();
        $data=$this->onRoad();$this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        DB::table('shipments')->where('id',$this->shipment->id)->update(['origin_hub'=>'Pila']);
        $flow=app(LogisticsRts::class);$flow->act($this->staff,$this->shipment->id,$this->rtsData('request',0));
        $this->actingAs($this->rider);
        $this->view('logistics.partials.rts-custody',['shipment'=>$this->shipment->fresh()])->assertSee('value="to_hub"',false)->assertDontSee('value="to_seller"',false);
        $flow->act($this->rider,$this->shipment->id,$this->rtsData('to_hub',1));$flow->act($this->staff,$this->shipment->id,$this->rtsData('hub_received',2));
        $this->issue('damaged');$this->actingAs($this->staff)->get('/logistics/issues')->assertOk()->assertSee('Parcel hold:')->assertDontSee('value="hub_released"',false)->assertDontSee('Retry Delivery');
        $other=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Pila','province'=>'Laguna','is_operational'=>true]);$wrong=$this->person('hub_staff',$other->id);
        $this->actingAs($wrong);
        $this->view('logistics.partials.rts-custody',['shipment'=>$this->shipment->fresh()])->assertDontSee('value="hub_received"',false)->assertDontSee('value="hub_released"',false);
    }

    public function test_reverse_missing_legacy_rts_state_requires_review_before_retry(): void
    {
        (require database_path('migrations/2026_10_29_000007_add_issue_rts_custody.php'))->up();
        $data=$this->onRoad();$this->coverage();$this->actingAs($this->rider)->patch($this->failUrl(),$data)->assertRedirect()->assertSessionHasNoErrors();
        $issue=$this->issue('return_to_sender');$issue->update(['rts_shipment_id'=>$this->shipment->id]);
        $this->actingAs($this->staff)->patchJson($this->retryUrl(),$this->retryData())->assertConflict();
        $this->assertSame('RTS custody review',app(ParcelCustody::class)->describe($this->shipment->fresh(),$this->staff)['label']);
        $this->assertSame(0,DB::table('logistics_issue_events')->where('issue_id',$issue->id)->count());
    }
}
