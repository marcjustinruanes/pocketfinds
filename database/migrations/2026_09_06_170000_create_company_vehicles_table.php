<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A logistics company's own vehicle fleet, one row per physical vehicle, always tied to
 * one specific hub (LogisticsHub) — the hub staff there are the ones who actually know
 * what's parked at their location, so they're the ones who list it; the company admin
 * reviews and approves it (mirrors the hub-staff-registration and hub-transfer-request
 * patterns elsewhere in this app: hub staff act, the admin signs off).
 *
 * `status` is the one-time admin approval gate (pending/approved/rejected). `is_available`
 * is separate and hub-staff-controlled — an approved vehicle can still be taken offline
 * for maintenance without re-triggering a whole new admin review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->foreignId('logistics_hub_id')->constrained('logistics_hubs')->cascadeOnDelete();
            $table->string('vehicle_type'); // matches vehicle_types.slug (two_wheels, four_wheels, ...)
            $table->string('brand');
            $table->string('model');
            $table->string('plate_number')->unique();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('status_reason')->nullable();
            $table->boolean('is_available')->default(true); // the maintenance toggle — only meaningful once approved
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_vehicles');
    }
};
