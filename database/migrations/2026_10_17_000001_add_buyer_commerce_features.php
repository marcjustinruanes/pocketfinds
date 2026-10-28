<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users' => ['is_on_vacation' => 'boolean', 'email_verified_at' => 'timestamp'], 'buyer_addresses' => ['region' => 'string', 'postal_code' => 'string'], 'vouchers' => ['starts_at' => 'timestamp'], 'orders' => ['commerce_snapshot' => 'json'], 'reviews' => ['item_key' => 'string', 'photos' => 'json', 'edited_at' => 'timestamp']] as $table => $columns) {
            foreach ($columns as $column => $type) {
                if (! Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $t) use ($column, $type) {
                        $type === 'boolean' ? $t->boolean($column)->default(false) : $t->{$type}($column)->nullable();
                    });
                }
            }
        }
        if (! Schema::hasIndex('reviews', 'reviews_order_item_unique')) {
            Schema::table('reviews', fn (Blueprint $t) => $t->unique(['order_id', 'item_key'], 'reviews_order_item_unique'));
        }
        foreach (Schema::getIndexes('reviews') as $index) {
            if ($index['unique'] && $index['columns'] === ['order_id']) {
                Schema::table('reviews', fn (Blueprint $t) => $t->dropUnique($index['name']));
            }
        }
        $mapped = [];
        foreach (DB::table('reviews')->whereNotNull('order_id')->whereNull('item_key')->orderBy('id')->get() as $review) {
            $items = json_decode(DB::table('orders')->where('id', $review->order_id)->value('items') ?? '[]', true);
            $key = 0;
            foreach ($items as $index => $item) {
                if (($item['product_id'] ?? null) === $review->product_id) {
                    $key = $index;
                    break;
                }
            }
            $slot = $review->order_id.':'.$key;
            $value = isset($mapped[$slot]) ? 'legacy-'.$review->id : (string) $key;
            $mapped[$slot] = true;
            DB::table('reviews')->where('id', $review->id)->update(['item_key' => $value]);
        }
        Schema::create('commerce_wishlist', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('buyer_id');
            $t->string('product_id', 36);
            $t->timestamp('created_at');
            $t->unique(['buyer_id', 'product_id']);
        });
        Schema::create('commerce_vouchers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->enum('kind', ['store', 'platform', 'shipping']);
            $t->bigInteger('seller_id')->nullable();
            $t->string('legacy_store_id', 36)->nullable()->unique();
            $t->string('legacy_platform_id', 36)->nullable()->unique();
            $t->unsignedInteger('legacy_used_count')->default(0);
            $t->enum('discount_type', ['fixed', 'percent']);
            $t->decimal('amount', 12, 2);
            $t->decimal('cap', 12, 2)->nullable();
            $t->decimal('minimum_spend', 12, 2)->default(0);
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->unsignedInteger('usage_limit');
            $t->unsignedInteger('per_buyer_limit')->default(1);
            $t->boolean('is_active')->default(true);
        });
        Schema::create('commerce_claims', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('buyer_id');
            $t->unsignedBigInteger('voucher_id');
            $t->unique(['buyer_id', 'voucher_id']);
        });
        Schema::create('commerce_allocations', function (Blueprint $t) {
            $t->id();
            $t->string('subject_id', 36)->index();
            $t->string('usage_key', 36)->index();
            $t->bigInteger('buyer_id');
            $t->unsignedBigInteger('voucher_id')->nullable()->index();
            $t->unsignedBigInteger('flash_sale_id')->nullable()->index();
            $t->unsignedInteger('quantity')->default(0);
            $t->enum('state', ['held', 'used', 'released'])->default('held');
            $t->timestamp('expires_at')->nullable();
        });
        Schema::create('commerce_flash_sales', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('seller_id');
            $t->string('product_id', 36)->index();
            $t->decimal('price', 12, 2);
            $t->unsignedInteger('quantity');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->boolean('is_active')->default(true);
        });
        Schema::create('commerce_reminders', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('buyer_id');
            $t->unsignedBigInteger('flash_sale_id');
            $t->timestamp('sent_at')->nullable();
            $t->unique(['buyer_id', 'flash_sale_id']);
        });
        Schema::create('commerce_shipping_rates', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('seller_id');
            $t->string('region');
            $t->decimal('first_kg', 12, 2);
            $t->decimal('additional_kg', 12, 2);
            $t->boolean('is_active')->default(true);
            $t->unique(['seller_id', 'region']);
        });
        Schema::create('commerce_checkouts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->bigInteger('buyer_id');
            $t->string('request_hash', 64);
            $t->json('result')->nullable();
            $t->timestamps();
        });
        Schema::create('commerce_returns', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('order_id', 36)->unique();
            $t->bigInteger('buyer_id');
            $t->bigInteger('seller_id');
            $t->json('item_keys');
            $t->string('reason');
            $t->text('description');
            $t->json('photos');
            $t->enum('status', ['pending', 'approved', 'rejected', 'escalated', 'received', 'refunded'])->default('pending');
            $t->decimal('amount', 12, 2);
            $t->string('refund_destination');
            $t->text('decision_reason')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('escalated_at')->nullable();
            $t->string('refund_reference')->nullable();
            $t->timestamps();
        });
        $db = DB::class;
        foreach ($db::table('vouchers')->get() as $v) {
            $code = strtoupper($v->code);
            if ($db::table('commerce_vouchers')->where('code', $code)->exists()) {
                $code = 'STORE'.strtoupper(substr(str_replace('-', '', $v->id), 0, 12));
            }
            $db::table('commerce_vouchers')->insert(['code' => $code, 'kind' => $v->type === 'free_shipping' ? 'shipping' : 'store', 'seller_id' => $v->seller_id, 'discount_type' => $v->type === 'free_shipping' ? 'percent' : 'fixed', 'amount' => $v->type === 'free_shipping' ? 100 : $v->discount_amount, 'minimum_spend' => $v->minimum_spend ?? 0, 'starts_at' => $v->starts_at ?? $v->created_at ?? now(), 'ends_at' => $v->expires_at ? Carbon::parse($v->expires_at)->endOfDay() : now()->addYears(10), 'usage_limit' => $v->usage_limit ?? 4294967295, 'per_buyer_limit' => 4294967295, 'is_active' => $v->is_active, 'legacy_store_id' => $v->id, 'legacy_used_count' => $v->used_count]);
        }
        foreach ($db::table('platform_vouchers')->whereIn('type', ['free_shipping', 'discount'])->get() as $v) {
            $shipping = $v->type === 'free_shipping';
            $percent = $shipping || ($v->discount_percent ?? 0) > 0;
            $db::table('commerce_vouchers')->insert(['code' => 'PLAT'.strtoupper(substr(str_replace('-', '', $v->id), 0, 12)), 'kind' => $shipping ? 'shipping' : 'platform', 'discount_type' => $percent ? 'percent' : 'fixed', 'amount' => $shipping ? 100 : ($percent ? $v->discount_percent : $v->discount_amount), 'minimum_spend' => $v->minimum_spend ?? 0, 'starts_at' => $v->created_at ?? now(), 'ends_at' => $v->expires_at ? Carbon::parse($v->expires_at)->endOfDay() : now()->addYears(10), 'usage_limit' => $v->usage_limit ?? 4294967295, 'per_buyer_limit' => 4294967295, 'is_active' => $v->is_active, 'legacy_platform_id' => $v->id, 'legacy_used_count' => $v->used_count]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Restore the pre-migration backup to undo commerce changes without losing records.');
    }
};
