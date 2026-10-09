<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The hub-to-hub leg gets the same mutual-confirmation treatment the pickup leg already
 * has: the rider carrying it must confirm they actually picked it up from the origin hub
 * BEFORE the destination hub can mark it received — previously a rider assigned to a leg
 * had no page, no action, and no visibility into it at all; the destination hub could mark
 * a leg "received" the instant a rider was assigned, whether or not they'd even left yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_hub_legs', function (Blueprint $table) {
            $table->timestamp('rider_confirmed_pickup_at')->nullable()->after('started_at');
        });

        // Legs already in_transit or completed under the old rule were implicitly "picked
        // up" the moment they started — backfill so nothing already in flight gets stuck.
        \Illuminate\Support\Facades\DB::statement("
            UPDATE shipment_hub_legs
            SET rider_confirmed_pickup_at = started_at
            WHERE started_at IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('shipment_hub_legs', function (Blueprint $table) {
            $table->dropColumn('rider_confirmed_pickup_at');
        });
    }
};
