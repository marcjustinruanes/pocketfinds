<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('logistics_staff_payouts', function (Blueprint $table) {
            // Actual external transfer date; paid_at remains the system recording timestamp.
            $table->date('payment_date')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve recorded salary payment dates; use a forward migration.');
    }
};
