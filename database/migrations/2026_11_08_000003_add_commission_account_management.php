<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commission_accounts', function (Blueprint $t) {
            $t->text('encrypted_details')->nullable();
        });
        Schema::create('commission_account_settings', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->uuid('active_account_id')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->foreign('active_account_id')->references('id')->on('commission_accounts')->restrictOnDelete();
        });
        Schema::create('commission_account_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('account_id');
            $t->uuid('previous_account_id')->nullable();
            $t->bigInteger('actor_id');
            $t->string('action', 20);
            $t->unsignedInteger('revision')->unique();
            $t->text('reason');
            $t->timestamp('created_at');
            $t->foreign('account_id')->references('id')->on('commission_accounts')->restrictOnDelete();
            $t->foreign('previous_account_id')->references('id')->on('commission_accounts')->restrictOnDelete();
            $t->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
        });
        // No existing destination is activated implicitly, and no historical row is rewritten.
        DB::table('commission_account_settings')->insert(['id' => 1, 'revision' => 1]);
    }

    public function down(): void
    {
        throw new RuntimeException('Commission receiving-account history must be preserved. Use a forward migration.');
    }
};
