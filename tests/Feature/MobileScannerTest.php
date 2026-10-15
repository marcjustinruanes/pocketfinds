<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\Shipment;
use App\Models\ShipmentHubLeg;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class MobileScannerTest extends TestCase
{
    use WorkflowSchema;

    private User $owner;

    private LogisticsHub $hub;

    private Shipment $parcel;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        (require database_path('migrations/2026_10_14_000003_add_mobile_scanner_api.php'))->up();
        $this->assertSame('array', config('cache.default'));
        Cache::flush();
        Mail::fake();
        $this->hub = LogisticsHub::create(['company_name' => 'Mobile Company', 'municipality' => 'Pila', 'province' => 'Laguna', 'is_operational' => true]);
        $this->owner = $this->person('hub_staff');
        DB::table('orders')->insert(['id' => 'mobile-order', 'order_number' => 'ORDER-MOBILE', 'status' => 'picked_up', 'created_at' => now()]);
        $this->parcel = Shipment::create(['id' => 'mobile-parcel', 'order_id' => 'mobile-order', 'tracking_number' => 'MOBILE-TRACK', 'shipping_status' => 'picked_up',
            'logistics_company' => 'Mobile Company', 'origin_hub' => 'Pila', 'origin_province' => 'Laguna', 'destination_hub' => 'Pila', 'destination_province' => 'Laguna']);
        $this->token = $this->owner->createToken('test', ['scanner'], now()->addHour())->plainTextToken;
    }

    private function person(string $role): User
    {
        return User::create(['email' => uniqid().'@example.test', 'username' => uniqid('mobile'), 'password' => 'Password123!', 'given_names' => 'Mobile', 'last_name' => 'Fixture',
            'account_type' => in_array($role, ['hub_staff', 'admin_logistics']) ? 'logistics' : $role, 'is_admin' => $role === 'admin',
            'is_logistics' => in_array($role, ['hub_staff', 'admin_logistics']), 'logistics_role' => $role === 'hub_staff' ? 'hub_staff' : ($role === 'admin_logistics' ? 'admin' : null),
            'business_name' => 'Mobile Company', 'logistics_hub_id' => $this->hub->id, 'status' => 'approved']);
    }

    private function api(string $path, array $body = [], ?string $token = null)
    {
        auth()->forgetGuards();

        return $this->withToken($token ?? $this->token)->postJson('/api/v1/mobile/'.$path, $body);
    }

    private function action(?string $id = null): array
    {
        return ['operation_id' => $id ?? (string) Str::uuid(), 'code' => 'MOBILE-TRACK', 'action' => 'receive', 'expected_status' => 'picked_up'];
    }

    public function test_login_identity_and_all_six_roles(): void
    {
        $this->postJson('/api/v1/mobile/login', ['email' => $this->owner->email, 'password' => 'wrong'])->assertUnauthorized();
        foreach (['admin', 'buyer', 'seller', 'rider'] as $role) {
            $user = $this->person($role);
            $this->postJson('/api/v1/mobile/login', ['email' => $user->email, 'password' => 'Password123!'])->assertForbidden();
        }
        foreach ([$this->owner, $this->person('admin_logistics')] as $user) {
            $this->travel(1)->minutes();
            $data = $this->postJson('/api/v1/mobile/login', ['email' => $user->email, 'password' => 'Password123!'])->assertOk()->json();
            auth()->forgetGuards();
            $this->withToken($data['token'])->getJson('/api/v1/mobile/me')->assertOk()->assertJsonPath('user.id', (string) $user->id)->assertJsonPath('user.hub_municipality', 'Pila')->assertJsonMissingPath('user.password')->assertJsonPath('offline_actions_enabled', false);
        }
    }

    public function test_scoped_lookup_privacy_and_no_automatic_changes(): void
    {
        $this->api('parcels/lookup', ['code' => 'MOBILE-TRACK'])->assertOk()->assertJsonPath('actions.0.id', 'receive')->assertJsonMissingPath('parcel.buyer_contact')->assertJsonMissingPath('parcel.address');
        $this->assertSame('picked_up', $this->parcel->fresh()->shipping_status);
        $this->assertDatabaseCount('logistics_activity_logs', 0);
        $this->parcel->update(['logistics_company' => 'Foreign']);
        $this->api('parcels/lookup', ['code' => 'MOBILE-TRACK'])->assertNotFound();
        $this->parcel->update(['logistics_company' => 'Mobile Company', 'origin_hub' => 'Cavinti', 'destination_hub' => 'Cavinti']);
        $this->api('parcels/lookup', ['code' => 'MOBILE-TRACK'])->assertNotFound();
    }

    public function test_receive_retries_are_durable_and_payload_changes_conflict(): void
    {
        $body = $this->action();
        $first = $this->api('operations', $body)->assertOk()->json();
        $this->assertSame('sorted', $this->parcel->fresh()->shipping_status);
        $this->api('operations', $body)->assertOk()->assertExactJson($first);
        $this->assertDatabaseCount('logistics_activity_logs', 1);
        $this->assertDatabaseCount('order_status_history', 2);
        $this->assertDatabaseCount('mobile_scanner_operations', 1);
        $body['expected_status'] = 'sorted';
        $this->api('operations', $body)->assertConflict();
        $this->api('operations', $this->action())->assertUnprocessable();
        $body['expected_status'] = 'picked_up';
        $this->parcel->update(['logistics_company' => 'Foreign']);
        $this->api('operations', $body)->assertNotFound();
    }

    public function test_stale_status_and_audit_failure_do_not_mutate(): void
    {
        $body = $this->action();
        $body['expected_status'] = 'sorted';
        $this->api('operations', $body)->assertConflict();
        $this->assertSame('picked_up', $this->parcel->fresh()->shipping_status);
        DB::statement("CREATE TRIGGER fail_mobile_audit BEFORE INSERT ON logistics_activity_logs BEGIN SELECT RAISE(ABORT, 'audit failed'); END");
        $this->api('operations', $this->action())->assertStatus(500);
        $this->assertSame('picked_up', $this->parcel->fresh()->shipping_status);
        $this->assertDatabaseCount('order_status_history', 0);
        $this->assertDatabaseCount('mobile_scanner_operations', 1); // Only the durable stale-status rejection.
    }

    public function test_transfer_receipt_requires_destination_and_rider_confirmation(): void
    {
        $destination = LogisticsHub::create(['company_name' => 'Mobile Company', 'municipality' => 'Cavinti', 'province' => 'Laguna', 'is_operational' => true]);
        $this->parcel->update(['shipping_status' => 'hub_transfer', 'destination_hub' => 'Cavinti']);
        DB::table('orders')->where('id', 'mobile-order')->update(['status' => 'hub_transfer']);
        $leg = ShipmentHubLeg::create(['shipment_id' => $this->parcel->id, 'sequence' => 1, 'leg_type' => 'direct',
            'from_hub' => 'Pila', 'from_province' => 'Laguna', 'to_hub' => 'Cavinti', 'to_province' => 'Laguna',
            'status' => 'in_transit', 'started_at' => now(), 'rider_confirmed_pickup_at' => now()]);
        $body = ['operation_id' => (string) Str::uuid(), 'code' => 'MOBILE-TRACK', 'action' => 'receive_transfer', 'expected_status' => 'hub_transfer'];
        $this->api('operations', $body)->assertUnprocessable();
        DB::table('users')->where('id', $this->owner->id)->update(['logistics_hub_id' => $destination->id]);
        $leg->update(['rider_confirmed_pickup_at' => null]);
        $body['operation_id'] = (string) Str::uuid();
        $this->api('operations', $body)->assertUnprocessable();
        $leg->update(['rider_confirmed_pickup_at' => now()]);
        $body['operation_id'] = (string) Str::uuid();
        $this->api('operations', $body)->assertOk();
        $this->assertSame('completed', $leg->fresh()->status);
        $this->assertSame('sorted', $this->parcel->fresh()->shipping_status);
        $this->api('operations', $body)->assertOk();
        $this->assertDatabaseCount('logistics_activity_logs', 1);
    }

    public function test_revocation_expiry_suspension_closed_hub_and_token_abilities(): void
    {
        $this->api('logout')->assertOk();
        $this->api('parcels/lookup', ['code' => 'MOBILE-TRACK'])->assertUnauthorized();
        $expired = $this->owner->createToken('expired', ['scanner'], now()->subMinute())->plainTextToken;
        $this->api('parcels/lookup', ['code' => 'MOBILE-TRACK'], $expired)->assertUnauthorized();
        $empty = $this->owner->createToken('wrong', ['other'])->plainTextToken;
        $this->api('parcels/lookup', ['code' => 'MOBILE-TRACK'], $empty)->assertUnauthorized();
        $valid = $this->owner->createToken('valid', ['scanner'])->plainTextToken;
        $this->hub->update(['is_operational' => false]);
        $this->api('parcels/lookup', ['code' => 'MOBILE-TRACK'], $valid)->assertForbidden();
        $this->hub->update(['is_operational' => true]);
        DB::table('users')->where('id', $this->owner->id)->update(['status' => 'suspended']);
        $this->api('parcels/lookup', ['code' => 'MOBILE-TRACK'], $valid)->assertForbidden();
        $this->api('logout', [], $valid)->assertOk();
    }

    public function test_scan_sync_is_idempotent_and_never_performs_an_action(): void
    {
        $body = ['operation_id' => (string) Str::uuid(), 'code' => 'MOBILE-TRACK', 'scanned_at' => now()->toIso8601String(), 'company' => 'Mobile Company', 'hub_id' => $this->hub->id];
        $this->api('scans', $body)->assertOk()->assertJsonPath('accepted', true);
        $this->api('scans', $body)->assertOk();
        $this->assertDatabaseCount('mobile_scanner_operations', 1);
        $this->assertDatabaseCount('logistics_activity_logs', 0);
        $this->assertSame('picked_up', $this->parcel->fresh()->shipping_status);
        $body['code'] = 'different';
        $this->api('scans', $body)->assertConflict();
        $body['operation_id'] = (string) Str::uuid();
        $this->api('scans', $body)->assertNotFound();
        $body['operation_id'] = (string) Str::uuid();
        $body['hub_id'] = 999;
        $this->api('scans', $body)->assertConflict();
        $this->api('scans', [])->assertUnprocessable();
    }

    public function test_both_devices_observe_authenticated_disconnect_and_cannot_revive_it(): void
    {
        foreach (['computer', 'phone'] as $closer) {
            $pair = $this->actingAs($this->owner)->postJson('/logistics/scan/phone-sessions')->assertOk()->json();
            $browserId = app('session')->getId();
            $path = 'pair/'.$pair['token'];
            $this->api($path)->assertOk();
            $second = $this->owner->createToken('unpaired phone', ['scanner'])->plainTextToken;
            auth()->forgetGuards();
            $this->withToken($second)->getJson('/api/v1/mobile/'.$path.'/status')->assertForbidden();
            $this->api($path.'/disconnect', [], $second)->assertForbidden();
            if ($closer === 'phone') {
                $this->api($path.'/disconnect')->assertOk()->assertJsonPath('disconnected_by', 'phone');
            } else {
                $this->withCredentials()->withCookie(config('session.cookie'), $browserId)->actingAs($this->owner)->deleteJson($pair['disconnect_url'])->assertOk();
            }
            auth()->forgetGuards();
            $this->withToken($this->token)->getJson('/api/v1/mobile/'.$path.'/status')->assertOk()->assertJsonPath('state', 'disconnected')->assertJsonPath('disconnected_by', $closer);
            $this->api($path.'/disconnect')->assertOk()->assertJsonPath('disconnected_by', $closer);
            $this->withCredentials()->withCookie(config('session.cookie'), $browserId)->actingAs($this->owner)->getJson($pair['events_url'])->assertOk()->assertJsonPath('disconnected_by', $closer)->assertJsonPath('events', []);
            $this->withCookie(config('session.cookie'), 'another-browser')->getJson($pair['events_url'])->assertForbidden();
            $this->api($path)->assertGone();
            $this->api($path.'/codes', ['code' => 'MOBILE-TRACK', 'scan_id' => str_repeat('b', 32)])->assertGone();
        }
        $this->assertSame('picked_up', $this->parcel->fresh()->shipping_status);
        $this->assertDatabaseCount('logistics_activity_logs', 0);
        $this->assertDatabaseCount('mobile_scanner_operations', 0);
    }

    public function test_native_pair_is_bound_to_one_phone_and_owner_and_expires(): void
    {
        $pair = $this->actingAs($this->owner)->postJson('/logistics/scan/phone-sessions')->assertOk()->json();
        $browserId = app('session')->getId();
        $this->api('pair/'.$pair['token'])->assertOk();
        $second = $this->owner->createToken('another phone', ['scanner'])->plainTextToken;
        $this->api('pair/'.$pair['token'], [], $second)->assertConflict();
        $other = $this->person('hub_staff')->createToken('foreign owner', ['scanner'])->plainTextToken;
        $this->api('pair/'.$pair['token'], [], $other)->assertForbidden();
        $data = ['code' => 'MOBILE-TRACK', 'scan_id' => str_repeat('a', 32)];
        $this->api('pair/'.$pair['token'].'/codes', $data)->assertOk()->assertJsonPath('sequence', 1);
        $this->api('pair/'.$pair['token'].'/codes', $data)->assertOk()->assertJsonPath('duplicate', true);
        $this->api('pair/'.$pair['token'].'/codes', $data, $second)->assertForbidden();
        $this->withCredentials()->withCookie(config('session.cookie'), $browserId)->actingAs($this->owner)->deleteJson($pair['disconnect_url'])->assertOk();
        $this->api('pair/'.$pair['token'])->assertGone();
        $this->api('pair/'.$pair['token'].'/codes', $data)->assertGone();
        $pair = $this->actingAs($this->owner)->postJson('/logistics/scan/phone-sessions')->assertOk()->json();
        $this->api('pair/'.$pair['token'])->assertOk();
        $this->travel(11)->minutes();
        $this->api('pair/'.$pair['token'].'/codes', $data)->assertGone();
    }
}
