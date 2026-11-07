<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->json('media')->nullable()->after('photos');
        });

        Schema::create('order_service_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('seller_id');
            $table->enum('review_type', ['shop', 'rider']);
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->json('media')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'review_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_service_reviews');
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('media');
        });
    }
};
