<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Preserve company-specific rates; only replace the previous platform defaults.
        DB::table('logistics_finance_settings')->where('pickup_cents', 2500)->where('delivery_cents', 4000)
            ->update(['pickup_cents' => 3000, 'delivery_cents' => 5000, 'updated_at' => now()]);
    }
    public function down(): void { }
};
