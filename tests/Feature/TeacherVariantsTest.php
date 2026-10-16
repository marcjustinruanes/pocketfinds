<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class TeacherVariantsTest extends TestCase
{
    use WorkflowSchema;
    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp(); $this->createWorkflowSchema(); Storage::fake('public');
        DB::table('categories')->insert(['id' => 1, 'name' => 'Home & Living']);
        DB::table('payment_methods')->insert(['id' => 1, 'name' => 'Cash on Delivery', 'type' => 'cod', 'is_active' => true]);
        $this->seller = User::create(['username' => 'matrix_seller', 'given_names' => 'Matrix Seller', 'account_type' => 'seller', 'status' => 'approved', 'category_id' => 1]);
        $this->buyer = User::create(['given_names' => 'Matrix Buyer', 'last_name' => 'Audit', 'account_type' => 'buyer', 'status' => 'approved', 'contact_no' => '09177770001',
            'province' => 'Metro Manila', 'municipality' => 'City of Makati', 'barangay' => 'Bel-Air', 'street' => 'Matrix Audit Street']);
    }

    private function data(): array
    {
        $axes = [];
        foreach (['Color' => ['Red', 'Blue'], 'Size' => ['S', 'M', 'L']] as $name => $options) $axes[] = ['id' => (string)Str::uuid(), 'name' => $name, 'options' => array_map(fn ($value) => ['id' => (string)Str::uuid(), 'value' => $value], $options)];
        $rows = [];
        foreach ($axes[0]['options'] as $color) foreach ($axes[1]['options'] as $i => $size) $rows[] = ['id' => (string)Str::uuid(), 'option_ids' => [$color['id'], $size['id']],
            'price' => [99,129,149][$i], 'stock' => 5, 'sku' => 'BCP-'.strtoupper($color['value']).'-'.$size['value']];
        return ['intent' => 'publish', 'creation_key' => (string)Str::uuid(), 'category_id' => 1, 'name' => 'Buri Coin Purse',
            'description' => 'Handwoven coin purses with color and size combinations.', 'price' => '', 'stock' => '', 'weight_grams' => 100, 'condition' => 'new',
            'variant_axes' => json_encode($axes), 'variant_rows' => json_encode($rows), 'images' => [UploadedFile::fake()->image('purse.jpg')]];
    }

    private function published(): Product
    {
        $result = $this->actingAs($this->seller)->postJson('/seller/inventory', $this->data())->assertOk();
        return Product::findOrFail($result->json('product_id'));
    }

    private function editData(Product $product): array
    {
        return ['intent' => 'publish', 'name' => $product->name, 'category_id' => 1, 'description' => $product->description, 'price' => $product->price,
            'stock' => $product->stock, 'weight_grams' => 100, 'condition' => 'new', 'variant_axes' => json_encode($product->variant_axes),
            'variant_rows' => json_encode($product->variant_rows), 'existing_images' => $product->images];
    }

    public function test_sel_var_001_six_combinations_save_with_independent_price_stock_and_sku(): void
    {
        $product = $this->published(); $this->assertCount(2, $product->variant_axes); $this->assertCount(6, $product->variant_rows); $this->assertSame(30, $product->total_stock);
        $this->assertEquals([99,129,149,99,129,149], array_column($product->variant_rows, 'price'));
        $this->assertDatabaseCount('product_skus', 6);
        $this->actingAs($this->buyer)->get('/buyer/product/'.$product->id)->assertOk()->assertViewHas('product', fn ($mapped) => $mapped['price'] === 99.0 && $mapped['price_max'] === 149.0);
    }

    public function test_sel_var_002_003_004_reject_third_axis_twenty_first_option_duplicate_or_empty_names(): void
    {
        foreach (['axes', 'options', 'duplicate', 'empty'] as $kind) {
            $data = $this->data(); $axes = json_decode($data['variant_axes'], true);
            if ($kind === 'axes') $axes[] = ['id' => (string)Str::uuid(), 'name' => 'Material', 'options' => [['id' => (string)Str::uuid(), 'value' => 'Buri']]];
            if ($kind === 'options') $axes[0]['options'] = array_map(fn ($n) => ['id' => (string)Str::uuid(), 'value' => 'Color '.$n], range(1,21));
            if ($kind === 'duplicate') $axes[0]['options'][1]['value'] = 'red';
            if ($kind === 'empty') $axes[0]['options'][1]['value'] = '';
            $data['variant_axes'] = json_encode($axes); $this->actingAs($this->seller)->postJson('/seller/inventory', $data)->assertUnprocessable();
            $this->assertDatabaseCount('products', 0);
        }
    }

    public function test_sel_var_005_missing_zero_price_and_negative_stock_have_specific_row_errors(): void
    {
        foreach ([['price',''], ['price',0], ['price','99.999'], ['stock',-1], ['stock',1.5], ['stock',100000]] as [$field,$value]) {
            $data = $this->data(); $rows = json_decode($data['variant_rows'], true); $rows[5][$field] = $value; $data['variant_rows'] = json_encode($rows);
            $this->actingAs($this->seller)->postJson('/seller/inventory', $data)->assertUnprocessable()->assertJsonValidationErrors('variant_rows.5.'.$field);
            $this->assertDatabaseCount('products', 0); $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_sel_var_006_duplicate_skus_within_rows_and_against_base_product_rejected_atomically(): void
    {
        $existing = Product::create(['seller_id' => $this->seller->id, 'name' => 'Existing Tote', 'price' => 199, 'stock' => 10, 'sku' => 'MCH-TOTE-NAT-S']);
        foreach (['BCP-RED-S', 'MCH-TOTE-NAT-S', 'mch-tote-nat-s'] as $sku) {
            $data = $this->data(); $rows = json_decode($data['variant_rows'], true); $rows[3]['sku'] = $sku; $data['variant_rows'] = json_encode($rows);
            $this->actingAs($this->seller)->postJson('/seller/inventory', $data)->assertUnprocessable()->assertJsonValidationErrors('variant_rows.3.sku');
            $this->assertDatabaseCount('products', 1); $this->assertDatabaseCount('product_skus', 1);
        }
        $this->assertSame('MCH-TOTE-NAT-S', $existing->fresh()->sku);
    }

    public function test_variant_stock_changes_only_the_selected_row_and_cancellation_restores_it(): void
    {
        $product = $this->published(); $row = $product->variant_rows[1];
        $result = $this->actingAs($this->buyer)->postJson('/buyer/cart/add', ['product_id' => $product->id, 'qty' => 1, 'variation_group' => 'variant:'.$row['id'], 'variation_value' => 'forged label'])->assertOk();
        $cart = CartItem::findOrFail($result->json('item_id')); $this->assertSame('Red-M', $cart->variation_value); $this->assertEquals(129, $cart->price);
        $this->post('/buyer/checkout', ['items' => [$cart->id], 'payment_method' => 1])->assertSessionMissing('error');
        $after = $product->fresh(); $this->assertSame(4, $after->variant_rows[1]['stock']);
        foreach ([0,2,3,4,5] as $index) $this->assertSame(5, $after->variant_rows[$index]['stock']);
        $order = Order::firstOrFail(); $this->assertEquals(129, $order->items[0]['price']);
        $this->patch('/buyer/orders/'.$order->id.'/cancel', ['cancellation_reason' => 'Changed my mind'])->assertSessionMissing('error');
        $this->assertSame(5, $product->fresh()->variant_rows[1]['stock']);
    }

    public function test_sel_var_011_removed_variant_disables_saved_cart_but_leaves_other_rows(): void
    {
        $product = $this->published(); $row = $product->variant_rows[5];
        $this->actingAs($this->buyer)->postJson('/buyer/cart/add', ['product_id' => $product->id, 'qty' => 1, 'variation_group' => 'variant:'.$row['id'], 'variation_value' => $row['label']])->assertOk();
        $data = $this->editData($product); $data['variant_rows'] = json_encode(array_slice($product->variant_rows,0,5));
        $this->actingAs($this->seller)->patchJson('/seller/inventory/'.$product->id, $data)->assertOk();
        $this->actingAs($this->buyer)->get('/buyer/cart')->assertOk()->assertViewHas('items', fn ($items) => $items->first()['unavailable_reason'] === 'Variation unavailable');
        $this->assertCount(5, $product->fresh()->variant_rows); $this->assertDatabaseCount('product_skus', 5);
    }

    public function test_sel_var_012_removing_variant_with_unfinished_order_is_blocked_without_changes(): void
    {
        $product = $this->published(); $row = $product->variant_rows[0];
        Order::create(['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'order_number' => 'VARIANT-UNFINISHED', 'status' => 'placed',
            'items' => [['product_id' => $product->id, 'variation_group' => 'variant:'.$row['id'], 'variation_value' => $row['label'], 'qty' => 1, 'price' => 99]], 'total' => 99]);
        $data = $this->editData($product); $data['variant_rows'] = json_encode(array_slice($product->variant_rows,1));
        $this->actingAs($this->seller)->patchJson('/seller/inventory/'.$product->id, $data)->assertUnprocessable()->assertJsonValidationErrors('variant_rows');
        $this->assertCount(6, $product->fresh()->variant_rows); $this->assertDatabaseCount('product_skus', 6);
    }
}
