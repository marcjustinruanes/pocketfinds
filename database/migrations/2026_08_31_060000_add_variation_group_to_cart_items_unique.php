<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function ($table) {
            $table->dropUnique(['buyer_id', 'product_id', 'color', 'size']);
        });
        // A plain unique() would let two rows with variation_group IS NULL
        // coexist (Postgres treats each NULL as distinct), so normalize it
        // to '' and index that instead — matching how color/size already
        // default to '' rather than null.
        DB::statement("UPDATE cart_items SET variation_group = '' WHERE variation_group IS NULL");
        Schema::table('cart_items', function ($table) {
            $table->string('variation_group')->default('')->nullable(false)->change();
            $table->unique(['buyer_id', 'product_id', 'color', 'size', 'variation_group'], 'cart_items_buyer_product_variation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function ($table) {
            $table->dropUnique('cart_items_buyer_product_variation_unique');
            $table->string('variation_group')->nullable()->change();
            $table->unique(['buyer_id', 'product_id', 'color', 'size']);
        });
    }
};
