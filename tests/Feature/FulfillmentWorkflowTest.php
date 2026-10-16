<?php

namespace Tests\Feature;

use App\Models\BuyerPaymentAccount;
use App\Models\CartItem;
use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('public');
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
        return User::create(['account_type' => $role, 'username' => $name, 'email' => $name.'@gmail.com', 'given_names' => 'Fixture', 'last_name' => $name, 'status' => 'approved', 'is_admin' => $role === 'admin', 'is_logistics' => $role === 'logistics', 'logistics_role' => $role === 'logistics' ? 'admin' : null, 'business_name' => $role === 'logistics' ? 'Alpha' : null, 'password' => 'FixturePassword123', 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'barangay' => 'Central', 'street' => 'Test street', 'shipping_fee' => 20]);
    }

    private function hub(): LogisticsHub
    {
        return LogisticsHub::create(['company_name' => 'Alpha', 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'is_operational' => true, 'is_hiring' => true]);
    }

    private function registration(string $role, LogisticsHub $hub): array
    {
        $data = ['account_type' => $role, 'logistics_mode' => 'join', 'auth_method' => 'manual', 'username' => 'fixture_'.$role, 'email' => $role.'@gmail.com', 'given_names' => 'New', 'last_name' => ucfirst($role), 'sex' => 'male', 'birthday' => '2000-01-01', 'password' => 'FixturePassword123', 'password_confirmation' => 'FixturePassword123', 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'barangay' => 'Central', 'business_name' => 'Alpha', 'logistics_hub_id' => $hub->id, 'selfie_file' => $this->image('selfie.png'), 'resume_file' => $this->image('resume.png')];
        if ($role === 'rider') {
            return $data + ['vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own', 'vehicle_brand' => 'Fixture', 'vehicle_model' => 'Test', 'plate_number' => 'TEST001', 'license_number' => 'TEST-LICENSE', 'license_expiry' => '2030-01-01', 'license_file' => $this->image('license.png'), 'or_file' => $this->image('or.png'), 'cr_file' => $this->image('cr.png')];
        }

        return $data + ['id_type_id' => 1, 'id_file' => $this->image('id.png')];
    }

    public function test_public_rider_registration_is_denied_and_staff_application_stays_pending(): void
    {
        $this->user('logistics', 'company_admin');
        $hub = $this->hub();
        foreach (['rider', 'logistics'] as $role) {
            if ($role === 'rider') {
                $this->postJson(route('register.store'), $this->registration($role, $hub))->assertForbidden();
                continue;
            }
            $this->postJson(route('register.store'), $this->registration($role, $hub))->assertOk()->assertJsonPath('success', true);
            $registered = User::where('username', 'fixture_'.$role)->firstOrFail();
            $this->assertSame('pending', $registered->status);
            $this->assertSame($hub->id, $registered->logistics_hub_id);
            $this->assertSame($role, $registered->account_type);
            $this->assertSame($role === 'logistics' ? 'hub_staff' : null, $registered->logistics_role);
            Storage::disk('public')->assertExists([$registered->id_file, $registered->selfie_file, $registered->resume_file]);
            if ($role === 'rider') {
                $this->assertSame($registered->license_file, $registered->id_file);
                Storage::disk('public')->assertExists([$registered->license_file, $registered->or_file, $registered->cr_file]);
            }
        }
        $this->assertCount(3, Storage::disk('public')->allFiles());
        Mail::assertNothingSent();
    }

    public function test_registration_failure_after_upload_removes_files_and_invalid_hub_never_uploads(): void
    {
        $this->user('logistics', 'company_admin');
        $hub = $this->hub();
        DB::unprepared("CREATE TRIGGER reject_registration BEFORE INSERT ON users WHEN NEW.username LIKE 'fixture_%' BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        $this->postJson(route('register.store'), $this->registration('logistics', $hub))->assertStatus(500);
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertFalse(User::where('username', 'fixture_logistics')->exists());
        $hub->update(['is_operational' => false]);
        $this->postJson(route('register.store'), $this->registration('logistics', $hub))->assertUnprocessable();
        $this->assertCount(0, Storage::disk('public')->allFiles());
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
        $this->assertSame(8, $product->fresh()->total_stock);
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
        $this->assertSame(8, $product->fresh()->total_stock);
        $this->assertSame(1, DB::table('order_status_history')->count());
    }

    public function test_buyer_cancellation_restock_is_idempotent_and_returned_parcels_restock_once(): void
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
        // An independently fulfilled fixture: the existing return status releases held stock.
        $product->update(['stock' => 8]);
        $order->update(['status' => 'delivery_failed']);
        $shipment = Shipment::create(['order_id' => $order->id, 'tracking_number' => 'FIXTURE-RETURN', 'shipping_status' => 'delivery_failed', 'logistics_company' => 'Alpha', 'origin_hub' => $hub->municipality, 'origin_province' => $hub->province, 'destination_hub' => $hub->municipality, 'destination_province' => $hub->province]);
        $admin = $this->user('logistics', 'logistics_fixture');
        $this->actingAs($admin)->patch(route('logistics.status.update', $shipment->id), ['status' => 'returned'])->assertRedirect();
        $this->assertSame('returned', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->total_stock);
        $this->patch(route('logistics.status.update', $shipment->id), ['status' => 'returned'])->assertRedirect()->assertSessionHasErrors('status');
        $this->assertSame(10, $product->fresh()->total_stock);
    }

    public function test_payment_account_verification_is_user_scoped_without_real_payment_or_email(): void
    {
        $buyer = $this->user('buyer', 'buyer_fixture');
        $this->actingAs($buyer)->postJson(route('buyer.payment-accounts.send-code'), ['type' => 'gcash', 'account_name' => 'Fixture', 'account_number' => '09123456789'])->assertOk()->assertJsonPath('success', true);
        $otp = Cache::get('payment_otp_'.$buyer->id)['otp'];
        $this->postJson(route('buyer.payment-accounts.verify'), ['otp' => 'xxxxxx'])->assertOk()->assertJsonPath('success', false);
        $this->postJson(route('buyer.payment-accounts.verify'), ['otp' => $otp])->assertOk()->assertJsonPath('success', true);
        $account = BuyerPaymentAccount::firstOrFail();
        $this->assertTrue($account->verified);
        $other = $this->user('buyer', 'other_fixture');
        $this->actingAs($other)->deleteJson(route('buyer.payment-accounts.destroy', $account))->assertForbidden();
        $this->assertTrue(BuyerPaymentAccount::whereKey($account->id)->exists());
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
