<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redesign the company vehicle workflow:
 *
 * OLD flow:  hub staff  → submits vehicle → logistics admin approves
 * NEW flow:  logistics admin → submits vehicle (assigns to a hub) → platform admin approves
 *            hub staff can still toggle availability (in/out of maintenance) on approved vehicles
 *
 * Changes:
 *  - Add `platform_status`          (pending|approved|rejected, default pending) — the platform admin's gate.
 *  - Add `platform_reviewed_by`     FK → users (nullable) — which PocketFinds admin reviewed it.
 *  - Add `platform_reviewed_at`     timestamp (nullable).
 *  - Add `platform_status_reason`   text (nullable) — populated on platform rejection.
 *  - The existing `status` / `reviewed_by` / `reviewed_at` / `status_reason` columns are
 *    repurposed as the logistics-admin's own internal notes / condition flag (kept for now
 *    but no longer part of the public approval gate — the platform admin's approval is what
 *    makes a vehicle visible to riders).
 *  - `submitted_by` now refers to a logistics admin user (was hub staff). No schema change
 *    needed — the FK column stays; the constraint already uses cascadeOnDelete from users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_vehicles', function (Blueprint $table) {
            // Platform-admin gate columns — added after the existing reviewed_at column.
            $table->string('platform_status')->default('pending')->after('reviewed_at');
            $table->text('platform_status_reason')->nullable()->after('platform_status');
            $table->foreignId('platform_reviewed_by')->nullable()->after('platform_status_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('platform_reviewed_at')->nullable()->after('platform_reviewed_by');
        });

        // Existing vehicles (submitted under the old hub-staff model) are considered
        // already reviewed — set platform_status to match their current status so
        // nothing disappears for riders mid-migration.
        \Illuminate\Support\Facades\DB::statement("
            UPDATE company_vehicles
            SET platform_status = CASE
                WHEN status = 'approved' THEN 'approved'
                WHEN status = 'rejected' THEN 'rejected'
                ELSE 'pending'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('company_vehicles', function (Blueprint $table) {
            $table->dropForeign(['platform_reviewed_by']);
            $table->dropColumn(['platform_status', 'platform_status_reason', 'platform_reviewed_by', 'platform_reviewed_at']);
        });
    }
};
