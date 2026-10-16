<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class TeacherSearchTest extends TestCase
{
    use WorkflowSchema;

    public function test_buy_srch_004_typo_suggestion_prefers_fewer_unmatched_words_and_search_accepts_separated_terms(): void
    {
        $this->createWorkflowSchema();
        $buyer = User::create(['account_type' => 'buyer', 'status' => 'approved']);
        $seller = User::create(['account_type' => 'seller', 'status' => 'approved', 'given_names' => 'Audit Seller']);
        DB::table('categories')->insert(['id' => 1, 'name' => 'Home & Living']);
        foreach (['Store Profile Rattan Basket', 'Rattan Fruit Basket'] as $name) {
            Product::create(['seller_id' => $seller->id, 'category_id' => 1, 'name' => $name, 'price' => 450, 'stock' => 10, 'status' => 'active']);
        }
        $this->actingAs($buyer)->get('/buyer/browse?q=Ratan%20baskit')->assertOk()->assertViewHas('suggestion', 'Rattan Fruit Basket');
        $this->get('/buyer/browse?q=rattan%20basket')->assertOk()->assertViewHas('products', fn ($products) => collect($products->items())->pluck('name')->contains('Rattan Fruit Basket'));
        $this->get('/buyer/browse?q=zzqxw')->assertOk()->assertViewHas('suggestion', null)->assertViewHas('products', fn ($products) => $products->total() === 0);
    }

    public function test_buy_srch_005_percent_and_sql_and_html_inputs_remain_literal(): void
    {
        $this->createWorkflowSchema();
        $buyer = User::create(['account_type' => 'buyer', 'status' => 'approved']);
        $seller = User::create(['account_type' => 'seller', 'status' => 'approved', 'given_names' => 'Audit Seller']);
        Product::create(['seller_id' => $seller->id, 'name' => 'Rattan Fruit Basket', 'price' => 450, 'stock' => 10, 'status' => 'active']);
        foreach (['%', "' OR '1'='1", '<script>alert(1)</script>', '"><img src=x onerror=alert(1)>'] as $term) {
            $this->actingAs($buyer)->get('/buyer/browse?'.http_build_query(['q' => $term]))->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 0);
        }
        $this->assertDatabaseCount('products', 1);
    }
}
