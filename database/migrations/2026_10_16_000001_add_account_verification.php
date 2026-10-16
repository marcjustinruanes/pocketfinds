<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 32)->default('pending')->change();
            $table->string('sex')->nullable()->change();
            $table->date('birthday')->nullable()->change();
            $table->smallInteger('age')->nullable()->change();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('seller_type', 32)->nullable();
            $table->string('tin', 15)->nullable()->unique();
            $table->string('sec_registration_no', 11)->nullable()->unique();
            $table->string('postal_code', 4)->nullable();
            $table->string('position')->nullable();
            $table->json('service_areas')->nullable();
            $table->string('dti_file')->nullable();
            $table->string('sec_file')->nullable();
            $table->string('bir_file')->nullable();
            $table->string('or_cr_file')->nullable();
        });
        Schema::create('account_verification_tokens', function (Blueprint $table) {
            $table->id();
            // The imported MySQL users.id is signed BIGINT.
            $table->bigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('token_hash');
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_verification_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tin']);
            $table->dropUnique(['sec_registration_no']);
            $table->dropColumn(['email_verified_at', 'seller_type', 'tin', 'sec_registration_no', 'postal_code',
                'position', 'service_areas', 'dti_file', 'sec_file', 'bir_file', 'or_cr_file']);
        });
        // Keep nullable identity fields and the wider status column: reverting them
        // would discard or invalidate accounts created using this registration flow.
    }
};
