<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        // Only defaults change. Never rewrite orders, entitlements or posted payments.
        if (Schema::hasTable('settings')) DB::table('settings')->update(['commission_rate' => 10]);
        if (Schema::hasTable('logistics_finance_settings')) {
            DB::table('logistics_finance_settings')->update([
                'pickup_cents' => 0, 'transfer_cents' => 0, 'delivery_cents' => 1000,
                'revision' => DB::raw('revision + 1'), 'updated_at' => now(),
            ]);
        }
        if (Schema::hasTable('commission_enrollment_settings')) {
            DB::table('commission_enrollment_settings')->where('id', 1)->where('enabled', false)->update([
                'enabled' => true, 'activation_at' => now()->addSecond()->startOfSecond(),
                'revision' => DB::raw('revision + 1'),
            ]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Financial policy changes require a forward migration; preserve posted financial history.');
    }
};
