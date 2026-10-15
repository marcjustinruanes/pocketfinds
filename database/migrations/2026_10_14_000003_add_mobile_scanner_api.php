<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            // Existing PocketFinds user IDs are signed BIGINTs.
            $table->string('tokenable_type');
            $table->bigInteger('tokenable_id');
            $table->index(['tokenable_type', 'tokenable_id']);
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('mobile_scanner_operations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->uuid('operation_id');
            $table->string('payload_hash', 64);
            $table->json('response');
            $table->unsignedSmallInteger('http_status');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'operation_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve mobile operation receipts and reconcile tokens before a manual rollback.');
    }
};
