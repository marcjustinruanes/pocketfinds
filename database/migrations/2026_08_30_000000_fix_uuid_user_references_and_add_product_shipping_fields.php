<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // These tables were provisioned for Supabase's own auth.uid()-based RLS
        // policies, which this Laravel app never uses (it authenticates its own
        // session against the bigint `users` table). Those policies reference the
        // uuid courier_id/buyer_id columns we need to retype, so drop them first.
        DB::statement('DROP POLICY IF EXISTS "Riders can view available shipments" ON shipments');
        DB::statement('DROP POLICY IF EXISTS "Riders can view their assigned shipments" ON shipments');
        DB::statement('DROP POLICY IF EXISTS "Buyers can create a shipment for their own order" ON shipments');
        DB::statement('DROP POLICY IF EXISTS "Riders can view their own assignments" ON delivery_assignments');

        // Their FKs point at the same dead uuid profile mirror tables
        // (courier_profiles/seller_profiles/buyer_profiles/profiles) rather than the
        // real `users` table — drop them so the columns can be retyped, then
        // re-point them at `users.id` below.
        DB::statement('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_courier_id_fkey');
        DB::statement('ALTER TABLE delivery_assignments DROP CONSTRAINT IF EXISTS delivery_assignments_courier_id_fkey');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_buyer_id_fkey');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_seller_id_fkey');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_order_item_id_fkey');
        DB::statement('ALTER TABLE order_status_history DROP CONSTRAINT IF EXISTS order_status_history_changed_by_fkey');

        // These tables were provisioned with uuid FK columns pointing at Supabase's
        // auth.users (uuid), but the app's real `users` table uses bigint ids.
        // All three tables are empty, so it's safe to correct the column types.
        DB::statement('ALTER TABLE shipments ALTER COLUMN courier_id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE delivery_assignments ALTER COLUMN courier_id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE delivery_assignments ALTER COLUMN courier_id DROP NOT NULL');
        DB::statement('ALTER TABLE reviews ALTER COLUMN buyer_id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE reviews ALTER COLUMN seller_id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE reviews ALTER COLUMN order_item_id DROP NOT NULL');
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'order_id')) {
                $table->uuid('order_id')->nullable()->after('id');
            }
        });
        DB::statement('ALTER TABLE order_status_history ALTER COLUMN changed_by TYPE bigint USING NULL');
        DB::statement('ALTER TABLE order_status_history ALTER COLUMN changed_by DROP NOT NULL');

        // Re-point the retyped columns at the real `users` table.
        DB::statement('ALTER TABLE shipments ADD CONSTRAINT shipments_courier_id_fkey FOREIGN KEY (courier_id) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE delivery_assignments ADD CONSTRAINT delivery_assignments_courier_id_fkey FOREIGN KEY (courier_id) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_buyer_id_fkey FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_seller_id_fkey FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_order_id_fkey FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE order_status_history ADD CONSTRAINT order_status_history_changed_by_fkey FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL');

        // Shipment model has Eloquent timestamps enabled (touches updated_at on every
        // save) but the table was never given the column — every save() would fatal.
        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'weight_grams')) {
                $table->unsignedInteger('weight_grams')->default(0)->after('stock');
            }
            if (!Schema::hasColumn('products', 'length_cm')) {
                $table->unsignedInteger('length_cm')->nullable()->after('weight_grams');
            }
            if (!Schema::hasColumn('products', 'width_cm')) {
                $table->unsignedInteger('width_cm')->nullable()->after('length_cm');
            }
            if (!Schema::hasColumn('products', 'height_cm')) {
                $table->unsignedInteger('height_cm')->nullable()->after('width_cm');
            }
            if (!Schema::hasColumn('products', 'condition')) {
                $table->string('condition')->default('new')->after('height_cm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['weight_grams', 'length_cm', 'width_cm', 'height_cm', 'condition']);
        });
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'order_id')) {
                $table->dropColumn('order_id');
            }
        });
        DB::statement('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_courier_id_fkey');
        DB::statement('ALTER TABLE delivery_assignments DROP CONSTRAINT IF EXISTS delivery_assignments_courier_id_fkey');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_buyer_id_fkey');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_seller_id_fkey');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_order_id_fkey');
        DB::statement('ALTER TABLE order_status_history DROP CONSTRAINT IF EXISTS order_status_history_changed_by_fkey');

        DB::statement('ALTER TABLE shipments ALTER COLUMN courier_id TYPE uuid USING NULL');
        DB::statement('ALTER TABLE delivery_assignments ALTER COLUMN courier_id TYPE uuid USING NULL');
        DB::statement('ALTER TABLE reviews ALTER COLUMN buyer_id TYPE uuid USING NULL');
        DB::statement('ALTER TABLE reviews ALTER COLUMN seller_id TYPE uuid USING NULL');
        DB::statement('ALTER TABLE order_status_history ALTER COLUMN changed_by TYPE uuid USING NULL');
    }
};
