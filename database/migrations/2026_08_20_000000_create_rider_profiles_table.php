<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // ── Copied from users (all required for a rider) ──
            $table->enum('auth_method', ['manual', 'google'])->default('manual');
            $table->string('google_id')->nullable();
            $table->string('username')->nullable();
            $table->string('last_name');
            $table->string('given_names');
            $table->string('middle_name')->nullable();
            $table->enum('sex', ['male', 'female']);
            $table->date('birthday');
            $table->unsignedTinyInteger('age');
            $table->string('email')->unique();
            $table->string('contact_no', 11);
            $table->string('province');
            $table->string('municipality');
            $table->string('barangay');
            $table->string('house_no')->nullable();
            $table->string('street')->nullable();
            $table->string('password')->nullable();
            $table->string('id_file')->nullable();
            $table->unsignedBigInteger('id_type_id')->nullable();
            $table->string('selfie_file')->nullable();

            // ── Vehicle Information ──
            $table->enum('vehicle_type', ['motorcycle', 'bicycle', 'car_van']);
            $table->string('vehicle_brand')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('plate_number')->nullable();   // null for bicycle
            $table->string('or_file')->nullable();        // Official Receipt
            $table->string('cr_file')->nullable();        // Certificate of Registration

            // ── Driver's License (motor vehicles only) ──
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('license_file')->nullable();

            // ── Admin approval ──
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_profiles');
    }
};
