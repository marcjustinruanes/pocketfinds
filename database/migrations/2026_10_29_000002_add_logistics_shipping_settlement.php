<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipments', fn (Blueprint $t) => $t->unsignedSmallInteger('shipping_finance_version')->default(0));
        Schema::create('logistics_shipping_accounts', function (Blueprint $t) {
            $t->string('company_name', 190)->primary();
            $t->string('account_name', 150);
            $t->string('account_number', 11);
            $t->unsignedInteger('revision')->default(1);
            $t->bigInteger('updated_by');
            $t->timestamps();
            $t->foreign('updated_by')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('logistics_shipping_rates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('company_name', 190);
            $t->string('region', 150);
            $t->unsignedInteger('revision');
            $t->unsignedBigInteger('first_kg_cents');
            $t->unsignedBigInteger('additional_kg_cents');
            $t->boolean('is_active')->default(true);
            $t->bigInteger('approved_by');
            $t->timestamp('created_at');
            $t->unique(['company_name', 'region', 'revision'], 'shipping_rate_revision_unique');
            $t->index(['company_name', 'region', 'is_active'], 'shipping_rate_lookup');
            $t->foreign('approved_by')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('logistics_shipping_obligations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('shipment_id')->unique();
            $t->uuid('order_id')->unique();
            $t->string('company_name', 190)->index();
            $t->bigInteger('seller_id');
            $t->uuid('rate_id');
            $t->unsignedInteger('chargeable_kg');
            $t->unsignedBigInteger('amount_cents');
            $t->unsignedBigInteger('platform_claim_cents')->default(0);
            $t->string('claim_status', 24)->default('not_applicable');
            $t->json('voucher_funding');
            $t->json('instructions')->nullable();
            $t->string('collection_mode', 16);
            $t->string('status', 24);
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
            $t->foreign('shipment_id')->references('id')->on('shipments')->restrictOnDelete();
            $t->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $t->foreign('seller_id')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('rate_id')->references('id')->on('logistics_shipping_rates')->restrictOnDelete();
        });
        Schema::create('logistics_payment_references', function (Blueprint $t) {
            $t->string('reference', 64)->primary();
            $t->string('subject_type', 24);
            $t->uuid('subject_id');
            $t->timestamp('created_at');
        });
        if (Schema::hasTable('gcash_purchases')) {
            DB::table('gcash_purchases')->whereNotNull('payment_reference')->orderBy('id')->chunk(200, function ($rows) {
                foreach ($rows as $row) DB::table('logistics_payment_references')->insert([
                    'reference' => strtoupper(trim($row->payment_reference)), 'subject_type' => 'buyer_gcash', 'subject_id' => $row->id, 'created_at' => now(),
                ]);
            });
        }
        Schema::create('logistics_shipping_payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('obligation_id');
            $t->string('reference', 64)->unique();
            $t->unsignedBigInteger('amount_cents');
            $t->uuid('submission_key')->unique();
            $t->string('proof_hash', 64)->unique();
            $t->string('proof_path');
            $t->string('proof_mime', 64);
            $t->bigInteger('submitted_by');
            $t->string('status', 24)->default('submitted');
            $t->bigInteger('reviewed_by')->nullable();
            $t->text('review_notes')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->foreign('obligation_id')->references('id')->on('logistics_shipping_obligations')->restrictOnDelete();
            $t->foreign('reference')->references('reference')->on('logistics_payment_references')->restrictOnDelete();
            foreach (['submitted_by', 'reviewed_by'] as $column) $t->foreign($column)->references('id')->on('users')->restrictOnDelete();
        });
        Schema::table('logistics_financial_transactions', function (Blueprint $t) {
            $t->uuid('shipping_obligation_id')->nullable()->unique();
            $t->foreign('shipping_obligation_id')->references('id')->on('logistics_shipping_obligations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Shipping settlement records require an audited forward migration.');
    }
};
