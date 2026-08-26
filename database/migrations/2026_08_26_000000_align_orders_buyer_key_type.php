<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'buyer_id')) {
            return;
        }

        if (Schema::getColumnType('orders', 'buyer_id') !== 'uuid') {
            return;
        }

        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_buyer_id_foreign');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_buyer_id_fkey');
        DB::statement('ALTER TABLE orders ALTER COLUMN buyer_id DROP NOT NULL');
        DB::statement('ALTER TABLE orders ALTER COLUMN buyer_id TYPE bigint USING NULL');

        if (Schema::hasColumn('orders', 'app_buyer_id')) {
            DB::statement('UPDATE orders SET buyer_id = app_buyer_id WHERE buyer_id IS NULL AND app_buyer_id IS NOT NULL');
        }

        if ((int) DB::table('orders')->whereNull('buyer_id')->count() === 0) {
            DB::statement('ALTER TABLE orders ALTER COLUMN buyer_id SET NOT NULL');
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('buyer_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        // Existing integer application user IDs cannot be losslessly converted to UUIDs.
    }
};
