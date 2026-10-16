<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('seller_id');
            $table->foreign('seller_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('store_categories')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('name_key', 50);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->unique(['seller_id', 'name_key']);
        });
        Schema::create('store_category_products', function (Blueprint $table) {
            $table->unsignedBigInteger('store_category_id');
            $table->foreign('store_category_id')->references('id')->on('store_categories')->cascadeOnDelete();
            // Preserve compatibility with the imported product identifiers.
            $table->string('product_id', 36);
            $table->primary(['store_category_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_category_products');
        Schema::dropIfExists('store_categories');
    }
};
