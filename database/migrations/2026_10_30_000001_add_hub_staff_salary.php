<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Per-staff monthly salary configuration. Append-only: changing the rate inserts a new row.
        Schema::create('logistics_staff_salaries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('company_name', 190)->index();
            $t->bigInteger('staff_id');
            $t->unsignedBigInteger('monthly_cents');
            $t->unsignedInteger('revision')->default(1);
            $t->bigInteger('set_by');
            $t->timestamp('effective_from');
            $t->timestamp('superseded_at')->nullable();
            $t->timestamps();
            $t->foreign('staff_id')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('set_by')->references('id')->on('users')->restrictOnDelete();
            $t->index(['staff_id', 'superseded_at']);
        });

        // One row per semi-monthly installment per staff member.
        // period_half: 1 = 1st–15th, 2 = 16th–end of month.
        Schema::create('logistics_staff_salary_periods', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('company_name', 190)->index();
            $t->bigInteger('staff_id');
            $t->uuid('salary_id');
            $t->unsignedSmallInteger('period_year');
            $t->unsignedTinyInteger('period_month');
            $t->unsignedTinyInteger('period_half');   // 1 or 2
            $t->date('pay_date');                     // 15th or last calendar day, Asia/Manila
            $t->unsignedBigInteger('amount_cents');   // snapshot of half-salary at generation time
            $t->string('status', 24)->default('pending'); // pending | prepared | verified | paid | cancelled
            $t->timestamps();
            $t->foreign('staff_id')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('salary_id')->references('id')->on('logistics_staff_salaries')->restrictOnDelete();
            // Prevent duplicate installments for the same staff member and period.
            $t->unique(['staff_id', 'period_year', 'period_month', 'period_half'], 'staff_period_unique');
            $t->index(['company_name', 'status']);
        });

        // GCash receiving accounts registered by Hub Staff themselves.
        Schema::create('logistics_staff_destinations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->bigInteger('staff_id');
            $t->string('channel', 16);
            $t->string('institution', 100);
            $t->string('account_name', 150);
            $t->string('account_number', 64);
            $t->timestamp('created_at');
            $t->foreign('staff_id')->references('id')->on('users')->restrictOnDelete();
        });

        // One payout record per salary period.
        Schema::create('logistics_staff_payouts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('period_id')->unique();
            $t->string('company_name', 190)->index();
            $t->bigInteger('staff_id');
            $t->uuid('treasury_account_id');
            $t->uuid('destination_id');
            $t->unsignedBigInteger('amount_cents');
            $t->string('status', 24);               // prepared | verified | paid | cancelled
            $t->unsignedInteger('revision')->default(1);
            $t->text('hold_reason')->nullable();
            $t->text('notes')->nullable();
            $t->bigInteger('prepared_by');
            $t->bigInteger('verified_by')->nullable();
            $t->bigInteger('paid_by')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('acknowledged_at')->nullable();
            $t->string('reference', 64)->nullable();
            $t->timestamps();
            $t->foreign('period_id')->references('id')->on('logistics_staff_salary_periods')->restrictOnDelete();
            $t->foreign('treasury_account_id')->references('id')->on('logistics_treasury_accounts')->restrictOnDelete();
            $t->foreign('destination_id')->references('id')->on('logistics_staff_destinations')->restrictOnDelete();
            foreach (['staff_id', 'prepared_by', 'verified_by', 'paid_by'] as $col) {
                $t->foreign($col)->references('id')->on('users')->restrictOnDelete();
            }
        });

        // Staff payroll funding bucket in treasury movements uses source_type='staff_funding'.
        // No schema change needed: logistics_treasury_movements already has a free-form source_type column.
    }

    public function down(): void
    {
        throw new RuntimeException('Staff salary history must not be erased by rollback. Use a forward migration.');
    }
};
