<?php

namespace Tests\Feature;

use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Services\BuyerCommerce;
use App\Services\DeliveryGuarantee;
use App\Services\GcashWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class DeliveryGuaranteeTest extends TestCase
{
    use WorkflowSchema;

    private User $buyer;
    private User $other;
    private User $seller;
    private BuyerAddress $address;
    private CartItem $cart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(4, 0));
        $this->createWorkflowSchema();
        foreach (['2026_10_14_000004_add_gcash_purchases', '2026_10_16_000001_add_registration_business_details', '2026_10_16_000002_add_logistics_registration_details', '2026_10_17_000001_add_buyer_commerce_features', '2026_10_31_100000_add_delivery_guarantees', '2026_10_31_110000_add_seller_regional_shipping_policy', '2026_10_31_120000_add_seller_late_delivery_voucher_policy'] as $file) {
            (require database_path('migrations/'.$file.'.php'))->up();
        }
        $this->buyer = $this->person('buyer');
        $this->other = $this->person('buyer');
        $this->seller = $this->person('seller');
        PaymentMethod::create(['id' => 1, 'name' => 'Cash on Delivery', 'type' => 'cod', 'is_active' => true]);
        PaymentMethod::create(['id' => 2, 'name' => 'GCash', 'type' => 'ewallet', 'is_active' => true]);
        $product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Item', 'price' => 100, 'stock' => 10, 'weight_grams' => 400, 'status' => 'active']);
        $this->cart = CartItem::create(['buyer_id' => $this->buyer->id, 'product_id' => $product->id, 'seller_slug' => $this->seller->username, 'name' => 'Item', 'price' => 100, 'qty' => 2]);
        $this->address = BuyerAddress::create(['buyer_id' => $this->buyer->id, 'region' => 'Test Region', 'postal_code' => '0123', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'B', 'street' => 'Street', 'recipient_name' => 'Buyer', 'contact_no' => '09171234567', 'is_default' => true]);
        DB::table('commerce_shipping_rates')->insert(['seller_id' => $this->seller->id, 'region' => 'Test Region', 'first_kg' => 60, 'additional_kg' => 20, 'free_shipping' => true, 'delivery_days_min' => 1, 'delivery_days_max' => 7, 'late_voucher_enabled' => true, 'late_voucher_amount' => 40]);
    }

    private function person(string $role): User
    {
        return User::create(['account_type' => $role, 'username' => uniqid('user'), 'email' => uniqid().'@example.test', 'given_names' => 'Test', 'last_name' => 'User', 'status' => 'approved', 'region' => 'Test Region', 'postal_code' => '0123', 'province' => 'Laguna', 'municipality' => 'Pila', 'barangay' => 'B', 'street' => 'Street']);
    }

    private function quote(array $codes = []): array
    {
        return DB::transaction(fn () => app(BuyerCommerce::class)->quote($this->buyer, [$this->cart->id], $this->address->id, $codes));
    }

    private function order(): Order
    {
        $row = $this->quote()['orders'][$this->seller->id];

        return Order::create(['order_number' => uniqid('PF-'), 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'status' => 'placed', 'items' => $row['items'], 'subtotal' => $row['subtotal'], 'shipping_amount' => $row['shipping_amount'], 'discount_amount' => $row['discount_amount'], 'total' => $row['total'], 'commerce_snapshot' => $row]);
    }

    public function test_free_shipping_is_automatic_and_keeps_the_courier_funding(): void
    {
        $row = $this->quote()['orders'][$this->seller->id];
        $this->assertEquals(60, $row['shipping_amount']);
        $this->assertEquals(60, $row['discount_amount']);
        $this->assertEquals(200, $row['total']);
        $this->assertSame('platform', $row['vouchers'][0]['funding_source']);
        $this->assertSame('shipping', $row['vouchers'][0]['kind']);
        $this->assertSame('PFSHIPFREE', $row['vouchers'][0]['code']);
        $this->assertSame('1 – 7 Nov', DeliveryGuarantee::label($row['delivery_policy']['from'], $row['delivery_policy']['by']));
        // A smaller shipping voucher must not replace the free-shipping policy.
        DB::table('commerce_vouchers')->insert(['code' => 'SHIP10', 'kind' => 'shipping', 'discount_type' => 'fixed', 'amount' => 10, 'minimum_spend' => 0, 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'usage_limit' => 10, 'per_buyer_limit' => 1]);
        $this->assertEquals(200, $this->quote(['SHIP10'])['total']);
    }

    public function test_dates_are_saved_on_new_orders_and_old_orders_are_not_changed(): void
    {
        $order = $this->order();
        $saved = $order->delivery_guaranteed_by->toDateTimeString();
        $this->assertSame('2026-11-07 23:59:59', $saved);
        $this->travel(2)->days();
        $this->assertSame($saved, $order->fresh()->delivery_guaranteed_by->toDateTimeString());
        $legacy = Order::create(['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'status' => 'placed', 'items' => [], 'total' => 10]);
        $this->assertNull($legacy->delivery_guaranteed_by);
    }

    public function test_late_order_gets_exactly_one_private_voucher_and_notification(): void
    {
        $order = $this->order();
        $this->travel(8)->days();
        $this->assertSame(1, app(DeliveryGuarantee::class)->sweep());
        $this->assertSame(0, app(DeliveryGuarantee::class)->sweep());
        $award = DB::table('delivery_late_vouchers')->where('order_id', $order->id)->first();
        $voucher = DB::table('commerce_vouchers')->find($award->voucher_id);
        $this->assertEquals(40, $voucher->amount);
        $this->assertSame(1, (int) $voucher->usage_limit);
        $this->assertTrue(DeliveryGuarantee::voucherBelongsTo($voucher->id, $this->buyer->id));
        $this->assertFalse(DeliveryGuarantee::voucherBelongsTo($voucher->id, $this->other->id));
        $this->assertDatabaseHas('commerce_claims', ['buyer_id' => $this->buyer->id, 'voucher_id' => $voucher->id]);
        $this->assertSame(1, DB::table('notifications')->where('notification_type', 'delivery_voucher')->count());
        $quote = $this->quote([$voucher->code]);
        $this->assertEquals(160, $quote['total']);
        app(BuyerCommerce::class)->allocate($order->id, $this->buyer, $quote['orders'][$this->seller->id], false);
        $this->expectException(ValidationException::class);
        $this->quote([$voucher->code]);
    }

    public function test_late_voucher_cannot_be_redeemed_by_another_buyer(): void
    {
        $this->order();
        $this->travel(8)->days();
        app(DeliveryGuarantee::class)->sweep();
        $award = DB::table('delivery_late_vouchers')->first();
        $code = DB::table('commerce_vouchers')->where('id', $award->voucher_id)->value('code');
        $this->actingAs($this->other)->postJson(route('buyer.commerce.claim', $award->voucher_id))->assertForbidden();
        $this->buyer = $this->other;
        $this->cart->update(['buyer_id' => $this->other->id]);
        $this->address->update(['buyer_id' => $this->other->id]);
        $this->expectException(ValidationException::class);
        $this->quote([$code]);
    }

    public function test_on_time_delivery_and_cancelled_orders_get_no_compensation(): void
    {
        $onTime = $this->order();
        $cancelled = $this->order();
        DB::table('orders')->where('id', $onTime->id)->update(['status' => 'completed']);
        DB::table('orders')->where('id', $cancelled->id)->update(['status' => 'cancelled']);
        DB::table('order_status_history')->insert(['id' => 'delivered-on-time', 'order_id' => $onTime->id, 'status' => 'delivered', 'created_at' => now()->addDays(5)]);
        $this->travel(8)->days();
        $this->assertSame(0, app(DeliveryGuarantee::class)->sweep());
    }

    public function test_late_delivery_is_eligible_even_after_completion(): void
    {
        $order = $this->order();
        $this->travel(8)->days();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'completed']);
        DB::table('order_status_history')->insert(['id' => 'delivered-late', 'order_id' => $order->id, 'status' => 'delivered', 'created_at' => now()]);
        $this->assertSame(1, app(DeliveryGuarantee::class)->sweep());
    }

    public function test_product_shipping_section_shows_real_policy_and_dynamic_dates(): void
    {
        $this->actingAs($this->buyer)->view('partials.product-shipping', ['product' => ['seller_id' => $this->seller->id]])->assertSee('Delivery 1 – 7 Nov')->assertDontSee('Free shipping')->assertDontSee('₱40 voucher')->assertSee('pd-shipping-icon');
        $this->travelTo(now()->setDate(2026, 11, 28));
        $window = DeliveryGuarantee::window();
        $this->assertSame('29 Nov – 5 Dec', DeliveryGuarantee::label($window['from'], $window['by']));
    }

    public function test_buyer_location_selects_seller_free_area_and_delivery_window(): void
    {
        $oldOrder = $this->order();
        $oldDeadline = $oldOrder->delivery_guaranteed_by->toDateTimeString();
        DB::table('commerce_shipping_rates')->insert(['seller_id' => $this->seller->id, 'region' => 'Other Region', 'first_kg' => 90, 'additional_kg' => 20, 'free_shipping' => false, 'delivery_days_min' => 4, 'delivery_days_max' => 9]);
        $this->address->update(['region' => 'Other Region']);
        $row = $this->quote()['orders'][$this->seller->id];
        $this->assertFalse($row['free_shipping_eligible']);
        $this->assertEquals(290, $row['total']);
        $this->assertEquals(0, $row['discount_amount']);
        $this->assertSame('4 – 9 Nov', $row['delivery_policy']['label']);
        $preview = DeliveryGuarantee::forProduct($this->seller->id, $this->buyer);
        $this->assertSame('Delivery 4 – 9 Nov', $preview['label']);
        $this->assertSame($oldDeadline, $oldOrder->fresh()->delivery_guaranteed_by->toDateTimeString());
    }

    public function test_free_shipping_is_not_applied_to_other_sellers_in_the_same_cart(): void
    {
        $second = $this->person('seller');
        $product = Product::create(['seller_id' => $second->id, 'name' => 'Paid-shipping item', 'price' => 100, 'stock' => 5, 'weight_grams' => 400, 'status' => 'active']);
        $cart = CartItem::create(['buyer_id' => $this->buyer->id, 'product_id' => $product->id, 'seller_slug' => $second->username, 'name' => 'Paid-shipping item', 'price' => 100, 'qty' => 1]);
        DB::table('commerce_shipping_rates')->insert(['seller_id' => $second->id, 'region' => 'Test Region', 'first_kg' => 80, 'additional_kg' => 10, 'free_shipping' => false, 'delivery_days_min' => 2, 'delivery_days_max' => 5]);
        $quote = DB::transaction(fn () => app(BuyerCommerce::class)->quote($this->buyer, [$this->cart->id, $cart->id], $this->address->id));
        $this->assertEquals(200, $quote['orders'][$this->seller->id]['total']);
        $this->assertEquals(180, $quote['orders'][$second->id]['total']);
        $this->assertEquals(380, $quote['total']);
        $this->assertSame('2 – 5 Nov', $quote['orders'][$second->id]['delivery_policy']['label']);
    }

    public function test_shipping_preview_does_not_guess_dates_without_an_address_or_for_unserved_regions(): void
    {
        $this->assertSame('Select a delivery address to see delivery dates', DeliveryGuarantee::forProduct($this->seller->id, null)['label']);
        $this->address->update(['region' => 'Unserved Region']);
        $this->assertSame('Delivery is unavailable for your address', DeliveryGuarantee::forProduct($this->seller->id, $this->buyer)['label']);
        $this->expectException(ValidationException::class);
        $this->quote();
    }

    public function test_reserved_free_shipping_code_cannot_bypass_seller_region_settings(): void
    {
        DB::table('commerce_shipping_rates')->where('seller_id', $this->seller->id)->update(['free_shipping' => false]);
        $this->expectException(ValidationException::class);
        $this->quote(['PFSHIPFREE']);
    }

    public function test_seller_can_save_regional_offer_and_estimates_with_valid_range(): void
    {
        $payload = ['region' => 'Test Region', 'first_kg' => 60, 'additional_kg' => 20, 'is_active' => 1, 'free_shipping' => 1, 'delivery_days_min' => 2, 'delivery_days_max' => 4, 'late_voucher_enabled' => 1, 'late_voucher_amount' => 25];
        $this->actingAs($this->seller)->post(route('seller.commerce.shipping'), $payload)->assertRedirect();
        $this->assertDatabaseHas('commerce_shipping_rates', ['seller_id' => $this->seller->id, 'region' => 'Test Region', 'free_shipping' => 1, 'delivery_days_min' => 2, 'delivery_days_max' => 4]);
        $this->postJson(route('seller.commerce.shipping'), array_replace($payload, ['delivery_days_max' => 1]))->assertUnprocessable();
        $this->assertSame('Delivery 2 – 4 Nov', DeliveryGuarantee::forProduct($this->seller->id, $this->buyer)['label']);
        $this->assertEquals(25, $this->quote()['orders'][$this->seller->id]['delivery_policy']['late_voucher_amount']);
    }

    public function test_disabled_seller_offer_does_not_issue_a_late_voucher(): void
    {
        DB::table('commerce_shipping_rates')->where('seller_id', $this->seller->id)->update(['late_voucher_enabled' => false]);
        $order = $this->order();
        $this->assertEquals(0, $order->commerce_snapshot['delivery_policy']['late_voucher_amount']);
        $this->travel(8)->days();
        $this->assertSame(0, app(DeliveryGuarantee::class)->sweep());
        $this->assertDatabaseCount('delivery_late_vouchers', 0);
    }

    public function test_late_voucher_amount_and_enabled_state_are_snapshotted_from_the_seller(): void
    {
        DB::table('commerce_shipping_rates')->where('seller_id', $this->seller->id)->update(['late_voucher_amount' => 25]);
        $order = $this->order();
        DB::table('commerce_shipping_rates')->where('seller_id', $this->seller->id)->update(['late_voucher_enabled' => false, 'late_voucher_amount' => 99]);
        $this->travel(8)->days();
        $this->assertSame(1, app(DeliveryGuarantee::class)->sweep());
        $award = DB::table('delivery_late_vouchers')->where('order_id', $order->id)->first();
        $this->assertEquals(25, DB::table('commerce_vouchers')->where('id', $award->voucher_id)->value('amount'));
        $this->assertSame('store', DB::table('commerce_vouchers')->where('id', $award->voucher_id)->value('kind'));
        $this->assertEquals($this->seller->id, DB::table('commerce_vouchers')->where('id', $award->voucher_id)->value('seller_id'));
        $this->assertSame(0, app(DeliveryGuarantee::class)->sweep());
    }

    public function test_cod_checkout_saves_guarantee_and_free_shipping_without_duplicate_orders(): void
    {
        $quote = $this->quote();
        $payload = ['items' => [$this->cart->id], 'delivery_address_id' => $this->address->id, 'payment_method' => 1, 'checkout_id' => (string) \Illuminate\Support\Str::uuid(), 'quote_hash' => hash('sha256', json_encode($quote))];
        $this->actingAs($this->buyer)->post(route('buyer.checkout'), $payload)->assertRedirect();
        $this->assertSame(1, Order::count());
        $order = Order::firstOrFail();
        $this->assertNotNull($order->delivery_guaranteed_by);
        $this->assertEquals(200, $order->total);
        $this->assertEquals(60, $order->discount_amount);
        $this->assertSame('platform', $order->commerce_snapshot['vouchers'][0]['funding_source']);
        $this->post(route('buyer.checkout'), $payload)->assertRedirect();
        $this->assertSame(1, Order::count());
    }

    public function test_gcash_reservation_keeps_free_shipping_and_the_original_promised_dates(): void
    {
        $quote = $this->quote();
        $purchase = app(GcashWorkflow::class)->create($this->buyer, [$this->cart->id], 2, (string) \Illuminate\Support\Str::uuid(), null, $this->address->id, null, $quote)[0];
        $this->assertEquals(200, $purchase->amount_due);
        $this->assertEquals($quote['orders'][$this->seller->id]['delivery_policy'], $purchase->snapshot['commerce']['delivery_policy']);
        $this->assertEquals(60, $purchase->snapshot['discount_amount']);
        $flow = app(GcashWorkflow::class);
        $purchase = $flow->instructions($this->seller, $purchase->id, $purchase->revision, 'Seller', '09123456789');
        $purchase = $flow->proof($this->buyer, $purchase->id, (string) \Illuminate\Support\Str::uuid(), 'gcash-proofs/test.png', 'image/png', 100, hash('sha256', 'receipt'));
        $purchase = $flow->decide($this->seller, $purchase->id, $purchase->revision, true, $purchase->amount_due, null, 'GCASH-GUARANTEE-TEST');
        $order = $flow->place($this->buyer, $purchase->id, $purchase->revision);
        $this->assertSame($quote['orders'][$this->seller->id]['delivery_policy']['by'], $order->delivery_guaranteed_by->toDateTimeString());
        $this->assertEquals(200, $order->total);
        $this->assertSame($order->id, $flow->place($this->buyer, $purchase->id, $purchase->revision)->id);
    }
}
