<?php

namespace Tests\Feature;

use App\Http\Controllers\SellerController;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductVariationSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class ProductCombinationTest extends TestCase
{
    use WorkflowSchema;

    private function group(): array
    {
        return ['name' => 'Options', 'type' => 'combinations', 'axes' => [
            ['name' => 'Color', 'options' => [
                ['value' => 'Pink', 'color' => '#ffaacc', 'existing_image' => 'product_images/pink.webp'],
                ['value' => 'Blue'],
            ]],
            ['name' => 'Size', 'options' => [['value' => 'S'], ['value' => 'M']]],
        ], 'options' => [
            ['value' => 'Pink / S', 'attributes' => ['Color' => 'Pink', 'Size' => 'S'], 'stock' => 3, 'price' => 120],
            ['value' => 'Pink / M', 'attributes' => ['Color' => 'Pink', 'Size' => 'M'], 'stock' => 5],
            ['value' => 'Blue / S', 'attributes' => ['Color' => 'Blue', 'Size' => 'S'], 'stock' => 0],
            ['value' => 'Blue / M', 'attributes' => ['Color' => 'Blue', 'Size' => 'M'], 'stock' => 2],
        ]];
    }

    public function test_combinations_keep_separate_stock_and_total_counts_each_sku_once(): void
    {
        $variations = [$this->group()];
        ProductVariationSet::validate($variations);
        $product = new Product(['variations' => $variations]);
        $this->assertSame(3, $product->availableStock('Options', 'Pink / S'));
        $this->assertSame(5, $product->availableStock('Options', 'Pink / M'));
        $this->assertSame(0, $product->availableStock('Options', 'Blue / S'));
        $this->assertSame(10, $product->total_stock);
        $groups = ProductVariationSet::displayGroups($variations);
        $this->assertSame(['Color', 'Size'], array_column($groups, 'name'));
        $this->assertSame([8, 2], array_column($groups[0]['options'], 'stock'));
    }

    public function test_saving_combinations_shares_the_color_photo_and_preserves_axes(): void
    {
        $variations = [$this->group()];
        $method = new \ReflectionMethod(SellerController::class, 'applyVariationImagesAndPrices');
        $args = [&$variations, []];
        $method->invokeArgs(new SellerController, $args);
        $this->assertSame('product_images/pink.webp', $variations[0]['axes'][0]['options'][0]['image']);
        $this->assertSame('product_images/pink.webp', $variations[0]['options'][0]['image']);
        $this->assertSame('product_images/pink.webp', $variations[0]['options'][1]['image']);
        $this->assertArrayNotHasKey('image', $variations[0]['options'][2]);
        $this->assertSame(['Color' => 'Pink', 'Size' => 'S'], $variations[0]['options'][0]['attributes']);
        $this->assertSame('#ffaacc', $variations[0]['axes'][0]['options'][0]['color']);
    }

    public function test_missing_or_invalid_combination_stock_is_rejected(): void
    {
        $group = $this->group();
        $group['options'][0]['stock'] = null;
        $this->expectException(ValidationException::class);
        ProductVariationSet::validate([$group]);
    }

    public function test_duplicate_combinations_are_rejected_even_with_a_different_label(): void
    {
        $group = $this->group();
        $group['options'][1]['attributes'] = $group['options'][0]['attributes'];
        $this->expectException(ValidationException::class);
        ProductVariationSet::validate([$group]);
    }

    public function test_missing_combinations_are_rejected(): void
    {
        $group = $this->group();
        array_pop($group['options']);
        $this->expectException(ValidationException::class);
        ProductVariationSet::validate([$group]);
    }

    public function test_legacy_stock_is_not_automatically_split_into_combinations(): void
    {
        $groups = [['name' => 'Color', 'options' => [['value' => 'Pink', 'stock' => 8]]]];
        $this->assertNull(ProductVariationSet::combination($groups));
        $this->assertSame($groups, ProductVariationSet::displayGroups($groups));
    }

    public function test_one_variation_keeps_individual_option_stock(): void
    {
        $groups = [['name' => 'Color', 'options' => [['value' => 'Pink', 'stock' => 8], ['value' => 'Blue', 'stock' => 5]]]];
        ProductVariationSet::validate($groups);
        $product = new Product(['variations' => $groups]);
        $this->assertNull(ProductVariationSet::combination($groups));
        $this->assertSame(8, $product->availableStock('Color', 'Pink'));
        $this->assertSame(5, $product->availableStock('Color', 'Blue'));
        $this->assertSame(13, $product->total_stock);
    }

    private function gramsGroup(): array
    {
        $group = $this->group();
        $group['axes'][1] = ['name' => 'Grams', 'options' => [['value' => '100g'], ['value' => '250g']]];
        foreach ($group['options'] as &$option) {
            $grams = $option['attributes']['Size'] === 'S' ? '100g' : '250g';
            unset($option['attributes']['Size']);
            $option['attributes']['Grams'] = $grams;
            $option['value'] = $option['attributes']['Color'].' / '.$grams;
        }

        return $group;
    }

    public function test_arbitrary_variation_groups_have_one_stock_per_combination(): void
    {
        $groups = [$this->gramsGroup()];
        ProductVariationSet::validate($groups);
        $product = new Product(['variations' => $groups]);
        $this->assertSame(3, $product->availableStock('Options', 'Pink / 100g'));
        $this->assertSame(5, $product->availableStock('Options', 'Pink / 250g'));
        $this->assertSame(10, $product->total_stock);
        $this->assertSame(['Color', 'Grams'], array_column(ProductVariationSet::displayGroups($groups), 'name'));
    }

    public function test_separate_stock_for_multiple_custom_variations_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        ProductVariationSet::validate([
            ['name' => 'Material', 'options' => [['value' => 'Cotton', 'stock' => 8]]],
            ['name' => 'Grams', 'options' => [['value' => '100g', 'stock' => 8]]],
        ]);
    }

    public function test_separate_color_and_size_quantities_are_rejected_on_save(): void
    {
        $this->expectException(ValidationException::class);
        ProductVariationSet::validate([
            ['name' => 'Color', 'options' => [['value' => 'Pink', 'stock' => 8]]],
            ['name' => 'Size', 'options' => [['value' => 'S', 'stock' => 8]]],
        ]);
    }

    public function test_preparing_legacy_inventory_removes_attribute_stock_without_guessing_pair_quantities(): void
    {
        $legacy = [
            ['name' => 'Color', 'options' => [['value' => 'Pink', 'stock' => 8]]],
            ['name' => 'Size', 'options' => [['value' => 'S', 'stock' => 3], ['value' => 'M', 'stock' => 5]]],
        ];
        $groups = ProductVariationSet::preparePairStock($legacy);
        $this->assertTrue($groups[0]['requires_stock_setup']);
        $this->assertCount(2, $groups[0]['options']);
        $this->assertSame([0, 0], array_column($groups[0]['options'], 'stock'));
        $this->assertArrayNotHasKey('stock', $groups[0]['axes'][0]['options'][0]);
        $this->assertArrayNotHasKey('stock', $groups[0]['axes'][1]['options'][0]);
        $this->assertSame('#ffc0cb', $groups[0]['axes'][0]['options'][0]['color']);
        $product = new Product(['variations' => $groups]);
        $this->assertSame(0, $product->availableStock('Options', 'Pink / S'));
    }

    public function test_cart_checks_the_selected_combination_and_stock_moves_only_that_pair(): void
    {
        $this->createWorkflowSchema();
        foreach (['2026_10_14_000004_add_gcash_purchases', '2026_10_16_000001_add_registration_business_details', '2026_10_16_000002_add_logistics_registration_details', '2026_10_17_000001_add_buyer_commerce_features'] as $file) {
            (require database_path('migrations/'.$file.'.php'))->up();
        }
        $buyer = User::create(['account_type' => 'buyer', 'username' => 'combination-buyer', 'email' => 'buyer@example.test', 'status' => 'approved']);
        $seller = User::create(['account_type' => 'seller', 'username' => 'combination-seller', 'email' => 'seller@example.test', 'status' => 'approved']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Shirt', 'price' => 100, 'stock' => 10, 'status' => 'active', 'variations' => [$this->group()]]);
        $payload = ['product_id' => $product->id, 'variation_group' => 'Options', 'variation_value' => 'Pink / S', 'qty' => 2];
        $this->actingAs($buyer)->postJson(route('buyer.cart.add'), $payload)->assertOk();
        $item = CartItem::firstOrFail();
        $this->assertSame('Pink / S', $item->variation_value);
        $this->assertEquals(120, $item->price);
        $this->postJson(route('buyer.cart.add'), $payload)->assertStatus(422);
        $this->postJson(route('buyer.cart.add'), array_replace($payload, ['variation_value' => 'Blue / S', 'qty' => 1]))->assertStatus(422);
        $this->postJson(route('buyer.cart.add'), ['product_id' => $product->id, 'qty' => 1])->assertStatus(422);

        DB::transaction(fn () => $product->deductStock(2, 'Options', 'Pink / S'));
        $product->refresh();
        $this->assertSame(1, $product->availableStock('Options', 'Pink / S'));
        $this->assertSame(8, (int) $product->stock);
        $this->assertSame(5, $product->availableStock('Options', 'Pink / M'));
        $this->assertSame(2, $product->availableStock('Options', 'Blue / M'));
        DB::transaction(fn () => $product->deductStock(-2, 'Options', 'Pink / S'));
        $this->assertSame(3, $product->fresh()->availableStock('Options', 'Pink / S'));

        CartItem::query()->delete();
        $pending = ProductVariationSet::preparePairStock($this->group()['axes']);
        $product->update(['variations' => $pending, 'stock' => 0]);
        $additions = array_map(fn ($option) => ['group' => 'Options', 'value' => $option['value'], 'qty' => 4], $pending[0]['options']);
        $this->actingAs($seller)->postJson(route('seller.inventory.add-stock', $product), ['additions' => array_slice($additions, 1)])->assertStatus(422);
        $this->post(route('seller.inventory.add-stock', $product), ['additions' => $additions])->assertRedirect();
        $product->refresh();
        $this->assertArrayNotHasKey('requires_stock_setup', $product->variations[0]);
        $this->assertSame(4, $product->availableStock('Options', 'Pink / S'));
        $this->assertSame(16, $product->total_stock);

        $product->update(['variations' => [$this->gramsGroup()]]);
        $this->actingAs($buyer)->postJson(route('buyer.cart.add'), array_replace($payload, ['variation_value' => 'Pink / 100g', 'qty' => 1]))->assertOk();
        $this->assertSame('Pink / 100g', CartItem::firstOrFail()->variation_value);
        $single = Product::create(['seller_id' => $seller->id, 'name' => 'Color-only item', 'price' => 50, 'stock' => 7, 'status' => 'active', 'variations' => [['name' => 'Color', 'options' => [['value' => 'Blue', 'stock' => 7]]]]]);
        $this->postJson(route('buyer.cart.add'), ['product_id' => $single->id, 'variation_group' => 'Color', 'variation_value' => 'Blue', 'qty' => 2])->assertOk();
        $this->assertSame(2, CartItem::where('product_id', $single->id)->firstOrFail()->qty);
    }

    public function test_database_normalization_backs_up_old_counts_and_is_idempotent(): void
    {
        $this->createWorkflowSchema();
        $legacy = [
            ['name' => 'Color', 'options' => [['value' => 'Blue', 'stock' => 7]]],
            ['name' => 'Size', 'options' => [['value' => 'S', 'stock' => 7]]],
        ];
        DB::table('products')->insert(['id' => 'normalize-test', 'name' => 'Legacy shirt', 'stock' => 14, 'variations' => json_encode($legacy)]);
        DB::table('products')->insert(['id' => 'normalize-referenced', 'name' => 'Referenced shirt', 'stock' => 14, 'variations' => json_encode($legacy)]);
        DB::table('orders')->insert(['id' => 'reference-order', 'items' => json_encode([['product_id' => 'normalize-referenced']])]);
        $testStorage = storage_path('framework/testing/variant-normalization-'.uniqid());
        $this->app->useStoragePath($testStorage);
        try {
            $this->artisan('products:normalize-variants')->assertExitCode(0);
            $this->assertSame(14, (int) DB::table('products')->where('id', 'normalize-test')->value('stock'));
            $this->artisan('products:normalize-variants', ['--apply' => true])->assertExitCode(0);
            $row = DB::table('products')->where('id', 'normalize-test')->first();
            $saved = json_decode($row->variations, true);
            $this->assertTrue($saved[0]['requires_stock_setup']);
            $this->assertSame(0, (int) $row->stock);
            $this->assertSame(14, (int) DB::table('products')->where('id', 'normalize-referenced')->value('stock'));
            $backups = glob($testStorage.'/app/private/variant-migration/*/normalize-test.json');
            $this->assertCount(1, $backups);
            $this->assertSame(14, json_decode(file_get_contents($backups[0]), true)['stock']);
            $this->artisan('products:normalize-variants', ['--apply' => true])->assertExitCode(0);
            $this->assertSame($row->variations, DB::table('products')->where('id', 'normalize-test')->value('variations'));
        } finally {
            \Illuminate\Support\Facades\File::deleteDirectory($testStorage);
        }
    }
}
