<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', fn(Blueprint $t) => $t->timestamp('buyer_confirmed_at')->nullable());
        Schema::create('logistics_treasury_accounts', function(Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('company_name',190)->index();
            $t->string('channel',16); $t->string('institution',100); $t->string('account_name',150); $t->string('account_number',64);
            $t->bigInteger('created_by'); $t->timestamp('created_at');
            $t->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $t->unique(['company_name','channel','account_number'],'treasury_account_unique');
        });
        Schema::create('logistics_payout_destinations', function(Blueprint $t) {
            $t->uuid('id')->primary(); $t->bigInteger('seller_id'); $t->string('channel',16); $t->string('institution',100);
            $t->string('account_name',150); $t->string('account_number',64); $t->timestamp('created_at');
            $t->foreign('seller_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('logistics_seller_payouts', function(Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('company_name',190)->index(); $t->uuid('order_id')->unique(); $t->uuid('collection_id')->unique();
            $t->bigInteger('seller_id'); $t->uuid('treasury_account_id'); $t->uuid('destination_id');
            $t->unsignedBigInteger('product_cents'); $t->unsignedBigInteger('shipping_cents'); $t->unsignedBigInteger('adjustment_cents')->default(0);
            $t->text('adjustment_reason')->nullable(); $t->string('status',24); $t->unsignedInteger('revision')->default(1);
            $t->bigInteger('prepared_by'); $t->bigInteger('verified_by')->nullable(); $t->bigInteger('paid_by')->nullable();
            $t->timestamp('verified_at')->nullable(); $t->timestamp('paid_at')->nullable(); $t->timestamp('acknowledged_at')->nullable();
            $t->text('hold_reason')->nullable(); $t->text('notes')->nullable(); $t->string('reference',64)->nullable(); $t->timestamps();
            foreach(['order_id'=>'orders','collection_id'=>'logistics_financial_transactions','treasury_account_id'=>'logistics_treasury_accounts','destination_id'=>'logistics_payout_destinations'] as $col=>$table) $t->foreign($col)->references('id')->on($table)->restrictOnDelete();
            foreach(['seller_id','prepared_by','verified_by','paid_by'] as $col) $t->foreign($col)->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('logistics_treasury_movements', function(Blueprint $t) {
            $t->bigIncrements('id'); $t->uuid('account_id'); $t->string('bucket',16); $t->bigInteger('delta_cents');
            $t->string('source_type',24); $t->uuid('source_id'); $t->string('reference',64)->nullable();
            $t->bigInteger('actor_id'); $t->timestamp('created_at');
            $t->unique(['source_type','source_id','bucket'],'treasury_movement_once');
            $t->unique(['account_id','reference'],'treasury_reference_unique');
            $t->foreign('account_id')->references('id')->on('logistics_treasury_accounts')->restrictOnDelete();
            $t->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('logistics_finance_evidence', function(Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('company_name',190)->index(); $t->string('source_type',24); $t->uuid('source_id');
            $t->string('path'); $t->string('mime',64); $t->string('content_hash',64)->unique(); $t->bigInteger('actor_id'); $t->timestamp('created_at');
            $t->unique(['source_type','source_id'],'finance_evidence_once');
            $t->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::table('logistics_cash_handovers', function(Blueprint $t) {
            $t->uuid('treasury_account_id')->nullable(); $t->unsignedBigInteger('cod_released_cents')->nullable(); $t->unsignedBigInteger('cod_received_cents')->nullable();
            $t->foreign('treasury_account_id')->references('id')->on('logistics_treasury_accounts')->restrictOnDelete();
        });
        Schema::table('logistics_financial_transactions', function(Blueprint $t) {
            $t->uuid('seller_payout_id')->nullable()->unique();
            $t->foreign('seller_payout_id')->references('id')->on('logistics_seller_payouts')->restrictOnDelete();
        });
    }
    public function down(): void { throw new RuntimeException('Payout history requires an audited forward migration.'); }
};
