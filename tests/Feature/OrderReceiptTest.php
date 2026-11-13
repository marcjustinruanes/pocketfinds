<?php

namespace Tests\Feature;

use App\Models\{GcashPurchase, Order};
use App\Support\OrderDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderReceiptTest extends RiderCompensationTest
{
    private function purchase(string $flow = 'admin', ?string $verified = null): Order
    {
        (require database_path('migrations/2026_11_08_120000_add_admin_gcash_review_flow.php'))->up();
        $method = DB::table('payment_methods')->insertGetId(['name' => 'GCash', 'type' => 'ewallet', 'is_active' => true]);
        $items = [['name' => 'Receipt item', 'price' => '1000.00', 'original_price' => '1200.00', 'qty' => 1, 'variation_group' => 'Color', 'variation_value' => 'Blue']];
        $purchase = GcashPurchase::create([
            'id' => (string)Str::uuid(), 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id,
            'checkout_id' => (string)Str::uuid(), 'request_hash' => hash('sha256', Str::uuid()), 'payment_method_id' => $method,
            'snapshot' => ['items' => $items, 'subtotal' => '1000', 'shipping_amount' => '50', 'discount_amount' => '0'],
            'amount_due' => '1050', 'payment_state' => $verified ? 'order_placed' : 'awaiting_payment', 'reservation_state' => 'transferred',
            'verified_amount' => $verified, 'verified_at' => $verified ? now() : null,
            'payment_reference' => $verified ? 'RECEIPT-123456' : null, 'review_flow' => $flow,
        ]);
        return Order::create([
            'order_number' => 'PF-RECEIPT-'.Str::upper(Str::random(6)), 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'status' => 'placed', 'gcash_purchase_id' => $purchase->id,
            'subtotal' => 1000, 'shipping_amount' => 50, 'discount_amount' => 0, 'total' => 1050, 'payment_method' => 'GCash',
            'items' => $items, 'commerce_snapshot' => ['vouchers' => []],
            'shipping_address' => ['recipient_name' => 'Snapshot recipient', 'contact_no' => '09170000000', 'street' => 'Receipt Street', 'municipality' => 'Santa Cruz', 'province' => 'Laguna'],
        ]);
    }

    public function test_receipt_unpaid_order_is_a_summary_with_no_inferred_payment(): void
    {
        $order = $this->purchase();
        $before = DB::table('gcash_purchases')->find($order->gcash_purchase_id);
        $this->actingAs($this->buyer)->get(route('orders.receipt', $order))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Order summary')->assertSee('Awaiting payment')->assertSee('1,050.00')
            ->assertSee('Snapshot recipient')->assertSee('09170000000')->assertSee('Color / Blue')
            ->assertDontSee('1,200.00')->assertDontSee('Seller earnings and settlement')
            ->assertDontSee('Delivery OTP');
        $this->assertEquals($before, DB::table('gcash_purchases')->find($order->gcash_purchase_id));
        $this->actingAs($this->seller)->get(route('orders.receipt', $order))->assertForbidden();
    }

    public function test_receipt_verified_platform_payment_is_not_a_released_seller_payout(): void
    {
        $order = $this->purchase('admin', '1050');
        $this->actingAs($this->buyer)->get(route('orders.receipt', $order))->assertOk()
            ->assertSee('Payment receipt')->assertSee('Payment verified')->assertSee('RECEIPT-123456')
            ->assertSee('PocketFinds platform account')->assertDontSee('Seller earnings and settlement');
        $this->actingAs($this->seller)->get(route('orders.receipt', $order))->assertOk()
            ->assertSee('No seller payout is recorded')->assertSee('Buyer payment approval does not prove');
    }

    public function test_receipt_partial_and_unreviewed_proof_do_not_become_paid_receipts(): void
    {
        $order = $this->purchase();
        DB::table('gcash_purchases')->where('id', $order->gcash_purchase_id)->update(['payment_state' => 'proof_submitted']);
        $detail = app(OrderDetails::class)->forOrders([$order])->get($order->id);
        $this->assertSame(0, $detail['applied_cents']);
        DB::table('gcash_purchases')->where('id', $order->gcash_purchase_id)->update(['payment_state' => 'underpaid', 'verified_amount' => 500]);
        $detail = app(OrderDetails::class)->forOrders([$order])->get($order->id);
        $this->assertSame('Order summary', $detail['title']);
        $this->assertSame(50000, $detail['applied_cents']);
        $this->assertSame(55000, $detail['remaining_cents']);
        $this->assertNull($detail['received_at']);
    }

    public function test_receipt_cod_collection_is_separate_from_hub_remittance_and_payroll(): void
    {
        $detail = app(OrderDetails::class)->forOrders([$this->order])->get($this->order->id);
        $this->assertSame('Payment receipt', $detail['title']);
        $this->assertSame(110000, $detail['cash_collected_cents']);
        $this->assertSame(0, $detail['cash_verified_cents']);
        $this->actingAs($this->admin)->get(route('orders.receipt', $this->order))->assertOk()
            ->assertSee('Cash collection recorded')->assertSee('Hub-verified cash remittance')->assertSee('Separate rider compensation');
        $this->actingAs($this->rider)->get(route('rider.deliveries.show', $this->shipment))->assertOk()
            ->assertSee('Collection and rider earnings')->assertSee('No payout recorded')->assertDontSee('Delivery OTP:');
    }

    public function test_receipt_is_private_to_the_order_participants(): void
    {
        $other = $this->person('buyer');
        $this->actingAs($other)->get(route('orders.receipt', $this->order))->assertForbidden();
        $other = $this->person('admin', 'Other company');
        $this->actingAs($other)->get(route('orders.receipt', $this->order))->assertForbidden();
        $this->actingAs($this->rider)->get(route('orders.receipt', $this->order))->assertForbidden();
        $this->buyer->update(['status' => 'suspended']);
        $this->actingAs($this->buyer)->get(route('orders.receipt', $this->order))->assertForbidden();
    }

    public function test_receipt_legacy_seller_payment_keeps_its_actual_receiver(): void
    {
        $order = $this->purchase('seller', '1050');
        $this->actingAs($this->seller)->get(route('orders.receipt', $order))->assertOk()
            ->assertSee('Seller account')->assertSee('seller-received GCash')->assertDontSee('PocketFinds platform account');
    }

    public function test_receipt_existing_order_screens_keep_their_actions_and_pending_summary(): void
    {
        $this->purchase();
        $response = $this->actingAs($this->buyer)->get(route('buyer.orders'))->assertOk()
            ->assertSee('View products and order total')->assertSee('Print / save PDF')
            ->assertSee('data-show-payment-progress', false)->assertSee('data-order-details', false)
            ->assertDontSee('Seller earnings and settlement');
        if (getenv('ORDER_RECEIPT_QA_EXPORT') === '1') {
            file_put_contents(storage_path('framework/testing/buyer-orders-preview.html'), $response->getContent());
        }
        $response = $this->actingAs($this->seller)->get(route('seller.orders'))->assertOk()
            ->assertSee('Seller earnings and settlement')->assertSee('data-seller-order-copy', false);
        if (getenv('ORDER_RECEIPT_QA_EXPORT') === '1') {
            file_put_contents(storage_path('framework/testing/seller-orders-preview.html'), $response->getContent());
        }
    }

    public function test_receipt_overpayment_and_refund_remain_separate_from_the_order_total(): void
    {
        $order = $this->purchase('admin', '1050');
        DB::table('gcash_purchase_history')->insert([
            'purchase_id' => $order->gcash_purchase_id, 'actor_id' => $this->admin->id, 'event' => 'overpayment_pending',
            'before' => json_encode(['received_amount' => '1100', 'refund_amount' => '50']), 'after' => '{}', 'created_at' => now(),
        ]);
        $detail = app(OrderDetails::class)->forOrders([$order])->get($order->id);
        $this->assertSame(105000, $detail['applied_cents']);
        $this->assertSame(110000, $detail['received_cents']);
        $this->assertSame(['amount' => 5000, 'status' => 'Awaiting refund'], $detail['excess']);
    }
}
