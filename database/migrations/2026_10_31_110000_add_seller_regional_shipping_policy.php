<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['free_shipping', 'delivery_days_min', 'delivery_days_max'] as $column) {
            if (Schema::hasColumn('commerce_shipping_rates', $column)) continue;
            Schema::table('commerce_shipping_rates', function (Blueprint $table) use ($column) {
                if ($column === 'free_shipping') $table->boolean($column)->default(false);
                else $table->unsignedSmallInteger($column)->default($column === 'delivery_days_min' ? 1 : 7);
            });
        }
    }

    public function down(): void
    {
        Schema::table('commerce_shipping_rates', fn (Blueprint $table) => $table->dropColumn(['free_shipping', 'delivery_days_min', 'delivery_days_max']));
    }
};
