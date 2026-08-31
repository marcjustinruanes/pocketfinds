<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sellers set a real discount price separate from the base price.
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('discount_price', 10, 2)->nullable()->after('price');
        });

        // Real scheduled courier pickup, distinct from when the seller
        // requested one (requested_at) and when it actually happened (picked_up_at).
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->timestamp('scheduled_pickup_at')->nullable()->after('requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropColumn('scheduled_pickup_at');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('discount_price');
        });
    }
};
