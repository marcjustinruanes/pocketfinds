<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('logistics_compensations', fn (Blueprint $t) => $t->string('earned_source_key',80)->nullable()->unique());
        Schema::create('logistics_rider_destinations', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->bigInteger('rider_id');
            $t->string('channel',16); $t->string('institution',100); $t->string('account_name',150); $t->string('account_number',64); $t->timestamp('created_at');
            $t->foreign('rider_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('logistics_rider_payouts', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('compensation_id')->unique(); $t->string('company_name',190)->index(); $t->bigInteger('rider_id');
            $t->uuid('treasury_account_id'); $t->uuid('destination_id'); $t->unsignedBigInteger('amount_cents');
            $t->string('status',24); $t->unsignedInteger('revision')->default(1); $t->text('hold_reason')->nullable(); $t->text('notes')->nullable();
            $t->bigInteger('prepared_by'); $t->bigInteger('verified_by')->nullable(); $t->bigInteger('paid_by')->nullable();
            $t->timestamp('verified_at')->nullable(); $t->timestamp('paid_at')->nullable(); $t->timestamp('acknowledged_at')->nullable(); $t->string('reference',64)->nullable(); $t->timestamps();
            foreach (['compensation_id'=>'logistics_compensations','treasury_account_id'=>'logistics_treasury_accounts','destination_id'=>'logistics_rider_destinations'] as $col=>$table) $t->foreign($col)->references('id')->on($table)->restrictOnDelete();
            foreach (['rider_id','prepared_by','verified_by','paid_by'] as $col) $t->foreign($col)->references('id')->on('users')->restrictOnDelete();
        });
        Schema::table('logistics_financial_transactions', function (Blueprint $t) {
            $t->uuid('rider_payout_id')->nullable()->unique();
            $t->foreign('rider_payout_id')->references('id')->on('logistics_rider_payouts')->restrictOnDelete();
        });
    }
    public function down(): void { throw new RuntimeException('Rider financial history requires an audited forward migration.'); }
};
