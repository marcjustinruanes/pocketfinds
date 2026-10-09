<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adds a second kind of logistics account — hub staff, tied to one specific
 * LogisticsHub row (their own municipality's hub for that company; whether it's
 * also that province's regional hub is just LogisticsHub.is_regional_hub) —
 * alongside the existing company-admin account. Every existing `is_logistics`
 * row defaults to 'admin' so nothing already registered changes behavior.
 *
 * Also turns hub-to-hub movement from "any staff can assign a rider outright"
 * into a request/approve cycle: hub staff request a leg move, the company
 * admin approves it, only then can a rider actually be assigned to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('logistics_role')->nullable()->after('is_logistics'); // 'admin' | 'hub_staff', null for non-logistics accounts
            $table->foreignId('logistics_hub_id')->nullable()->after('logistics_role')->constrained('logistics_hubs')->nullOnDelete();
        });

        DB::table('users')->where('is_logistics', true)->update(['logistics_role' => 'admin']);

        Schema::table('shipment_hub_legs', function (Blueprint $table) {
            $table->timestamp('requested_at')->nullable()->after('status');
            $table->foreignId('requested_by')->nullable()->after('requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('requested_by');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipment_hub_legs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['requested_at', 'approved_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('logistics_hub_id');
            $table->dropColumn('logistics_role');
        });
    }
};
