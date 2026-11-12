<?php

namespace Tests\Feature;

use App\Models\CompanyVehicle;
use App\Models\DeliveryAssignment;
use App\Models\LogisticsHub;
use App\Models\Shipment;
use App\Models\User;
use App\Services\RiderDeliveryOffers;
use Illuminate\Support\Facades\DB;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class RiderAssignmentWorkflowTest extends TestCase
{
    use WorkflowSchema;

    private LogisticsHub $hub;

    private User $staff;

    private User $rider;

    private RiderDeliveryOffers $flow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        $this->hub = LogisticsHub::create(['company_name' => 'Assignment Test', 'municipality' => 'Pila', 'province' => 'Laguna', 'is_operational' => true]);
        $this->staff = $this->person('logistics');
        $this->rider = $this->person('rider');
        $this->flow = app(RiderDeliveryOffers::class);
    }

    private function person(string $role): User
    {
        $user = User::create(['username' => uniqid('assignment_'), 'given_names' => 'Assignment', 'last_name' => 'Fixture', 'account_type' => $role,
            'is_logistics' => $role === 'logistics', 'logistics_role' => $role === 'logistics' ? 'hub_staff' : null,
            'business_name' => $this->hub->company_name, 'logistics_hub_id' => $this->hub->id, 'status' => 'approved',
            'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own']);
        $user->forceFill(['is_available' => true])->save();

        return $user;
    }

    private function parcel(string $leg = 'delivery'): Shipment
    {
        return Shipment::create(['tracking_number' => uniqid('TEST-'), 'logistics_company' => $this->hub->company_name,
            'origin_hub' => $this->hub->municipality, 'origin_province' => $this->hub->province,
            'destination_hub' => $this->hub->municipality, 'destination_province' => $this->hub->province,
            'shipping_status' => $leg === 'pickup' ? 'ready_for_pickup' : 'sorted', 'pickup_approved_at' => now()]);
    }

    private function staffUrl(Shipment $shipment, string $leg): string
    {
        return $leg === 'pickup' ? '/logistics/requests/'.$shipment->id.'/assign-pickup' : '/logistics/assignments/'.$shipment->id.'/assign';
    }

    private function acceptUrl(Shipment $shipment, string $leg): string
    {
        return '/rider/'.($leg === 'pickup' ? 'pickup-requests' : 'requests').'/'.$shipment->id.'/accept';
    }

    public function test_manual_pickup_and_delivery_wait_for_rider_confirmation_and_accept_only_once(): void
    {
        foreach (['pickup', 'delivery'] as $leg) {
            $shipment = $this->parcel($leg);
            $this->actingAs($this->staff)->patch($this->staffUrl($shipment, $leg), ['courier_id' => $this->rider->id])->assertRedirect()->assertSessionHasNoErrors();
            $assignment = DeliveryAssignment::where('shipment_id', $shipment->id)->firstOrFail();
            $notifications = DB::table('notifications')->count();
            $this->patch($this->staffUrl($shipment, $leg), ['courier_id' => $this->rider->id])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($notifications, DB::table('notifications')->count());
            $this->assertSame('offered', $assignment->status);
            $this->assertNull($assignment->accepted_at);
            $this->assertNull($shipment->fresh()->pickup_rider_id);
            $this->assertNull($shipment->fresh()->courier_id);
            $this->assertSame($leg === 'pickup' ? 'ready_for_pickup' : 'sorted', $shipment->fresh()->shipping_status);
            $this->actingAs($this->rider)->patch($this->acceptUrl($shipment, $leg))->assertRedirect()->assertSessionHasNoErrors();
            $this->assertNotNull($assignment->fresh()->accepted_at);
            $this->assertSame($this->rider->id, $leg === 'pickup' ? $shipment->fresh()->pickup_rider_id : $shipment->fresh()->courier_id);
            $this->patchJson($this->acceptUrl($shipment, $leg))->assertUnprocessable();
        }
        $this->assertDatabaseCount('delivery_assignments', 2);
    }

    public function test_all_entry_points_reject_the_same_ineligible_riders(): void
    {
        $otherHub = LogisticsHub::create(['company_name' => $this->hub->company_name, 'municipality' => 'Pila', 'province' => 'Other Province', 'is_operational' => true]);
        foreach (['pickup', 'delivery'] as $leg) {
            foreach ([['status' => 'suspended'], ['is_available' => false], ['vehicle_type' => 'four_wheels'], ['vehicle_ownership' => null], ['vehicle_ownership' => 'company'], ['logistics_hub_id' => $otherHub->id], ['business_name' => 'Other Company']] as $changes) {
                DB::table('users')->where('id', $this->rider->id)->update(['status' => 'approved', 'is_available' => true, 'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own', 'logistics_hub_id' => $this->hub->id, 'business_name' => $this->hub->company_name]);
                $this->rider->refresh();
                $shipment = $this->parcel($leg);
                $offer = $this->flow->offer($shipment, $leg);
                $this->assertNotNull($offer);
                DB::table('users')->where('id', $this->rider->id)->update($changes);
                $this->rider->refresh();
                $this->assertFalse($this->flow->eligible($shipment, $leg)->where('users.id', $this->rider->id)->exists());
                $this->actingAs($this->staff)->patchJson($this->staffUrl($shipment, $leg), ['courier_id' => $this->rider->id])->assertUnprocessable();
                $actor = clone $this->rider;
                $actor->status = 'approved'; // Simulate a session authenticated before suspension.
                $this->actingAs($actor)->patchJson($this->acceptUrl($shipment, $leg))->assertUnprocessable();
                $this->assertNull($shipment->fresh()->courier_id);
                $this->assertNull($shipment->fresh()->pickup_rider_id);
                $offer->delete();
                $this->assertNull($this->flow->offer($shipment, $leg));
                $shipment->delete();
            }
        }
    }

    public function test_capacity_is_combined_and_rechecked_when_a_stale_offer_is_accepted(): void
    {
        $delivery = $this->parcel();
        $this->flow->propose($this->staff, $delivery->id, $this->rider->id, 'delivery');
        foreach (['pickup', 'pickup', 'delivery'] as $leg) {
            $shipment = $this->parcel($leg);
            $this->flow->propose($this->staff, $shipment->id, $this->rider->id, $leg);
            $this->flow->accept($this->rider, $shipment->id, $leg);
        }
        $this->assertSame(3, $this->flow->activeJobs($this->rider->id));
        $this->actingAs($this->rider)->patchJson($this->acceptUrl($delivery, 'delivery'))->assertUnprocessable();
        $this->actingAs($this->staff)->patchJson($this->staffUrl($delivery, 'delivery'), ['courier_id' => $this->rider->id])->assertUnprocessable();
        $this->assertNull($this->flow->offer($this->parcel()));
        $this->assertSame(3, $this->flow->activeJobs($this->rider->id));
    }

    public function test_declined_and_expired_offers_keep_manual_delivery_controls_available(): void
    {
        foreach (['declined', 'expired', 'offered'] as $state) {
            $shipment = $this->parcel();
            $offer = $this->flow->propose($this->staff, $shipment->id, $this->rider->id, 'delivery');
            $offer->update(['status' => $state, 'expires_at' => $state === 'offered' ? now()->addMinute() : now()->subMinute()]);
            $this->actingAs($this->staff)->get('/logistics/assignments')->assertOk()->assertSee($this->staffUrl($shipment, 'delivery'), false)->assertSee('Send offer')->assertSee('Riders must confirm');
            $this->patch($this->staffUrl($shipment, 'delivery'), ['courier_id' => $this->rider->id])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('offered', $offer->fresh()->status);
            $this->assertNull($offer->fresh()->accepted_at);
        }
    }

    public function test_expiry_decline_and_replacement_cannot_be_accepted_by_the_old_rider(): void
    {
        $shipment = $this->parcel();
        $other = $this->person('rider');
        $offer = $this->flow->propose($this->staff, $shipment->id, $this->rider->id, 'delivery');
        $this->flow->propose($this->staff, $shipment->id, $other->id, 'delivery');
        $this->actingAs($this->rider)->patchJson($this->acceptUrl($shipment, 'delivery'))->assertUnprocessable();
        $offer->refresh()->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($other)->patchJson($this->acceptUrl($shipment, 'delivery'))->assertUnprocessable();
        $this->flow->expire($shipment->id);
        $this->assertNull($shipment->fresh()->courier_id);
        $this->flow->propose($this->staff, $shipment->id, $this->rider->id, 'delivery');
        // Exhaust automatic retries so the declined assignment remains visible.
        $shipment->update(['delivery_offer_failures' => RiderDeliveryOffers::MAX_FAILURES]);
        $this->flow->decline($this->rider, $shipment->id);
        $this->assertSame('declined', $offer->fresh()->status);
        $this->actingAs($this->rider)->patchJson($this->acceptUrl($shipment, 'delivery'))->assertUnprocessable();
        $this->assertNull($offer->fresh()->accepted_at);
    }

    public function test_company_vehicle_must_be_approved_available_and_at_the_matching_hub(): void
    {
        $this->rider->update(['vehicle_ownership' => 'company']);
        $shipment = $this->parcel();
        $vehicle = CompanyVehicle::create(['company_name' => $this->hub->company_name, 'logistics_hub_id' => $this->hub->id, 'vehicle_type' => 'two_wheels', 'platform_status' => 'pending', 'is_available' => true]);
        $this->assertNull($this->flow->offer($shipment));
        $vehicle->update(['platform_status' => 'approved', 'is_available' => false]);
        $this->assertNull($this->flow->offer($shipment));
        $vehicle->update(['is_available' => true]);
        $this->assertNotNull($this->flow->offer($shipment));
        $vehicle->update(['is_available' => false]);
        $this->actingAs($this->rider)->patchJson($this->acceptUrl($shipment, 'delivery'))->assertUnprocessable();
        $vehicle->update(['is_available' => true]);
        $this->hub->update(['is_operational' => false]);
        $this->patchJson($this->acceptUrl($shipment, 'delivery'))->assertUnprocessable();
    }

    public function test_audit_failure_rolls_back_acceptance_and_parcel_ownership(): void
    {
        $shipment = $this->parcel();
        $offer = $this->flow->propose($this->staff, $shipment->id, $this->rider->id, 'delivery');
        DB::unprepared("CREATE TRIGGER reject_assignment_audit BEFORE INSERT ON logistics_activity_logs BEGIN SELECT RAISE(ABORT, 'test audit failure'); END");
        $this->actingAs($this->rider)->patchJson($this->acceptUrl($shipment, 'delivery'))->assertStatus(500);
        $this->assertNull($shipment->fresh()->courier_id);
        $this->assertSame('sorted', $shipment->fresh()->shipping_status);
        $this->assertSame('offered', $offer->fresh()->status);
        $this->assertNull($offer->fresh()->accepted_at);
    }

    public function test_unoffered_parcels_are_not_shown_as_accept_actions_and_cannot_be_claimed(): void
    {
        $this->parcel();
        $pickup = $this->parcel('pickup');
        $other = $this->person('rider');
        $this->flow->propose($this->staff, $pickup->id, $other->id, 'pickup');
        $this->actingAs($this->rider)->patchJson($this->acceptUrl($pickup, 'pickup'))->assertUnprocessable();
        $response = $this->get(route('rider.dashboard'))->assertOk();
        $response->assertDontSee($this->acceptUrl($pickup, 'pickup'), false)->assertSee('Your Delivery Offers');
    }

    public function test_offers_rotate_only_to_eligible_riders_and_manual_retry_survives_retry_limit(): void
    {
        $shipment = $this->parcel();
        $other = $this->person('rider');
        $offer = $this->flow->offer($shipment);
        $this->assertSame($this->rider->id, $offer->courier_id);
        $this->flow->decline($this->rider, $shipment->id);
        $this->assertSame($other->id, $offer->fresh()->courier_id);
        $this->assertSame('offered', $offer->fresh()->status);
        $offer->fresh()->update(['expires_at' => now()->subSecond()]);
        $shipment->update(['delivery_offer_failures' => RiderDeliveryOffers::MAX_FAILURES - 1]);
        $this->flow->expire($shipment->id);
        $this->assertSame('expired', $offer->fresh()->status);
        $this->assertNull($this->flow->offer($shipment));
        $this->flow->propose($this->staff, $shipment->id, $this->rider->id, 'delivery');
        $this->flow->accept($this->rider, $shipment->id, 'delivery');
        $this->assertSame($this->rider->id, $shipment->fresh()->courier_id);
    }
}
