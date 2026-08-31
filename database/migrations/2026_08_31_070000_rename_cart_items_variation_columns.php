<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "color" was a leftover name from an assumption that every product's
        // single variation is a color swatch — it isn't (e.g. "50X Niacinamide
        // and Omega 6: 90g"). Rename it to what it actually holds, and drop
        // the separate "size" slot — the real add-to-cart flow only ever
        // carries one variation_group + variation_value pair, never a
        // genuinely independent second dimension.
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_buyer_product_variation_unique');
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->renameColumn('color', 'variation_value');
            $table->dropColumn('size');
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['buyer_id', 'product_id', 'variation_value', 'variation_group'], 'cart_items_buyer_product_variation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_buyer_product_variation_unique');
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->renameColumn('variation_value', 'color');
            $table->string('size')->default('');
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['buyer_id', 'product_id', 'color', 'size', 'variation_group'], 'cart_items_buyer_product_variation_unique');
        });
    }
};
