<?php

namespace Tests\Feature;

use App\Enums\{DriverLicenseCode, LicenseType, Sex, VehicleOwnership};
use Illuminate\Support\Facades\DB;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class RegistrationEnumTest extends TestCase
{
    use WorkflowSchema;

    public function test_migration_preserves_existing_values_and_optional_empty_selections(): void
    {
        $this->createWorkflowSchema(legacyRiderFields:true);
        $id = DB::table('users')->insertGetId(['license_type'=>LicenseType::Professional->value, 'vehicle_ownership'=>VehicleOwnership::Own->value, 'vehicle_dl_code'=>DriverLicenseCode::A->value]);
        (require database_path('migrations/2026_10_31_160000_use_enums_for_registration_choices.php'))->up();
        $this->assertDatabaseHas('users', ['id'=>$id, 'license_type'=>'professional', 'vehicle_ownership'=>'own', 'vehicle_dl_code'=>'A']);
        $emptyId = DB::table('users')->insertGetId(['license_type'=>null, 'vehicle_ownership'=>null, 'vehicle_dl_code'=>null]);
        $this->assertDatabaseHas('users', ['id'=>$emptyId, 'license_type'=>null, 'vehicle_ownership'=>null, 'vehicle_dl_code'=>null]);
    }

    public function test_enum_migration_checks_all_legacy_values_before_changing_columns(): void
    {
        $this->createWorkflowSchema(legacyRiderFields:true);
        $id = DB::table('users')->insertGetId(['license_type'=>'legacy-invalid']);
        try {
            (require database_path('migrations/2026_10_31_160000_use_enums_for_registration_choices.php'))->up();
            $this->fail('Expected the migration to stop before altering invalid records.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('users.license_type', $exception->getMessage());
        }
        $this->assertDatabaseHas('users', ['id'=>$id, 'license_type'=>'legacy-invalid']);
    }

    public function test_retired_rider_fields_are_removed_without_removing_license_or_nbi(): void
    {
        $this->createWorkflowSchema(legacyRiderFields:true);
        $id=DB::table('users')->insertGetId(['license_type'=>'professional','dl_codes'=>'["A"]','vehicle_dl_code'=>'A','load_capacity_kg'=>20,'license_number'=>'A12-34-123456','nbi_clearance_file'=>'private/nbi.png']);
        (require database_path('migrations/2026_11_02_230000_remove_retired_rider_fields.php'))->up();
        foreach(['license_type','dl_codes','vehicle_dl_code','load_capacity_kg'] as $field)$this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('users',$field));
        $this->assertDatabaseHas('users',['id'=>$id,'license_number'=>'A12-34-123456','nbi_clearance_file'=>'private/nbi.png']);
        $this->assertSame(['male','female'], Sex::values());
    }
}
