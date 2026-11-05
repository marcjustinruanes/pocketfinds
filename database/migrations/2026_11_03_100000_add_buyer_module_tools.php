<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shop_categories', function (Blueprint $t) {
            $t->id(); $t->bigInteger('seller_id')->index(); $t->unsignedBigInteger('parent_id')->nullable()->index();
            $t->string('name', 50); $t->timestamps(); $t->unique(['seller_id', 'name']);
        });
        Schema::create('shop_category_products', function (Blueprint $t) {
            $t->unsignedBigInteger('shop_category_id'); $t->string('product_id', 36);
            $t->primary(['shop_category_id', 'product_id']);
        });
        Schema::create('cancellation_refunds', function (Blueprint $t) {
            $t->id(); $t->string('order_id', 36)->unique(); $t->bigInteger('buyer_id')->index(); $t->bigInteger('seller_id')->index();
            $t->decimal('amount', 12, 2); $t->string('method', 30)->default('gcash');
            $t->string('status', 20)->default('pending'); $t->string('payment_reference')->nullable();
            $t->string('repayment_reference')->nullable()->unique(); $t->timestamp('completed_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('cancellation_refunds'); Schema::dropIfExists('shop_category_products'); Schema::dropIfExists('shop_categories');
    }
};
