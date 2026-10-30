<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_financial_transactions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('company_name', 190)->index();
            $t->string('kind', 32);
            $t->uuid('order_id')->nullable();
            $t->uuid('shipment_id')->nullable();
            // Non-null only for a COD collection: the database allows exactly one per shipment.
            $t->uuid('collection_shipment_id')->nullable()->unique();
            $t->uuid('collection_order_id')->nullable()->unique();
            $t->bigInteger('hub_id')->nullable();
            $t->bigInteger('actor_id');
            $t->bigInteger('recipient_id')->nullable();
            $t->unsignedBigInteger('amount_cents');
            $t->string('currency', 3)->default('PHP');
            $t->string('status', 24);
            $t->string('verification_state', 24)->default('pending');
            $t->uuid('idempotency_key');
            $t->string('request_hash', 64);
            $t->json('details');
            $t->timestamp('recorded_at');
            $t->timestamps();
            $t->unique(['company_name', 'idempotency_key'], 'logistics_transaction_idempotency_unique');
            $t->index(['company_name', 'hub_id', 'kind', 'verification_state'], 'logistics_transaction_verification_index');
            $t->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $t->foreign('shipment_id')->references('id')->on('shipments')->restrictOnDelete();
            $t->foreign('collection_shipment_id')->references('id')->on('shipments')->restrictOnDelete();
            $t->foreign('collection_order_id')->references('id')->on('orders')->restrictOnDelete();
            $t->foreign('hub_id')->references('id')->on('logistics_hubs')->restrictOnDelete();
            $t->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('recipient_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Financial transactions must not be erased by rollback. Use an audited forward migration.');
    }
};
