<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('logistics_hub_salary_rates', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('company_name',190)->index();
            $t->bigInteger('hub_id'); $t->unsignedInteger('revision');
            $t->unsignedBigInteger('monthly_cents'); $t->date('effective_period');
            $t->bigInteger('set_by'); $t->timestamp('created_at');
            $t->foreign('hub_id')->references('id')->on('logistics_hubs')->restrictOnDelete();
            $t->foreign('set_by')->references('id')->on('users')->restrictOnDelete();
            $t->unique(['hub_id','revision']);
        });
        Schema::table('logistics_staff_salaries', function (Blueprint $t) {
            $t->string('salary_source',16)->default('individual');
            $t->bigInteger('source_hub_id')->nullable(); $t->uuid('hub_rate_id')->nullable();
            $t->date('period_effective')->nullable();
            $t->foreign('source_hub_id')->references('id')->on('logistics_hubs')->restrictOnDelete();
            $t->foreign('hub_rate_id')->references('id')->on('logistics_hub_salary_rates')->restrictOnDelete();
        });
        Schema::create('logistics_staff_attendance', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('company_name',190); $t->bigInteger('staff_id'); $t->bigInteger('hub_id');
            $t->date('business_date'); $t->string('kind',16)->default('shift');
            $t->timestamp('time_in')->nullable(); $t->timestamp('time_out')->nullable();
            $t->timestamp('original_in')->nullable(); $t->timestamp('original_out')->nullable();
            $t->boolean('corrected')->default(false); $t->boolean('needs_review')->default(false);
            $t->bigInteger('active_staff_id')->nullable()->unique();
            $t->string('day_key',100)->nullable()->unique();
            $t->unsignedInteger('revision')->default(1); $t->text('reason')->nullable(); $t->timestamps();
            $t->foreign('staff_id')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('hub_id')->references('id')->on('logistics_hubs')->restrictOnDelete();
            $t->foreign('active_staff_id')->references('id')->on('users')->restrictOnDelete();
            $t->index(['company_name','business_date','staff_id'],'staff_attendance_period');
            $t->index(['staff_id','business_date']);
        });
        Schema::create('logistics_staff_attendance_corrections', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('company_name',190); $t->bigInteger('staff_id'); $t->bigInteger('hub_id');
            $t->uuid('attendance_id')->nullable(); $t->date('business_date'); $t->unsignedInteger('base_revision')->nullable();
            $t->timestamp('requested_in'); $t->timestamp('requested_out')->nullable();
            $t->text('reason'); $t->string('status',16)->default('pending');
            $t->string('pending_key',100)->nullable()->unique();
            $t->bigInteger('decided_by')->nullable(); $t->text('decision_reason')->nullable(); $t->timestamp('decided_at')->nullable(); $t->timestamps();
            $t->foreign('staff_id')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('hub_id')->references('id')->on('logistics_hubs')->restrictOnDelete();
            $t->foreign('attendance_id')->references('id')->on('logistics_staff_attendance')->restrictOnDelete();
            $t->foreign('decided_by')->references('id')->on('users')->restrictOnDelete();
            $t->index(['company_name','status','business_date'],'staff_attendance_corrections_queue');
        });
        Schema::create('logistics_staff_attendance_events', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->string('company_name',190); $t->bigInteger('staff_id');
            $t->uuid('subject_id'); $t->string('event',64); $t->bigInteger('actor_id');
            $t->json('details'); $t->timestamp('created_at');
            $t->foreign('staff_id')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
            $t->index(['company_name','subject_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve salary and attendance history; use a reviewed forward migration.');
    }
};
