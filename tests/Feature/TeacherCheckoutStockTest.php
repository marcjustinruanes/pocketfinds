<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class TeacherCheckoutStockTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        $this->travelTo(Carbon::parse('2026-10-16 12:00:00'));
        DB::table('payment_methods')->insert(['id' => 1, 'name' => 'Cash on Delivery', 'is_active' => true]);
    }

    private function fixture(int $stock = 10, int $qty = 2): array
    {
        $buyer = User::create(['account_type' => 'buyer', 'status' => 'approved', 'given_names' => 'Juan',
            'province' => 'Metro Manila', 'municipality' => 'Makati City', 'barangay' => 'Bel-Air', 'street' => 'Office Street']);
        $seller = User::create(['account_type' => 'seller', 'status' => 'approved', 'username' => 'teacher_seller', 'shipping_fee' => 20]);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Handwoven Basket', 'price' => 100, 'stock' => $stock, 'status' => 'active']);
        $cart = $this->cart($buyer, $seller, $product, $qty);

        return [$buyer, $seller, $product, $cart];
    }

    private function cart(User $buyer, User $seller, Product $product, int $qty): CartItem
    {
        return CartItem::create(['buyer_id' => $buyer->id, 'product_id' => $product->id, 'seller_slug' => $seller->username,
            'seller' => 'Teacher Shop', 'name' => $product->name, 'price' => 100, 'qty' => $qty, 'variation_group' => '', 'variation_value' => '']);
    }

    public function test_buy_cart_006_refresh_uses_live_price_and_checkout_charges_it(): void
    {
        [$buyer, $seller, $product, $cart] = $this->fixture();
        $product->update(['price' => 420]);
        $this->actingAs($buyer)->get('/buyer/cart')->assertOk()->assertViewHas('items', fn ($items) => (float) $items->first()['price'] === 420.0);
        $this->assertEquals(420, $cart->fresh()->price);
        $this->post('/buyer/checkout', ['items' => [$cart->id], 'payment_method' => 1])->assertRedirect()->assertSessionMissing('error');
        $order = Order::firstOrFail();
        $this->assertEquals(420, $order->items[0]['price']);
        $this->assertEquals(860, $order->total);
    }

    public function test_buy_cart_007_unavailable_lines_are_disabled_while_available_line_can_checkout(): void
    {
        [$buyer, $seller, $product, $cart] = $this->fixture();
        $product->update(['stock' => 0]);
        $deleted = Product::create(['seller_id' => $seller->id, 'name' => 'Deleted Basket', 'price' => 100, 'stock' => 10, 'status' => 'active']);
        $deletedCart = $this->cart($buyer, $seller, $deleted, 1);
        $deleted->delete();
        $available = Product::create(['seller_id' => $seller->id, 'name' => 'Available Basket', 'price' => 100, 'stock' => 10, 'status' => 'active']);
        $availableCart = $this->cart($buyer, $seller, $available, 1);
        $response = $this->actingAs($buyer)->get('/buyer/cart')->assertOk();
        $response->assertViewHas('items', fn ($items) => $items[$cart->id]['unavailable_reason'] === 'Out of stock'
            && $items[$deletedCart->id]['unavailable_reason'] === 'Unavailable'
            && $items[$availableCart->id]['unavailable_reason'] === null);
        $response->assertSee('aria-label="Select Handwoven Basket"  disabled', false);
        $this->post('/buyer/checkout', ['items' => [$availableCart->id], 'payment_method' => 1])->assertRedirect()->assertSessionMissing('error');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame($available->id, Order::firstOrFail()->items[0]['product_id']);
        $this->assertDatabaseCount('cart_items', 2);
    }

    public function test_sel_inv_001_cod_stock_leaves_inventory_at_checkout_and_is_not_deducted_at_confirmation(): void
    {
        [$buyer, $seller, $product, $cart] = $this->fixture();
        $this->actingAs($buyer)->post('/buyer/checkout', ['items' => [$cart->id], 'payment_method' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame('placed', $order->status);
        $this->assertTrue($order->inventory_reserved);
        $this->assertSame(8, $product->fresh()->total_stock);
        $this->actingAs($seller)->patch('/seller/orders/'.$order->id.'/confirm')->assertRedirect()->assertSessionMissing('error');
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->total_stock);
    }

    public function test_sel_inv_003_buyer_cancelling_before_confirmation_restores_stock_only_once(): void
    {
        [$buyer, $seller, $product, $cart] = $this->fixture();
        $this->actingAs($buyer)->post('/buyer/checkout', ['items' => [$cart->id], 'payment_method' => 1])->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame(8, $product->fresh()->total_stock);
        $this->patch('/buyer/orders/'.$order->id.'/cancel', ['cancellation_reason' => 'Changed my mind'])->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->total_stock);
        $this->patch('/buyer/orders/'.$order->id.'/cancel', ['cancellation_reason' => 'Changed my mind'])->assertRedirect();
        $this->assertSame(10, $product->fresh()->total_stock);
    }

    public function test_last_unit_cannot_be_ordered_again_from_a_stale_second_buyers_cart(): void
    {
        [$buyer, $seller, $product, $cart] = $this->fixture(1, 1);
        $other = User::create(['account_type' => 'buyer', 'status' => 'approved', 'province' => 'Metro Manila',
            'municipality' => 'Makati City', 'barangay' => 'Bel-Air', 'street' => 'Another Street']);
        $stale = $this->cart($other, $seller, $product, 1);
        $this->actingAs($buyer)->post('/buyer/checkout', ['items' => [$cart->id], 'payment_method' => 1])->assertRedirect();
        $this->actingAs($other)->post('/buyer/checkout', ['items' => [$stale->id], 'payment_method' => 1])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, $product->fresh()->total_stock);
        $this->assertSame(1, Order::count());
        $this->assertNotNull($stale->fresh());
    }

    public function test_duplicate_cart_ids_do_not_duplicate_items_or_deductions(): void
    {
        [$buyer, $seller, $product, $cart] = $this->fixture();
        $this->actingAs($buyer)->post('/buyer/checkout', ['items' => [$cart->id, $cart->id], 'payment_method' => 1])->assertRedirect();
        $this->assertSame(8, $product->fresh()->total_stock);
        $this->assertCount(1, Order::firstOrFail()->items);
        $this->assertEquals(220, Order::firstOrFail()->total);
    }

    public function test_two_cart_lines_for_same_product_cannot_overdraw_stock_and_roll_back_together(): void
    {
        [$buyer, $seller, $product, $cart] = $this->fixture(10, 6);
        $second = $this->cart($buyer, $seller, $product, 5);
        $this->actingAs($buyer)->post('/buyer/checkout', ['items' => [$cart->id, $second->id], 'payment_method' => 1])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(10, $product->fresh()->total_stock);
        $this->assertSame(0, Order::count());
        $this->assertSame(2, CartItem::count());
    }

    public function test_sel_prof_009_010_vacation_blocks_new_cart_and_cod_checkout_then_reopening_allows_order(): void
    {
        [$buyer, $seller, $product, $cart] = $this->fixture();
        $this->actingAs($seller)->post('/seller/account/vacation', ['is_on_vacation' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($seller->fresh()->is_on_vacation);
        $this->actingAs($buyer)->postJson('/buyer/cart/add', ['product_id' => $product->id, 'qty' => 1])->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->post('/buyer/checkout', ['items' => [$cart->id], 'payment_method' => 1])->assertRedirect()->assertSessionHas('error', 'This store is on vacation and is not accepting new orders.');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame('active', $product->fresh()->status);
        $this->assertNotNull($cart->fresh());
        $this->actingAs($seller)->post('/seller/account/vacation', ['is_on_vacation' => 0])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post('/buyer/checkout', ['items' => [$cart->id], 'payment_method' => 1])->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_buy_trk_005_completion_occurs_at_168_hours_after_delivery_despite_other_order_updates(): void
    {
        [$buyer, $seller, $product, $cart] = $this->fixture();
        $order = Order::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'delivered', 'items' => [], 'total' => 100]);
        Shipment::create(['order_id' => $order->id, 'shipping_status' => 'delivered', 'tracking_number' => 'TEACHER-168', 'delivered_at' => now()]);
        $this->travel(167)->hours();
        $this->travel(59)->minutes();
        $this->travel(59)->seconds();
        $this->assertSame(0, Order::autoCompleteDelivered());
        $order->update(['buyer_note' => 'An update must not reset the delivery clock.']);
        $this->travel(1)->seconds();
        $this->assertSame(1, Order::autoCompleteDelivered());
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(0, Order::autoCompleteDelivered());
        $this->assertSame(2, DB::table('notifications')->count());
    }
}
