<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rider registration redesign: vehicle types collapse from
 * motorcycle/bicycle/car_van down to two generic categories
 * (two_wheels/four_wheels), and riders can now say whether the
 * vehicle is their own or the logistics company's.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bicycle is no longer a selectable vehicle type — no riders currently
        // have it set (verified: the one real rider row has vehicle_type = null),
        // so this is a safe delete, not a data-loss risk.
        DB::table('vehicle_types')->where('slug', 'bicycle')->delete();

        // motorcycle -> two_wheels, car_van -> four_wheels. Any existing user row
        // referencing the old slug is remapped in the same migration so nothing
        // is left pointing at a slug that no longer exists.
        DB::table('vehicle_types')->where('slug', 'motorcycle')->update([
            'slug' => 'two_wheels',
            'name' => 'Two Wheels',
            'requires_documents' => true,
        ]);
        DB::table('vehicle_types')->where('slug', 'car_van')->update([
            'slug' => 'four_wheels',
            'name' => 'Four Wheels',
            'requires_documents' => true,
        ]);
        DB::table('users')->where('vehicle_type', 'motorcycle')->update(['vehicle_type' => 'two_wheels']);
        DB::table('users')->where('vehicle_type', 'car_van')->update(['vehicle_type' => 'four_wheels']);

        if (!Schema::hasColumn('users', 'vehicle_ownership')) {
            Schema::table('users', function (Blueprint $table) {
                // 'own' = rider's personal vehicle (brand/model/plate/OR/CR required),
                // 'company' = a logistics-company vehicle (those fields don't apply).
                $table->string('vehicle_ownership')->nullable()->after('vehicle_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'vehicle_ownership')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('vehicle_ownership');
            });
        }

        DB::table('users')->where('vehicle_type', 'two_wheels')->update(['vehicle_type' => 'motorcycle']);
        DB::table('users')->where('vehicle_type', 'four_wheels')->update(['vehicle_type' => 'car_van']);

        DB::table('vehicle_types')->where('slug', 'two_wheels')->update([
            'slug' => 'motorcycle',
            'name' => 'Motorcycle',
        ]);
        DB::table('vehicle_types')->where('slug', 'four_wheels')->update([
            'slug' => 'car_van',
            'name' => 'Car / Van',
        ]);

        DB::table('vehicle_types')->insert([
            'slug' => 'bicycle',
            'name' => 'Bicycle',
            'requires_documents' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
