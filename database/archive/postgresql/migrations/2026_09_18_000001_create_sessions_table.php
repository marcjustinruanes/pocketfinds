<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** SESSION_DRIVER was "file" on a host without a shared/persistent disk across
 *  app instances — a request landing on a different instance than the one that
 *  wrote the session file couldn't find it, so the CSRF token looked invalid
 *  and form submits (e.g. seller/inventory) 419'd. Sessions now live in the
 *  same shared Postgres DB every instance already talks to. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
