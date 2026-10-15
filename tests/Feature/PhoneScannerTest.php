<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class PhoneScannerTest extends TestCase
{
    use WorkflowSchema;

    private LogisticsHub $hub;

    private User $owner;

    private Shipment $parcel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        $this->assertSame('array', config('cache.default'));
        Cache::flush();
        Http::preventStrayRequests();
        Mail::fake();
        $this->hub = LogisticsHub::create(['company_name' => 'Phone Company', 'municipality' => 'Pila', 'province' => 'Laguna', 'is_operational' => true]);
        $this->owner = $this->person('hub_staff');
        DB::table('orders')->insert(['id' => 'phone-order', 'order_number' => 'ORDER-PHONE', 'status' => 'sorted', 'created_at' => now()]);
        $this->parcel = Shipment::create(['id' => 'phone-parcel', 'order_id' => 'phone-order', 'tracking_number' => 'PHONE-TRACK', 'shipping_status' => 'sorted',
            'logistics_company' => $this->hub->company_name, 'origin_hub' => 'Pila', 'origin_province' => 'Laguna', 'destination_hub' => 'Pila', 'destination_province' => 'Laguna']);
    }

    private function person(string $role, ?LogisticsHub $hub = null): User
    {
        $hub ??= $this->hub;

        return User::create(['email' => uniqid().'@example.test', 'username' => uniqid('phone'), 'given_names' => 'Phone', 'last_name' => 'Fixture', 'password' => 'PhonePassword123',
            'account_type' => in_array($role, ['hub_staff', 'logistics_admin']) ? 'logistics' : $role, 'is_admin' => $role === 'admin',
            'is_logistics' => in_array($role, ['hub_staff', 'logistics_admin']), 'logistics_role' => $role === 'hub_staff' ? 'hub_staff' : ($role === 'logistics_admin' ? 'admin' : null),
            'business_name' => $hub->company_name, 'logistics_hub_id' => $hub->id, 'status' => 'approved']);
    }

    private function pair(): array
    {
        $pair = $this->actingAs($this->owner)->postJson('/logistics/scan/phone-sessions')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
        $this->withCredentials()->withCookie(config('session.cookie'), app('session')->getId());

        return $pair;
    }

    private function send(array $pair, string $code = 'PHONE-TRACK', ?string $id = null)
    {
        return $this->postJson($pair['url'].'/codes', ['code' => $code, 'scan_id' => $id ?? str_repeat('a', 32)]);
    }

    public function test_phone_codes_reach_computer_in_order_without_mutating_parcels(): void
    {
        $pair = $this->pair();
        $this->assertSame(64, strlen($pair['token']));
        $this->get($pair['url'])->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('PocketFinds Scanner for Android');
        $this->send($pair)->assertOk()->assertJsonPath('sequence', 1);
        $this->send($pair, 'ORDER-PHONE', str_repeat('b', 32))->assertOk()->assertJsonPath('sequence', 2);
        $this->getJson($pair['events_url'])->assertOk()->assertJsonCount(2, 'events')->assertJsonPath('events.0.code', 'PHONE-TRACK')->assertJsonPath('events.1.code', 'ORDER-PHONE');
        $this->postJson($pair['ack_url'], ['sequence' => 1])->assertOk();
        $this->getJson($pair['events_url'])->assertJsonCount(1, 'events')->assertJsonPath('events.0.sequence', 2);
        $this->postJson($pair['ack_url'], ['sequence' => 2])->assertOk();
        $this->getJson($pair['events_url'])->assertJsonCount(0, 'events');
        $this->assertSame('sorted', $this->parcel->fresh()->shipping_status);
        $this->assertSame('sorted', DB::table('orders')->where('id', 'phone-order')->value('status'));
        $this->assertSame(0, DB::table('logistics_activity_logs')->count());
        $this->assertSame(0, DB::table('order_status_history')->count());
    }

    public function test_retry_ids_are_idempotent_and_cannot_be_reused_for_a_different_code(): void
    {
        $pair = $this->pair();
        $this->send($pair)->assertOk()->assertJsonPath('duplicate', false);
        $this->send($pair)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('sequence', 1);
        $this->send($pair, 'ORDER-PHONE')->assertUnprocessable();
        $this->postJson($pair['ack_url'], ['sequence' => 1])->assertOk();
        $this->send($pair)->assertOk()->assertJsonPath('duplicate', true);
        $this->getJson($pair['events_url'])->assertJsonCount(0, 'events');
        $this->postJson($pair['ack_url'], ['sequence' => 999])->assertUnprocessable();
    }

    public function test_connection_cannot_be_used_by_another_user_even_at_the_same_hub(): void
    {
        $pair = $this->pair();
        $this->actingAs($this->person('hub_staff'));
        $this->get($pair['url'])->assertForbidden();
        $this->getJson($pair['events_url'])->assertForbidden();
        $this->send($pair)->assertForbidden();
        $this->postJson($pair['ack_url'], ['sequence' => 1])->assertForbidden();
        $this->deleteJson($pair['disconnect_url'])->assertForbidden();
        $this->actingAs($this->owner)->getJson($pair['events_url'])->assertOk()->assertJsonCount(0, 'events');
    }

    public function test_staff_scope_and_company_scope_are_checked_for_each_code(): void
    {
        $other = LogisticsHub::create(['company_name' => 'Phone Company', 'municipality' => 'Cavinti', 'province' => 'Laguna', 'is_operational' => true]);
        $foreign = LogisticsHub::create(['company_name' => 'Foreign', 'municipality' => 'Pila', 'province' => 'Laguna', 'is_operational' => true]);
        foreach ([$other, $foreign] as $hub) {
            Shipment::create(['id' => 'outside-'.$hub->id, 'tracking_number' => 'OUTSIDE-'.$hub->id, 'shipping_status' => 'sorted', 'logistics_company' => $hub->company_name,
                'origin_hub' => $hub->municipality, 'origin_province' => $hub->province, 'destination_hub' => $hub->municipality, 'destination_province' => $hub->province]);
        }
        $pair = $this->pair();
        $this->send($pair, 'OUTSIDE-'.$other->id)->assertNotFound();
        $this->send($pair, 'OUTSIDE-'.$foreign->id)->assertNotFound();
        $this->owner = $this->person('logistics_admin');
        $adminPair = $this->pair();
        $this->send($adminPair, 'OUTSIDE-'.$other->id)->assertOk();
        $this->send($adminPair, 'OUTSIDE-'.$foreign->id, str_repeat('c', 32))->assertNotFound();
    }

    public function test_expiry_disconnection_validation_and_bounded_queue(): void
    {
        $pair = $this->pair();
        $this->postJson($pair['url'].'/codes', ['code' => str_repeat('x', 101), 'scan_id' => 'invalid'])->assertUnprocessable();
        for ($i = 0; $i < 20; $i++) {
            $this->send($pair, 'PHONE-TRACK', str_pad(dechex($i), 32, '0', STR_PAD_LEFT))->assertOk();
        }
        $this->send($pair, 'PHONE-TRACK', str_repeat('f', 32))->assertStatus(429);
        $this->getJson($pair['events_url'])->assertJsonCount(20, 'events');
        $this->deleteJson($pair['disconnect_url'])->assertOk();
        $this->send($pair)->assertGone();
        $this->getJson($pair['events_url'])->assertOk()->assertJsonPath('state', 'disconnected');
        $another = $this->pair();
        $this->travel(11)->minutes();
        $this->getJson($another['events_url'])->assertGone();
    }

    public function test_all_six_roles_and_suspended_staff_keep_existing_access_rules(): void
    {
        foreach (['admin', 'buyer', 'seller', 'rider'] as $role) {
            $response = $this->actingAs($this->person($role))->postJson('/logistics/scan/phone-sessions');
            $this->assertContains($response->status(), [302, 403]);
        }
        DB::table('users')->where('id', $this->owner->id)->update(['status' => 'suspended', 'status_reason' => 'Fixture suspension']);
        $this->owner->refresh();
        $this->actingAs($this->owner)->postJson('/logistics/scan/phone-sessions')->assertForbidden();
    }

    public function test_anonymous_users_and_closed_hubs_cannot_use_a_connection(): void
    {
        $pair = $this->pair();
        auth()->logout();
        $this->get($pair['url'])->assertRedirect(route('login'));
        $this->send($pair)->assertRedirect(route('login'));
        $this->actingAs($this->owner);
        $this->hub->update(['is_operational' => false]);
        $this->actingAs($this->owner->fresh());
        $this->getJson($pair['events_url'])->assertForbidden();
        $this->send($pair)->assertForbidden();
    }

    public function test_reassignment_invalidates_connection_and_usb_preference_keeps_phone_option(): void
    {
        $pair = $this->pair();
        $this->owner->update(['preferred_scanner' => 'usb']);
        $this->get('/logistics/scan')->assertOk()->assertSee('Connect phone scanner')->assertSee('Workstation scanner')->assertSee('id="startCamera"', false);
        $this->assertSame('usb', $this->owner->fresh()->preferred_scanner);
        $other = LogisticsHub::create(['company_name' => 'Phone Company', 'municipality' => 'Cavinti', 'province' => 'Laguna', 'is_operational' => true]);
        $this->owner->update(['logistics_hub_id' => $other->id]);
        $this->getJson($pair['events_url'])->assertForbidden();
    }

    public function test_another_browser_session_of_the_same_owner_cannot_collect_codes(): void
    {
        $pair = $this->pair();
        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->getJson($pair['events_url'])->assertForbidden();
        $this->deleteJson($pair['disconnect_url'])->assertForbidden();
    }
}
