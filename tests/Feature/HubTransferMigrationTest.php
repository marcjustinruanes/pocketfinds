<?php

namespace Tests\Feature;

use App\Models\HubTransferBatch;
use App\Models\LogisticsHub;
use App\Models\Shipment;
use App\Models\ShipmentHubLeg;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class HubTransferMigrationTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema(false);
    }

    private function leg(): ShipmentHubLeg
    {
        foreach (['Laguna', 'Marinduque'] as $province) {
            LogisticsHub::create(['company_name' => 'Alpha', 'municipality' => 'Santa Cruz', 'province' => $province]);
        }
        $s = Shipment::create(['logistics_company' => 'Alpha', 'origin_hub' => 'Santa Cruz', 'origin_province' => 'Laguna', 'destination_hub' => 'Santa Cruz', 'destination_province' => 'Marinduque', 'shipping_status' => 'at_sorting_center']);

        return ShipmentHubLeg::create(['shipment_id' => $s->id, 'sequence' => 1, 'leg_type' => 'relay', 'from_hub' => 'Santa Cruz', 'to_hub' => 'Santa Cruz', 'status' => 'pending']);
    }

    public function test_legacy_approval_without_an_admin_is_preserved_but_does_not_grant_dispatch(): void
    {
        $leg = $this->leg();
        $leg->update(['requested_at' => now()->subDay(), 'approved_at' => now()]);
        $before = DB::table('shipment_hub_legs')->first();
        (require database_path('migrations/2026_10_14_000001_add_hub_transfer_batches.php'))->up();
        $after = DB::table('shipment_hub_legs')->select(array_keys((array) $before))->first();
        $this->assertEquals($before, $after);
        $batch = HubTransferBatch::firstOrFail();
        $this->assertSame('pending', $batch->status);
        $this->assertTrue($batch->legacy_import);
        $this->assertSame(1, $batch->members()->count());
        $this->assertSame(0, $batch->events()->count());
    }

    public function test_valid_legacy_approval_is_imported_without_changing_existing_values(): void
    {
        $leg = $this->leg();
        $admin = User::create(['username' => 'legacy_admin', 'account_type' => 'logistics', 'business_name' => 'Alpha', 'is_logistics' => true, 'is_admin' => false, 'logistics_role' => 'admin']);
        $leg->update(['requested_at' => now()->subDay(), 'approved_at' => now(), 'approved_by' => $admin->id]);
        $before = DB::table('shipment_hub_legs')->first();
        (require database_path('migrations/2026_10_14_000001_add_hub_transfer_batches.php'))->up();
        $this->assertEquals($before, DB::table('shipment_hub_legs')->select(array_keys((array) $before))->first());
        $this->assertSame('approved', HubTransferBatch::firstOrFail()->status);
        $this->assertSame(1, HubTransferBatch::count());
    }

    public function test_duplicate_route_sequences_block_installation_before_any_schema_or_data_changes(): void
    {
        $leg = $this->leg();
        ShipmentHubLeg::create(['shipment_id' => $leg->shipment_id, 'sequence' => 1, 'leg_type' => 'relay', 'from_hub' => 'Santa Cruz', 'to_hub' => 'Santa Cruz', 'status' => 'pending']);
        try {
            (require database_path('migrations/2026_10_14_000001_add_hub_transfer_batches.php'))->up();
            $this->fail('Expected duplicate route preflight to fail.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Duplicate shipment route sequences', $error->getMessage());
        }
        $this->assertFalse(Schema::hasTable('hub_transfer_batches'));
        $this->assertFalse(Schema::hasColumn('shipment_hub_legs', 'transfer_batch_id'));
        $this->assertSame(2, ShipmentHubLeg::count());
    }
}
