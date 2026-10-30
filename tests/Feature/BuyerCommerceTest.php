<?php

namespace Tests\Feature;

use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\BuyerCommerce;
use App\Services\GcashWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class BuyerCommerceTest extends TestCase
{
    use WorkflowSchema;

    private User $buyer;

    private User $seller;

    private Product $product;

    private CartItem $cart;

    private BuyerAddress $address;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        foreach (['2026_10_14_000004_add_gcash_purchases', '2026_10_16_000001_add_registration_business_details', '2026_10_16_000002_add_logistics_registration_details', '2026_10_17_000001_add_buyer_commerce_features'] as $file) {
            (require database_path('migrations/'.$file.'.php'))->up();
        }
        Storage::fake('public'); Storage::fake('sensitive_documents');
        Storage::fake('local');
        $this->buyer = $this->person('buyer');
        $this->seller = $this->person('seller');
        $this->product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Basket', 'price' => 100, 'stock' => 20, 'weight_grams' => 400, 'status' => 'active']);
        $this->cart = CartItem::create(['buyer_id' => $this->buyer->id, 'product_id' => $this->product->id, 'seller_slug' => $this->seller->username, 'name' => 'Basket', 'price' => 100, 'qty' => 3]);
        $this->address = BuyerAddress::create(['buyer_id' => $this->buyer->id, 'region' => 'Test Region', 'postal_code' => '0123', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'B', 'street' => 'Street', 'recipient_name' => 'Buyer', 'contact_no' => '09171234567', 'is_default' => true]);
        DB::table('commerce_shipping_rates')->insert(['seller_id' => $this->seller->id, 'region' => 'Test Region', 'first_kg' => 60, 'additional_kg' => 20]);
        PaymentMethod::create(['id' => 1, 'name' => 'Cash on Delivery', 'type' => 'cod', 'is_active' => true]);
        PaymentMethod::create(['id' => 2, 'name' => 'GCash', 'type' => 'ewallet', 'is_active' => true]);
    }

    private function person(string $role): User
    {
        return User::create(['account_type' => $role, 'username' => uniqid('u'), 'email' => uniqid().'@gmail.com', 'given_names' => 'Niño', 'last_name' => 'Reyes', 'password' => 'Pass@2026', 'status' => 'approved', 'region' => 'Test Region', 'postal_code' => '0123', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'B', 'street' => 'Street']);
    }

    private function quote(array $codes = []): array
    {
        return DB::transaction(fn () => app(BuyerCommerce::class)->quote($this->buyer, [$this->cart->id], $this->address->id, $codes));
    }

    private function voucher(string $code, string $kind, float $amount): int
    {
        return DB::table('commerce_vouchers')->insertGetId(['code' => $code, 'kind' => $kind, 'seller_id' => $kind === 'store' ? $this->seller->id : null, 'discount_type' => 'fixed', 'amount' => $amount, 'minimum_spend' => 0, 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'usage_limit' => 10, 'per_buyer_limit' => 1]);
    }

    private function payload(): array
    {
        $quote = $this->quote();

        return ['items' => [$this->cart->id], 'delivery_address_id' => $this->address->id, 'payment_method' => 1, 'checkout_id' => (string) Str::uuid(), 'quote_hash' => hash('sha256', json_encode($quote))];
    }

    private function invalid(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected validation failure.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1sAAAAASUVORK5CYII='));
    }

    public function test_shipping_rounds_weight_and_vouchers_stack_with_replacement(): void
    {
        $this->voucher('STORE10', 'store', 10);
        $this->voucher('STORE20', 'store', 20);
        $this->voucher('PLAT30', 'platform', 30);
        $this->voucher('SHIP50', 'shipping', 50);
        $q = $this->quote(['STORE10', 'STORE20', 'PLAT30', 'SHIP50']);
        $row = $q['orders'][$this->seller->id];
        $this->assertSame(2, $row['chargeable_kg']);
        $this->assertEquals(80, $row['shipping_amount']);
        $this->assertEquals(280, $q['total']);
        $this->assertCount(3, $row['vouchers']);
        $this->assertEquals(20, $row['seller_discount']);
        $this->assertSame(100, array_sum(app(BuyerCommerce::class)->split(100, [1 => 101, 2 => 200])));
    }

    public function test_vacation_unserviceable_address_and_future_vouchers_are_blocked(): void
    {
        $this->seller->update(['is_on_vacation' => true]);
        $this->invalid(fn () => $this->quote());
        $this->seller->update(['is_on_vacation' => false]);
        DB::table('commerce_shipping_rates')->update(['is_active' => false]);
        $this->invalid(fn () => $this->quote());
        DB::table('commerce_shipping_rates')->update(['is_active' => true]);
        $id = $this->voucher('FUTURE', 'platform', 10);
        DB::table('commerce_vouchers')->where('id', $id)->update(['starts_at' => now()->addHour()]);
        $this->invalid(fn () => $this->quote(['FUTURE']));
    }

    public function test_seller_without_regional_rates_uses_legacy_flat_shipping_fee(): void
    {
        DB::table('commerce_shipping_rates')->where('seller_id', $this->seller->id)->delete();
        $this->seller->update(['shipping_fee' => 45]);

        $row = $this->quote()['orders'][$this->seller->id];

        $this->assertFalse($row['shipping_pending']);
        $this->assertNull($row['shipping_rate_id']);
        $this->assertEquals(45, $row['shipping_amount']);
        $this->assertEquals(345, $row['total']);
    }

    public function test_seller_without_regional_rates_or_legacy_fee_gets_free_shipping_fallback(): void
    {
        DB::table('commerce_shipping_rates')->where('seller_id', $this->seller->id)->delete();
        $this->seller->update(['shipping_fee' => null]);

        $row = $this->quote()['orders'][$this->seller->id];

        $this->assertFalse($row['shipping_pending']);
        $this->assertEquals(0, $row['shipping_amount']);
        $this->assertEquals(300, $row['total']);
    }

    public function test_flash_sale_excess_units_use_regular_price(): void
    {
        DB::table('commerce_flash_sales')->insert(['seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'price' => 50, 'quantity' => 1, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
        $row = $this->quote()['orders'][$this->seller->id];
        $this->assertCount(2, $row['items']);
        $this->assertEquals(250, $row['subtotal']);
        $this->assertEquals(1, $row['items'][0]['qty']);
        $this->assertEquals(2, $row['items'][1]['qty']);
    }

    public function test_cod_checkout_retry_and_cancel_do_not_duplicate_stock(): void
    {
        $d = $this->payload();
        $this->actingAs($this->buyer)->post(route('buyer.commerce.place'), $d)->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('buyer.commerce.place'), $d)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(17, $this->product->fresh()->stock);
        $order = Order::first();
        DB::transaction(fn () => $order->cancelAndRestock('Changed my mind', null, $this->buyer->id));
        $this->assertSame(20, $this->product->fresh()->stock);
        DB::transaction(fn () => $order->cancelAndRestock('Changed my mind', null, $this->buyer->id));
        $this->assertSame(20, $this->product->fresh()->stock);
    }

    public function test_gcash_reserves_one_hour_and_releases_new_vouchers(): void
    {
        $this->voucher('STORE10', 'store', 10);
        $q = $this->quote(['STORE10']);
        $p = app(GcashWorkflow::class)->create($this->buyer, [$this->cart->id], 2, (string) Str::uuid(), null, $this->address->id, null, $q)[0]->fresh();
        $this->assertEquals(370, $p->amount_due);
        $this->assertSame(3600, $p->expires_at->timestamp - $p->activated_at->timestamp);
        $this->assertSame(17, $this->product->fresh()->stock);
        $this->travel(61)->minutes();
        app(GcashWorkflow::class)->expire($p->id);
        $this->assertSame(20, $this->product->fresh()->stock);
        $this->assertSame('released', DB::table('commerce_allocations')->first()->state);
    }

    public function test_address_limit_and_cross_buyer_protection(): void
    {
        for ($i = 1; $i < 10; $i++) {
            BuyerAddress::create(['buyer_id' => $this->buyer->id, 'label' => 'Other', 'province' => 'Laguna']);
        }
        $d = ['recipient_name' => 'Buyer', 'region' => 'Test Region', 'postal_code' => '0123', 'contact_no' => '9171234567', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'B'];
        $this->actingAs($this->buyer)->postJson(route('buyer.addresses.store'), $d)->assertUnprocessable()->assertJsonValidationErrors('address');
        $other = $this->person('buyer');
        $this->actingAs($other)->deleteJson(route('buyer.addresses.destroy', $this->address))->assertForbidden();
    }

    public function test_wishlist_and_mark_all_are_scoped_and_idempotent(): void
    {
        $other = $this->person('buyer');
        Order::notifyUser($this->buyer->id, 'A', 'A', 'order_status', null);
        Order::notifyUser($other->id, 'B', 'B', 'order_status', null);
        $this->actingAs($this->buyer)->post(route('buyer.commerce.wishlist', $this->product->id), ['saved' => 1])->assertRedirect();
        $this->post(route('buyer.commerce.wishlist', $this->product->id), ['saved' => 1]);
        $this->assertDatabaseCount('commerce_wishlist', 1);
        $this->post(route('buyer.commerce.read-all'));
        $this->assertEquals(1, DB::table('notifications')->where('user_id', $this->buyer->id)->value('is_read'));
        $this->assertEquals(0, DB::table('notifications')->where('user_id', $other->id)->value('is_read'));
    }

    public function test_email_changes_only_after_correct_otp(): void
    {
        $code = null;
        Mail::shouldReceive('raw')->once()->withArgs(function ($body, $callback) use (&$code) {
            preg_match('/\b[0-9]{6}\b/', $body, $m);
            $code = $m[0];

            return true;
        });
        $old = $this->buyer->email;
        $this->actingAs($this->buyer)->post(route('buyer.commerce.email.code'), ['email' => 'new.address@gmail.com', 'current_password' => 'Pass@2026'])->assertSessionHasNoErrors();
        $this->assertSame($old, $this->buyer->fresh()->email);
        $this->post(route('buyer.commerce.email.verify'), ['otp' => $code])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'Email verified and updated.');
        $this->assertSame('new.address@gmail.com', $this->buyer->fresh()->email);
    }

    public function test_all_commerce_pages_render_for_their_roles(): void
    {
        $this->actingAs($this->buyer)->get(route('buyer.commerce.index'))->assertRedirect(route('buyer.wishlist'));
        $this->get(route('buyer.wishlist'))->assertOk();
        $this->get(route('buyer.commerce.checkout'))->assertRedirect(route('buyer.cart'));
        $this->get(route('buyer.cart'))->assertOk()->assertSee('data-quote-url', false)->assertDontSee('buyer-shopping-tools', false);
        $this->get(route('buyer.account'))->assertOk()->assertSee('id="change-email"', false)->assertSee('id="my-vouchers"', false);
        $this->get(route('buyer.orders', ['tab' => 'refunds']))->assertOk()->assertSee('Return / Refund')->assertDontSee('buyer-shopping-tools', false);
        $this->actingAs($this->seller)->get(route('seller.commerce.index'))->assertRedirect(route('seller.account').'#shipping-rates');
        $admin = $this->person('admin');
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get(route('admin.commerce.index'))->assertRedirect(route('admin.products').'#platform-vouchers');
    }

    public function test_item_reviews_support_photos_and_only_one_edit(): void
    {
        $order = Order::create(['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'status' => 'completed', 'order_number' => 'REVIEW1', 'items' => [['product_id' => $this->product->id, 'name' => 'Basket', 'price' => 100, 'qty' => 1]], 'subtotal' => 100, 'total' => 100]);
        $order->logStatus('completed', $this->buyer->id);
        $d = ['item_key' => 0, 'rating' => 5, 'comment' => 'Good basket', 'photos' => [$this->image('photo.png')]];
        $this->actingAs($this->buyer)->post(route('buyer.commerce.review', $order), $d)->assertRedirect()->assertSessionHasNoErrors();
        $review = Review::first();
        $this->assertCount(1, $review->photos);
        Storage::disk('public')->assertExists($review->photos[0]);
        $this->post(route('buyer.commerce.review', $order), ['item_key' => 0, 'rating' => 4])->assertSessionHasErrors('checkout');
        $this->post(route('buyer.commerce.review.edit', [$order, $review]), ['item_key' => 0, 'rating' => 4, 'comment' => 'Updated'])->assertSessionHasNoErrors();
        $this->post(route('buyer.commerce.review.edit', [$order, $review]), ['item_key' => 0, 'rating' => 3])->assertSessionHasErrors('checkout');
        $this->assertEquals(4, $review->fresh()->rating);
        $this->get(route('buyer.orders', ['tab' => 'completed']))->assertOk()->assertSee('Review Items')->assertSee('Product reviews')->assertSee('already been edited once');
        $this->travel(31)->days();
        $this->post(route('buyer.commerce.review.edit', [$order, $review]), ['item_key' => 0, 'rating' => 2])->assertSessionHasErrors('checkout');
    }

    public function test_returns_require_evidence_and_restore_stock_only_once(): void
    {
        $order = Order::create(['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'status' => 'delivered', 'order_number' => 'RETURN1', 'items' => [['product_id' => $this->product->id, 'name' => 'Basket', 'price' => 100, 'qty' => 1]], 'subtotal' => 100, 'total' => 100]);
        $order->logStatus('delivered', $this->buyer->id);
        $d = ['item_keys' => [0], 'reason' => 'Damaged item', 'description' => 'The basket arrived broken.', 'refund_account_name'=>'Synthetic Buyer','refund_gcash_number'=>'09123456789'];
        $this->actingAs($this->buyer)->post(route('buyer.commerce.return', $order), $d)->assertSessionHasErrors('photos');
        $this->post(route('buyer.commerce.return', $order), $d + ['photos' => [$this->image('evidence.png')]])->assertRedirect()->assertSessionHasNoErrors();
        $row = DB::table('commerce_returns')->first();
        $this->assertEquals(100, $row->amount);
        $this->get(route('buyer.orders', ['tab' => 'refunds']))->assertOk()->assertSee('Return / Refund')->assertSee('The basket arrived broken.')->assertSee('View return / refund details');
        $this->patch(route('buyer.orders.confirm-receipt', $order))->assertSessionHas('error', 'Resolve the return/refund request before completing this order.');
        $this->actingAs($this->seller)->post(route('commerce.return.action', $row->id), ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->post(route('commerce.return.action', $row->id), ['action' => 'received'])->assertSessionHasNoErrors();
        $this->assertSame(21, $this->product->fresh()->stock);
        $this->post(route('commerce.return.action', $row->id), ['action' => 'received'])->assertForbidden();
        $this->assertSame(21, $this->product->fresh()->stock);
    }

    private function roleAccount(string $role): User
    {
        if ($role === 'rider') {
            $hub = LogisticsHub::create(['company_name' => 'Fixture Shipping', 'province' => 'Laguna', 'municipality' => 'Pila', 'is_operational' => true]);

            return User::create(['account_type' => 'rider', 'username' => uniqid('rider'), 'email' => uniqid().'@gmail.com', 'given_names' => 'Ana', 'last_name' => 'Reyes', 'password' => 'Pass@2026', 'status' => 'approved', 'region' => 'Test Region', 'postal_code' => '0123', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'B', 'business_name' => 'Fixture Shipping', 'logistics_hub_id' => $hub->id]);
        }
        $user = $this->person($role);
        if ($role === 'admin') {
            $user->forceFill(['is_admin' => true])->save();
        }
        if ($role === 'logistics') {
            $user->forceFill(['is_logistics' => true, 'logistics_role' => 'admin', 'business_name' => 'Fixture Shipping'])->save();
        }

        return $user;
    }

    public function test_other_roles_use_native_account_and_commerce_pages(): void
    {
        foreach (['seller', 'admin', 'logistics', 'rider'] as $role) {
            $user = $this->roleAccount($role);
            $this->actingAs($user)->get(route($role.'.account'))->assertOk()->assertSee('data-role-region', false)->assertSee('name="postal_code"', false)->assertSee(route('account.email.code'))->assertDontSee('buyer-shopping-tools', false);
        }
        $this->actingAs($this->seller)->get(route('seller.account'))->assertOk()->assertSee('id="shipping-rates"', false)->assertSee('Vacation mode')->assertDontSee('Charged once per order');
        $this->get(route('seller.vouchers'))->assertOk()->assertSee('name="starts_at"', false);
        $this->get(route('seller.inventory'))->assertOk()->assertSee('id="flash-sales"', false);
        $this->get(route('seller.orders', ['status' => 'refunds']))->assertOk()->assertSee('Return / Refund');
        $this->actingAs($this->roleAccount('admin'))->get(route('admin.products'))->assertOk()->assertSee('id="platform-vouchers"', false);
        $this->get(route('admin.complaints'))->assertOk()->assertSee('id="returns-refunds"', false);
    }

    public function test_all_other_roles_enforce_profile_and_password_rules(): void
    {
        // Controlled geography fixtures exercise the real hierarchy validation without live network calls.
        Cache::flush();
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake([
            'https://psgc.gitlab.io/api/regions/' => \Illuminate\Support\Facades\Http::response([['code'=>'040000000','name'=>'Test Region'],['code'=>'050000000','name'=>'Another Region']]),
            'https://psgc.gitlab.io/api/provinces/' => \Illuminate\Support\Facades\Http::response([['code'=>'043400000','name'=>'Laguna','regionCode'=>'040000000'],['code'=>'053400000','name'=>'Another Province','regionCode'=>'050000000']]),
            'https://psgc.gitlab.io/api/provinces/043400000/cities-municipalities/' => \Illuminate\Support\Facades\Http::response([['code'=>'043425000','name'=>'Pila']]),
            'https://psgc.gitlab.io/api/provinces/053400000/cities-municipalities/' => \Illuminate\Support\Facades\Http::response([['code'=>'053425000','name'=>'Pila']]),
            'https://psgc.gitlab.io/api/cities-municipalities/043425000/barangays/' => \Illuminate\Support\Facades\Http::response([['code'=>'043425001','name'=>'B']]),
            'https://psgc.gitlab.io/api/cities-municipalities/053425000/barangays/' => \Illuminate\Support\Facades\Http::response([['code'=>'053425001','name'=>'B']]),
        ]);
        foreach (['seller', 'admin', 'logistics', 'rider'] as $role) {
            $user = $this->roleAccount($role);
            $profileRoute = $role === 'seller' ? 'seller.account.profile' : $role.'.account.update';
            $data = ['given_names' => 'Ana', 'last_name' => 'Reyes', 'middle_name' => 'Maria', 'contact_no' => '9171234567', 'sex' => 'female', 'email' => $user->email, 'region' => 'Test Region', 'postal_code' => '0123', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'B'];
            $this->actingAs($user)->postJson(route($profileRoute), array_replace($data, ['given_names' => 'Bad123']))->assertUnprocessable()->assertJsonValidationErrors('given_names');
            $this->post(route($profileRoute), $data)->assertRedirect()->assertSessionHasNoErrors();
            if ($role === 'admin') {
                $this->assertSame('09171234567', $user->fresh()->contact_no);
            } else {
                $request = DB::table('account_update_requests')->where('user_id', $user->id)->first();
                $changes = json_decode($request->requested_changes, true);
                $this->assertSame('09171234567', $changes['contact_no']);
            }
            if ($role !== 'admin') {
                $this->post(route($role.'.account.address'), ['region' => 'Another Region', 'postal_code' => '0456', 'province' => 'Another Province', 'municipality' => 'Pila', 'barangay' => 'B'])->assertSessionHasNoErrors();
                $changes = json_decode(DB::table('account_update_requests')->where('user_id', $user->id)->value('requested_changes'), true);
                $this->assertSame('Another Region', $changes['region']);
                $this->assertSame('0456', $changes['postal_code']);
            }
            $this->postJson(route($role.'.account.password'), ['current_password' => 'Pass@2026', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->post(route($role.'.account.password'), ['current_password' => 'Pass@2026', 'password' => 'Strong@2026', 'password_confirmation' => 'Strong@2026'])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertTrue(Hash::check('Strong@2026', $user->fresh()->password));
        }
    }

    public function test_other_roles_cannot_bypass_verified_email_or_manage_another_sellers_returns(): void
    {
        $admin = $this->roleAccount('admin');
        $old = $admin->email;
        $this->actingAs($admin)->postJson(route('admin.account.update'), ['given_names' => 'Ana', 'last_name' => 'Reyes', 'email' => 'unverified@gmail.com', 'contact_no' => '9171234567', 'region' => 'Test Region', 'postal_code' => '0123', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'B'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame($old, $admin->fresh()->email);
        foreach (['seller', 'admin', 'logistics', 'rider'] as $role) {
            $user = $this->roleAccount($role);
            Cache::put('buyer-email-change:'.$user->id, ['email' => $role.'.new@gmail.com', 'hash' => Hash::make('123456')], now()->addMinutes(5));
            $this->actingAs($user)->post(route('account.email.verify'), ['otp' => '123456'])->assertSessionHasNoErrors();
            $this->assertSame($role.'.new@gmail.com', $user->fresh()->email);
        }
    }

    public function test_native_return_controls_keep_sellers_and_admin_actions_separate(): void
    {
        $admin=$this->roleAccount('admin');
        $order=Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'delivered','order_number'=>'NATIVE-RETURN','items'=>[['product_id'=>$this->product->id,'name'=>'Basket','price'=>100,'qty'=>1]],'subtotal'=>100,'total'=>100]);
        $order->logStatus('delivered',$this->buyer->id);
        $this->actingAs($this->buyer)->post(route('buyer.commerce.return',$order),['item_keys'=>[0],'reason'=>'Damaged item','description'=>'Broken basket requiring review.','photos'=>[$this->image('evidence.png')],'refund_account_name'=>'Synthetic Buyer','refund_gcash_number'=>'09123456789'])->assertSessionHasNoErrors();
        $request=DB::table('commerce_returns')->first();
        $this->actingAs($this->seller)->get(route('seller.orders',['status'=>'refunds']))->assertOk()->assertSee('Broken basket requiring review.')->assertSee('Submit decision');
        $other=$this->roleAccount('seller');
        $this->actingAs($other)->get(route('seller.orders',['status'=>'refunds']))->assertOk()->assertDontSee('Broken basket requiring review.');
        $this->post(route('commerce.return.action',$request->id),['action'=>'approve'])->assertForbidden();
        foreach(['logistics','rider'] as $role){$this->actingAs($this->roleAccount($role))->get(route('commerce.return.photo',[$request->id,0]))->assertForbidden();}
        $this->actingAs($this->seller)->post(route('commerce.return.action',$request->id),['action'=>'approve'])->assertSessionHasNoErrors();
        $this->post(route('commerce.return.action',$request->id),['action'=>'received'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('admin.complaints'))->assertOk()->assertSee('Broken basket requiring review.')->assertSee('Record completed repayment');
        $this->post(route('commerce.return.action',$request->id),['action'=>'refunded','reference'=>'ACTUAL-REFUND-123'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('commerce_returns',['id'=>$request->id,'status'=>'refunded','refund_reference'=>'ACTUAL-REFUND-123']);
    }

    public function test_return_auto_approval_notifies_buyer_and_seller_once(): void
    {
        $order=Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'delivered','order_number'=>'AUTO-RETURN','items'=>[['product_id'=>$this->product->id,'name'=>'Basket','price'=>100,'qty'=>1]],'subtotal'=>100,'total'=>100]);
        $order->logStatus('delivered',$this->buyer->id);
        $this->actingAs($this->buyer)->post(route('buyer.commerce.return',$order),['item_keys'=>[0],'reason'=>'Wrong item','description'=>'The delivered basket is incorrect.','photos'=>[$this->image('wrong.png')],'refund_account_name'=>'Synthetic Buyer','refund_gcash_number'=>'09123456789'])->assertSessionHasNoErrors();
        $before=DB::table('notifications')->count();$this->travel(4)->days();
        app(BuyerCommerce::class)->sweep();$this->assertDatabaseHas('commerce_returns',['order_id'=>$order->id,'status'=>'approved']);
        $this->assertSame($before+2,DB::table('notifications')->count());app(BuyerCommerce::class)->sweep();$this->assertSame($before+2,DB::table('notifications')->count());
    }

    public function test_shipping_settings_and_notification_actions_are_owned_by_the_signed_in_role(): void
    {
        $other = $this->roleAccount('seller');
        $this->actingAs($other)->post(route('seller.commerce.shipping'), ['seller_id' => $this->seller->id, 'region' => 'Test Region', 'first_kg' => 90, 'additional_kg' => 25, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertEquals(60, DB::table('commerce_shipping_rates')->where('seller_id', $this->seller->id)->value('first_kg'));
        $this->assertEquals(90, DB::table('commerce_shipping_rates')->where('seller_id', $other->id)->value('first_kg'));
        Order::notifyUser($other->id, 'Test', 'Owned notification', 'order_status', null);
        Order::notifyUser($this->seller->id, 'Other', 'Other notification', 'order_status', null);
        $this->post(route('account.notifications.read-all'))->assertRedirect();
        $this->assertEquals(0, DB::table('notifications')->where('user_id', $other->id)->where('is_read', false)->count());
        $foreign = DB::table('notifications')->where('user_id', $this->seller->id)->value('id');
        $this->get(route('account.notifications.open', $foreign))->assertNotFound();
        $this->assertEquals(0, DB::table('notifications')->where('id', $foreign)->value('is_read'));
    }

    public function test_new_delivery_address_becomes_the_selected_checkout_address(): void
    {
        $data = ['label' => 'Work', 'recipient_name' => 'Buyer Example', 'contact_no' => '9171234567', 'region' => 'Test Region', 'postal_code' => '0123', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'B', 'street' => 'New Street'];
        $response = $this->actingAs($this->buyer)->postJson(route('buyer.addresses.store'), $data)->assertOk();
        $id = $response->json('address.id');
        $response->assertSessionHas('buyer_checkout_address', $id);
        $html = $this->get(route('buyer.cart'))->assertOk()->assertSee('Add New Address')->getContent();
        $this->assertMatchesRegularExpression('/name="delivery_address_radio" value="'.$id.'"\s+checked/', $html);
        $this->assertTrue($this->address->fresh()->is_default);
        $this->assertFalse(BuyerAddress::find($id)->is_default);
    }

    public function test_older_saved_addresses_can_complete_missing_checkout_fields_only(): void
    {
        $this->address->update(['region' => null, 'postal_code' => null]);
        $this->actingAs($this->buyer)->get(route('buyer.cart'))->assertOk()->assertSee('Complete missing delivery details')->assertSee('Save Delivery Details');
        $data = ['region' => 'Test Region', 'postal_code' => '0123'];
        $this->actingAs($this->person('buyer'))->patchJson(route('buyer.addresses.complete', $this->address), $data)->assertForbidden();
        $this->actingAs($this->buyer)->patchJson(route('buyer.addresses.complete', $this->address), ['region' => 'Test Region', 'postal_code' => '123'])->assertUnprocessable();
        $this->patchJson(route('buyer.addresses.complete', $this->address), $data)->assertOk()->assertJsonPath('success', true);
        $this->assertSame('0123', $this->address->fresh()->postal_code);
        $this->assertEquals(380, $this->quote()['total']);
        $this->patchJson(route('buyer.addresses.complete', $this->address), ['region' => 'Another Region', 'postal_code' => '9999'])->assertUnprocessable();
        $this->assertSame('Test Region', $this->address->fresh()->region);
    }

    public function test_vouchers_preview_without_shipping_setup_but_cannot_authorize_checkout(): void
    {
        $this->voucher('STORE20', 'store', 20);
        $this->voucher('PLAT30', 'platform', 30);
        DB::table('commerce_shipping_rates')->delete();
        $this->address->update(['region' => null, 'postal_code' => null]);
        $data = ['preview' => true, 'items' => [$this->cart->id], 'delivery_address_id' => $this->address->id, 'vouchers' => ['STORE20', 'PLAT30']];
        $response = $this->actingAs($this->buyer)->postJson(route('buyer.commerce.quote'), $data)->assertOk()->assertJsonPath('quote.total', 250)->assertJsonPath('hash', null);
        $row = $response->json('quote.orders.'.$this->seller->id);
        $this->assertEquals(50, $row['discount_amount']);
        $this->assertTrue($row['shipping_pending']);
        $this->assertNotEmpty($response->json('quote.warnings'));
        $this->assertDatabaseCount('commerce_allocations', 0);
        $this->assertDatabaseCount('orders', 0);
        $data['preview'] = false;
        $data['payment_method'] = 1;
        $data['checkout_id'] = (string) Str::uuid();
        $this->postJson(route('buyer.commerce.quote'), $data)->assertUnprocessable()->assertJsonValidationErrors('delivery_address_id');
        $this->postJson(route('buyer.checkout'), $data)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_native_cart_quote_and_submit_create_an_order_without_a_separate_checkout_page(): void
    {
        $this->voucher('STORE10', 'store', 10);
        $data = $this->payload();
        $data['vouchers'] = ['STORE10'];
        $quote = $this->actingAs($this->buyer)->postJson(route('buyer.commerce.quote'), $data)->assertOk()->assertJsonPath('quote.total', 370)->json();
        $data['quote_hash'] = $quote['hash'];
        $this->post(route('buyer.checkout'), $data)->assertRedirect(route('buyer.orders'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(370, Order::first()->total);
        $this->get(route('buyer.orders'))
            ->assertOk()
            ->assertSee('Customer name')
            ->assertSee('Buyer')
            ->assertSee('Contact number')
            ->assertSee('09171234567')
            ->assertSee('Order date')
            ->assertSee('Order time');
        $this->actingAs($this->seller)->get(route('seller.orders'))
            ->assertOk()
            ->assertSee('Customer:')
            ->assertSee('Buyer')
            ->assertSee('Contact:')
            ->assertSee('09171234567')
            ->assertSee('Date &amp; time:', false);
    }

    public function test_flash_sales_render_in_the_existing_deals_gallery_and_wishlist_is_separate(): void
    {
        DB::table('commerce_flash_sales')->insert(['seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'price' => 50, 'quantity' => 2, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
        $this->actingAs($this->buyer)->get(route('buyer.dashboard'))->assertOk()->assertSee('id="deals"', false)->assertSee('Explore deals')->assertSee('Flash sale')->assertSee('data-countdown', false)->assertDontSee('buyer-shopping-tools', false);
        $this->post(route('buyer.commerce.wishlist', $this->product->id), ['saved' => 1]);
        $this->get(route('buyer.wishlist'))->assertOk()->assertSee('Basket')->assertSee('Remove from Wishlist')->assertDontSee('Verify a new email');
        $this->get(route('buyer.commerce.index', ['tab' => 'returns']))->assertRedirect(route('buyer.orders', ['tab' => 'refunds']));
        $this->get(route('buyer.commerce.index', ['tab' => 'sales']))->assertRedirect(route('buyer.dashboard').'#deals');
    }

    public function test_gcash_payment_preserves_flash_prices_and_stacked_vouchers(): void
    {
        $this->voucher('STORE10', 'store', 10);
        $this->voucher('PLAT30', 'platform', 30);
        DB::table('commerce_flash_sales')->insert(['seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'price' => 50, 'quantity' => 1, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
        $q = $this->quote(['STORE10', 'PLAT30']);
        $flow = app(GcashWorkflow::class);
        $p = $flow->create($this->buyer, [$this->cart->id], 2, (string) Str::uuid(), null, $this->address->id, null, $q)[0]->fresh();
        $this->assertEquals(290, $p->amount_due);
        $p = $flow->instructions($this->seller, $p->id, $p->revision, 'Seller', '09123456789');
        $p = $flow->proof($this->buyer, $p->id, (string) Str::uuid(), 'gcash-proofs/fixture.png', 'image/png', 100, hash('sha256', 'proof'));
        $p = $flow->decide($this->seller, $p->id, $p->revision, true, $p->amount_due, null, 'COMMERCE-12345');
        DB::table('commerce_shipping_rates')->update(['first_kg' => 999]);
        $order = $flow->place($this->buyer, $p->id, $p->revision);
        $this->assertEquals(290, $order->total);
        $this->assertEquals(10, $order->commerce_snapshot['seller_discount']);
        $this->assertEquals(0, CartItem::count());
        $this->assertSame(17, $this->product->fresh()->stock);
        $this->assertEquals(0, DB::table('commerce_allocations')->where('state', 'held')->count());
    }

    public function test_duplicate_cart_lines_cannot_oversell_and_discounts_cannot_exceed_merchandise(): void
    {
        $this->cart->update(['qty' => 15]);
        $other = CartItem::create(['buyer_id' => $this->buyer->id, 'product_id' => $this->product->id, 'seller_slug' => $this->seller->username, 'name' => 'Basket', 'price' => 100, 'qty' => 15]);
        $this->invalid(fn () => DB::transaction(fn () => app(BuyerCommerce::class)->quote($this->buyer, [$this->cart->id, $other->id], $this->address->id)));
        $this->cart->update(['qty' => 3]);
        $this->voucher('STOREBIG', 'store', 1000);
        $this->voucher('PLATBIG', 'platform', 1000);
        $row = $this->quote(['STOREBIG', 'PLATBIG'])['orders'][$this->seller->id];
        $this->assertEquals(300, array_sum(array_column($row['vouchers'], 'amount')));
        $this->assertEquals(300, $row['seller_discount']);
        $this->assertEquals(80, $row['total']);
    }

    public function test_platform_voucher_counts_once_for_a_checkout_with_two_sellers(): void
    {
        $seller = $this->person('seller');
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Other basket', 'price' => 100, 'stock' => 10, 'weight_grams' => 400, 'status' => 'active']);
        $cart = CartItem::create(['buyer_id' => $this->buyer->id, 'product_id' => $product->id, 'seller_slug' => $seller->username, 'name' => 'Other basket', 'price' => 100, 'qty' => 1]);
        DB::table('commerce_shipping_rates')->insert(['seller_id' => $seller->id, 'region' => 'Test Region', 'first_kg' => 60, 'additional_kg' => 20]);
        $id = $this->voucher('SHARED30', 'platform', 30);
        DB::table('commerce_vouchers')->where('id', $id)->update(['usage_limit' => 1]);
        $q = DB::transaction(fn () => app(BuyerCommerce::class)->quote($this->buyer, [$this->cart->id, $cart->id], $this->address->id, ['SHARED30']));
        $d = ['items' => [$this->cart->id, $cart->id], 'delivery_address_id' => $this->address->id, 'payment_method' => 1, 'checkout_id' => (string) Str::uuid(), 'quote_hash' => hash('sha256', json_encode($q)), 'vouchers' => ['SHARED30']];
        $this->actingAs($this->buyer)->post(route('buyer.commerce.place'), $d)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 2);
        $this->assertEquals(510, Order::sum('total'));
        $this->assertEquals(1, DB::table('commerce_allocations')->where('voucher_id', $id)->distinct()->count('usage_key'));
        $this->assertEquals(2, DB::table('commerce_allocations')->where('voucher_id', $id)->count());
    }

    public function test_flash_reminder_sends_once_and_wrong_email_code_does_not_change_email(): void
    {
        $id = DB::table('commerce_flash_sales')->insertGetId(['seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'price' => 50, 'quantity' => 1, 'starts_at' => now()->addMinutes(4), 'ends_at' => now()->addHour()]);
        DB::table('commerce_reminders')->insert(['buyer_id' => $this->buyer->id, 'flash_sale_id' => $id]);
        app(BuyerCommerce::class)->sweep();
        app(BuyerCommerce::class)->sweep();
        $this->assertEquals(1, DB::table('notifications')->where('notification_type', 'flash_sale')->count());
        $old = $this->buyer->email;
        Cache::put('buyer-email-change:'.$this->buyer->id, ['email' => 'other@gmail.com', 'hash' => Hash::make('123456')], now()->addMinutes(5));
        $this->actingAs($this->buyer)->post(route('buyer.commerce.email.verify'), ['otp' => '000000'])->assertSessionHasErrors('otp');
        $this->assertSame($old, $this->buyer->fresh()->email);
    }
}
