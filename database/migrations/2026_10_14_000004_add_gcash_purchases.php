<?php

use App\Services\GcashSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existingProblems = GcashSchema::problems(allowMissing: true);
        if ($existingProblems) {
            throw new RuntimeException('Incompatible existing GCash schema; preserve records and reconcile explicitly: '.implode('; ', $existingProblems));
        }
        if (! Schema::hasTable('gcash_purchases')) {
            Schema::create('gcash_purchases', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->bigInteger('buyer_id');
                $t->bigInteger('seller_id');
                $t->uuid('checkout_id');
                $t->string('request_hash', 64);
                $t->bigInteger('payment_method_id');
                $t->json('snapshot');
                $t->decimal('amount_due', 12, 2);
                $t->string('payment_state', 32)->default('awaiting_payment');
                $t->string('reservation_state', 24)->default('initializing');
                $t->timestamp('activated_at')->nullable();
                $t->timestamp('expires_at')->nullable()->index();
                $t->string('payment_reference', 64)->nullable()->unique();
                $t->unique(['seller_id', 'payment_reference']);
                $t->decimal('verified_amount', 12, 2)->nullable();
                $t->bigInteger('verified_by')->nullable();
                $t->timestamp('verified_at')->nullable();
                $t->unsignedInteger('revision')->default(0);
                $t->unsignedInteger('generation')->default(1);
                $t->json('instructions')->nullable();
                $t->timestamps();
                $t->unique(['buyer_id', 'checkout_id', 'seller_id']);
                $t->index(['seller_id', 'payment_state']);
                foreach (['buyer_id', 'seller_id', 'verified_by'] as $column) {
                    $t->foreign($column)->references('id')->on('users')->restrictOnDelete();
                }
                $t->foreign('payment_method_id')->references('id')->on('payment_methods')->restrictOnDelete();
            });
        }
        if (! Schema::hasTable('gcash_stock_allocations')) {
            Schema::create('gcash_stock_allocations', function (Blueprint $t) {
                $t->id();
                $t->uuid('purchase_id');
                $t->uuid('product_id')->nullable();
                $t->uuid('product_reference');
                $t->unsignedInteger('generation');
                $t->string('option_key', 64);
                $t->string('variation_group')->nullable();
                $t->string('variation_value')->nullable();
                $t->unsignedInteger('quantity');
                $t->string('state', 24);
                $t->timestamp('released_at')->nullable();
                $t->timestamps();
                $t->unique(['purchase_id', 'generation', 'product_reference', 'option_key'], 'gcash_stock_purchase_option_unique');
                $t->index(['product_id', 'state']);
                $t->foreign('purchase_id')->references('id')->on('gcash_purchases')->restrictOnDelete();
                $t->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            });
        }
        if (! Schema::hasTable('gcash_voucher_allocations')) {
            Schema::create('gcash_voucher_allocations', function (Blueprint $t) {
                $t->id();
                $t->uuid('purchase_id');
                $t->uuid('voucher_id')->nullable();
                $t->uuid('voucher_reference');
                $t->unsignedInteger('generation');
                $t->string('state', 16);
                $t->timestamps();
                $t->unique(['purchase_id', 'generation']);
                $t->index(['voucher_id', 'state']);
                $t->foreign('purchase_id')->references('id')->on('gcash_purchases')->restrictOnDelete();
                $t->foreign('voucher_id')->references('id')->on('vouchers')->nullOnDelete();
            });
        }
        if (! Schema::hasTable('gcash_proofs')) {
            Schema::create('gcash_proofs', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('purchase_id');
                $t->uuid('submission_id');
                $t->bigInteger('submitted_by');
                $t->string('content_hash', 64);
                $t->string('path');
                $t->string('mime', 80);
                $t->unsignedInteger('size');
                $t->timestamp('created_at');
                $t->unique(['purchase_id', 'submission_id']);
                $t->foreign('purchase_id')->references('id')->on('gcash_purchases')->restrictOnDelete();
                $t->foreign('submitted_by')->references('id')->on('users')->restrictOnDelete();
            });
        }
        if (! Schema::hasTable('gcash_purchase_history')) {
            Schema::create('gcash_purchase_history', function (Blueprint $t) {
                $t->id();
                $t->uuid('purchase_id');
                $t->bigInteger('actor_id')->nullable();
                $t->string('event', 40);
                $t->json('before');
                $t->json('after');
                $t->text('reason')->nullable();
                $t->timestamp('created_at');
                $t->index(['purchase_id', 'id']);
                $t->foreign('purchase_id')->references('id')->on('gcash_purchases')->restrictOnDelete();
                $t->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
            });
        }
        foreach (['orders', 'messages'] as $table) {
            if (! Schema::hasColumn($table, 'gcash_purchase_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->uuid('gcash_purchase_id')->nullable());
            }
        }
        // Forward-only recovery also repairs ALTER statements interrupted after adding a column.
        foreach (GcashSchema::UNIQUE as $table => $groups) {
            foreach ($groups as $columns) {
                if (! collect(Schema::getIndexes($table))->contains(fn ($index) => $index['unique'] && $index['columns'] === $columns)) {
                    $name = $table === 'gcash_stock_allocations' ? 'gcash_stock_purchase_option_unique' : $table.'_'.implode('_', $columns).'_unique';
                    Schema::table($table, fn (Blueprint $t) => $t->unique($columns, $name));
                }
            }
        }
        if (! collect(Schema::getIndexes('messages'))->contains(fn ($index) => $index['columns'] === ['gcash_purchase_id'])) {
            Schema::table('messages', fn (Blueprint $t) => $t->index('gcash_purchase_id'));
        }
        foreach (GcashSchema::FOREIGN as $table => $columns) {
            foreach ($columns as $column => $target) {
                $keys = Schema::getForeignKeys($table);
                $existing = collect($keys)->first(fn ($key) => $key['columns'] === [$column]);
                if ($existing) {
                    if (! GcashSchema::validForeign($existing, $column, $target)) {
                        throw new RuntimeException('Unexpected foreign key; manual forward recovery required: '.$table.'.'.$column);
                    }

                    continue;
                }
                Schema::table($table, function (Blueprint $t) use ($column, $target) {
                    $key = $t->foreign($column)->references('id')->on($target);
                    in_array($column, ['product_id', 'voucher_id']) ? $key->nullOnDelete() : $key->restrictOnDelete();
                });
            }
        }
        $problems = GcashSchema::problems();
        if ($problems) {
            throw new RuntimeException('Incomplete GCash schema: '.implode('; ', $problems));
        }

    }

    public function down(): void
    {
        throw new RuntimeException('Preserve GCash payment history; destructive rollback is not supported.');
    }
};
