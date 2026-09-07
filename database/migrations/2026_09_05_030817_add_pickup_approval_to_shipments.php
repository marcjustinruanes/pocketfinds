<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * The ERP-Flow glossary keeps a single flat READY_FOR_PICKUP status, but the
 * ERP-Components doc separately lists "Confirm/approve/verify parcel pickup requests
 * from seller" as its own Logistics responsibility, distinct from a rider then accepting
 * it. Rather than split ready_for_pickup into two status values (breaking the doc's exact
 * vocabulary), that approval gate is tracked as its own timestamp: a shipment only shows
 * up in a pickup rider's "available requests" list once pickup_approved_at is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->timestamp('pickup_approved_at')->nullable()->after('sorted_area');
        });

        // Every shipment currently sitting at ready_for_pickup in the live data already
        // went through the old approveRequest() flow (it's how it left 'pending' at all),
        // so backfill them as already-approved rather than silently yanking them from
        // sight the moment this migration runs.
        DB::table('shipments')->where('shipping_status', 'ready_for_pickup')->update(['pickup_approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('pickup_approved_at');
        });
    }
};
