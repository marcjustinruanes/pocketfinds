<?php

namespace Tests\Feature;

use App\Models\CompanyVehicle;
use App\Models\HubTransferBatch;
use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentHubLeg;
use App\Models\User;
use App\Services\HubTransferWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class HubTransferWorkflowTest extends TestCase
{
    use WorkflowSchema;

    private LogisticsHub $origin;

    private LogisticsHub $destination;

    private User $admin;

    private User $sender;

    private User $receiver;

    private User $rider;

    private array $parcels = [];

    private array $legs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        Mail::fake();
        Http::preventStrayRequests();
        $this->origin = $this->hub('Alpha', 'Santa Cruz', 'Laguna');
        $this->destination = $this->hub('Alpha', 'Santa Cruz', 'Marinduque');
        $this->admin = $this->user('logistics_admin', $this->origin);
        $this->sender = $this->user('hub_staff', $this->origin);
        $this->receiver = $this->user('hub_staff', $this->destination);
        $this->rider = $this->user('rider', $this->origin);
        for ($i = 0; $i < 2; $i++) {
            [$this->parcels[$i], $this->legs[$i]] = $this->parcel($this->origin, $this->destination);
        }
    }

    private function hub(string $company, string $town, string $province): LogisticsHub
    {
        return LogisticsHub::create(['company_name' => $company, 'municipality' => $town, 'province' => $province, 'is_operational' => true]);
    }

    private function user(string $role, LogisticsHub $hub): User
    {
        $name = $role.'_'.bin2hex(random_bytes(4));

        return User::create(['account_type' => in_array($role, ['logistics_admin', 'hub_staff']) ? 'logistics' : $role, 'username' => $name, 'given_names' => 'Fixture', 'last_name' => 'User', 'email' => $name.'@example.test', 'password' => 'TestPassword123', 'status' => 'approved', 'is_admin' => $role === 'admin', 'is_logistics' => in_array($role, ['logistics_admin', 'hub_staff']), 'logistics_role' => $role === 'logistics_admin' ? 'admin' : ($role === 'hub_staff' ? 'hub_staff' : null), 'business_name' => $hub->company_name, 'logistics_hub_id' => $hub->id, 'vehicle_type' => 'four_wheels', 'vehicle_ownership' => 'own']);
    }

    private function parcel(LogisticsHub $from, LogisticsHub $to): array
    {
        $order = Order::create(['status' => 'at_sorting_center', 'items' => []]);
        $shipment = Shipment::create(['order_id' => $order->id, 'logistics_company' => $from->company_name, 'origin_hub' => $from->municipality, 'origin_province' => $from->province, 'destination_hub' => $to->municipality, 'destination_province' => $to->province, 'tracking_number' => 'TRACK-'.bin2hex(random_bytes(4)), 'shipping_status' => 'at_sorting_center']);
        $leg = ShipmentHubLeg::create(['shipment_id' => $shipment->id, 'sequence' => 1, 'leg_type' => 'direct', 'from_hub' => $from->municipality, 'from_province' => $from->province, 'to_hub' => $to->municipality, 'to_province' => $to->province, 'status' => 'pending']);

        return [$shipment, $leg];
    }

    private function requestBatch(): HubTransferBatch
    {
        $response = $this->actingAs($this->sender)->postJson('/logistics/transfers', ['origin_hub_id' => $this->origin->id, 'destination_hub_id' => $this->destination->id, 'parcel_ids' => array_map(fn ($s) => $s->id, $this->parcels), 'company_name' => 'Beta', 'requested_by' => $this->admin->id, 'status' => 'approved'])->assertOk();

        return HubTransferBatch::findOrFail($response->json('batch_id'));
    }

    private function approved(): HubTransferBatch
    {
        $batch = $this->requestBatch();
        $this->actingAs($this->admin)->patchJson('/logistics/transfers/'.$batch->id.'/approve')->assertOk();

        return $batch->fresh();
    }

    private function dispatched(): HubTransferBatch
    {
        $batch = $this->approved();
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertOk();
        foreach ($this->legs as $leg) {
            $this->actingAs($this->rider)->patch('/rider/hub-transfers/'.$leg->id.'/confirm')->assertRedirect();
        }

        return $batch->fresh();
    }

    public function test_request_approval_dispatch_scanning_and_receipt_are_separate_audited_transitions(): void
    {
        $batch = $this->requestBatch();
        $this->assertSame('pending', $batch->status);
        $this->assertSame('Alpha', $batch->company_name);
        $this->assertSame($this->sender->id, $batch->requested_by);
        $this->actingAs($this->admin)->patchJson('/logistics/transfers/'.$batch->id.'/approve')->assertOk();
        $this->assertSame('approved', $batch->fresh()->status);
        $this->assertNull($batch->fresh()->dispatched_at);
        foreach ($this->legs as $leg) {
            $this->assertSame('pending', $leg->fresh()->status);
        }
        $this->assertSame(0, DB::table('delivery_assignments')->count());
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertOk();
        foreach ($this->legs as $leg) {
            $this->actingAs($this->rider)->patch('/rider/hub-transfers/'.$leg->id.'/confirm')->assertRedirect();
        }
        $this->actingAs($this->receiver)->postJson('/logistics/transfers/'.$batch->id.'/discrepancy', ['reason' => 'Damaged outer carton', 'shipment_id' => $this->parcels[0]->id])->assertOk();
        $this->patchJson('/logistics/transfers/'.$batch->id.'/receive', ['tracking_number' => $this->parcels[0]->tracking_number])->assertOk();
        $this->assertSame('in_transit', $batch->fresh()->status);
        $this->assertSame(1, $batch->members()->whereNotNull('received_at')->count());
        $assignment=\App\Models\DeliveryAssignment::where('shipment_id',$this->parcels[0]->id)->where('leg','hub_transfer')->firstOrFail();
        $this->assertSame('active',app(\App\Services\AssignmentWork::class)->classify($assignment));
        $this->patchJson('/logistics/transfers/'.$batch->id.'/receive', ['confirm_all' => 1])->assertOk();
        $this->assertSame('completed',app(\App\Services\AssignmentWork::class)->classify($assignment->fresh()));
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame($this->receiver->id, $batch->fresh()->received_by);
        foreach ($this->parcels as $parcel) {
            $this->assertSame('sorted', $parcel->fresh()->shipping_status);
            $this->assertSame('sorted', $parcel->order->fresh()->status);
        }
        $this->assertSame(2, DB::table('delivery_assignments')->where('status', 'completed')->count());
        $this->assertEqualsCanonicalizing(['requested', 'approved', 'dispatched', 'pickup_confirmed', 'pickup_confirmed', 'discrepancy', 'parcel_received', 'parcel_received', 'received'], $batch->events()->pluck('action')->all());
        $this->assertSame('Damaged outer carton', $batch->events()->where('action', 'discrepancy')->value('reason'));
        $this->assertSame(2, DB::table('logistics_activity_logs')->where('action', 'logistics.transfers.dispatch')->count());
        $this->actingAs($this->admin)->get('/logistics/transfers/'.$batch->id)->assertOk()->assertSee('Received')->assertSee('Damaged outer carton');
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_staff_cannot_decide_or_dispatch_on_new_or_existing_routes(): void
    {
        $batch = $this->approved();
        $this->actingAs($this->sender);
        foreach (['approve', 'reject', 'dispatch'] as $action) {
            $this->patchJson('/logistics/transfers/'.$batch->id.'/'.$action, ['courier_id' => $this->rider->id, 'reason' => 'Forged'])->assertForbidden();
        }
        $this->patchJson('/logistics/assignments/'.$this->parcels[0]->id.'/assign-hub-transfer', ['courier_id' => $this->rider->id])->assertForbidden();
        $this->get('/logistics/transfers/'.$batch->id)->assertOk()->assertDontSee('Dispatch approved batch')->assertDontSee('Approve request')->assertDontSee('Reject request');
        $this->postJson('/logistics/scan/lookup', ['code' => $this->parcels[0]->tracking_number])->assertOk()->assertJsonPath('stage', 'none');
        $this->assertSame('approved', $batch->fresh()->status);
    }

    public function test_hub_and_company_scope_cannot_be_changed_using_ids_or_filter_parameters(): void
    {
        $batch = $this->approved();
        $unrelated = $this->hub('Alpha', 'Pila', 'Laguna');
        $other = $this->user('hub_staff', $unrelated);
        $foreign = $this->hub('Beta', 'Santa Cruz', 'Laguna');
        $this->actingAs($other)->getJson('/logistics/transfers/'.$batch->id)->assertNotFound();
        $this->get('/logistics/transfers?hub_id='.$this->origin->id)->assertNotFound();
        $this->get('/logistics/transfers')->assertOk()->assertViewHas('batches', fn ($rows) => $rows->total() === 0);
        $this->actingAs($this->user('logistics_admin', $foreign))->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertNotFound();
        $this->actingAs($this->sender)->postJson('/logistics/transfers', ['origin_hub_id' => $this->destination->id, 'destination_hub_id' => $this->origin->id, 'parcel_ids' => [$this->parcels[0]->id]])->assertForbidden();
        $this->get('/logistics/transfers/request?origin_hub_id='.$this->destination->id)->assertForbidden();
        $this->actingAs($this->receiver)->patchJson('/logistics/transfers/'.$batch->id.'/cancel', ['reason' => 'Not origin'])->assertUnprocessable();
    }

    public function test_rejection_requires_reason_preserves_history_and_cannot_dispatch(): void
    {
        $batch = $this->requestBatch();
        $this->actingAs($this->admin)->patchJson('/logistics/transfers/'.$batch->id.'/reject')->assertUnprocessable();
        $this->patchJson('/logistics/transfers/'.$batch->id.'/reject', ['reason' => '   '])->assertUnprocessable();
        $this->patchJson('/logistics/transfers/'.$batch->id.'/reject', ['reason' => 'Destination capacity unavailable'])->assertOk();
        $this->assertSame('rejected', $batch->fresh()->status);
        $this->assertSame('Destination capacity unavailable', $batch->fresh()->rejection_reason);
        $this->assertCount(2, $batch->members);
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertUnprocessable();
        $again = $this->requestBatch();
        $this->assertNotSame($batch->id, $again->id);
        $this->assertSame('pending', $again->status);
        $this->assertSame(2, $batch->members()->count());
        $this->assertSame(2, $batch->events()->count());
    }

    public function test_cancellation_policy_blocks_approved_staff_and_any_dispatched_cancellation(): void
    {
        $batch = $this->requestBatch();
        $this->patchJson('/logistics/transfers/'.$batch->id.'/cancel', ['reason' => 'Request error'])->assertOk();
        $this->assertSame('cancelled', $batch->fresh()->status);
        $this->actingAs($this->admin)->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertUnprocessable();
        $batch = $this->approved();
        $this->actingAs($this->sender)->patchJson('/logistics/transfers/'.$batch->id.'/cancel', ['reason' => 'Too late'])->assertUnprocessable();
        $this->actingAs($this->admin)->patchJson('/logistics/transfers/'.$batch->id.'/cancel', ['reason' => 'Route unavailable'])->assertOk();
        $batch = $this->dispatched();
        $this->actingAs($this->admin)->patchJson('/logistics/transfers/'.$batch->id.'/cancel', ['reason' => 'Already dispatched'])->assertUnprocessable();
    }

    public function test_invalid_transitions_replayed_dispatch_and_receipt_do_not_write_extra_history(): void
    {
        $batch = $this->requestBatch();
        $this->actingAs($this->admin)->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertUnprocessable();
        $this->actingAs($this->receiver)->patchJson('/logistics/transfers/'.$batch->id.'/receive', ['confirm_all' => 1])->assertUnprocessable();
        $this->actingAs($this->admin)->patchJson('/logistics/transfers/'.$batch->id.'/approve')->assertOk();
        $this->patchJson('/logistics/transfers/'.$batch->id.'/approve')->assertUnprocessable();
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertOk();
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertUnprocessable();
        $this->actingAs($this->receiver)->patchJson('/logistics/transfers/'.$batch->id.'/receive', ['confirm_all' => 1])->assertUnprocessable();
        $this->assertSame(0, $batch->members()->whereNotNull('received_at')->count());
        foreach ($this->legs as $leg) {
            $this->actingAs($this->rider)->patch('/rider/hub-transfers/'.$leg->id.'/confirm')->assertRedirect();
        }
        $this->actingAs($this->receiver)->patchJson('/logistics/transfers/'.$batch->id.'/receive', ['confirm_all' => 1])->assertOk();
        $count = $batch->events()->count();
        $this->patchJson('/logistics/transfers/'.$batch->id.'/receive', ['confirm_all' => 1])->assertUnprocessable();
        $this->assertSame($count, $batch->events()->count());
    }

    public function test_origin_or_other_hub_cannot_receive_or_report_a_destination_discrepancy(): void
    {
        $batch = $this->dispatched();
        $this->actingAs($this->sender)->patchJson('/logistics/transfers/'.$batch->id.'/receive', ['confirm_all' => 1])->assertForbidden();
        $this->postJson('/logistics/transfers/'.$batch->id.'/discrepancy', ['reason' => 'Wrong hub'])->assertForbidden();
        $this->actingAs($this->receiver)->patchJson('/logistics/transfers/'.$batch->id.'/receive', ['tracking_number' => 'FOREIGN-TRACK'])->assertUnprocessable();
        $this->postJson('/logistics/transfers/'.$batch->id.'/discrepancy', ['reason' => 'Forged ID', 'shipment_id' => 'other-parcel'])->assertUnprocessable();
        $this->assertSame('in_transit', $batch->fresh()->status);
    }

    public function test_duplicates_foreign_parcels_wrong_hubs_and_ineligible_parcels_are_rejected(): void
    {
        $data = ['origin_hub_id' => $this->origin->id, 'destination_hub_id' => $this->destination->id, 'parcel_ids' => [$this->parcels[0]->id, $this->parcels[0]->id]];
        $this->actingAs($this->sender)->postJson('/logistics/transfers', $data)->assertUnprocessable();
        $data['parcel_ids'] = ['foreign-parcel'];
        $this->postJson('/logistics/transfers', $data)->assertUnprocessable();
        $data['parcel_ids'] = [$this->parcels[0]->id];
        $data['destination_hub_id'] = $this->origin->id;
        $this->postJson('/logistics/transfers', $data)->assertUnprocessable();
        $data['destination_hub_id'] = $this->destination->id;
        $this->parcels[0]->update(['shipping_status' => 'cancelled']);
        $this->postJson('/logistics/transfers', $data)->assertUnprocessable();
        $this->assertSame(0, HubTransferBatch::count());
    }

    public function test_closed_hub_suspended_actor_and_changed_parcel_are_rechecked_at_dispatch(): void
    {
        $batch = $this->approved();
        $this->destination->update(['is_operational' => false]);
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertUnprocessable();
        $this->destination->update(['is_operational' => true]);
        DB::table('users')->where('id', $this->admin->id)->update(['status' => 'suspended']);
        // The guard still has the old approved object; the transaction must reload it.
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertForbidden();
        DB::table('users')->where('id', $this->admin->id)->update(['status' => 'approved']);
        $this->parcels[0]->update(['shipping_status' => 'returned']);
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertUnprocessable();
        $this->assertSame('approved', $batch->fresh()->status);
        $this->assertSame(0, DB::table('delivery_assignments')->count());
    }

    public function test_rider_and_vehicle_validation_and_conflicting_assignments(): void
    {
        $batch = $this->approved();
        foreach ([['status' => 'suspended'], ['vehicle_type' => 'two_wheels'], ['logistics_hub_id' => $this->destination->id]] as $change) {
            $this->rider->update($change+($change['status']??null?['status_reason'=>'Fixture suspension']:[]));
            $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertUnprocessable();
            $this->rider->hiringDecision=['reason'=>'Fixture reactivation'];
            $this->rider->update(['status' => 'approved', 'vehicle_type' => 'four_wheels', 'logistics_hub_id' => $this->origin->id]);
        }
        $this->rider->update(['vehicle_ownership' => 'company']);
        $vehicle = CompanyVehicle::create(['company_name' => 'Alpha', 'logistics_hub_id' => $this->origin->id, 'vehicle_type' => 'four_wheels', 'platform_status' => 'pending', 'is_available' => true]);
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id, 'vehicle_id' => $vehicle->id])->assertUnprocessable();
        $vehicle->update(['platform_status' => 'approved', 'is_available' => false]);
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id, 'vehicle_id' => $vehicle->id])->assertUnprocessable();
        $vehicle->update(['is_available' => true]);
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id, 'vehicle_id' => $vehicle->id])->assertOk();
        [$s] = $this->parcel($this->origin, $this->destination);
        $next = app(HubTransferWorkflow::class)->request($this->sender, $this->origin->id, $this->destination->id, [$s->id]);
        app(HubTransferWorkflow::class)->decide($this->admin, $next->id, 'approved');
        $this->patchJson('/logistics/transfers/'.$next->id.'/dispatch', ['courier_id' => $this->rider->id, 'vehicle_id' => $vehicle->id])->assertUnprocessable();
        $otherRider = $this->user('rider', $this->origin);
        $otherRider->update(['vehicle_ownership' => 'company']);
        $this->patchJson('/logistics/transfers/'.$next->id.'/dispatch', ['courier_id' => $otherRider->id, 'vehicle_id' => $vehicle->id])->assertUnprocessable();
        $this->assertSame('approved', $next->fresh()->status);
    }

    public function test_event_and_activity_failures_roll_back_dispatch_and_receipt(): void
    {
        $batch = $this->approved();
        DB::unprepared("CREATE TRIGGER fail_transfer_event BEFORE INSERT ON hub_transfer_events BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertStatus(500);
        $this->assertSame('approved', $batch->fresh()->status);
        $this->assertSame(0, DB::table('delivery_assignments')->count());
        $this->assertSame('at_sorting_center', $this->parcels[0]->fresh()->shipping_status);
        DB::unprepared('DROP TRIGGER fail_transfer_event');
        $this->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertOk();
        foreach ($this->legs as $leg) {
            $this->actingAs($this->rider)->patch('/rider/hub-transfers/'.$leg->id.'/confirm')->assertRedirect();
        }
        DB::unprepared("CREATE TRIGGER fail_transfer_audit BEFORE INSERT ON logistics_activity_logs BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        $before = $batch->events()->count();
        $this->actingAs($this->receiver)->patchJson('/logistics/transfers/'.$batch->id.'/receive', ['confirm_all' => 1])->assertStatus(500);
        $this->assertSame('in_transit', $batch->fresh()->status);
        $this->assertSame(0, $batch->members()->whereNotNull('received_at')->count());
        $this->assertSame($before, $batch->events()->count());
        $this->assertSame('hub_transfer', $this->parcels[0]->fresh()->shipping_status);
    }

    public function test_all_six_roles_and_scoped_list_filters_and_details(): void
    {
        $batch = $this->requestBatch();
        foreach (['admin', 'buyer', 'seller', 'rider'] as $role) {
            $user = $this->user($role, $this->origin);
            $this->actingAs($user);
            foreach (['GET', 'POST', 'PATCH'] as $method) {
                $response = $this->json($method, $method === 'PATCH' ? '/logistics/transfers/'.$batch->id.'/dispatch' : '/logistics/transfers', ['courier_id' => $this->rider->id]);
                $this->assertContains($response->status(), [302, 303, 401, 403, 404]);
            }
        }
        $this->actingAs($this->sender)->get('/logistics/transfers?direction=incoming')->assertOk()->assertViewHas('batches', fn ($rows) => $rows->total() === 0);
        $this->get('/logistics/transfers?direction=outgoing&search='.$this->parcels[0]->tracking_number)->assertOk()->assertViewHas('batches', fn ($rows) => $rows->total() === 1);
        $this->actingAs($this->receiver)->get('/logistics/transfers?direction=incoming')->assertOk()->assertViewHas('batches', fn ($rows) => $rows->total() === 1);
        $this->actingAs($this->admin)->get('/logistics/transfers?status=pending&hub_id='.$this->destination->id.'&from='.now()->toDateString().'&to='.now()->toDateString())->assertOk()->assertViewHas('batches', fn ($rows) => $rows->total() === 1);
        $this->get('/logistics/transfers/request?origin_hub_id='.$this->origin->id.'&destination_hub_id='.$this->destination->id)->assertOk()->assertViewHas('candidates', fn ($rows) => $rows->total() === 0);
        $this->get('/logistics/transfers/'.$batch->id)->assertOk()->assertSee('Approve request')->assertSee('Rejection reason')->assertDontSee('Dispatch approved batch');
    }

    public function test_duplicate_membership_constraint_and_second_request_are_enforced(): void
    {
        $batch = $this->requestBatch();
        $this->postJson('/logistics/transfers', ['origin_hub_id' => $this->origin->id, 'destination_hub_id' => $this->destination->id, 'parcel_ids' => [$this->parcels[0]->id]])->assertUnprocessable();
        $this->expectException(QueryException::class);
        DB::table('hub_transfer_members')->insert(['batch_id' => $batch->id, 'leg_id' => $this->legs[0]->id, 'shipment_id' => $this->parcels[0]->id]);
    }

    public function test_paginated_transfer_lists_retain_filters_and_full_totals(): void
    {
        $batch = $this->requestBatch();
        $flow = app(HubTransferWorkflow::class);
        for ($i = 0; $i < 20; $i++) {
            [$s] = $this->parcel($this->origin, $this->destination);
            $flow->request($this->sender, $this->origin->id, $this->destination->id, [$s->id]);
        }
        $rows = $this->actingAs($this->admin)->get('/logistics/transfers?status=pending&page=2')->assertOk()->viewData('batches');
        $this->assertSame(21, $rows->total());
        $this->assertCount(1, $rows->items());
        $this->assertStringContainsString('status=pending', $rows->previousPageUrl());
        $this->get('/logistics/transfers?to='.now()->toDateString())->assertOk()->assertViewHas('batches', fn ($rows) => $rows->total() === 21);
        $this->get('/logistics/transfers?search='.$this->parcels[0]->tracking_number)->assertOk()->assertViewHas('batches', fn ($rows) => $rows->total() === 1);
        $this->get('/logistics/transfers?status=completed')->assertOk()->assertViewHas('batches', fn ($rows) => $rows->total() === 0);
    }

    public function test_multi_hop_transfer_hides_other_hubs_current_leg_and_requires_each_hop_approval(): void
    {
        $middle = $this->hub('Alpha', 'Calamba', 'Laguna');
        $middleStaff = $this->user('hub_staff', $middle);
        $middleRider = $this->user('rider', $middle);
        $s = $this->parcels[0];
        $first = $this->legs[0];
        $first->update(['to_hub' => $middle->municipality, 'to_province' => $middle->province, 'leg_type' => 'dispatch']);
        $second = ShipmentHubLeg::create(['shipment_id' => $s->id, 'sequence' => 2, 'leg_type' => 'relay', 'from_hub' => $middle->municipality, 'from_province' => $middle->province, 'to_hub' => $this->destination->municipality, 'to_province' => $this->destination->province, 'status' => 'pending']);
        $flow = app(HubTransferWorkflow::class);
        $batch = $flow->request($this->sender, $this->origin->id, $middle->id, [$s->id]);
        $flow->decide($this->admin, $batch->id, 'approved');
        $this->actingAs($this->receiver)->getJson('/logistics/transfers/'.$batch->id)->assertNotFound();
        $this->postJson('/logistics/scan/lookup', ['code' => $s->tracking_number])->assertOk()->assertJsonPath('shipment.current_leg', null)->assertJsonPath('stage', 'none');
        $flow->dispatch($this->admin, $batch->id, $this->rider->id);
        $flow->confirmPickup($this->rider, $first->id);
        $flow->receive($middleStaff, $batch->id);
        $this->assertSame('hub_transfer', $s->fresh()->shipping_status);
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame('Received at Next Hub', app(\App\Services\ParcelCustody::class)->describe($s->fresh(), $middleStaff)['label']);
        $onward = $flow->request($middleStaff, $middle->id, $this->destination->id, [$s->id]);
        $this->actingAs($this->admin)->patchJson('/logistics/transfers/'.$onward->id.'/dispatch', ['courier_id' => $middleRider->id])->assertUnprocessable();
        $flow->decide($this->admin, $onward->id, 'approved');
        $flow->dispatch($this->admin, $onward->id, $middleRider->id);
        $flow->confirmPickup($middleRider, $second->id);
        $flow->receive($this->receiver, $onward->id);
        $this->assertSame('sorted', $s->fresh()->shipping_status);
        $this->assertSame('completed', $onward->fresh()->status);
    }
    public function test_financial_foundation_snapshots_a_multi_parcel_dispatch_once(): void
    {
        (require database_path('migrations/2026_10_28_000001_add_logistics_financial_foundation.php'))->up();
        $batch = $this->dispatched();
        $this->assertDatabaseCount('logistics_compensations', 1);
        $this->assertDatabaseHas('logistics_compensations', ['source_type' => 'transfer_batch', 'source_id' => $batch->id,
            'rider_id' => $this->rider->id, 'amount_cents' => 15000, 'status' => 'pending']);
        $this->actingAs($this->admin)->patchJson('/logistics/transfers/'.$batch->id.'/dispatch', ['courier_id' => $this->rider->id])->assertUnprocessable();
        $this->assertDatabaseCount('logistics_compensations', 1);
        $this->assertSame(2, $batch->members()->count());
    }

    public function test_admin_cannot_record_batch_or_legacy_scan_arrival(): void
    {
        $batch=$this->dispatched();
        $this->actingAs($this->admin)->patchJson('/logistics/transfers/'.$batch->id.'/receive',['confirm_all'=>1])->assertForbidden();
        $this->patchJson('/logistics/scan/'.$this->parcels[0]->id.'/complete-hub-transfer')->assertForbidden();
        $this->assertSame(0,$batch->members()->whereNotNull('received_at')->count());
        $this->assertSame('in_transit',$batch->fresh()->status);
        $this->actingAs($this->receiver)->patchJson('/logistics/transfers/'.$batch->id.'/receive',['confirm_all'=>1])->assertOk();
    }
}
