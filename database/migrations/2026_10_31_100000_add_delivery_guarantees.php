<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['delivery_guaranteed_from', 'delivery_guaranteed_by'] as $column) {
            if (! Schema::hasColumn('orders', $column)) {
                Schema::table('orders', fn (Blueprint $table) => $table->dateTime($column)->nullable()->index());
            }
        }
        if (! Schema::hasTable('delivery_late_vouchers')) {
            Schema::create('delivery_late_vouchers', function (Blueprint $table) {
                $table->id();
                $table->string('order_id', 36)->unique();
                $table->bigInteger('buyer_id')->index();
                $table->unsignedBigInteger('voucher_id')->unique();
                $table->dateTime('issued_at');
            });
        }
        DB::table('commerce_vouchers')->insertOrIgnore([
            'code' => 'PFSHIPFREE', 'kind' => 'shipping', 'seller_id' => null,
            'discount_type' => 'percent', 'amount' => 100, 'minimum_spend' => 0,
            'starts_at' => now(), 'ends_at' => '2037-12-31 23:59:59',
            'usage_limit' => 4294967295, 'per_buyer_limit' => 4294967295, 'is_active' => true,
        ]);
    }

    public function down(): void
    {
        // Financial entitlements and promised dates must be retained for existing orders.
        throw new RuntimeException('Delivery guarantees require forward recovery; issued entitlements cannot be deleted.');
    }
};
