<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        if(!Schema::hasColumn('orders','platform_payment')) Schema::table('orders', function (Blueprint $t) {
            $t->boolean('platform_payment')->default(false)->index();
        });
        // Existing verification rows and their unique order constraint are retained.
        if(DB::getDriverName()==='mysql') DB::statement('ALTER TABLE payment_verifications MODIFY status VARCHAR(32) NOT NULL DEFAULT \'submitted\'');
        Schema::table('payment_verifications', function (Blueprint $t) {
            if(!Schema::hasColumn('payment_verifications','amount_paid')) $t->decimal('amount_paid', 12, 2)->nullable();
            if(!Schema::hasColumn('payment_verifications','reference_hash')) $t->string('reference_hash', 64)->nullable()->unique();
            if(!Schema::hasColumn('payment_verifications','submission_id')) $t->uuid('submission_id')->nullable()->unique();
            if(!Schema::hasColumn('payment_verifications','reviewed_by')) $t->unsignedBigInteger('reviewed_by')->nullable();
            if(!Schema::hasColumn('payment_verifications','verification_deadline')) $t->timestamp('verification_deadline')->nullable();
            if(!Schema::hasColumn('payment_verifications','can_resubmit')) $t->boolean('can_resubmit')->default(false);
            if(!Schema::hasColumn('payment_verifications','refund_reference')) $t->string('refund_reference',150)->nullable()->unique();
            if(!Schema::hasColumn('payment_verifications','refunded_at')) $t->timestamp('refunded_at')->nullable();
        });
        if(!Schema::hasTable('platform_payment_audit')) Schema::create('platform_payment_audit', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('subject_id', 64)->index();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('action', 60);
            $t->json('details');
            $t->timestamp('created_at');
        });
        if(!Schema::hasTable('platform_payment_references')) Schema::create('platform_payment_references', function(Blueprint $t) {
            $t->string('reference_hash',64)->primary();
            $t->uuid('order_id');
            $t->timestamp('created_at');
        });
        if(!Schema::hasTable('rider_payouts')) Schema::create('rider_payouts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('shipment_id');
            $t->string('leg', 16);
            $t->unsignedBigInteger('rider_id')->index();
            $t->decimal('amount', 12, 2);
            $t->string('status', 24)->default('approved');
            $t->string('reference', 150)->nullable()->unique();
            $t->unsignedBigInteger('approved_by');
            $t->unsignedBigInteger('released_by')->nullable();
            $t->timestamp('created_at');
            $t->timestamp('released_at')->nullable();
            $t->unique(['shipment_id', 'leg']);
        });
    }

    public function down(): void
    {
        // Financial/audit records must never be deleted by a rollback.
        throw new RuntimeException('This financial migration requires a reviewed forward migration to reverse.');
    }
};
