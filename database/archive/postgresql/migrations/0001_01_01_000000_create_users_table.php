<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Account type & auth
            $table->enum('account_type', ['buyer', 'rider', 'seller'])->default('buyer');
            $table->enum('auth_method', ['manual', 'google'])->default('manual');
            $table->string('google_id')->nullable()->unique();

            // Personal
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_initial', 1)->nullable();
            $table->enum('sex', ['male', 'female']);
            $table->date('birthday');
            $table->unsignedTinyInteger('age');

            // Contact
            $table->string('email')->unique();
            $table->string('contact_no', 11);

            // Address
            $table->string('province');
            $table->string('municipality');
            $table->string('barangay');
            $table->string('house_no')->nullable();
            $table->string('street')->nullable();

            // Account
            $table->string('password')->nullable(); // nullable for Google auth
            $table->string('id_file')->nullable();  // stored file path

            // Admin approval
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

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
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
