<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// app/Models/ProductImage.php and Product::images() already exist and are
// used by admin/products.blade.php, but this branch never had a migration
// to actually create the backing table -- a genuine gap (not a table
// dropped elsewhere), unlike the other 2026_09_12_* "recreate" migrations.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_images')) {
            return;
        }

        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('image_url');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
