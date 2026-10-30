<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Workspace company/role/status restriction; employee pages never scan salary history.
        Schema::table('users', function (Blueprint $table) {
            $table->index(['business_name', 'logistics_role', 'status', 'id'], 'staff_payroll_members');
        });
        // Correlated rate lookups use Staff/company and revision ordering.
        Schema::table('logistics_staff_salaries', function (Blueprint $table) {
            $table->index(['staff_id', 'company_name', 'revision'], 'staff_payroll_rate_revision');
        });
        // Latest destination and identical-time ambiguity checks use this exact prefix.
        Schema::table('logistics_staff_destinations', function (Blueprint $table) {
            $table->index(['staff_id', 'created_at', 'id'], 'staff_payroll_destination_latest');
        });
    }

    public function down(): void
    {
        Schema::table('logistics_staff_destinations', fn (Blueprint $table) => $table->dropIndex('staff_payroll_destination_latest'));
        Schema::table('logistics_staff_salaries', fn (Blueprint $table) => $table->dropIndex('staff_payroll_rate_revision'));
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('staff_payroll_members'));
    }
};
