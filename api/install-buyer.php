<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/bootstrap.php';
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
$sql=file_get_contents(__DIR__.'/../sql/mysql/2026_11_04_buyer_requirements.sql');
$sql=preg_replace('/^--.*$/m','',$sql);
foreach(explode(';',$sql) as $statement) if(trim($statement)!=='') DB::unprepared($statement);
        if (!Schema::hasTable('mobile_commerce_wishlist')) Schema::create('mobile_commerce_wishlist', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('buyer_id');
            $t->string('product_id', 36);
            $t->timestamp('created_at');
            $t->unique(['buyer_id', 'product_id']);
        });
        if (!Schema::hasTable('mobile_commerce_vouchers')) Schema::create('mobile_commerce_vouchers', function (Blueprint $t) {
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
        if (!Schema::hasTable('mobile_commerce_claims')) Schema::create('mobile_commerce_claims', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('buyer_id');
            $t->unsignedBigInteger('voucher_id');
            $t->unique(['buyer_id', 'voucher_id']);
        });
        if (!Schema::hasTable('mobile_commerce_allocations')) Schema::create('mobile_commerce_allocations', function (Blueprint $t) {
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
        if (!Schema::hasTable('mobile_commerce_flash_sales')) Schema::create('mobile_commerce_flash_sales', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('seller_id');
            $t->string('product_id', 36)->index();
            $t->decimal('price', 12, 2);
            $t->unsignedInteger('quantity');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->boolean('is_active')->default(true);
        });
        if (!Schema::hasTable('mobile_commerce_reminders')) Schema::create('mobile_commerce_reminders', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('buyer_id');
            $t->unsignedBigInteger('flash_sale_id');
            $t->timestamp('sent_at')->nullable();
            $t->unique(['buyer_id', 'flash_sale_id']);
        });
        if (!Schema::hasTable('mobile_commerce_shipping_rates')) Schema::create('mobile_commerce_shipping_rates', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('seller_id');
            $t->string('region');
            $t->decimal('first_kg', 12, 2);
            $t->decimal('additional_kg', 12, 2);
            $t->boolean('is_active')->default(true);
            $t->unique(['seller_id', 'region']);
        });
        if (!Schema::hasTable('mobile_commerce_checkouts')) Schema::create('mobile_commerce_checkouts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->bigInteger('buyer_id');
            $t->string('request_hash', 64);
            $t->json('result')->nullable();
            $t->timestamps();
        });
        if (!Schema::hasTable('mobile_commerce_returns')) Schema::create('mobile_commerce_returns', function (Blueprint $t) {
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

if (!Schema::hasColumn('mobile_push_devices','session_hash')) Schema::table('mobile_push_devices',fn(Blueprint $t)=>$t->string('session_hash',64)->nullable());

echo "Buyer app schema installed additively. Website source unchanged.\n";
