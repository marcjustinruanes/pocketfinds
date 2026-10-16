<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class TeacherStoreCategoryTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        DB::table('categories')->insert(['id' => 1, 'name' => "Women's Bags"]);
    }

    private function seller(): User
    {
        $n = User::count() + 1;

        return User::create(['account_type' => 'seller', 'status' => 'approved', 'username' => 'category_seller_'.$n,
            'email' => "category{$n}@example.com", 'given_names' => 'Category', 'last_name' => 'Seller', 'category_id' => 1]);
    }

    private function product(User $seller, string $name = 'Abaca Tote'): Product
    {
        return Product::create(['seller_id' => $seller->id, 'category_id' => 1, 'name' => $name, 'description' => 'A handwoven bag for everyday use.', 'price' => 249, 'stock' => 20, 'status' => 'active']);
    }

    private function category(User $seller, string $name = 'Bags', ?int $parentId = null): StoreCategory
    {
        $this->actingAs($seller)->post('/seller/store-categories', ['name' => $name, 'parent_id' => $parentId])->assertSessionHasNoErrors();

        return StoreCategory::where('seller_id', $seller->id)->where('name', $name)->firstOrFail();
    }

    public function test_sel_scat_001_create_and_assign_only_owned_product_without_changing_platform_category(): void
    {
        $seller = $this->seller();
        $product = $this->product($seller);
        $category = $this->category($seller);
        $this->post('/seller/store-categories/'.$category->id.'/products', ['product_ids' => [$product->id]])->assertSessionHasNoErrors();
        $this->assertSame([$product->id], $category->products()->pluck('products.id')->all());
        $this->assertSame(1, $product->fresh()->category_id);
        $this->get('/seller/store-categories')->assertOk()->assertSee('1 product(s)')->assertSee('Abaca Tote');
    }

    public function test_sel_scat_002_blank_spaces_and_51_characters_rejected_one_and_fifty_save(): void
    {
        $this->actingAs($seller = $this->seller());
        foreach (['', '   ', str_repeat('A', 51)] as $name) {
            $this->postJson('/seller/store-categories', ['name' => $name])->assertUnprocessable()->assertJsonValidationErrors('name');
        }
        $this->assertDatabaseCount('store_categories', 0);
        foreach (['A', str_repeat('B', 50)] as $name) {
            $this->category($seller, $name);
        }
        $this->assertSame(['A', str_repeat('B', 50)], StoreCategory::pluck('name')->all());
    }

    public function test_sel_scat_003_004_005_unique_trimmed_case_insensitive_within_store_only(): void
    {
        $seller = $this->seller();
        $other = $this->seller();
        $bags = $this->category($seller);
        $otherCategory = $this->category($seller, 'Kitchen');
        foreach (['Bags', 'bags', ' BAGS '] as $name) {
            $this->postJson('/seller/store-categories', ['name' => $name])->assertUnprocessable()->assertJsonValidationErrors('name');
        }
        $this->patchJson('/seller/store-categories/'.$otherCategory->id, ['name' => ' bags '])->assertUnprocessable();
        $this->assertSame('Kitchen', $otherCategory->fresh()->name);
        $this->category($other, 'Bags');
        $this->assertSame(2, StoreCategory::where('name_key', 'bags')->count());
        $this->assertSame('Bags', $bags->fresh()->name);
    }

    public function test_sel_scat_006_rename_reorder_and_preserve_product_membership(): void
    {
        $seller = $this->seller();
        $first = $this->category($seller);
        $second = $this->category($seller, 'Dining');
        $product = $this->product($seller);
        $second->products()->attach($product);
        $this->patch('/seller/store-categories/'.$second->id, ['name' => 'Kitchen'])->assertSessionHasNoErrors();
        $this->post('/seller/store-categories/reorder', ['ids' => [$second->id, $first->id]])->assertSessionHasNoErrors();
        $this->assertSame(['Kitchen', 'Bags'], StoreCategory::orderBy('position')->pluck('name')->all());
        $this->assertSame([$product->id], $second->products()->pluck('products.id')->all());
    }

    public function test_sel_scat_007_008_hidden_parent_hides_children_without_hiding_products_and_show_preserves_position(): void
    {
        $seller = $this->seller();
        $parent = $this->category($seller);
        $child = $this->category($seller, 'Small Bags', $parent->id);
        $product = $this->product($seller);
        $parent->products()->attach($product);
        $position = $parent->position;
        $this->patch('/seller/store-categories/'.$parent->id, ['name' => 'Bags', 'is_visible' => false])->assertSessionHasNoErrors();
        $this->assertCount(0, StoreCategory::visible()->get());
        $this->assertSame('active', $product->fresh()->status);
        $this->patch('/seller/store-categories/'.$parent->id, ['name' => 'Bags', 'is_visible' => true])->assertSessionHasNoErrors();
        $this->assertCount(2, StoreCategory::visible()->get());
        $this->assertSame($position, $parent->fresh()->position);
        $this->assertTrue($child->fresh()->is_visible);
    }

    public function test_sel_scat_009_delete_requires_confirmation_and_keeps_active_product(): void
    {
        $seller = $this->seller();
        $category = $this->category($seller);
        $product = $this->product($seller);
        $category->products()->attach($product);
        $this->deleteJson('/seller/store-categories/'.$category->id)->assertUnprocessable();
        $this->assertNotNull($category->fresh());
        $this->get('/seller/store-categories')->assertSee('1 product(s) affected.');
        $this->delete('/seller/store-categories/'.$category->id, ['confirm' => 1])->assertSessionHasNoErrors();
        $this->assertNull($category->fresh());
        $this->assertSame('active', $product->fresh()->status);
        $this->assertDatabaseCount('store_category_products', 0);
    }

    public function test_sel_scat_010_delete_parent_removes_children_and_memberships_never_products(): void
    {
        $seller = $this->seller();
        $parent = $this->category($seller, 'Home Decor');
        $first = $this->category($seller, 'Lamps', $parent->id);
        $second = $this->category($seller, 'Baskets', $parent->id);
        $lamp = $this->product($seller, 'Native Lamp');
        $basket = $this->product($seller, 'Rattan Basket');
        $first->products()->attach($lamp);
        $second->products()->attach($basket);
        $this->get('/seller/store-categories')->assertSee('Also deletes: Lamps, Baskets. 2 product(s) affected.');
        $this->delete('/seller/store-categories/'.$parent->id, ['confirm' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('store_categories', 0);
        $this->assertDatabaseCount('store_category_products', 0);
        $this->assertSame(2, Product::where('status', 'active')->count());
    }

    public function test_sel_scat_011_third_level_cycles_and_cross_store_parent_are_rejected(): void
    {
        $seller = $this->seller();
        $parent = $this->category($seller, 'Home Decor');
        $child = $this->category($seller, 'Lamps', $parent->id);
        $this->postJson('/seller/store-categories', ['name' => 'Third level', 'parent_id' => $child->id])->assertUnprocessable();
        $this->patchJson('/seller/store-categories/'.$parent->id, ['name' => 'Home Decor', 'parent_id' => $child->id])->assertUnprocessable();
        $this->patchJson('/seller/store-categories/'.$parent->id, ['name' => 'Home Decor', 'parent_id' => $parent->id])->assertUnprocessable();
        $other = $this->seller();
        $this->actingAs($other)->postJson('/seller/store-categories', ['name' => 'Other', 'parent_id' => $parent->id])->assertUnprocessable();
        $this->assertDatabaseCount('store_categories', 2);
    }

    public function test_sel_scat_012_fourth_membership_rejected_and_removal_makes_room(): void
    {
        $seller = $this->seller();
        $product = $this->product($seller);
        $categories = collect(range(1, 4))->map(fn ($i) => $this->category($seller, 'Category '.$i));
        foreach ($categories->take(3) as $category) {
            $this->post('/seller/store-categories/'.$category->id.'/products', ['product_ids' => [$product->id]])->assertSessionHasNoErrors();
        }
        $this->postJson('/seller/store-categories/'.$categories[3]->id.'/products', ['product_ids' => [$product->id]])->assertUnprocessable()->assertJsonValidationErrors('product_ids');
        $this->assertDatabaseCount('store_category_products', 3);
        $this->post('/seller/store-categories/'.$categories[0]->id.'/products', ['empty_selection' => 1])->assertSessionHasNoErrors();
        $this->post('/seller/store-categories/'.$categories[3]->id.'/products', ['product_ids' => [$product->id]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('store_category_products', 3);
    }

    public function test_store_category_ownership_and_reorder_validation_prevent_foreign_changes(): void
    {
        $seller = $this->seller();
        $category = $this->category($seller);
        $other = $this->seller();
        $product = $this->product($other);
        $this->postJson('/seller/store-categories/'.$category->id.'/products', ['product_ids' => [$product->id]])->assertUnprocessable();
        $this->actingAs($other)->patchJson('/seller/store-categories/'.$category->id, ['name' => 'Stolen'])->assertForbidden();
        $this->deleteJson('/seller/store-categories/'.$category->id, ['confirm' => 1])->assertForbidden();
        $this->postJson('/seller/store-categories/'.$category->id.'/products', ['product_ids' => []])->assertForbidden();
        $this->postJson('/seller/store-categories/reorder', ['ids' => [$category->id]])->assertUnprocessable();
        $this->assertSame('Bags', $category->fresh()->name);
    }
}
