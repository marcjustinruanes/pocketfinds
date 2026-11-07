<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach(['commission_payments','commission_recoveries'] as $table) Schema::table($table,function(Blueprint $t){$t->string('review_decision',16)->nullable();});
        Schema::create('commission_payment_messages',function(Blueprint $t){
            $t->bigIncrements('id');$t->uuid('payment_id')->nullable();$t->uuid('recovery_id')->nullable();
            $t->bigInteger('sender_id');$t->string('sender_label',190);$t->string('sender_role',24);
            $t->uuid('submission_key')->nullable()->unique();$t->string('event_key',190)->nullable()->unique();
            $t->string('kind',24);$t->text('body');$t->timestamp('created_at');
            $t->string('proof_path')->nullable();$t->string('proof_mime',64)->nullable();$t->string('proof_hash',64)->nullable();
            $t->index(['payment_id','id']);$t->index(['recovery_id','id']);
            $t->foreign('payment_id')->references('id')->on('commission_payments')->restrictOnDelete();
            $t->foreign('recovery_id')->references('id')->on('commission_recoveries')->restrictOnDelete();
            $t->foreign('sender_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
    public function down(): void {throw new RuntimeException('Payment conversation history must be preserved. Use a forward migration.');}
};
