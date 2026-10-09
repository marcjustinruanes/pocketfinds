<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('product_id');
            $table->string('seller_slug');
            $table->string('seller');
            $table->string('name');
            $table->string('img')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('qty');
            $table->string('color')->default('');
            $table->string('size')->default('');
            // The real name of the variation group the buyer picked from
            // (e.g. "Flavor"), so the cart/edit UI can label it correctly
            // instead of always assuming "Color".
            $table->string('variation_group')->nullable();
            $table->timestamps();

            $table->unique(['buyer_id', 'product_id', 'color', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
