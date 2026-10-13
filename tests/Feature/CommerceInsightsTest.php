<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CommerceInsights;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommerceInsightsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary(); $table->integer('buyer_id'); $table->integer('seller_id'); $table->string('status');
            $table->decimal('total'); $table->text('items'); $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table) { $table->integer('id')->primary(); $table->string('name'); });
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('name'); $table->string('status'); $table->integer('stock');
            $table->text('variations')->nullable(); $table->text('description')->nullable(); $table->integer('category_id')->nullable();
            $table->decimal('price'); $table->decimal('discount_price')->nullable(); $table->timestamps();
        });
        DB::table('products')->insert(['id' => 'p1', 'name' => '=Unsafe spreadsheet title', 'status' => 'active', 'stock' => 2, 'price' => 999,
            'created_at' => now()->subDays(60), 'updated_at' => now()]);
    }

    private function order(string $id, int $days, string $status = 'completed', float $total = 100, int $buyer = 1): void
    {
        DB::table('orders')->insert(['id' => $id, 'buyer_id' => $buyer, 'seller_id' => 2, 'status' => $status, 'total' => $total,
            'items' => json_encode([['product_id' => 'p1', 'qty' => 2, 'price' => 25]]), 'created_at' => now()->subDays($days), 'updated_at' => now()]);
    }

    public function test_sales_use_completed_history_and_forecasts_need_evidence(): void
    {
        $this->order('a', 1); $this->order('b', 2, 'delivered', 200); $this->order('c', 2, 'cancelled', 9000); $this->order('d', 2, 'placed', 8000);
        $data = app(CommerceInsights::class)->snapshot(7);
        $this->assertSame(300.0, array_sum(array_column($data['daily_sales'], 'order_value')));
        $this->assertSame(4, $data['product_sales'][0]['units']);
        $this->assertSame(100.0, $data['product_sales'][0]['item_value_before_order_discounts']);
        $this->assertNull($data['forecast']['next_7_days_order_value']);
        $this->assertNull($data['inventory_recommendations'][0]['estimated_reorder_units_for_30_days']);
        $this->assertSame(1, $data['customer_behavior']['repeat_purchasing_customers']);
        $this->assertSame('low_stock', $data['alerts'][0]['type']);
        $this->assertCount(7, $data['daily_sales']);
        $this->assertStringNotContainsString('shipping_address', json_encode($data));
    }

    public function test_estimates_and_stock_cover_are_deterministic_with_sufficient_history(): void
    {
        for ($i = 0; $i < 28; $i++) $this->order('order-'.$i, $i, 'completed', 100, $i + 1);
        $data = app(CommerceInsights::class)->snapshot(30);
        $this->assertSame(700.0, $data['forecast']['next_7_days_order_value']);
        $this->assertSame(1.0, $data['inventory_recommendations'][0]['estimated_days_remaining']);
        $this->assertSame(58, $data['inventory_recommendations'][0]['estimated_reorder_units_for_30_days']);
        $this->assertStringContainsString('Estimate', $data['forecast']['label']);
        DB::table('products')->where('id', 'p1')->update(['stock' => 200]);
        $this->assertSame('potential_overstock', app(CommerceInsights::class)->snapshot()['inventory_recommendations'][0]['flag']);
    }

    public function test_order_flags_are_warnings_and_do_not_change_records(): void
    {
        for ($i = 0; $i < 5; $i++) $this->order('burst-'.$i, 0, $i < 3 ? 'cancelled' : 'placed');
        $data = app(CommerceInsights::class)->snapshot();
        $types = array_column($data['alerts'], 'type');
        $this->assertContains('order_burst', $types); $this->assertContains('repeated_cancellations', $types);
        $this->assertStringContainsString('not proof of fraud', json_encode($data));
        $this->assertSame(3, DB::table('orders')->where('status', 'cancelled')->count());
        $this->assertSame(2, DB::table('products')->value('stock'));
    }

    public function test_report_exports_are_admin_only_and_escape_csv_formulas(): void
    {
        $this->get(route('admin.ai.export'))->assertRedirect(route('login'));
        $admin = (new User(['account_type' => 'buyer', 'is_admin' => true]))->forceFill(['id' => 10]);
        $response = $this->actingAs($admin)->get(route('admin.ai.export', ['days' => 7]));
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString("'=Unsafe spreadsheet title", $response->streamedContent());
        $this->get(route('admin.ai.export', ['days' => 365]))->assertSessionHasErrors('days');
    }

    private function movementSchema(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id(); $table->string('product_id'); $table->integer('stock_before'); $table->integer('stock_after');
            $table->integer('delta'); $table->integer('requested_delta'); $table->integer('actor_id')->nullable();
            $table->string('reason'); $table->timestamp('created_at');
        });
    }

    public function test_stock_operations_log_actual_changes_and_roll_back_with_the_transaction(): void
    {
        $this->movementSchema();
        try {
            DB::transaction(function () {
                \App\Models\Product::findOrFail('p1')->deductStock(-30);
                throw new \RuntimeException('Rollback test');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback test', $exception->getMessage());
        }
        $this->assertSame(2, DB::table('products')->value('stock'));
        $this->assertSame(0, DB::table('inventory_movements')->count());
        \App\Models\Product::findOrFail('p1')->deductStock(-30);
        $this->assertSame(30, DB::table('inventory_movements')->value('delta'));
        $data = app(CommerceInsights::class)->snapshot();
        $this->assertTrue($data['inventory_movement_tracking']);
        $this->assertContains('inventory_change', array_column($data['alerts'], 'type'));
        DB::table('products')->where('id', 'p1')->update(['stock' => 1]);
        $this->assertStringContainsString('differs from the latest logged balance', json_encode(app(CommerceInsights::class)->snapshot()));
    }

    public function test_variation_movements_and_quantity_mismatches_are_recorded(): void
    {
        $this->movementSchema();
        DB::table('products')->where('id', 'p1')->update(['variations' => json_encode([['name' => 'Color', 'options' => [['value' => 'Gray', 'stock' => 8], ['value' => 'Black', 'stock' => 6]]]])]);
        \App\Models\Product::findOrFail('p1')->deductStock(2, 'Color', 'Gray');
        $this->assertSame(14, DB::table('inventory_movements')->value('stock_before'));
        $this->assertSame(12, DB::table('inventory_movements')->value('stock_after'));
        \App\Models\Product::findOrFail('p1')->deductStock(50, 'Color', 'Gray');
        $this->assertStringContainsString('differed from requested quantity', json_encode(app(CommerceInsights::class)->snapshot()));
    }

    public function test_order_investigation_requires_admin_and_view_escapes_listing_text(): void
    {
        $this->order('case-1', 0, 'placed');
        $this->get(route('admin.ai.order', 'case-1'))->assertRedirect(route('login'));
        $buyer = (new User(['account_type' => 'buyer', 'is_admin' => false]))->forceFill(['id' => 1]);
        $this->actingAs($buyer)->get(route('admin.ai.order', 'case-1'))->assertRedirect(route('login'));
        $admin = (new User(['account_type' => 'buyer', 'is_admin' => true]))->forceFill(['id' => 10]);
        $this->actingAs($admin);
        $order = \App\Models\Order::findOrFail('case-1');
        $order->items = [['name' => '<script>evil()</script>', 'qty' => 2, 'price' => 50]];
        $this->view('admin.ai-order-review', ['order' => $order, 'pendingRegistrations' => 0, 'openDisputes' => 0, 'unreadMessages' => 0, 'pendingDocs' => 0, 'pendingProducts' => 0])
            ->assertSee('Read-only review')->assertSee('&lt;script&gt;evil()&lt;/script&gt;', false)->assertDontSee('<script>evil()</script>', false);
        $this->assertSame('placed', DB::table('orders')->where('id', 'case-1')->value('status'));
    }
}
