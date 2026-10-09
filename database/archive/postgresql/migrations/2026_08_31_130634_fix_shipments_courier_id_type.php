<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * shipments.courier_id was left as `uuid`, FK'd to the dead `courier_profiles.user_id`
 * table — a leftover from an abandoned Supabase-auth-uuid design, from before `users.id`
 * became a bigint. Every real courier id (a users.id) is a bigint, so any attempt to write
 * one into this column fails with "invalid input syntax for type uuid" — this is what makes
 * both LogisticsController::assignCourier() and the new self-service courier "Accept" flow
 * blow up. delivery_assignments.courier_id was already fixed to bigint→users.id correctly;
 * this migration brings shipments.courier_id in line with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The column is currently empty in production (no shipment has ever successfully
        // been assigned a courier because of this exact bug), but guard with a count check
        // anyway so this never silently discards real data if that ever changes.
        $stray = DB::table('shipments')->whereNotNull('courier_id')->count();
        if ($stray > 0) {
            throw new \RuntimeException("Refusing to alter shipments.courier_id: {$stray} row(s) still have a value in it. Resolve manually first.");
        }

        // A leftover RLS policy from the same abandoned Supabase-auth design compares
        // courier_id to auth.uid() (a uuid) and blocks retyping the column outright.
        // The app connects as the table-owning Postgres role (via the pooler), which
        // bypasses RLS entirely — these policies were never actually enforced against
        // our queries — so it's dropped rather than rewritten for a type it no longer is.
        DB::statement('DROP POLICY IF EXISTS "Riders can view available or their own shipments" ON shipments');
        DB::statement('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_courier_id_fkey');
        DB::statement('ALTER TABLE shipments ALTER COLUMN courier_id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE shipments ADD CONSTRAINT shipments_courier_id_fkey FOREIGN KEY (courier_id) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_courier_id_fkey');
        DB::statement('ALTER TABLE shipments ALTER COLUMN courier_id TYPE uuid USING NULL');
    }
};
