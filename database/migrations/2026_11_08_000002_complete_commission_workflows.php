<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commission_verified_receipts', function(Blueprint $t) {
            $t->string('reference',64)->primary(); $t->string('source_type',16); $t->uuid('source_id');
            $t->unsignedBigInteger('amount_cents'); $t->bigInteger('verified_by'); $t->timestamp('verified_at');
            $t->string('proof_hash',64)->nullable()->unique();
            $t->unique(['source_type','source_id']);
            $t->foreign('verified_by')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('commission_recoveries', function(Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('submission_key')->unique(); $t->string('request_hash',64);
            $t->bigInteger('seller_id')->index(); $t->uuid('receiving_account_id');
            $t->string('reference',64)->index(); $t->unsignedBigInteger('amount_cents');
            $t->unsignedBigInteger('unapplied_cents')->default(0); $t->string('status',16)->default('pending');
            $t->string('proof_path'); $t->string('proof_mime',64); $t->string('proof_hash',64)->index();
            $t->text('reason'); $t->text('review_reason')->nullable(); $t->uuid('previous_payment_id')->nullable();
            $t->bigInteger('reviewed_by')->nullable(); $t->unsignedInteger('revision')->default(1);
            $t->timestamp('verified_at')->nullable(); $t->timestamps();
            $t->index(['seller_id','created_at']);
            $t->foreign('seller_id')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('receiving_account_id')->references('id')->on('commission_accounts')->restrictOnDelete();
            $t->foreign('previous_payment_id')->references('id')->on('commission_payments')->restrictOnDelete();
        });
        Schema::create('commission_recovery_events', function(Blueprint $t) {
            $t->bigIncrements('id'); $t->uuid('recovery_id'); $t->string('kind',32); $t->bigInteger('actor_id');
            $t->text('details'); $t->timestamp('created_at');
            $t->foreign('recovery_id')->references('id')->on('commission_recoveries')->restrictOnDelete();
        });
        Schema::create('commission_recovery_allocations', function(Blueprint $t) {
            $t->bigIncrements('id'); $t->uuid('recovery_id'); $t->uuid('obligation_id');
            $t->unsignedBigInteger('amount_cents'); $t->timestamp('created_at');
            $t->foreign('recovery_id')->references('id')->on('commission_recoveries')->restrictOnDelete();
            $t->foreign('obligation_id')->references('id')->on('commission_obligations')->restrictOnDelete();
        });
        Schema::create('commission_recovery_refunds', function(Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('recovery_id'); $t->unsignedBigInteger('amount_cents');
            $t->string('reference',64)->unique(); $t->text('destination'); $t->string('proof_path');
            $t->string('proof_mime',64); $t->text('reason'); $t->bigInteger('paid_by'); $t->timestamp('paid_at');
            $t->foreign('recovery_id')->references('id')->on('commission_recoveries')->restrictOnDelete();
        });
        Schema::create('cod_refund_reconciliations', function(Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('return_id')->unique(); $t->uuid('order_id')->unique();
            $t->string('company_name',190)->index(); $t->uuid('account_id'); $t->string('payer',16);
            $t->unsignedBigInteger('amount_cents'); $t->unsignedBigInteger('remaining_cents');
            $t->unsignedBigInteger('seller_reimbursement_cents')->default(0);
            $t->unsignedBigInteger('required_subsidy_cents')->default(0);
            $t->string('source_hash',64); $t->string('reference',64)->unique(); $t->text('buyer_destination');
            $t->string('status',24); $t->text('reason'); $t->bigInteger('reviewed_by');
            $t->uuid('platform_account_id')->nullable(); $t->text('platform_destination')->nullable();
            $t->string('reimbursement_reference',64)->nullable()->unique();
            $t->bigInteger('platform_verified_by')->nullable(); $t->timestamp('platform_verified_at')->nullable();
            $t->unsignedInteger('revision')->default(1); $t->timestamps();
            $t->foreign('return_id')->references('id')->on('commerce_returns')->restrictOnDelete();
            $t->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $t->foreign('account_id')->references('id')->on('logistics_treasury_accounts')->restrictOnDelete();
            $t->foreign('platform_account_id')->references('id')->on('commission_accounts')->restrictOnDelete();
        });
        Schema::create('cod_residual_settlements', function(Blueprint $t) {
            $t->uuid('payout_id')->primary(); $t->uuid('reconciliation_id');
            $t->unsignedBigInteger('original_product_cents'); $t->unsignedBigInteger('buyer_refund_cents');
            $t->unsignedBigInteger('remaining_product_cents'); $t->unsignedBigInteger('seller_reimbursement_cents');
            $t->foreign('payout_id')->references('id')->on('logistics_seller_payouts')->restrictOnDelete();
            $t->foreign('reconciliation_id')->references('id')->on('cod_refund_reconciliations')->restrictOnDelete();
        });
    }
    public function down(): void { throw new RuntimeException('Financial recovery and refund history requires a forward migration.'); }
};
