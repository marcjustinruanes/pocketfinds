<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\StoreCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class TeacherProductPublishingTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        Storage::fake('public');
        DB::table('categories')->insert(['id' => 1, 'name' => 'Home & Living']);
    }

    private function seller(string $status = 'approved'): User
    {
        $n = User::count() + 1;

        return User::create(['account_type' => 'seller', 'status' => $status, 'username' => 'publishing_'.$n, 'given_names' => 'Maria Clara',
            'last_name' => 'Santos', 'email' => "publishing{$n}@example.com", 'category_id' => 1]);
    }

    private function data(): array
    {
        return ['intent' => 'publish', 'creation_key' => (string) Str::uuid(), 'category_id' => 1, 'name' => 'Buri Placemat Set',
            'description' => 'Handwoven placemats for everyday dining.', 'price' => '299.00', 'stock' => 30,
            'weight_grams' => 500, 'length_cm' => 20, 'width_cm' => 20, 'height_cm' => 2, 'condition' => 'new',
            'images' => [UploadedFile::fake()->image('front.png'), UploadedFile::fake()->image('back.png')]];
    }

    public function test_seller_dashboard_renders_publish_form_with_categories(): void
    {
        $this->actingAs($this->seller())->get('/seller/dashboard')->assertOk()->assertSee('Platform Category')->assertSee('Home &amp; Living', false);
    }

    public function test_sel_prod_001_approved_seller_publishes_immediately_with_ordered_images_and_store_category(): void
    {
        $seller = $this->seller();
        $category = StoreCategory::create(['seller_id' => $seller->id, 'name' => 'Kitchen & Dining', 'name_key' => 'kitchen & dining']);
        $response = $this->actingAs($seller)->postJson('/seller/inventory', $this->data() + ['store_category_ids' => [$category->id]])->assertOk()->assertJsonPath('success', true);
        $product = Product::findOrFail($response->json('product_id'));
        $this->assertSame('active', $product->status);
        $this->assertEquals(299, $product->price);
        $this->assertSame(30, $product->stock);
        $this->assertCount(2, $product->images);
        $this->assertSame($product->images[0], $product->image);
        $this->assertSame([$category->id], $product->storeCategories()->pluck('store_categories.id')->all());
        Storage::disk('public')->assertExists($product->images);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_sel_prod_002_each_required_field_rejected_without_creating_product(): void
    {
        $seller = $this->seller();
        foreach (['name', 'description', 'price', 'stock', 'weight_grams', 'condition', 'category_id'] as $field) {
            $this->actingAs($seller)->postJson('/seller/inventory', array_replace($this->data(), [$field => '']))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_sel_prod_006_zero_and_ten_images_rejected_one_and_nine_publish(): void
    {
        $this->actingAs($this->seller());
        foreach ([0, 10] as $count) {
            $images = $count ? array_map(fn ($n) => UploadedFile::fake()->image("image{$n}.png"), range(1, $count)) : [];
            $this->postJson('/seller/inventory', array_replace($this->data(), ['images' => $images]))->assertUnprocessable()->assertJsonValidationErrors('images');
        }
        $this->assertDatabaseCount('products', 0);
        foreach ([1, 9] as $count) {
            $images = array_map(fn ($n) => UploadedFile::fake()->image("image{$n}.png"), range(1, $count));
            $response = $this->postJson('/seller/inventory', array_replace($this->data(), ['images' => $images]))->assertOk();
            $this->assertCount($count, Product::findOrFail($response->json('product_id'))->images);
        }
        $this->assertDatabaseCount('products', 2);
    }

    public function test_sel_prod_009_name_only_draft_saves_without_other_fields_and_is_excluded_from_active_query(): void
    {
        $seller = $this->seller();
        $seller->update(['category_id' => null]);
        $this->actingAs($seller)->postJson('/seller/inventory', ['intent' => 'draft', 'creation_key' => (string) Str::uuid(), 'name' => 'Draft Basket'])
            ->assertOk()->assertJsonPath('success', true);
        $product = Product::firstOrFail();
        $this->assertSame('draft', $product->status);
        $this->assertNull($product->category_id);
        $this->assertEquals(0, $product->price);
        $this->assertSame(0, Product::where('status', 'active')->sellerApproved()->count());
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_draft_retains_optional_video_and_weight_range_when_saved_again(): void
    {
        $this->actingAs($this->seller());
        $saved = $this->postJson('/seller/inventory', ['intent' => 'draft', 'creation_key' => (string) Str::uuid(), 'name' => 'Draft Video Basket',
            'weight_grams' => 500, 'weight_grams_max' => 700, 'video' => UploadedFile::fake()->create('product.mp4', 1000, 'video/mp4')])->assertOk();
        $product = Product::findOrFail($saved->json('product_id'));
        $this->assertEquals(700, $product->weight_grams_max);
        $this->assertNotNull($product->video);
        Storage::disk('public')->assertExists($product->video);
        $this->patchJson('/seller/inventory/'.$product->id, ['intent' => 'draft', 'name' => 'Draft Video Basket'])->assertOk();
        $this->assertSame($product->video, $product->fresh()->video);
        Storage::disk('public')->assertExists($product->video);
    }

    public function test_sel_prod_010_incomplete_draft_publish_fails_and_draft_stays_unchanged(): void
    {
        $seller = $this->seller();
        $draft = Product::create(['seller_id' => $seller->id, 'name' => 'Draft Basket', 'price' => 0, 'stock' => 0, 'status' => 'draft']);
        $this->actingAs($seller)->patchJson('/seller/inventory/'.$draft->id, ['intent' => 'publish', 'name' => 'Draft Basket'])->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'price', 'description', 'stock', 'weight_grams', 'condition']);
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertEquals(0, $draft->fresh()->price);
    }

    public function test_sel_prod_017_same_creation_key_reuses_one_product_and_does_not_upload_twice(): void
    {
        $this->actingAs($this->seller());
        $data = $this->data();
        $first = $this->postJson('/seller/inventory', $data)->assertOk();
        $second = $this->postJson('/seller/inventory', $data)->assertOk();
        $this->assertSame($first->json('product_id'), $second->json('product_id'));
        $this->assertDatabaseCount('products', 1);
        $this->assertCount(2, Storage::disk('public')->allFiles());
    }

    public function test_sel_prof_011_pending_existing_session_can_save_draft_but_cannot_publish_or_manage_live_products(): void
    {
        $seller = $this->seller('pending');
        $this->actingAs($seller)->postJson('/seller/inventory', $this->data())->assertForbidden();
        $this->assertDatabaseCount('products', 0);
        $this->postJson('/seller/inventory', ['intent' => 'draft', 'creation_key' => (string) Str::uuid(), 'name' => 'Handwoven Coin Purse'])->assertOk();
        $this->assertSame('draft', Product::firstOrFail()->status);
        $this->get('/seller/inventory')->assertOk();
        $this->get('/seller/orders')->assertForbidden();
        $this->postJson('/seller/account/vacation', ['is_on_vacation' => false])->assertForbidden();
    }

    public function test_sel_prod_018_foreign_edit_and_creation_key_replay_are_forbidden(): void
    {
        $seller = $this->seller();
        $other = $this->seller();
        $data = $this->data();
        $response = $this->actingAs($seller)->postJson('/seller/inventory', $data)->assertOk();
        $this->actingAs($other)->patchJson('/seller/inventory/'.$response->json('product_id'), $this->data())->assertForbidden();
        $this->postJson('/seller/inventory', $data)->assertForbidden();
        $this->assertDatabaseCount('products', 1);
    }

    public function test_sel_prod_012_duplicate_is_private_with_new_id_and_preserves_original_details(): void
    {
        $seller = $this->seller();
        $saved = $this->actingAs($seller)->postJson('/seller/inventory', $this->data())->assertOk();
        $original = Product::findOrFail($saved->json('product_id'));
        $response = $this->postJson('/seller/inventory/'.$original->id.'/duplicate')->assertOk();
        $copy = Product::findOrFail($response->json('product_id'));
        $this->assertNotSame($original->id, $copy->id);
        $this->assertSame('draft', $copy->status);
        foreach (['name', 'description', 'price', 'stock', 'images', 'weight_grams', 'length_cm', 'width_cm', 'height_cm'] as $field) {
            $this->assertEquals($original->$field, $copy->$field);
        }
        $this->assertSame('active', $original->fresh()->status);
        $this->assertSame(1, Product::where('status', 'active')->count());
    }

    public function test_sel_prod_014_unfinished_order_blocks_deletion_and_preserves_active_product(): void
    {
        $seller = $this->seller();
        $response = $this->actingAs($seller)->postJson('/seller/inventory', $this->data())->assertOk();
        $product = Product::findOrFail($response->json('product_id'));
        Order::create(['seller_id' => $seller->id, 'buyer_id' => $seller->id, 'status' => 'placed', 'items' => [['product_id' => $product->id, 'qty' => 1, 'name' => $product->name, 'price' => 299]]]);
        $this->deleteJson('/seller/inventory/'.$product->id)->assertUnprocessable()->assertJsonValidationErrors('product');
        $this->assertSame('active', $product->fresh()->status);
    }

    public function test_sel_prod_015_finished_order_keeps_item_and_image_snapshot_after_product_deletion(): void
    {
        $seller = $this->seller();
        $response = $this->actingAs($seller)->postJson('/seller/inventory', $this->data())->assertOk();
        $product = Product::findOrFail($response->json('product_id'));
        $items = [['product_id' => $product->id, 'qty' => 1, 'name' => $product->name, 'price' => 299, 'img' => $product->image]];
        $order = Order::create(['seller_id' => $seller->id, 'buyer_id' => $seller->id, 'status' => 'completed', 'items' => $items]);
        $this->deleteJson('/seller/inventory/'.$product->id)->assertRedirect();
        $this->assertNull($product->fresh());
        $this->assertSame($items, $order->fresh()->items);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_sel_prod_016_bulk_deactivation_and_partial_deletion_report_unfinished_order_skip(): void
    {
        $seller = $this->seller();
        $this->actingAs($seller);
        $products = [];
        foreach (['Rattan Basket', 'Buri Placemat', 'New Placemat Set'] as $name) {
            $response = $this->postJson('/seller/inventory', array_replace($this->data(), ['name' => $name]))->assertOk();
            $products[] = Product::findOrFail($response->json('product_id'));
        }
        Order::create(['seller_id' => $seller->id, 'buyer_id' => $seller->id, 'status' => 'placed', 'items' => [['product_id' => $products[0]->id, 'qty' => 1]]]);
        $ids = array_map(fn ($product) => $product->id, $products);
        $this->postJson('/seller/inventory/bulk', ['action' => 'deactivate', 'ids' => $ids])->assertOk()->assertJsonPath('updated', 3);
        $this->assertSame(3, Product::where('status', 'archived')->count());
        $this->postJson('/seller/inventory/bulk', ['action' => 'delete', 'ids' => $ids])->assertOk()->assertJsonPath('updated', 2)->assertJsonPath('skipped.0.id', $products[0]->id);
        $this->assertDatabaseCount('products', 1);
        $this->assertNotNull($products[0]->fresh());
    }

    public function test_sel_inv_009_absolute_stock_accepts_zero_and_99999_and_rejects_invalid_values(): void
    {
        $seller = $this->seller();
        $saved = $this->actingAs($seller)->postJson('/seller/inventory', $this->data())->assertOk();
        $product = Product::findOrFail($saved->json('product_id'));
        foreach ([-1, 1.5, 'abc', 100000] as $stock) {
            $this->patchJson('/seller/inventory/'.$product->id.'/stock', ['stock' => $stock])->assertUnprocessable()->assertJsonValidationErrors('stock');
            $this->assertSame(30, $product->fresh()->stock);
        }
        foreach ([0, 99999] as $stock) {
            $this->patch('/seller/inventory/'.$product->id.'/stock', ['stock' => $stock])->assertSessionHasNoErrors();
            $this->assertSame($stock, $product->fresh()->stock);
        }
    }

    public function test_sel_inv_008_low_stock_alert_once_per_crossing_at_five(): void
    {
        $seller = $this->seller();
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Rattan Basket', 'price' => 450, 'stock' => 20]);
        foreach ([14 => 0, 1 => 1] as $qty => $alerts) {
            DB::transaction(function () use ($product, $qty) {
                $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                $locked->deductStock($qty);
            });
            $this->assertSame($alerts, DB::table('notifications')->where('notification_type', 'low_stock')->count());
        }
        DB::transaction(fn () => $product->fresh()->deductStock(1));
        $this->assertSame(1, DB::table('notifications')->where('notification_type', 'low_stock')->count());
        $product->fresh()->update(['stock' => 6]);
        $product->fresh()->update(['stock' => 5]);
        $this->assertSame(2, DB::table('notifications')->where('notification_type', 'low_stock')->count());
    }
}
