<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('buyer_id');
            $table->foreign('buyer_id')->references('id')->on('users')->cascadeOnDelete();
            // Retain the snapshot when a listing is removed, as the workbook requires.
            $table->uuid('product_id');
            $table->string('product_name');
            $table->string('image')->nullable();
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->unique(['buyer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_entries');
    }
};
