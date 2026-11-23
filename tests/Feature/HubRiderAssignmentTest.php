<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Services\RiderDeliveryOffers;
use Illuminate\Support\Facades\DB;

class HubRiderAssignmentTest extends OperationalRecoveryTest
{
    public function test_approved_pickup_stays_at_origin_and_is_not_a_final_delivery(): void
    {
        $destination = LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Pila','province'=>'Laguna','is_operational'=>true]);
        $buyerHubStaff = $this->person('hub_staff', $destination->id);
        DB::table('shipments')->where('id', $this->shipment->id)->update(['origin_hub'=>$this->staff->logisticsHub->municipality,'origin_province'=>'Laguna','destination_hub'=>'Pila','destination_province'=>'Laguna','shipping_status'=>'ready_for_pickup','pickup_rider_id'=>null]);
        $this->actingAs($this->staff)->get('/logistics/requests')->assertOk()->assertSee($this->shipment->tracking_number);
        $this->actingAs($buyerHubStaff)->get('/logistics/requests')->assertOk()->assertDontSee($this->shipment->tracking_number);
        foreach ([$this->staff, $buyerHubStaff, $this->admin] as $actor) {
            $this->actingAs($actor)->get('/logistics/assignments')->assertOk()->assertViewHas('shipments', fn($parcels)=>!$parcels->contains('id', $this->shipment->id));
        }
        DB::table('shipments')->where('id', $this->shipment->id)->update(['shipping_status'=>'sorted','sorted_at'=>now()]);
        $this->actingAs($buyerHubStaff)->get('/logistics/assignments')->assertOk()->assertViewHas('shipments', fn($parcels)=>$parcels->contains('id', $this->shipment->id));
        $this->actingAs($this->staff)->get('/logistics/assignments')->assertOk()->assertViewHas('shipments', fn($parcels)=>!$parcels->contains('id', $this->shipment->id));
    }

    public function test_company_admin_cannot_send_hub_rider_offers(): void
    {
        foreach (['logistics.requests.assign-pickup', 'logistics.assignments.assign'] as $route) {
            $this->actingAs($this->admin)->patchJson(route($route, $this->shipment->id), ['courier_id' => $this->replacement->id])->assertForbidden();
        }
        $this->assertSame($this->rider->id, $this->shipment->fresh()->pickup_rider_id);
        $this->actingAs($this->admin)->get('/logistics/requests?queue=assignment')->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('logistics.assignments.reassign', $this->assignment->id), $this->requestData())->assertForbidden();
    }

    public function test_only_responsible_hub_can_send_pickup_and_delivery_offers(): void
    {
        $other = LogisticsHub::create(['company_name' => 'Alpha', 'municipality' => 'Pila', 'province' => 'Laguna', 'is_operational' => true]);
        $outsider = $this->person('hub_staff', $other->id);
        DB::table('shipments')->where('id', $this->shipment->id)->update(['pickup_rider_id' => null]);
        $this->assignment->delete();
        $this->actingAs($outsider)->patchJson(route('logistics.requests.assign-pickup', $this->shipment->id), ['courier_id' => $this->replacement->id])->assertForbidden();
        $offer = app(RiderDeliveryOffers::class)->propose($this->staff, $this->shipment->id, $this->replacement->id, 'pickup');
        $this->assertSame('offered', $offer->status);
        $this->assertNull($this->shipment->fresh()->pickup_rider_id);

        $offer->delete();
        DB::table('shipments')->where('id', $this->shipment->id)->update(['shipping_status' => 'sorted', 'sorted_at' => now()]);
        $this->actingAs($outsider)->patchJson(route('logistics.assignments.assign', $this->shipment->id), ['courier_id' => $this->replacement->id])->assertForbidden();
        $delivery = app(RiderDeliveryOffers::class)->propose($this->staff, $this->shipment->id, $this->replacement->id, 'delivery');
        $this->assertSame('offered', $delivery->status);
        $this->assertNull($this->shipment->fresh()->courier_id);
    }

    public function test_hub_pickup_queue_keeps_company_review_with_admin(): void
    {
        $this->actingAs($this->staff)->get('/logistics/requests')->assertOk()->assertSee('Awaiting Pickup Rider');
        $this->actingAs($this->staff)->get('/logistics/requests?queue=review')->assertForbidden();
        $this->actingAs($this->admin)->get('/logistics/requests')->assertOk()->assertDontSee('Send pickup offer');
        $other = LogisticsHub::create(['company_name' => 'Alpha', 'municipality' => 'Pila', 'province' => 'Laguna', 'is_operational' => true]);
        DB::table('shipments')->where('id', $this->shipment->id)->update(['pickup_rider_id' => null, 'origin_hub' => 'Pila']);
        $this->actingAs($this->staff)->get('/logistics/requests')->assertOk()->assertDontSee($this->shipment->tracking_number);
        $origin = $this->person('hub_staff', $other->id);
        $this->actingAs($origin)->get('/logistics/requests')->assertOk()->assertSee($this->shipment->tracking_number)->assertSee('Send pickup offer');
    }
}
