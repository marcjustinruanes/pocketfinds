<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commerce_shipping_rates', 'late_voucher_enabled')) {
            Schema::table('commerce_shipping_rates', fn (Blueprint $table) => $table->boolean('late_voucher_enabled')->default(false));
        }
        if (! Schema::hasColumn('commerce_shipping_rates', 'late_voucher_amount')) {
            Schema::table('commerce_shipping_rates', fn (Blueprint $table) => $table->decimal('late_voucher_amount', 12, 2)->default(40));
        }
    }

    public function down(): void
    {
        Schema::table('commerce_shipping_rates', fn (Blueprint $table) => $table->dropColumn(['late_voucher_enabled', 'late_voucher_amount']));
    }
};
