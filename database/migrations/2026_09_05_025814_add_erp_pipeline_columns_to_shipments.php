<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Expands the order/shipment lifecycle to the full ERP-Flow pipeline:
 * placed -> confirmed -> preparing -> ready_for_pickup -> picked_up ->
 * at_sorting_center -> sorted -> assigned_to_rider -> out_for_delivery ->
 * delivered -> completed, with delivery_failed/returned as a failure branch.
 *
 * This also splits the single-rider "seller to buyer" run into two legs:
 * a pickup rider (seller -> sorting center) and a delivery rider (sorting
 * center -> buyer, still tracked on the existing `courier_id` column).
 *
 * `orders.status` and `shipping_status` are both plain unconstrained varchar
 * columns already (verified: no CHECK constraint on either), so no column
 * type change is needed — only new columns to carry the new stages' data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('pickup_rider_id')->nullable()->after('courier_id')->constrained('users')->nullOnDelete();
            $table->string('sorted_area')->nullable()->after('shipping_status');
            $table->text('delivery_failed_reason')->nullable()->after('sorted_area');
            $table->timestamp('at_sorting_center_at')->nullable()->after('picked_up_at');
            $table->timestamp('sorted_at')->nullable()->after('at_sorting_center_at');
            $table->timestamp('assigned_at')->nullable()->after('sorted_at');
            $table->timestamp('delivery_failed_at')->nullable()->after('delivered_at');
            $table->timestamp('returned_at')->nullable()->after('delivery_failed_at');
        });

        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->string('leg')->default('delivery')->after('shipment_id'); // 'pickup' or 'delivery'
        });

        // Backfill: the live dataset is tiny (5 orders, 5 shipments), so remap it precisely
        // by meaning rather than a blind find/replace — 'available' meant "logistics
        // approved, awaiting a rider claim", which is READY_FOR_PICKUP in the new pipeline,
        // not SORTED. 'for_verification'/'verified' were declared but never actually used
        // anywhere in the app, so they're dropped rather than carried forward.
        $shipmentMap = [
            'pending'          => 'ready_for_pickup',
            'for_verification' => 'ready_for_pickup',
            'verified'         => 'ready_for_pickup',
            'available'        => 'ready_for_pickup',
            'accepted'         => 'ready_for_pickup', // a rider claimed it but hadn't physically picked up yet
            'failed'           => 'delivery_failed',
            // picked_up, out_for_delivery, delivered, completed, cancelled, returned already match as-is.
        ];
        foreach ($shipmentMap as $old => $new) {
            DB::table('shipments')->where('shipping_status', $old)->update(['shipping_status' => $new]);
        }

        // Orders without a shipment yet only ever sat at the old 'to_ship' bucket, which
        // covered PLACED/CONFIRMED/PREPARING indistinguishably — reset those to the
        // earliest of the three rather than guess. Orders that already have a shipment
        // mirror whatever stage that shipment just got remapped to, matching how the new
        // controllers keep order.status in lockstep with shipment progress going forward.
        DB::table('orders')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('shipments')->whereColumn('shipments.order_id', 'orders.id');
            })
            ->update(['status' => 'placed']);

        DB::statement("
            UPDATE orders SET status = shipments.shipping_status
            FROM shipments
            WHERE shipments.order_id = orders.id
              AND orders.status NOT IN ('completed', 'cancelled')
        ");
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_rider_id');
            $table->dropColumn(['sorted_area', 'delivery_failed_reason', 'at_sorting_center_at', 'sorted_at', 'assigned_at', 'delivery_failed_at', 'returned_at']);
        });
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropColumn('leg');
        });
    }
};
