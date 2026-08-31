<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'shopee_url')) {
                $table->dropColumn('shopee_url');
            }
            if (!Schema::hasColumn('products', 'weight_grams_max')) {
                $table->unsignedInteger('weight_grams_max')->nullable()->after('weight_grams');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('shopee_url')->nullable();
            $table->dropColumn('weight_grams_max');
        });
    }
};
