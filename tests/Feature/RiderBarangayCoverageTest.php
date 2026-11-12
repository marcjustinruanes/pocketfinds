<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\RiderBarangayRoute;
use App\Models\Shipment;
use App\Models\User;
use App\Services\RiderBarangayCoverage;
use App\Services\RiderDeliveryOffers;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class RiderBarangayCoverageTest extends TestCase
{
    use WorkflowSchema;

    private LogisticsHub $hub;

    private User $admin;

    private User $staff;

    private User $rider;

    private User $seller;

    private User $buyer;

    private RiderBarangayCoverage $coverage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        Schema::table('logistics_hubs', fn ($t) => $t->string('region')->nullable());
        (require database_path('migrations/2026_11_11_000001_add_rider_barangay_routes.php'))->up();
        Http::preventStrayRequests();
        Http::fake([
            '*/api/provinces/' => Http::response([['code' => '043400000', 'name' => 'Laguna']]),
            '*/api/provinces/043400000/cities-municipalities/' => Http::response([['code' => '043424000', 'name' => 'Pila']]),
            '*/api/cities-municipalities/043424000/barangays/' => Http::response([
                ['code' => '043424001', 'name' => 'Central'], ['code' => '043424002', 'name' => 'Santa Clara'],
            ]),
        ]);
        $this->hub = LogisticsHub::create(['company_name' => 'Coverage Test', 'region' => 'CALABARZON', 'province' => 'Laguna', 'municipality' => 'Pila', 'is_operational' => true]);
        $this->admin = $this->person('logistics', 'admin');
        $this->staff = $this->person('logistics', 'hub_staff');
        $this->rider = $this->person('rider');
        $this->seller = $this->person('seller');
        $this->buyer = $this->person('buyer');
        $this->coverage = app(RiderBarangayCoverage::class);
    }

    private function person(string $role, ?string $logisticsRole = null): User
    {
        $person = User::create(['username' => uniqid('coverage_'), 'account_type' => $role, 'is_logistics' => $role === 'logistics',
            'logistics_role' => $logisticsRole, 'given_names' => 'Coverage', 'last_name' => 'Fixture', 'status' => 'approved',
            'business_name' => $this->hub->company_name, 'logistics_hub_id' => $this->hub->id, 'municipality' => 'Pila', 'province' => 'Laguna',
            'barangay' => 'Central', 'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own']);
        $person->forceFill(['is_available' => true])->save();

        return $person;
    }

    private function parcel(string $leg): Shipment
    {
        $order = Order::create(['order_number' => uniqid('COVER-'), 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id,
            'status' => $leg === 'pickup' ? 'ready_for_pickup' : 'sorted', 'items' => [], 'shipping_address' => ['barangay' => 'Santa Clara', 'municipality' => 'Pila', 'province' => 'Laguna']]);

        return Shipment::create(['order_id' => $order->id, 'tracking_number' => uniqid('COV-'), 'logistics_company' => $this->hub->company_name,
            'origin_hub' => 'Pila', 'origin_province' => 'Laguna', 'destination_hub' => 'Pila', 'destination_province' => 'Laguna',
            'shipping_status' => $order->status, 'pickup_approved_at' => now()]);
    }

    private function data(array $codes): array
    {
        return ['hub_id' => $this->rider->logistics_hub_id, 'version' => $this->coverage->version($this->rider), 'barangays' => $codes];
    }

    private function url(): string
    {
        return '/logistics/riders/'.$this->rider->id.'/coverage';
    }

    private function configure(array $codes): void
    {
        $this->actingAs($this->admin)->put($this->url(), $this->data($codes))->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_admin_can_assign_edit_clear_and_display_multiple_barangays(): void
    {
        $this->actingAs($this->admin)->get($this->url())->assertOk()->assertSee('Central')->assertSee('Santa Clara');
        $this->configure(['043424001', '043424002']);
        $this->assertDatabaseCount('rider_barangay_routes', 2);
        $this->get('/logistics/riders')->assertOk()->assertSee('Central, Santa Clara')->assertSee('Manage barangay routes');
        $this->configure(['043424002']);
        $this->assertDatabaseCount('rider_barangay_routes', 1);
        $this->assertDatabaseHas('rider_barangay_routes', ['rider_id' => $this->rider->id, 'hub_id' => $this->hub->id, 'barangay_name' => 'Santa Clara', 'barangay_key' => 'santa clara']);
        $this->configure([]);
        $this->assertDatabaseCount('rider_barangay_routes', 0);
    }

    public function test_only_company_admins_can_manage_routes(): void
    {
        foreach ([$this->staff, $this->rider, $this->seller, $this->buyer] as $actor) {
            $expected = $actor->is_logistics ? 403 : 302;
            $this->actingAs($actor)->get($this->url())->assertStatus($expected);
            $this->putJson($this->url(), $this->data(['043424001']))->assertStatus($expected);
        }
        DB::table('users')->where('id', $this->admin->id)->update(['business_name' => 'Other Company']);
        $this->actingAs($this->admin->fresh())->get($this->url())->assertNotFound();
        $this->putJson($this->url(), $this->data(['043424001']))->assertNotFound();
        $this->assertDatabaseCount('rider_barangay_routes', 0);
    }

    public function test_unknown_duplicate_and_foreign_hub_codes_do_not_change_coverage(): void
    {
        $this->configure(['043424001']);
        foreach ([['999999999'], ['043424001', '043424001'], ['bad']] as $codes) {
            $this->putJson($this->url(), $this->data($codes))->assertUnprocessable();
            $this->assertDatabaseCount('rider_barangay_routes', 1);
        }
        $other = LogisticsHub::create(['company_name' => 'Other', 'province' => 'Laguna', 'municipality' => 'Pila', 'is_operational' => true]);
        $data = $this->data(['043424002']);
        $data['hub_id'] = $other->id;
        $this->putJson($this->url(), $data)->assertNotFound();
        $this->assertDatabaseHas('rider_barangay_routes', ['barangay_code' => '043424001']);
    }

    public function test_stale_form_and_hub_changes_require_reload(): void
    {
        $old = $this->data(['043424001']);
        $this->configure(['043424002']);
        $this->putJson($this->url(), $old)->assertStatus(409);
        $data = $this->data(['043424001']);
        $other = LogisticsHub::create(['company_name' => $this->hub->company_name, 'province' => 'Laguna', 'municipality' => 'Other Town', 'is_operational' => true]);
        DB::table('users')->where('id', $this->rider->id)->update(['logistics_hub_id' => $other->id]);
        $this->putJson($this->url(), $data)->assertStatus(409);
        $this->assertDatabaseHas('rider_barangay_routes', ['hub_id' => $this->hub->id, 'barangay_code' => '043424002']);
        $this->assertCount(0, app(RiderDeliveryOffers::class)->eligible($this->parcel('delivery'), 'delivery')->get());
    }

    public function test_pickup_and_delivery_use_their_own_barangay_for_choices_manual_and_automatic_offers(): void
    {
        $this->configure(['043424001']);
        $pickup = $this->parcel('pickup');
        $delivery = $this->parcel('delivery');
        $flow = app(RiderDeliveryOffers::class);
        $this->assertSame([$this->rider->id], $flow->eligible($pickup, 'pickup')->pluck('users.id')->all());
        $this->assertCount(0, $flow->eligible($delivery, 'delivery')->get());
        $this->assertNull($flow->offer($delivery));
        $this->actingAs($this->staff)->patchJson('/logistics/assignments/'.$delivery->id.'/assign', ['courier_id' => $this->rider->id])->assertUnprocessable();
        $this->get('/logistics/assignments')->assertOk()->assertSee('No eligible rider is available for the destination barangay Santa Clara');
        $this->assertNotNull($flow->offer($pickup, 'pickup'));
        $this->configure(['043424001', '043424002']);
        $this->actingAs($this->staff)->patch('/logistics/assignments/'.$delivery->id.'/assign', ['courier_id' => $this->rider->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($delivery->fresh()->courier_id);
        $this->assertSame('offered', $delivery->deliveryAssignment()->firstOrFail()->status);
        $this->assertNull($delivery->deliveryAssignment()->firstOrFail()->accepted_at);
        $flow->accept($this->rider, $delivery->id, 'delivery');
        $this->assertSame('assigned_to_rider', $delivery->fresh()->shipping_status);
    }

    public function test_pickup_page_excludes_wrong_barangay_and_reports_no_available_rider(): void
    {
        $this->configure(['043424002']);
        $pickup = $this->parcel('pickup');
        $this->actingAs($this->staff)->get(route('logistics.dashboard'))->assertOk()->assertSee('No eligible rider is available for the pickup barangay Central');
        $this->patchJson('/logistics/requests/'.$pickup->id.'/assign-pickup', ['courier_id' => $this->rider->id])->assertUnprocessable();
        $this->assertNull(app(RiderDeliveryOffers::class)->offer($pickup, 'pickup'));
        $this->configure(['043424001']);
        $this->actingAs($this->staff)->patch('/logistics/requests/'.$pickup->id.'/assign-pickup', ['courier_id' => $this->rider->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($pickup->fresh()->pickup_rider_id);
    }

    public function test_missing_address_unassigned_coverage_and_stale_hub_coverage_never_fall_back(): void
    {
        $pickup = $this->parcel('pickup');
        $flow = app(RiderDeliveryOffers::class);
        $this->assertNull($flow->offer($pickup, 'pickup'));
        $this->configure(['043424001']);
        DB::table('users')->where('id', $this->seller->id)->update(['barangay' => '']);
        $this->assertNull($flow->offer($pickup->fresh(), 'pickup'));
        DB::table('users')->where('id', $this->seller->id)->update(['barangay' => ' central ']);
        $this->assertNotNull($flow->offer($pickup->fresh(), 'pickup'));
        $other = LogisticsHub::create(['company_name' => $this->hub->company_name, 'municipality' => 'Other Town', 'province' => 'Laguna', 'is_operational' => true]);
        RiderBarangayRoute::where('rider_id', $this->rider->id)->update(['hub_id' => $other->id]);
        $this->assertNull($flow->offer($pickup->fresh(), 'pickup'));
    }

    public function test_coverage_does_not_bypass_existing_rider_eligibility_or_combined_capacity(): void
    {
        $this->configure(['043424001', '043424002']);
        $flow = app(RiderDeliveryOffers::class);
        $pickup = $this->parcel('pickup');
        foreach ([['is_available' => false], ['status' => 'suspended'], ['vehicle_type' => 'four_wheels'], ['vehicle_ownership' => 'company']] as $change) {
            DB::table('users')->where('id', $this->rider->id)->update(array_merge(['status' => 'approved', 'is_available' => true, 'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own'], $change));
            $this->assertNull($flow->offer($pickup, 'pickup'));
        }
        DB::table('users')->where('id', $this->rider->id)->update(['status' => 'approved', 'is_available' => true, 'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own']);
        foreach (['pickup', 'delivery', 'pickup'] as $leg) {
            $parcel = $this->parcel($leg);
            $flow->propose($this->staff, $parcel->id, $this->rider->id, $leg);
            $flow->accept($this->rider, $parcel->id, $leg);
        }
        $this->assertSame(3, $flow->activeJobs($this->rider->id));
        $this->assertNull($flow->offer($pickup, 'pickup'));
    }

    public function test_lookup_failure_preserves_routes_and_exposes_retry_message(): void
    {
        $this->configure(['043424001']);
        Cache::flush();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 503)]);
        $this->get($this->url())->assertOk()->assertSee('Address choices could not be loaded')->assertSee('Retry');
        $this->putJson($this->url(), $this->data(['043424002']))->assertUnprocessable();
        $this->assertDatabaseHas('rider_barangay_routes', ['barangay_code' => '043424001']);
    }

    public function test_audit_failure_rolls_back_coverage_changes(): void
    {
        $this->configure(['043424001']);
        DB::unprepared("CREATE TRIGGER reject_coverage_audit BEFORE INSERT ON logistics_activity_logs BEGIN SELECT RAISE(ABORT, 'coverage audit test'); END");
        $this->putJson($this->url(), $this->data(['043424002']))->assertStatus(500);
        $this->assertDatabaseHas('rider_barangay_routes', ['barangay_code' => '043424001']);
        $this->assertDatabaseCount('rider_barangay_routes', 1);
    }

    public function test_hub_staff_dropdowns_show_only_riders_covering_each_parcel_barangay(): void
    {
        $this->configure(['043424001']);
        $other = $this->person('rider');
        $other->update(['given_names' => 'Destination']);
        $this->coverage->save($this->admin, $other, ['043424002'], $this->hub->id, $this->coverage->version($other));
        $this->parcel('pickup');
        $this->parcel('delivery');
        $this->actingAs($this->staff)->get(route('logistics.dashboard'))->assertOk()
            ->assertSee('<option value="'.$this->rider->id.'">', false)->assertDontSee('<option value="'.$other->id.'">', false);
        $this->get('/logistics/assignments')->assertOk()
            ->assertSee('<option value="'.$other->id.'">', false)->assertDontSee('<option value="'.$this->rider->id.'">', false);
        $this->assertSame($this->rider->id, app(RiderDeliveryOffers::class)->offer($this->parcel('pickup'), 'pickup')->courier_id);
        $this->assertSame($other->id, app(RiderDeliveryOffers::class)->offer($this->parcel('delivery'), 'delivery')->courier_id);
    }

    public function test_region_only_hubs_validate_barangays_without_inventing_a_province(): void
    {
        $this->hub->update(['region' => 'National Capital Region', 'province' => 'National Capital Region', 'municipality' => 'Manila']);
        Http::fake([
            '*/api/regions/' => Http::response([['code' => '130000000', 'name' => 'National Capital Region']]),
            '*/api/regions/130000000/cities-municipalities/' => Http::response([['code' => '133900000', 'name' => 'Manila']]),
            '*/api/cities-municipalities/133900000/barangays/' => Http::response([['code' => '133900001', 'name' => 'Barangay 1']]),
        ]);
        $this->configure(['133900001']);
        $this->assertDatabaseHas('rider_barangay_routes', ['hub_id' => $this->hub->id, 'barangay_code' => '133900001', 'barangay_name' => 'Barangay 1']);
    }
}
