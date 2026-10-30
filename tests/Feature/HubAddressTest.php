<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class HubAddressTest extends TestCase
{
    use WorkflowSchema;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        Cache::flush();
        Http::preventStrayRequests();
        $this->admin = User::create(['account_type' => 'logistics', 'is_logistics' => true, 'is_admin' => false, 'logistics_role' => 'admin',
            'business_name' => 'Alpha', 'status' => 'approved', 'username' => 'address_admin', 'email' => 'address_admin@example.test', 'password' => 'FixturePassword123']);
        Http::fake([
            'https://psgc.gitlab.io/api/regions/' => Http::response([['code' => '040000000', 'name' => 'CALABARZON']]),
            'https://psgc.gitlab.io/api/provinces/' => Http::response([['code' => '043400000', 'name' => 'Laguna', 'regionCode' => '040000000']]),
            'https://psgc.gitlab.io/api/provinces/043400000/cities-municipalities/' => Http::response([
                ['code' => '043426000', 'name' => 'Santa Cruz'], ['code' => '043425000', 'name' => 'San Pablo']]),
            'https://psgc.gitlab.io/api/cities-municipalities/043426000/barangays/' => Http::response([['code' => '043426001', 'name' => 'Central']]),
            'https://psgc.gitlab.io/api/cities-municipalities/043425000/barangays/' => Http::response([['code' => '043425001', 'name' => 'Other']]),
        ]);
        $this->actingAs($this->admin);
    }

    private function data(): array
    {
        return ['name' => 'Address Test Hub', 'province_code' => '043400000', 'municipality_code' => '043426000', 'barangay_code' => '043426001',
            'address_line' => '12 Main Street', 'contact_no' => '09123456789'];
    }

    public function test_creation_requires_api_codes_and_stores_the_canonical_address(): void
    {
        $data = $this->data() + ['province' => 'Forged Province', 'municipality' => 'Forged Town', 'business_name' => 'Beta'];
        $this->post('/logistics/hubs', $data)->assertRedirect();
        $this->assertDatabaseHas('logistics_hubs', ['company_name' => 'Alpha', 'province' => 'Laguna', 'municipality' => 'Santa Cruz',
            'address' => '12 Main Street, Central, Santa Cruz, Laguna', 'contact_no' => '09123456789']);
        $this->assertDatabaseHas('logistics_activity_logs', ['actor_id' => $this->admin->id, 'action' => 'logistics.hubs.store']);
        $this->post('/logistics/hubs', $data)->assertSessionHasErrors('municipality');
        $this->assertDatabaseCount('logistics_hubs', 1);
    }

    public function test_every_field_and_exact_mobile_format_are_enforced_on_direct_requests(): void
    {
        foreach (array_keys($this->data()) as $field) {
            $data = $this->data();
            unset($data[$field]);
            $this->postJson('/logistics/hubs', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        foreach (['0912345678', '091234567890', '08123456789', '+639123456789', '09abcdefghi', '09123 45678', 9123456789] as $phone) {
            $this->postJson('/logistics/hubs', array_merge($this->data(), ['contact_no' => $phone]))->assertUnprocessable()->assertJsonValidationErrors('contact_no');
        }
        $this->assertDatabaseCount('logistics_hubs', 0);
    }

    public function test_tampered_geography_and_wrong_barangay_are_rejected(): void
    {
        foreach ([['province_code' => '999999999'], ['municipality_code' => '999999999'], ['barangay_code' => '043425001'], ['province_code' => '../users']] as $bad) {
            $this->postJson('/logistics/hubs', array_merge($this->data(), $bad))->assertUnprocessable();
        }
        $this->assertDatabaseCount('logistics_hubs', 0);
    }

    public function test_update_requires_all_details_and_preserves_hub_geography(): void
    {
        $this->post('/logistics/hubs', $this->data())->assertRedirect();
        $hub = LogisticsHub::firstOrFail();
        $this->patchJson('/logistics/hubs/'.$hub->id, ['is_operational' => true])->assertUnprocessable();
        $this->patch('/logistics/hubs/'.$hub->id, array_merge($this->data(), ['name' => 'Updated Hub', 'address_line' => '24 Second Street', 'is_operational' => true]))->assertRedirect();
        $this->assertSame('24 Second Street, Central, Santa Cruz, Laguna', $hub->fresh()->address);
        $this->patchJson('/logistics/hubs/'.$hub->id, array_merge($this->data(), ['municipality_code' => '043425000', 'barangay_code' => '043425001', 'is_operational' => true]))->assertUnprocessable();
        $this->assertSame('Santa Cruz', $hub->fresh()->municipality);
    }

    public function test_api_failure_blocks_writes_and_returns_a_retry_message(): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => Http::response([], 503)]);
        $this->postJson('/logistics/hubs', $this->data())->assertUnprocessable()->assertJsonValidationErrors('address');
        $this->assertDatabaseCount('logistics_hubs', 0);
        $this->getJson('/logistics/hub-locations/provinces')->assertUnprocessable();
    }

    public function test_location_proxy_is_cached_and_scoped_to_logistics_admin(): void
    {
        $this->getJson('/logistics/hub-locations/provinces')->assertOk()->assertJsonPath('0.name', 'Laguna');
        $this->getJson('/logistics/hub-locations/provinces')->assertOk();
        Http::assertSentCount(1);
        $this->getJson('/logistics/hub-locations/barangays/invalid')->assertUnprocessable();
        $foreign = LogisticsHub::create(['company_name' => 'Beta', 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'is_operational' => true]);
        $this->patchJson('/logistics/hubs/'.$foreign->id, $this->data() + ['is_operational' => true])->assertNotFound();
        $ownHub = LogisticsHub::create(['company_name' => 'Alpha', 'province' => 'Laguna', 'municipality' => 'Pila', 'is_operational' => true]);
        foreach (['admin', 'buyer', 'seller', 'rider', 'hub_staff'] as $role) {
            $u = User::create(['account_type' => $role === 'hub_staff' ? 'logistics' : $role, 'is_logistics' => $role === 'hub_staff', 'is_admin' => $role === 'admin',
                'logistics_role' => $role === 'hub_staff' ? 'hub_staff' : null, 'business_name' => 'Alpha', 'logistics_hub_id' => $ownHub->id, 'status' => 'approved', 'password' => 'FixturePassword123']);
            $this->actingAs($u);
            $this->assertContains($this->getJson('/logistics/hub-locations/provinces')->status(), [302, 403]);
        }
    }
    public function test_profile_proxy_requires_approved_logistics_access_and_rejects_invalid_paths(): void
    {
        $this->getJson('/logistics/address-locations/regions')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertJsonPath('0.name', 'CALABARZON');
        $this->getJson('/logistics/address-locations/provinces')->assertOk()->assertJsonPath('0.regionCode', '040000000');
        $this->getJson('/logistics/address-locations/unknown')->assertUnprocessable();
        $this->getJson('/logistics/address-locations/regions/043400000')->assertUnprocessable();
        $this->getJson('/logistics/address-locations/barangays/invalid')->assertUnprocessable();
        $buyer = User::create(['account_type' => 'buyer', 'status' => 'approved', 'password' => 'FixturePassword!123']);
        $this->actingAs($buyer)->getJson('/logistics/address-locations/regions')->assertRedirect(route('login'));
        app('auth')->forgetGuards();
        $this->getJson('/logistics/address-locations/regions')->assertRedirect(route('login'));
    }

    public function test_profile_address_names_are_verified_against_api_hierarchy(): void
    {
        $service = app(\App\Services\HubAddress::class);
        $data = ['region' => 'CALABARZON', 'province' => 'laguna', 'municipality' => 'Santa Cruz', 'barangay' => 'Central'];
        $this->assertSame(['region' => 'CALABARZON', 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'barangay' => 'Central'], $service->validateAccount($data));
        $this->postJson('/logistics/account/address', array_merge($data, ['postal_code' => '4009', 'barangay' => 'Other']))
            ->assertUnprocessable()->assertJsonValidationErrors('address');
        $this->postJson('/logistics/account/address', array_merge($data, ['postal_code' => '4009', 'region' => 'Forged']))
            ->assertUnprocessable()->assertJsonValidationErrors('region');
    }

    public function test_profile_lookup_is_rate_limited_before_additional_upstream_requests(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/logistics/address-locations/regions')->assertOk();
        }
        $this->getJson('/logistics/address-locations/regions')->assertStatus(429);
        Http::assertSentCount(1);
    }

    public function test_malformed_upstream_rows_fail_closed_without_leaking_response(): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => Http::response([['code' => ['invalid'], 'name' => 'Unsafe']])]);
        $this->getJson('/logistics/address-locations/regions')->assertUnprocessable()
            ->assertDontSee('Unsafe')->assertJsonValidationErrors('address');
    }

    public function test_valid_profile_address_is_canonical_and_still_requires_admin_approval(): void
    {
        $this->post('/logistics/account/address', ['region'=>'CALABARZON','province'=>'laguna','municipality'=>'Santa Cruz','barangay'=>'Central','postal_code'=>'4009'])->assertRedirect();
        $change = \App\Models\AccountUpdateRequest::where('user_id', $this->admin->id)->firstOrFail();
        $this->assertSame('pending', $change->status);
        $this->assertSame('Laguna', $change->requested_changes['province']);
        $this->assertSame('Central', $change->requested_changes['barangay']);
    }

    public function test_personnel_barangay_must_belong_to_assigned_hub(): void
    {
        $hub = LogisticsHub::create(['company_name'=>'Alpha','province'=>'Laguna','municipality'=>'Santa Cruz','is_operational'=>true]);
        $this->assertSame('Central', app(\App\Services\HubAddress::class)->validateBarangay($hub, 'central'));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\HubAddress::class)->validateBarangay($hub, 'Other');
    }

}
