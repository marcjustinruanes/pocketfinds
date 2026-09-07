<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The pickup leg (seller -> origin hub) becomes a mutual confirmation, mirroring how the
 * delivery leg already needs the rider to mark "delivered" AND the buyer to confirm receipt:
 * the seller confirms handing the parcel over, the rider confirms actually receiving it, and
 * only once BOTH have confirmed does the shipment advance to 'picked_up' (previously the
 * seller's confirmation alone flipped it, with no rider-side attestation at all).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->timestamp('seller_confirmed_pickup_at')->nullable()->after('pickup_rider_id');
            $table->timestamp('rider_confirmed_pickup_at')->nullable()->after('seller_confirmed_pickup_at');
        });

        // Every shipment that's already past ready_for_pickup was picked up under the old
        // seller-only rule — backfill both flags from picked_up_at so it reads consistently.
        \Illuminate\Support\Facades\DB::statement("
            UPDATE shipments
            SET seller_confirmed_pickup_at = picked_up_at, rider_confirmed_pickup_at = picked_up_at
            WHERE picked_up_at IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['seller_confirmed_pickup_at', 'rider_confirmed_pickup_at']);
        });
    }
};
