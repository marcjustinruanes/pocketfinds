<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'store_description')) $table->text('store_description')->nullable();
            if (!Schema::hasColumn('users', 'store_banner')) $table->string('store_banner')->nullable();
        });

        Schema::table('shop_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_categories', 'is_visible')) $table->boolean('is_visible')->default(true);
            if (!Schema::hasColumn('shop_categories', 'sort_order')) $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::table('commerce_vouchers', function (Blueprint $table) {
            if (!Schema::hasColumn('commerce_vouchers', 'display_mode')) $table->enum('display_mode', ['claimable', 'code_only'])->default('claimable');
            if (!Schema::hasColumn('commerce_vouchers', 'product_ids')) $table->json('product_ids')->nullable();
        });

        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'seller_reply_edited_at')) $table->timestamp('seller_reply_edited_at')->nullable();
        });

        Schema::create('commerce_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('product_id', 36)->index();
            $table->enum('discount_type', ['percent', 'fixed']);
            $table->decimal('amount', 12, 2);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('commerce_bundles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->enum('kind', ['quantity_percent', 'buy_x_get_y']);
            $table->json('product_ids');
            $table->string('gift_product_id', 36)->nullable();
            $table->unsignedInteger('buy_quantity')->default(1);
            $table->unsignedInteger('gift_quantity')->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('seller_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->index();
            $table->string('order_id', 36)->unique();
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('seller_discount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->enum('status', ['pending', 'available', 'excluded', 'paid'])->default('pending');
            $table->timestamps();
        });

        Schema::create('seller_payout_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('seller_id')->index();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payout_requests');
        Schema::dropIfExists('seller_earnings');
        Schema::dropIfExists('commerce_bundles');
        Schema::dropIfExists('commerce_sales');
    }
};
