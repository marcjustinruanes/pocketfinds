<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds hub-aware routing on top of the existing two-leg pipeline — additive,
 * the existing pickup/sort/deliver flow is untouched when a shipment's origin
 * and destination hub are the same city (still the common case). When they
 * differ, a new 'hub_transfer' stage (see Shipment::STATUSES) carries the
 * parcel between the two hubs before local delivery proceeds exactly as
 * before. `logistics_company` is which company the seller picked for this
 * shipment — every existing rider/staff query gets scoped to it so two
 * companies' hub networks never mix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('logistics_company')->nullable()->after('order_id');
            $table->string('origin_hub')->nullable()->after('logistics_company');
            $table->string('destination_hub')->nullable()->after('origin_hub');
            $table->foreignId('hub_transfer_rider_id')->nullable()->after('pickup_rider_id')->constrained('users')->nullOnDelete();
            $table->timestamp('hub_transfer_started_at')->nullable();
            $table->timestamp('hub_transfer_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hub_transfer_rider_id');
            $table->dropColumn(['logistics_company', 'origin_hub', 'destination_hub', 'hub_transfer_started_at', 'hub_transfer_completed_at']);
        });
    }
};
