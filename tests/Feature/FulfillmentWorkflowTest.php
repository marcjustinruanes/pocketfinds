<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentHubLeg;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class FulfillmentWorkflowTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        (require database_path('migrations/2026_10_16_000001_add_registration_business_details.php'))->up();
        (require database_path('migrations/2026_10_16_000002_add_logistics_registration_details.php'))->up();
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('public');
        Storage::fake('sensitive_documents');
        Storage::fake('messages');
        DB::table('id_types')->insert(['id' => 1, 'name' => "Driver's License"]);
        DB::table('vehicle_types')->insert(['id' => 1, 'slug' => 'two_wheels', 'name' => 'Motorcycle']);
        DB::table('payment_methods')->insert(['id' => 1, 'name' => 'Cash on delivery', 'is_active' => true]);
    }

    private function image(string $name = 'document.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
    }

    private function user(string $role, string $name): User
    {
        return User::create(['account_type' => $role, 'username' => $name, 'email' => $name.'@gmail.com', 'given_names' => 'Fixture', 'last_name' => $name, 'status' => 'approved', 'is_admin' => $role === 'admin', 'is_logistics' => $role === 'logistics', 'logistics_role' => $role === 'logistics' ? 'admin' : null, 'business_name' => $role === 'logistics' ? 'Alpha' : null, 'password' => 'FixturePassword123!', 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'barangay' => 'Central', 'street' => 'Test street', 'shipping_fee' => 20]);
    }

    private function hub(): LogisticsHub
    {
        return LogisticsHub::create(['company_name' => 'Alpha', 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'is_operational' => true, 'is_hiring' => true]);
    }

    private function deliveredFixture(?User $buyer = null, ?User $seller = null, string $suffix = 'DELIVERED'): array
    {
        $buyer ??= $this->user('buyer', 'buyer_'.$suffix);
        $seller ??= $this->user('seller', 'seller_'.$suffix);
        $order = Order::create([
            'order_number' => 'PF-'.$suffix,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'delivered',
            'items' => [],
            'subtotal' => 100,
            'total' => 100,
        ]);
        DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subHours(2), 'updated_at' => now()->subHour()]);
        $shipment = Shipment::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRACK-'.$suffix,
            'shipping_status' => 'delivered',
            'logistics_company' => 'Alpha',
            'origin_hub' => 'Santa Cruz',
            'origin_province' => 'Laguna',
            'destination_hub' => 'Santa Cruz',
            'destination_province' => 'Laguna',
            'delivered_at' => now()->subHour(),
        ]);

        return [$buyer, $seller, $order, $shipment];
    }

    private function registration(string $role, LogisticsHub $hub): array
    {
        $data = ['account_type' => $role, 'logistics_mode' => 'join', 'auth_method' => 'manual', 'username' => 'fixture_'.$role, 'email' => $role.'@gmail.com', 'given_names' => 'New', 'middle_name' => 'Fixture', 'last_name' => ucfirst($role), 'sex' => 'male', 'birthday' => '2000-01-01', 'password' => 'FixturePassword123!', 'password_confirmation' => 'FixturePassword123!', 'contact_no' => ($role === 'rider' ? '09123456789' : '09123456788'), 'region' => 'CALABARZON', 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'barangay' => 'Central', 'business_name' => 'Alpha', 'logistics_hub_id' => $hub->id, 'selfie_file' => $this->image('selfie.png'), 'resume_file' => UploadedFile::fake()->createWithContent('resume.pdf', "%PDF-1.4\nFixture resume")];
        $this->withSession(['registration_verified_email'=>['email'=>$data['email'], 'expires_at'=>now()->addHour()->timestamp]]);
        if ($role === 'rider') {
            return $data + ['nbi_clearance_file' => $this->image('nbi.png'), 'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own', 'vehicle_brand' => 'Fixture', 'vehicle_model' => 'Test', 'plate_number' => 'TEST001', 'license_number' => 'F01-00-000001', 'license_expiry' => '2030-01-01', 'license_file' => $this->image('license.png'), 'or_file' => $this->image('or.png'), 'cr_file' => $this->image('cr.png')];
        }

        return $data + ['id_type_id' => 1, 'id_file' => $this->image('id.png')];
    }

    public function test_public_rider_and_staff_registration_store_documents_and_preserve_pending_roles(): void
    {
        $this->user('logistics', 'company_admin');
        $hub = $this->hub();
        foreach (['rider', 'logistics'] as $role) {
            $this->postJson(route('register.store'), $this->registration($role, $hub))->assertOk()->assertJsonPath('success', true);
            $registered = User::where('username', 'fixture_'.$role)->firstOrFail();
            $this->assertSame('pending', $registered->status);
            $this->assertSame($hub->id, $registered->logistics_hub_id);
            $this->assertSame($role, $registered->account_type);
            $this->assertSame($role === 'logistics' ? 'hub_staff' : null, $registered->logistics_role);
            Storage::disk('sensitive_documents')->assertExists([$registered->id_file, $registered->selfie_file, $registered->resume_file]);
            if ($role === 'rider') {
                $this->assertSame($registered->license_file, $registered->id_file);
                Storage::disk('sensitive_documents')->assertExists([$registered->license_file, $registered->nbi_clearance_file, $registered->or_file, $registered->cr_file]);
            }
        }
        $this->assertCount(9, Storage::disk('sensitive_documents')->allFiles());
        Mail::assertNothingSent();
    }

    public function test_registration_failure_after_upload_removes_files_and_invalid_hub_never_uploads(): void
    {
        $this->user('logistics', 'company_admin');
        $hub = $this->hub();
        DB::unprepared("CREATE TRIGGER reject_registration BEFORE INSERT ON users WHEN NEW.username LIKE 'fixture_%' BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        $this->postJson(route('register.store'), $this->registration('rider', $hub))->assertStatus(500);
        $this->assertCount(0, Storage::disk('sensitive_documents')->allFiles());
        $this->assertFalse(User::where('username', 'fixture_rider')->exists());
        $hub->update(['is_operational' => false]);
        $this->postJson(route('register.store'), $this->registration('logistics', $hub))->assertUnprocessable();
        $this->assertCount(0, Storage::disk('sensitive_documents')->allFiles());
    }

    private function checkoutFixture(): array
    {
        $buyer = $this->user('buyer', 'buyer_fixture');
        $seller = $this->user('seller', 'seller_fixture');
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Fixture parcel', 'status' => 'active', 'price' => 100, 'stock' => 10]);
        $item = CartItem::create(['buyer_id' => $buyer->id, 'product_id' => $product->id, 'seller_slug' => $seller->username, 'seller' => 'Fixture shop', 'name' => $product->name, 'price' => 100, 'qty' => 2, 'variation_group' => '', 'variation_value' => '']);

        return [$buyer, $seller, $product, $item];
    }

    public function test_checkout_seller_confirmation_preparation_and_logistics_handoff(): void
    {
        [$buyer, $seller, $product, $item] = $this->checkoutFixture();
        $this->hub();
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['items' => [$item->id], 'payment_method' => 1])->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame('placed', $order->status);
        $this->assertEquals(220, $order->total);
        $this->assertSame(10, $product->fresh()->total_stock);
        $this->assertFalse(CartItem::whereKey($item->id)->exists());
        $this->actingAs($buyer)->patchJson(route('seller.orders.confirm', $order))->assertForbidden();
        $this->actingAs($seller)->patch(route('seller.orders.confirm', $order))->assertRedirect();
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->total_stock);
        $this->patchJson(route('seller.orders.confirm', $order))->assertRedirect()->assertSessionHas('error');
        $this->assertSame(8, $product->fresh()->total_stock);
        $this->patch(route('seller.orders.preparing', $order))->assertRedirect();
        $this->patch(route('seller.orders.ready', $order), ['logistics_company' => 'Alpha'])->assertRedirect();
        $this->assertSame('ready_for_pickup', $order->fresh()->status);
        $this->assertSame('Alpha', $order->fresh()->shipment->logistics_company);
        $this->assertSame('Laguna', $order->fresh()->shipment->origin_province);
        $this->assertSame(4, DB::table('order_status_history')->where('order_id', $order->id)->count());
        $buyerEvents = DB::table('notifications')->where('user_id', $buyer->id)->where('reference_id', $order->id)->pluck('title')->all();
        $this->assertEqualsCanonicalizing(['Order Confirmed', 'Order Being Prepared', 'Parcel Ready for Pickup'], $buyerEvents);

        $pickupAt = now()->addDay()->format('Y-m-d\\TH:i');
        $this->patch(route('seller.orders.schedule-pickup', $order), ['scheduled_pickup_at' => $pickupAt])->assertRedirect();
        $this->patch(route('seller.orders.schedule-pickup', $order), ['scheduled_pickup_at' => $pickupAt])->assertRedirect();
        $this->assertSame(1, DB::table('notifications')->where('user_id', $buyer->id)->where('reference_id', $order->id)->where('title', 'Pickup Scheduled')->count());
    }

    public function test_seller_pickup_request_flows_through_logistics_approval_rider_and_hub_receipt(): void
    {
        [$buyer, $seller, $product, $item] = $this->checkoutFixture();
        $hub = $this->hub();
        $logisticsAdmin = $this->user('logistics', 'pickup_carrier_admin');
        $rider = User::create([
            'account_type' => 'rider', 'username' => 'pickup_workflow_rider', 'email' => 'pickup_workflow_rider@gmail.com',
            'given_names' => 'Pickup', 'last_name' => 'Rider', 'status' => 'approved', 'business_name' => 'Alpha',
            'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own', 'logistics_hub_id' => $hub->id,
        ]);
        $rider->forceFill(['is_available' => true])->save();
        $hubStaff = User::create([
            'account_type' => 'logistics', 'username' => 'pickup_workflow_staff', 'email' => 'pickup_workflow_staff@gmail.com',
            'given_names' => 'Hub', 'last_name' => 'Staff', 'status' => 'approved', 'is_logistics' => true,
            'logistics_role' => 'hub_staff', 'business_name' => 'Alpha', 'logistics_hub_id' => $hub->id,
        ]);

        $this->actingAs($buyer)->post(route('buyer.checkout'), ['items' => [$item->id], 'payment_method' => 1])->assertRedirect();
        $order = Order::firstOrFail();
        $this->actingAs($seller)->patch(route('seller.orders.confirm', $order))->assertRedirect();
        $this->patch(route('seller.orders.preparing', $order))->assertRedirect();
        $this->patch(route('seller.orders.ready', $order), ['logistics_company' => 'Alpha'])->assertRedirect();

        $shipment = Shipment::where('order_id', $order->id)->sole();
        $this->assertSame('ready_for_pickup', $order->fresh()->status);
        $this->assertSame('ready_for_pickup', $shipment->shipping_status);
        $this->assertNull($shipment->pickup_approved_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $logisticsAdmin->id, 'title' => 'New Seller Pickup Request', 'reference_id' => $order->id,
        ]);

        $this->actingAs($logisticsAdmin)->get(route('logistics.requests'))->assertOk()->assertSee($shipment->tracking_number);
        $this->actingAs($rider)->get(route('rider.pickup-requests'))->assertOk()->assertDontSee($shipment->tracking_number);

        $this->actingAs($logisticsAdmin)->patch(route('logistics.requests.approve', $shipment->id))->assertRedirect();
        $this->assertNotNull($shipment->fresh()->pickup_approved_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $seller->id, 'title' => 'Pickup Approved', 'reference_id' => $order->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $buyer->id, 'title' => 'Pickup Approved', 'reference_id' => $order->id,
        ]);
        $this->actingAs($rider)->get(route('rider.pickup-requests'))->assertOk()->assertSee($shipment->tracking_number);
        $this->patch(route('rider.pickup-requests.accept', $shipment->id))->assertRedirect();
        $this->assertSame($rider->id, $shipment->fresh()->pickup_rider_id);

        $this->actingAs($rider)->patch(route('rider.my-pickups.confirm', $shipment->id), [
            'tracking_number' => $shipment->tracking_number,
        ])->assertRedirect();
        $this->actingAs($seller)->patch(route('seller.orders.confirm-pickup', $order))->assertRedirect();
        $this->assertSame('picked_up', $shipment->fresh()->shipping_status);
        $this->assertSame('picked_up', $order->fresh()->status);

        $this->actingAs($hubStaff)->patch(route('logistics.scan.receive', $shipment->id))->assertOk();
        $this->assertSame('sorted', $shipment->fresh()->shipping_status);
        $this->assertSame('sorted', $order->fresh()->status);
    }

    public function test_buyer_tracking_shows_only_recorded_events_and_linked_transfer_details(): void
    {
        $buyer = $this->user('buyer', 'tracking_owner');
        $seller = $this->user('seller', 'tracking_seller');
        $hub = $this->hub();
        $pickupRider = User::create(['account_type' => 'rider', 'username' => 'tracking_pickup_rider', 'given_names' => 'Pickup', 'last_name' => 'Courier', 'status' => 'approved', 'business_name' => 'Alpha', 'logistics_hub_id' => $hub->id]);
        $order = Order::create([
            'order_number' => 'PF-TRACKING-LIVE', 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'status' => 'hub_transfer', 'items' => [], 'created_at' => now()->subDays(3), 'updated_at' => now()->subHour(),
        ]);
        $shipment = Shipment::create([
            'order_id' => $order->id, 'tracking_number' => 'PF-LIVE-TRACK-001', 'shipping_status' => 'hub_transfer',
            'logistics_company' => 'Alpha', 'origin_hub' => 'Santa Cruz', 'origin_province' => 'Laguna',
            'destination_hub' => 'Manila', 'destination_province' => 'Metro Manila', 'pickup_rider_id' => $pickupRider->id,
            'pickup_approved_at' => now()->subDays(2)->subMinutes(5), 'picked_up_at' => now()->subDays(2),
            'at_sorting_center_at' => now()->subDays(1), 'hub_transfer_started_at' => now()->subHours(2),
        ]);
        foreach ([
            ['placed', now()->subDays(3)], ['confirmed', now()->subDays(3)->addMinutes(10)],
            ['preparing', now()->subDays(3)->addMinutes(20)], ['ready_for_pickup', now()->subDays(2)->subMinutes(10)],
            ['picked_up', now()->subDays(2)], ['at_sorting_center', now()->subDays(1)], ['hub_transfer', now()->subHours(2)],
        ] as [$status, $at]) {
            DB::table('order_status_history')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'order_id' => $order->id, 'status' => $status, 'changed_by' => null, 'created_at' => $at]);
        }
        ShipmentHubLeg::create([
            'shipment_id' => $shipment->id, 'sequence' => 1, 'leg_type' => 'dispatch',
            'from_hub' => 'Santa Cruz', 'from_province' => 'Laguna', 'to_hub' => 'Manila', 'to_province' => 'Metro Manila',
            'rider_id' => $pickupRider->id, 'status' => 'completed', 'started_at' => now()->subHours(4), 'completed_at' => now()->subHours(3),
        ]);
        ShipmentHubLeg::create([
            'shipment_id' => $shipment->id, 'sequence' => 2, 'leg_type' => 'relay',
            'from_hub' => 'Manila', 'from_province' => 'Metro Manila', 'to_hub' => 'Manila', 'to_province' => 'Metro Manila',
            'status' => 'in_transit', 'started_at' => now()->subHours(2),
        ]);

        $this->actingAs($buyer)->get(route('buyer.orders'))->assertOk()
            ->assertSee($shipment->tracking_number)
            ->assertSee('Alpha')
            ->assertSee('Pickup Courier')
            ->assertSee('Courier: Pickup Courier')
            ->assertSee('Hub transfer progress')
            ->assertSee('Santa Cruz, Laguna')
            ->assertSee('In transit')
            ->assertSeeInOrder(['Order placed', 'Seller confirmed', 'Preparing', 'Ready for pickup', 'Pickup approved', 'Picked up', 'At sorting center', 'Hub transfer'])
            ->assertDontSee('Out for delivery')
            ->assertDontSee('Order delivered')
            ->assertDontSee('Order completed');
    }

    public function test_buyer_delivery_proof_and_order_details_are_owner_scoped(): void
    {
        [$buyer, $seller, $order, $shipment] = $this->deliveredFixture(suffix: 'PROOFOWNER');
        $path = 'delivery_proofs/private-owner-proof.png';
        Storage::disk('sensitive_documents')->put($path, 'private proof bytes');
        $shipment->update(['delivery_proof_file' => $path]);
        $otherBuyer = $this->user('buyer', 'proof_other_buyer');
        Order::create(['order_number' => 'PF-OTHER-PRIVATE', 'buyer_id' => $otherBuyer->id, 'seller_id' => $seller->id, 'status' => 'placed', 'items' => []]);

        $timeline = \App\Support\OrderTimeline::forOrder($order->fresh()->load('shipment'), []);
        $this->assertSame(['placed', 'delivered'], array_column($timeline['rows'], 'status'));
        $this->actingAs($buyer)->get(route('buyer.orders'))->assertOk()->assertSee($order->order_number)->assertDontSee('PF-OTHER-PRIVATE')
            ->assertSeeInOrder(['Order placed', 'Order delivered'])
            ->assertDontSee('Order completed');
        $proof = $this->get(route('buyer.orders.delivery-proof', $order));
        $proof->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('private proof bytes', $proof->streamedContent());
        $this->actingAs($otherBuyer)->get(route('buyer.orders.delivery-proof', $order))->assertForbidden();
        $this->get(route('delivery-proofs.show', $shipment))->assertNotFound();
    }

    public function test_buyer_receipt_completion_updates_order_shipment_history_and_notifications_once(): void
    {
        [$buyer, $seller, $order, $shipment] = $this->deliveredFixture(suffix: 'BUYERDONE');

        $this->actingAs($buyer)->patch(route('buyer.orders.confirm-receipt', $order))->assertRedirect(route('buyer.orders', ['tab' => 'completed']));

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('completed', $shipment->fresh()->shipping_status);
        $this->assertNotNull($shipment->fresh()->delivered_at);
        $this->assertSame(1, DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'completed')->count());
        $this->assertSame(1, DB::table('notifications')->where('user_id', $buyer->id)->where('reference_id', $order->id)->where('title', 'Order Completed')->count());
        $this->assertSame(1, DB::table('notifications')->where('user_id', $seller->id)->where('reference_id', $order->id)->where('title', 'Order Completed')->count());
        $this->get(route('buyer.orders'))->assertOk()->assertSeeInOrder(['Order delivered', 'Order completed']);

        $this->actingAs($buyer)->patch(route('buyer.orders.confirm-receipt', $order))->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'completed')->count());
        $this->assertSame(2, DB::table('notifications')->where('reference_id', $order->id)->where('title', 'Order Completed')->count());
    }

    public function test_scheduled_auto_completion_updates_both_records_and_skips_cancelled_or_returned_orders(): void
    {
        [$buyer, $seller, $order, $shipment] = $this->deliveredFixture(suffix: 'AUTODONE');
        DB::table('orders')->where('id', $order->id)->update(['updated_at' => now()->subDays(Order::AUTO_COMPLETE_DAYS + 1)]);
        $cancelled = Order::create(['order_number' => 'PF-CANCEL-SKIP', 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'cancelled', 'items' => [], 'total' => 0]);
        $returned = Order::create(['order_number' => 'PF-RETURN-SKIP', 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'returned', 'items' => [], 'total' => 0]);
        DB::table('orders')->whereIn('id', [$cancelled->id, $returned->id])->update(['updated_at' => now()->subDays(Order::AUTO_COMPLETE_DAYS + 1)]);

        $this->assertSame(1, Order::autoCompleteDelivered());
        $this->assertSame(0, Order::autoCompleteDelivered());
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('completed', $shipment->fresh()->shipping_status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('returned', $returned->fresh()->status);
        $this->assertSame(1, DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'completed')->count());
        $this->assertSame(2, DB::table('notifications')->where('reference_id', $order->id)->where('title', 'Order Completed')->count());
    }

    public function test_logistics_admin_completion_uses_the_same_synchronized_transition(): void
    {
        [$buyer, $seller, $order, $shipment] = $this->deliveredFixture(suffix: 'LOGDONE');
        $admin = $this->user('logistics', 'logistics_complete');

        $this->actingAs($admin)->patch(route('logistics.status.update', $shipment->id), [
            'status' => 'completed',
            'reason' => 'Verified completion after delivery review',
        ])->assertRedirect();

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('completed', $shipment->fresh()->shipping_status);
        $this->assertSame(1, DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'completed')->count());
        $this->assertSame(2, DB::table('notifications')->where('reference_id', $order->id)->where('title', 'Order Completed')->count());

        $this->actingAs($admin)->patch(route('logistics.status.update', $shipment->id), [
            'status' => 'completed',
            'reason' => 'Repeat completion attempt should be rejected',
        ])->assertRedirect()->assertSessionHasErrors('status');
        $this->assertSame(1, DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'completed')->count());
    }

    public function test_buyer_cannot_complete_another_buyers_order_or_a_non_delivered_shipment(): void
    {
        [$buyer, $seller, $order, $shipment] = $this->deliveredFixture(suffix: 'GUARDED');
        $otherBuyer = $this->user('buyer', 'other_buyer_guarded');
        $this->actingAs($otherBuyer)->patch(route('buyer.orders.confirm-receipt', $order))->assertForbidden();

        $shipment->update(['shipping_status' => 'cancelled']);
        $this->actingAs($buyer)->patch(route('buyer.orders.confirm-receipt', $order))->assertRedirect()->assertSessionHas('error');
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('cancelled', $shipment->fresh()->shipping_status);
        $this->assertSame(0, DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'completed')->count());
        $this->assertSame(0, DB::table('notifications')->where('reference_id', $order->id)->where('title', 'Order Completed')->count());
    }

    public function test_checkout_rejects_foreign_addresses_and_rolls_back_failed_order_creation(): void
    {
        [$buyer, $seller, $product, $item] = $this->checkoutFixture();
        $foreign = DB::table('buyer_addresses')->insertGetId(['buyer_id' => $seller->id, 'province' => 'Laguna']);
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['items' => [$item->id], 'payment_method' => 1, 'delivery_address_id' => $foreign])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, Order::count());
        DB::unprepared("CREATE TRIGGER reject_notification BEFORE INSERT ON notifications BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        $this->postJson(route('buyer.checkout'), ['items' => [$item->id], 'payment_method' => 1])->assertRedirect()->assertSessionHas('error', 'Unable to submit your order right now. Please try again.');
        $this->assertSame(0, Order::count());
        $this->assertSame(0, DB::table('order_status_history')->count());
        $this->assertTrue(CartItem::whereKey($item->id)->exists());
        $this->assertSame(10, $product->fresh()->total_stock);
    }

    public function test_failed_seller_confirmation_rolls_back_stock_and_hides_database_details(): void
    {
        [$buyer, $seller, $product, $item] = $this->checkoutFixture();
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['items' => [$item->id], 'payment_method' => 1])->assertRedirect();
        $order = Order::firstOrFail();
        DB::unprepared("CREATE TRIGGER reject_history BEFORE INSERT ON order_status_history BEGIN SELECT RAISE(ABORT, 'private database detail'); END");
        $this->actingAs($seller)->patch(route('seller.orders.confirm', $order))->assertRedirect()->assertSessionHas('error', 'Unable to confirm this order right now. Please try again.');
        $this->assertSame('placed', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->total_stock);
        $this->assertSame(1, DB::table('order_status_history')->count());
    }

    public function test_buyer_cancellation_restock_is_idempotent_and_generic_return_cannot_restock(): void
    {
        [$buyer, $seller, $product, $item] = $this->checkoutFixture();
        $hub = $this->hub();
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['items' => [$item->id], 'payment_method' => 1])->assertRedirect();
        $order = Order::firstOrFail();
        $this->actingAs($seller)->patch(route('seller.orders.confirm', $order))->assertRedirect();
        $this->actingAs($buyer)->patch(route('buyer.orders.cancel', $order), ['cancellation_reason' => 'Changed my mind'])->assertRedirect();
        $this->assertSame(10, $product->fresh()->total_stock);
        $this->patch(route('buyer.orders.cancel', $order), ['cancellation_reason' => 'Changed my mind'])->assertRedirect();
        $this->assertSame(10, $product->fresh()->total_stock);
        // Failed delivery alone does not establish authenticated Seller receipt.
        $product->update(['stock' => 8]);
        $order->update(['status' => 'delivery_failed']);
        $shipment = Shipment::create(['order_id' => $order->id, 'tracking_number' => 'FIXTURE-RETURN', 'shipping_status' => 'delivery_failed', 'logistics_company' => 'Alpha', 'origin_hub' => $hub->municipality, 'origin_province' => $hub->province, 'destination_hub' => $hub->municipality, 'destination_province' => $hub->province]);
        $admin = $this->user('logistics', 'logistics_fixture');
        $this->actingAs($admin)->patchJson(route('logistics.status.update', $shipment->id), ['status' => 'returned'])->assertStatus(422);
        $this->assertSame('delivery_failed', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->total_stock);
        $this->patchJson(route('logistics.status.update', $shipment->id), ['status' => 'returned'])->assertStatus(422);
        $this->assertSame(8, $product->fresh()->total_stock);
    }

    public function test_admin_approval_notification_is_deferred_until_commit_and_discarded_on_rollback(): void
    {
        $admin = $this->user('admin', 'platform_fixture');
        $seller = $this->user('seller', 'seller_fixture');
        $seller->update(['status' => 'pending']);
        Mail::shouldReceive('send')->once()->withArgs(fn ($view, $data, $callback) => $view === 'emails.account-status' && $data['status'] === 'approved' && DB::transactionLevel() === 0);
        $this->actingAs($admin);
        DB::beginTransaction();
        $this->patch(route('admin.registrations.approve', $seller))->assertRedirect();
        DB::rollBack();
        $this->assertSame('pending', $seller->fresh()->status);
        DB::beginTransaction();
        $this->patch(route('admin.registrations.approve', $seller))->assertRedirect();
        DB::commit();
        $this->assertSame('approved', $seller->fresh()->status);
    }
}
