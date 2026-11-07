<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commission_obligations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('source_key', 80)->unique();
            $t->uuid('order_id')->nullable()->unique();
            $t->uuid('purchase_id')->nullable()->unique();
            $t->bigInteger('seller_id')->index();
            $t->string('method', 8);
            $t->string('policy', 40)->default('seller-net-products-v1');
            $t->unsignedInteger('rate_bps')->default(1000);
            $t->unsignedBigInteger('basis_cents');
            $t->unsignedBigInteger('commission_cents');
            $t->unsignedBigInteger('reversed_cents')->default(0);
            $t->unsignedBigInteger('withheld_cents')->default(0);
            $t->timestamp('activation_at');
            $t->timestamp('earned_at')->nullable();
            $t->timestamp('due_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('company_name', 190)->nullable()->index();
            $t->uuid('treasury_account_id')->nullable();
            $t->timestamps();
            $t->index(['method', 'earned_at', 'due_at'], 'commission_due_index');
            $t->index(['seller_id', 'created_at'], 'commission_seller_history');
            $t->index(['company_name', 'created_at'], 'commission_company_history');
            $t->foreign('seller_id')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $t->foreign('purchase_id')->references('id')->on('gcash_purchases')->restrictOnDelete();
            $t->foreign('treasury_account_id')->references('id')->on('logistics_treasury_accounts')->restrictOnDelete();
        });
        Schema::create('commission_lines', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->uuid('obligation_id'); $t->unsignedInteger('line_key');
            $t->uuid('product_reference')->nullable(); $t->text('item_snapshot');
            $t->string('label'); $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('selling_cents'); $t->unsignedBigInteger('seller_discount_cents');
            $t->unsignedBigInteger('basis_cents'); $t->unsignedBigInteger('commission_cents');
            $t->unique(['obligation_id', 'line_key']);
            $t->foreign('obligation_id')->references('id')->on('commission_obligations')->restrictOnDelete();
        });
        Schema::create('commission_events', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->uuid('obligation_id'); $t->string('event_key', 160)->unique();
            $t->string('kind', 32); $t->bigInteger('amount_cents')->default(0);
            $t->bigInteger('actor_id')->nullable(); $t->text('details'); $t->timestamp('created_at');
            $t->foreign('obligation_id')->references('id')->on('commission_obligations')->restrictOnDelete();
            $t->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('commission_accounts', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('account_name', 150); $t->string('account_number', 11);
            $t->bigInteger('created_by'); $t->timestamp('created_at');
            $t->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('commission_payments', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('submission_key')->unique(); $t->string('request_hash', 64);
            $t->uuid('receiving_account_id'); $t->bigInteger('submitted_by');
            $t->bigInteger('seller_id')->nullable()->index(); $t->string('company_name', 190)->nullable()->index();
            $t->uuid('treasury_account_id')->nullable(); $t->string('method', 8);
            $t->unsignedBigInteger('amount_cents'); $t->unsignedBigInteger('unapplied_cents')->default(0);
            $t->string('reference', 64)->nullable()->unique(); $t->string('proof_path')->nullable(); $t->string('proof_mime', 64)->nullable();
            $t->string('proof_hash', 64)->nullable()->unique(); $t->string('status', 16)->default('pending');
            $t->unsignedInteger('revision')->default(1); $t->bigInteger('reviewed_by')->nullable();
            $t->timestamp('verified_at')->nullable(); $t->text('review_reason')->nullable(); $t->timestamps();
            $t->foreign('receiving_account_id')->references('id')->on('commission_accounts')->restrictOnDelete();
            foreach (['submitted_by','reviewed_by','seller_id'] as $column) $t->foreign($column)->references('id')->on('users')->restrictOnDelete();
            $t->foreign('treasury_account_id')->references('id')->on('logistics_treasury_accounts')->restrictOnDelete();
        });
        Schema::create('commission_allocations', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->uuid('payment_id'); $t->uuid('obligation_id');
            $t->unsignedBigInteger('requested_cents'); $t->unsignedBigInteger('allocated_cents')->default(0);
            $t->unique(['payment_id', 'obligation_id']);
            $t->foreign('payment_id')->references('id')->on('commission_payments')->restrictOnDelete();
            $t->foreign('obligation_id')->references('id')->on('commission_obligations')->restrictOnDelete();
        });
        Schema::create('commission_cod_settlements', function (Blueprint $t) {
            $t->uuid('payout_id')->primary(); $t->uuid('obligation_id')->unique();
            $t->unsignedBigInteger('commission_cents'); $t->timestamp('withheld_at')->nullable();
            $t->foreign('payout_id')->references('id')->on('logistics_seller_payouts')->restrictOnDelete();
            $t->foreign('obligation_id')->references('id')->on('commission_obligations')->restrictOnDelete();
        });
        Schema::create('commission_credits', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('obligation_id'); $t->string('source_key', 160)->unique();
            $t->string('payer', 16); $t->unsignedBigInteger('amount_cents');
            $t->string('kind', 16)->default('reversal');
            $t->text('destination')->nullable();
            $t->string('status', 16)->default('pending'); $t->string('reference', 64)->nullable()->unique();
            $t->string('proof_path')->nullable(); $t->string('proof_mime', 64)->nullable();
            $t->text('reason'); $t->bigInteger('paid_by')->nullable(); $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->foreign('obligation_id')->references('id')->on('commission_obligations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Commission history must be preserved; use a reviewed forward migration.');
    }
};
