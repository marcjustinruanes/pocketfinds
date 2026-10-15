<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\GcashInventory;
use App\Services\GcashSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class GcashMigrationTest extends TestCase
{
    use WorkflowSchema;

    public function test_additive_schema_preserves_preexisting_rows(): void
    {
        $this->createWorkflowSchema();
        $seller = User::create(['account_type' => 'seller', 'email' => 'migration@example.test', 'username' => 'seller', 'status' => 'approved']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Original', 'price' => 150, 'stock' => 17, 'status' => 'active']);
        $before = [];
        foreach (['users', 'products', 'orders', 'messages', 'payment_methods', 'vouchers', 'notifications'] as $table) {
            $before[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }
        (require database_path('migrations/2026_10_14_000004_add_gcash_purchases.php'))->up();
        foreach ($before as $table => $rows) {
            $columns = $rows ? array_keys($rows[0]) : ['id'];
            $this->assertSame($rows, DB::table($table)->get($columns)->map(fn ($row) => (array) $row)->all(), $table);
        }
        $this->assertSame(17, $product->fresh()->stock);
        $this->assertDatabaseCount('gcash_purchases', 0);
    }

    public function test_forward_recovery_from_interrupted_creation_keeps_existing_rows(): void
    {
        $this->createWorkflowSchema();
        $seller = User::create(['account_type' => 'seller', 'email' => 'recovery@example.test', 'username' => 'recovery', 'status' => 'approved']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Retained', 'price' => 150, 'stock' => 17, 'status' => 'active']);
        $interrupt = true;
        DB::listen(function ($query) use (&$interrupt) {
            if ($interrupt && str_starts_with(strtolower($query->sql), 'create table') && str_contains($query->sql, 'gcash_stock_allocations')) {
                $interrupt = false;
                throw new \RuntimeException('Simulated DDL interruption');
            }
        });
        $migration = require database_path('migrations/2026_10_14_000004_add_gcash_purchases.php');
        try {
            $migration->up();
            $this->fail('Expected interruption');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated DDL interruption', $e->getMessage());
        }
        $this->assertNotEmpty(GcashSchema::problems());
        try {
            GcashInventory::installed();
            $this->fail('Incomplete schema must fail closed');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
        $migration->up();
        $migration->up();
        $this->assertSame([], GcashSchema::problems());
        $this->assertSame(17, $product->fresh()->stock);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_forward_recovery_repairs_interrupted_order_alter(): void
    {
        $this->createWorkflowSchema();
        Schema::table('orders', fn ($t) => $t->uuid('gcash_purchase_id')->nullable());
        $migration = require database_path('migrations/2026_10_14_000004_add_gcash_purchases.php');
        $migration->up();
        $this->assertSame([], GcashSchema::problems());
    }
}
