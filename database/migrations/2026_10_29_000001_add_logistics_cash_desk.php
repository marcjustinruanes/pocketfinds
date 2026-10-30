<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('logistics_finance_settings', function (Blueprint $t) {
            $t->string('remittance_cutoff', 5)->default('23:59');
        });
        // A remittance is a physical handover, not a second buyer payment.
        Schema::create('logistics_cash_handovers', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('company_name', 190)->index();
            $t->string('kind', 16); // remittance or transfer
            $t->bigInteger('hub_id');
            $t->bigInteger('destination_hub_id')->nullable();
            $t->bigInteger('actor_id');
            $t->bigInteger('counted_by')->nullable();
            $t->unsignedBigInteger('expected_cents');
            $t->unsignedBigInteger('counted_cents')->nullable();
            $t->string('status', 24)->default('submitted');
            $t->uuid('idempotency_key');
            $t->string('request_hash', 64);
            $t->text('notes')->nullable();
            $t->text('resolution')->nullable();
            $t->timestamp('counted_at')->nullable();
            $t->timestamp('dispatched_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->bigInteger('resolved_by')->nullable();
            $t->timestamps();
            $t->unique(['company_name', 'idempotency_key'], 'cash_handover_key_unique');
            $t->index(['company_name', 'hub_id', 'status']);
            foreach (['hub_id', 'destination_hub_id'] as $column) {
                $t->foreign($column)->references('id')->on('logistics_hubs')->restrictOnDelete();
            }
            foreach (['actor_id', 'counted_by', 'resolved_by'] as $column) {
                $t->foreign($column)->references('id')->on('users')->restrictOnDelete();
            }
        });
        Schema::create('logistics_cash_allocations', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('handover_id');
            $t->uuid('collection_id');
            $t->unsignedBigInteger('expected_cents');
            $t->unsignedBigInteger('verified_cents')->nullable();
            $t->unique(['handover_id', 'collection_id'], 'cash_allocation_unique');
            $t->foreign('handover_id')->references('id')->on('logistics_cash_handovers')->restrictOnDelete();
            $t->foreign('collection_id')->references('id')->on('logistics_financial_transactions')->restrictOnDelete();
        });
        // Append-only movements: receipt, dispatch and confirmed transfer receipt.
        Schema::create('logistics_cash_movements', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('handover_id');
            $t->bigInteger('hub_id');
            $t->string('kind', 24);
            $t->bigInteger('delta_cents');
            $t->bigInteger('actor_id');
            $t->timestamp('created_at');
            $t->unique(['handover_id', 'kind'], 'cash_movement_once');
            $t->foreign('handover_id')->references('id')->on('logistics_cash_handovers')->restrictOnDelete();
            $t->foreign('hub_id')->references('id')->on('logistics_hubs')->restrictOnDelete();
            $t->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Cash custody history requires an audited forward migration.');
    }
};
