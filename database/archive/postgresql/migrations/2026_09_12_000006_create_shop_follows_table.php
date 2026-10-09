<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// app/Models/ShopFollow.php is new, but the shop_follows table itself
// already exists in the database (created outside a migration, same gap
// as product_images in 2026_09_12_000003) - this just brings it under
// version control so a fresh environment gets it too.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_follows')) {
            return;
        }

        Schema::create('shop_follows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['buyer_id', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_follows');
    }
};
