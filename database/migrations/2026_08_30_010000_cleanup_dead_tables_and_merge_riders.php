<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Tables never read or written by any app code — safe to drop outright (all verified 0 rows). */
    private array $deadTables = [
        'barangays', 'municipalities', 'provinces',
        'buyer_profiles', 'seller_profiles', 'courier_profiles',
        'cart_items', 'carts', 'conversations', 'inventory',
        'logistics_settings', 'platform_settings', 'product_variations',
        'profiles', 'registration_applications', 'seller_violations',
        'vouchers', 'payments', 'order_items',
    ];

    public function up(): void
    {
        // ── 0. Drop leftover Supabase RLS policies / RPC helper functions.
        // This app talks to Postgres directly via Eloquent — it never goes through
        // PostgREST, so none of these auth.uid()-based policies/functions apply to
        // it, but they hold type/constraint dependencies that block the DDL below.
        foreach ([
            'orders' => ['Buyers can view their own orders', 'Buyers can create their own orders', 'Riders can view orders for visible shipments'],
            'shipments' => ['Buyers can view their own order shipment'],
            'products' => ['Public can view active products'],
            'users' => ['Public can view basic info of approved users'],
        ] as $table => $policies) {
            foreach ($policies as $policy) {
                DB::statement("DROP POLICY IF EXISTS \"$policy\" ON $table");
            }
        }
        foreach ([
            'get_my_profile()', 'my_legacy_user_id()',
            'update_my_profile(text, text, text, text, text, text)',
            'get_my_rider_profile()', 'claim_shipment(uuid)',
            'update_delivery_status(uuid, text)', 'get_delivery_contact_info(uuid)',
            'is_my_order(uuid)', 'shipment_visible_to_rider(uuid)', 'complete_registration()',
        ] as $fn) {
            DB::statement("DROP FUNCTION IF EXISTS $fn CASCADE");
        }

        // ── 1. Drop dead `orders` columns (from the same unused parallel schema),
        // then the `user_addresses` table they pointed at, then every other
        // confirmed-dead table (CASCADE is safe — they only reference each other).
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_shipping_address_id_fkey');
        Schema::table('orders', function (Blueprint $table) {
            foreach (['shipping_address_id', 'voucher_id', 'total_amount', 'order_status', 'placed_at', 'app_buyer_id', 'app_seller_id'] as $col) {
                if (Schema::hasColumn('orders', $col)) $table->dropColumn($col);
            }
        });
        DB::statement('DROP TABLE IF EXISTS "user_addresses" CASCADE');
        foreach ($this->deadTables as $table) {
            DB::statement("DROP TABLE IF EXISTS \"$table\" CASCADE");
        }

        // ── 2. Merge rider_profiles into users, close the duplication gap ────
        Schema::table('users', function (Blueprint $table) {
            $table->string('vehicle_type')->nullable();
            $table->string('vehicle_brand')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('or_file')->nullable();
            $table->string('cr_file')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('license_file')->nullable();
        });
        DB::statement('
            UPDATE users SET
                vehicle_type = rp.vehicle_type, vehicle_brand = rp.vehicle_brand, vehicle_model = rp.vehicle_model,
                plate_number = rp.plate_number, or_file = rp.or_file, cr_file = rp.cr_file,
                license_number = rp.license_number, license_expiry = rp.license_expiry, license_file = rp.license_file
            FROM rider_profiles rp WHERE rp.user_id = users.id
        ');
        Schema::dropIfExists('rider_profiles');

        // ── 3. Fix the uuid-vs-bigint FK bugs on complaints/commissions (same
        // family of bug already fixed on reviews/shipments last session) — today
        // these break "Report Message" and the admin commission report outright.
        DB::statement('ALTER TABLE complaints DROP CONSTRAINT IF EXISTS complaints_complainant_id_fkey');
        DB::statement('ALTER TABLE complaints DROP CONSTRAINT IF EXISTS complaints_respondent_id_fkey');
        DB::statement('ALTER TABLE complaints DROP CONSTRAINT IF EXISTS complaints_handled_by_fkey');
        DB::statement('ALTER TABLE complaints ALTER COLUMN complainant_id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE complaints ALTER COLUMN respondent_id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE complaints ALTER COLUMN handled_by TYPE bigint USING NULL');
        DB::statement('ALTER TABLE complaints ADD CONSTRAINT complaints_complainant_id_fkey FOREIGN KEY (complainant_id) REFERENCES users(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE complaints ADD CONSTRAINT complaints_respondent_id_fkey FOREIGN KEY (respondent_id) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE complaints ADD CONSTRAINT complaints_handled_by_fkey FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL');

        DB::statement('ALTER TABLE commissions DROP CONSTRAINT IF EXISTS commissions_seller_id_fkey');
        DB::statement('ALTER TABLE commissions ALTER COLUMN seller_id TYPE bigint USING NULL');
        DB::statement('ALTER TABLE commissions ADD CONSTRAINT commissions_seller_id_fkey FOREIGN KEY (seller_id) REFERENCES users(id)');

        // ── 4. Fix announcements — the model/controller/view all use
        // title/body/audience/created_by, but the real table had
        // title/content/posted_by(uuid) and no audience column at all, so
        // "Post Announcement" has been erroring on every submit.
        DB::statement('ALTER TABLE announcements DROP CONSTRAINT IF EXISTS announcements_posted_by_fkey');
        Schema::table('announcements', function (Blueprint $table) {
            $table->renameColumn('content', 'body');
            $table->renameColumn('posted_by', 'created_by');
            if (!Schema::hasColumn('announcements', 'audience')) {
                $table->string('audience')->default('all')->after('body');
            }
        });
        DB::statement('ALTER TABLE announcements ALTER COLUMN created_by TYPE bigint USING NULL');
        DB::statement('ALTER TABLE announcements ADD CONSTRAINT announcements_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        throw new \RuntimeException('Not reversible — restore from the pre-migration backup in storage/app/backups/ if needed.');
    }
};
