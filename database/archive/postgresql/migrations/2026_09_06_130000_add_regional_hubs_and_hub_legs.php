<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Splits a company's hub network into two tiers instead of one flat list:
 * every registered municipality is a local hub, and one municipality per
 * province can additionally be that province's regional hub — still nothing
 * hardcoded, both tiers are entirely built from municipalities the company
 * itself registered (see LogisticsHub::buildRoute()).
 *
 * A same-province transfer is unaffected (still a single hub-to-hub hop). A
 * cross-province transfer now expands into a real multi-leg journey — local
 * hub -> regional hub -> regional hub -> local hub — with its own rider
 * assignment per leg, tracked in the new shipment_hub_legs table. The
 * shipment's own `shipping_status` stays exactly as before ('at_sorting_center'
 * -> 'hub_transfer' -> 'sorted'); only which leg is currently active changes
 * as staff assign/complete each hop in turn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_hubs', function (Blueprint $table) {
            $table->boolean('is_regional_hub')->default(false)->after('municipality');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->string('origin_province')->nullable()->after('origin_hub');
            $table->string('destination_province')->nullable()->after('destination_hub');
        });

        Schema::create('shipment_hub_legs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('shipment_id');
            $table->foreign('shipment_id')->references('id')->on('shipments')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('leg_type'); // 'direct' (same province) | 'dispatch' | 'relay' | 'delivery'
            $table->string('from_hub');
            $table->string('to_hub');
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending | in_transit | completed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['shipment_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_hub_legs');
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['origin_province', 'destination_province']);
        });
        Schema::table('logistics_hubs', function (Blueprint $table) {
            $table->dropColumn('is_regional_hub');
        });
    }
};
