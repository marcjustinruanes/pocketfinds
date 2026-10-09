<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $type = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'shipments')
            ->where('column_name', 'courier_id')
            ->value('data_type');

        if ($type === 'bigint') {
            return;
        }

        if ($type !== 'uuid') {
            throw new RuntimeException("Unexpected shipments.courier_id type: {$type}");
        }

        $assignedCount = DB::table('shipments')->whereNotNull('courier_id')->count();

        if ($assignedCount > 0) {
            throw new RuntimeException(
                "Refusing to convert shipments.courier_id because {$assignedCount} existing value(s) require manual mapping."
            );
        }

        DB::transaction(function () {
            // The old policy compared this app's bigint user id with auth.uid()'s UUID.
            // Preserve its valid visibility rules while removing that impossible clause.
            DB::statement('DROP POLICY IF EXISTS "Riders can view available or their own shipments" ON shipments');
            DB::statement('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_courier_id_fkey');
            DB::statement('ALTER TABLE shipments ALTER COLUMN courier_id TYPE bigint USING NULL');
            DB::statement('ALTER TABLE shipments ADD CONSTRAINT shipments_courier_id_fkey FOREIGN KEY (courier_id) REFERENCES users(id) ON DELETE SET NULL');
            DB::statement(<<<'SQL'
                CREATE POLICY "Riders can view available or their own shipments"
                ON shipments
                FOR SELECT
                TO authenticated
                USING (
                    (shipping_status = 'available' AND courier_id IS NULL)
                    OR is_my_order(order_id)
                )
            SQL);
        });
    }

    public function down(): void
    {
        $assignedCount = DB::table('shipments')->whereNotNull('courier_id')->count();

        if ($assignedCount > 0) {
            throw new RuntimeException(
                "Refusing to revert shipments.courier_id because {$assignedCount} assignment(s) would be lost."
            );
        }

        DB::transaction(function () {
            DB::statement('DROP POLICY IF EXISTS "Riders can view available or their own shipments" ON shipments');
            DB::statement('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_courier_id_fkey');
            DB::statement('ALTER TABLE shipments ALTER COLUMN courier_id TYPE uuid USING NULL');
            DB::statement(<<<'SQL'
                CREATE POLICY "Riders can view available or their own shipments"
                ON shipments
                FOR SELECT
                TO authenticated
                USING (
                    (shipping_status = 'available' AND courier_id IS NULL)
                    OR courier_id = auth.uid()
                    OR is_my_order(order_id)
                )
            SQL);
        });
    }
};
