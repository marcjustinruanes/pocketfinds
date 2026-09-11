<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Same situation as the other 2026_09_12_* "recreate" migrations in this
// batch: 2026_08_20_000000_create_rider_profiles_table is recorded as "Ran"
// but the table is missing from this shared database.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rider_profiles')) {
            return;
        }

        Schema::create('rider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

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

            $table->enum('vehicle_type', ['motorcycle', 'bicycle', 'car_van']);
            $table->string('vehicle_brand')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('or_file')->nullable();
            $table->string('cr_file')->nullable();

            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('license_file')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_profiles');
    }
};
