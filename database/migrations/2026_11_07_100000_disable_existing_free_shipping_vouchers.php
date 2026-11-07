<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commerce_vouchers')) {
            DB::table('commerce_vouchers')->where('kind', 'shipping')->update(['is_active' => false]);
        }
        foreach (['vouchers', 'platform_vouchers'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('type', 'free_shipping')->update(['is_active' => false]);
            }
        }
    }

    public function down(): void
    {
        // Do not reactivate expired, used, or previously disabled vouchers.
    }
};
