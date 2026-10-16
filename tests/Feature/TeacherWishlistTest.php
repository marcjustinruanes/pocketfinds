<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShopFollow;
use App\Models\User;
use App\Models\WishlistEntry;
use Illuminate\Support\Facades\DB;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class TeacherWishlistTest extends TestCase
{
    use WorkflowSchema;

    public function test_buy_wish_001_and_002_explicit_state_is_idempotent_and_buyer_scoped(): void
    {
        $this->createWorkflowSchema();
        DB::statement('CREATE TABLE shop_follows (id TEXT PRIMARY KEY, buyer_id INTEGER, seller_id INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(buyer_id, seller_id))');
        $seller = User::create(['account_type' => 'seller', 'status' => 'approved', 'username' => 'saved_seller']);
        $buyer = User::create(['account_type' => 'buyer', 'status' => 'approved']);
        $other = User::create(['account_type' => 'buyer', 'status' => 'approved']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Rattan Fruit Basket', 'price' => 450, 'stock' => 20, 'status' => 'active']);
        $this->actingAs($other)->putJson('/buyer/wishlist/'.$product->id, ['saved' => true])->assertOk();
        foreach (range(1, 2) as $attempt) {
            $this->actingAs($buyer)->putJson('/buyer/wishlist/'.$product->id, ['saved' => true, 'buyer_id' => $other->id])->assertOk()->assertJsonPath('saved', true);
        }
        $this->assertSame(1, WishlistEntry::where('buyer_id', $buyer->id)->count());
        $this->get('/buyer/wishlist')->assertOk()->assertViewHas('entries', fn ($entries) => $entries->count() === 1 && $entries->first()->buyer_id === $buyer->id);
        $this->putJson('/buyer/wishlist/'.$product->id, ['saved' => false])->assertOk();
        $this->assertSame(0, WishlistEntry::where('buyer_id', $buyer->id)->count());
        $this->assertSame(1, WishlistEntry::where('buyer_id', $other->id)->count());
    }

    public function test_buy_wish_003_deleted_snapshot_remains_removable_and_sold_out_product_is_disabled(): void
    {
        $this->createWorkflowSchema();
        DB::statement('CREATE TABLE shop_follows (id TEXT PRIMARY KEY, buyer_id INTEGER, seller_id INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(buyer_id, seller_id))');
        $seller = User::create(['account_type' => 'seller', 'status' => 'approved']);
        $buyer = User::create(['account_type' => 'buyer', 'status' => 'approved']);
        foreach (['Deleted Shirt', 'Sold Out Basket'] as $name) {
            $product = Product::create(['seller_id' => $seller->id, 'name' => $name, 'price' => 450, 'stock' => 20, 'status' => 'active']);
            $this->actingAs($buyer)->putJson('/buyer/wishlist/'.$product->id, ['saved' => true])->assertOk();
            if ($name === 'Deleted Shirt') {
                $deletedId = $product->id;
                $product->delete();
            } else {
                $product->update(['stock' => 0]);
            }
        }
        $this->get('/buyer/wishlist')->assertOk()->assertSee('Deleted Shirt')->assertSee('Unavailable')->assertSee('Sold out')->assertSee('disabled>Add to Cart', false);
        $this->assertDatabaseCount('wishlist_entries', 2);
        $this->putJson('/buyer/wishlist/'.$deletedId, ['saved' => false])->assertOk();
        $this->assertDatabaseCount('wishlist_entries', 1);
    }

    public function test_buy_wish_004_explicit_follow_is_idempotent_and_following_is_scoped(): void
    {
        $this->createWorkflowSchema();
        DB::statement('CREATE TABLE shop_follows (id TEXT PRIMARY KEY, buyer_id INTEGER, seller_id INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(buyer_id, seller_id))');
        $seller = User::create(['account_type' => 'seller', 'status' => 'approved', 'username' => 'followed_store', 'business_name' => 'Followed Store']);
        $buyer = User::create(['account_type' => 'buyer', 'status' => 'approved']);
        $other = User::create(['account_type' => 'buyer', 'status' => 'approved']);
        foreach (range(1, 2) as $attempt) {
            $this->actingAs($buyer)->postJson('/buyer/shop/followed_store/follow', ['following' => true])->assertOk()->assertJsonPath('followers', 1);
        }
        $this->assertDatabaseCount('shop_follows', 1);
        $this->get('/buyer/following')->assertOk()->assertSee('Followed Store');
        $this->actingAs($other)->get('/buyer/following')->assertOk()->assertDontSee('Followed Store');
        $this->actingAs($buyer)->postJson('/buyer/shop/followed_store/follow', ['following' => false])->assertOk()->assertJsonPath('followers', 0);
        $this->assertSame(0, ShopFollow::count());
    }
}
