<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_finance_settings', function (Blueprint $t) {
            $t->string('company_name', 190)->primary();
            $t->unsignedBigInteger('pickup_cents')->default(2500);
            $t->unsignedBigInteger('transfer_cents')->default(15000);
            $t->unsignedBigInteger('delivery_cents')->default(4000);
            $t->unsignedInteger('confirmation_hours')->default(168);
            $t->unsignedInteger('revision')->default(1);
            $t->bigInteger('updated_by')->nullable();
            $t->foreign('updated_by')->references('id')->on('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('logistics_compensations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('company_name', 190)->index();
            $t->string('source_type', 24);
            $t->uuid('source_id');
            $t->bigInteger('rider_id');
            $t->bigInteger('hub_id')->nullable();
            $t->string('leg', 16);
            $t->unsignedBigInteger('amount_cents');
            $t->unsignedInteger('settings_revision');
            $t->string('status', 24)->default('pending');
            $t->timestamp('earned_at')->nullable();
            $t->timestamps();
            $t->foreign('rider_id')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('hub_id')->references('id')->on('logistics_hubs')->restrictOnDelete();
            $t->unique(['source_type', 'source_id', 'rider_id'], 'logistics_compensation_source_rider_unique');
            $t->index(['company_name', 'status']);
        });
        Schema::create('logistics_financial_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('company_name', 190)->index();
            $t->bigInteger('actor_id')->nullable();
            $t->string('event', 64);
            $t->string('subject_type', 32);
            $t->string('subject_id', 190);
            $t->json('details');
            $t->timestamp('created_at');
            $t->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Financial history must not be erased by rollback. Restore a verified backup or use a forward migration.');
    }
};
