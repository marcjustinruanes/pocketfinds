<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('variation_label')->nullable()->after('product_id');
            $table->decimal('variation_price', 10, 2)->nullable()->after('variation_label');
            $table->string('variation_image')->nullable()->after('variation_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['variation_label', 'variation_price', 'variation_image']);
        });
    }
};
