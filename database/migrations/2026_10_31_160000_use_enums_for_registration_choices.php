<?php

use App\Enums\{DriverLicenseCode, LicenseType, VehicleOwnership};
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        $choices = [
            'license_type' => LicenseType::values(),
            'vehicle_ownership' => VehicleOwnership::values(),
            'vehicle_dl_code' => array_column(DriverLicenseCode::vehicleCategories(), 'value'),
        ];
        // Check every column before altering any, preserving existing records.
        foreach ($choices as $field => $values) {
            if (DB::table('users')->whereNotNull($field)->whereNotIn($field, $values)->exists()) {
                throw new RuntimeException("The users.{$field} column contains values outside the registration enum. Correct these records before migrating.");
            }
        }
        foreach ($choices as $field => $values) {
            Schema::table('users', fn (Blueprint $table) => $table->enum($field, $values)->nullable()->change());
        }
    }

    public function down(): void
    {
        foreach (['license_type', 'vehicle_ownership', 'vehicle_dl_code'] as $field) {
            Schema::table('users', fn (Blueprint $table) => $table->string($field)->nullable()->change());
        }
    }
};
