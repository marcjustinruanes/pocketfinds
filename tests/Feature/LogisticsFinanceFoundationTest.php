<?php

namespace Tests\Feature;

use App\Models\DeliveryAssignment;
use App\Models\HubTransferBatch;
use App\Models\LogisticsHub;
use App\Models\Shipment;
use App\Models\User;
use App\Services\LogisticsFinance;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class LogisticsFinanceFoundationTest extends TestCase
{
    use WorkflowSchema;

    private LogisticsFinance $finance;
    private User $admin;
    private User $rider;
    private LogisticsHub $hub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        (require database_path('migrations/2026_10_28_000001_add_logistics_financial_foundation.php'))->up();
        $this->finance = app(LogisticsFinance::class);
        $this->hub = LogisticsHub::create(['company_name' => 'Alpha', 'municipality' => 'Santa Cruz', 'province' => 'Laguna', 'is_operational' => true]);
        $this->admin = $this->person('admin');
        $this->rider = $this->person('rider');
    }

    private function person(string $role, string $company = 'Alpha'): User
    {
        return User::create(['username' => (string) Str::uuid(), 'account_type' => $role === 'rider' ? 'rider' : 'logistics',
            'is_admin' => false, 'is_logistics' => $role !== 'rider', 'logistics_role' => $role === 'admin' ? 'admin' : 'hub_staff',
            'business_name' => $company, 'status' => 'approved', 'logistics_hub_id' => $this->hub->id]);
    }

    private function assignment(string $leg = 'delivery'): DeliveryAssignment
    {
        $shipment = Shipment::create(['logistics_company' => 'Alpha', 'shipping_status' => 'sorted']);

        return DeliveryAssignment::create(['shipment_id' => $shipment->id, 'courier_id' => $this->rider->id,
            'leg' => $leg, 'status' => $leg === 'pickup' ? 'accepted' : 'assigned_to_rider', 'accepted_at' => now()]);
    }

    public function test_defaults_are_company_scoped_and_money_is_exact(): void
    {
        $settings = $this->finance->settings('Alpha');
        $this->assertEquals([2500, 15000, 4000, 168], [$settings->pickup_cents, $settings->transfer_cents, $settings->delivery_cents, $settings->confirmation_hours]);
        $this->assertSame(101, $this->finance->cents('1.01'));
        $this->assertSame(10, $this->finance->cents('0.1'));
        $this->finance->configure($this->admin, ['pickup' => '30.25', 'transfer' => '160', 'delivery' => '42', 'revision' => 1]);
        $this->assertEquals(3025, $this->finance->settings('Alpha')->pickup_cents);
        $this->assertEquals(2500, $this->finance->settings('Beta')->pickup_cents);
        $this->assertDatabaseHas('logistics_financial_events', ['company_name' => 'Alpha', 'actor_id' => $this->admin->id, 'event' => 'rates_updated']);
    }

    public function test_rate_edits_preserve_existing_assignment_snapshots(): void
    {
        $assignment = $this->assignment();
        $this->finance->configure($this->admin, ['pickup' => '25', 'transfer' => '150', 'delivery' => '75', 'revision' => 1]);
        $this->finance->snapshotAssignment($assignment);
        $this->assertDatabaseHas('logistics_compensations', ['source_id' => $assignment->id, 'amount_cents' => 4000, 'settings_revision' => 1]);
        $next = $this->assignment();
        $this->assertDatabaseHas('logistics_compensations', ['source_id' => $next->id, 'amount_cents' => 7500, 'settings_revision' => 2]);
        $this->assertDatabaseCount('logistics_compensations', 2);
    }

    public function test_transfer_has_one_snapshot_for_the_batch_not_its_parcels(): void
    {
        $batch = HubTransferBatch::create(['company_name' => 'Alpha', 'origin_hub_id' => $this->hub->id, 'destination_hub_id' => $this->hub->id,
            'from_hub' => 'Santa Cruz', 'from_province' => 'Laguna', 'to_hub' => 'Pila', 'to_province' => 'Laguna',
            'status' => 'in_transit', 'rider_id' => $this->rider->id, 'legacy_import' => false]);
        $this->finance->snapshotTransfer($batch);
        $this->finance->snapshotTransfer($batch);
        $this->assignment('hub_transfer');
        $this->assignment('hub_transfer');
        $this->assertDatabaseCount('logistics_compensations', 1);
        $this->assertDatabaseHas('logistics_compensations', ['source_type' => 'transfer_batch', 'source_id' => $batch->id, 'rider_id' => $this->rider->id, 'amount_cents' => 15000]);
    }

    public function test_reassignment_cancels_only_the_unearned_snapshot(): void
    {
        $assignment = $this->assignment();
        $next = $this->person('rider');
        $assignment->courier_id = $next->id;
        $assignment->save();
        $this->assertDatabaseHas('logistics_compensations', ['rider_id' => $this->rider->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('logistics_compensations', ['rider_id' => $next->id, 'status' => 'pending']);
        $assignment->courier_id = $this->rider->id;
        $assignment->save();
        $this->assertDatabaseCount('logistics_compensations', 2);
        $this->assertDatabaseHas('logistics_compensations', ['rider_id' => $this->rider->id, 'status' => 'pending']);
    }

    public function test_earned_assignment_cannot_be_reassigned_or_paid_twice(): void
    {
        $assignment = $this->assignment();
        DB::table('logistics_compensations')->update(['status' => 'earned']);
        $next = $this->person('rider');
        try {
            $assignment->courier_id = $next->id;
            $assignment->save();
            $this->fail('Earned reassignment must fail.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertEquals($this->rider->id, $assignment->fresh()->courier_id);
        $this->assertDatabaseCount('logistics_compensations', 1);
    }

    public function test_hub_staff_riders_and_cross_company_inputs_cannot_edit_company_rates(): void
    {
        $data = ['pickup' => '1', 'transfer' => '1', 'delivery' => '1', 'revision' => 1, 'company_name' => 'Beta'];
        $this->actingAs($this->person('staff'))->postJson('/logistics/finance/settings', $data)->assertForbidden();
        $this->actingAs($this->rider)->postJson('/logistics/finance/settings', $data)->assertRedirect();
        $this->actingAs($this->admin)->post('/logistics/finance/settings', $data)->assertRedirect(route('logistics.finance'));
        $this->assertEquals(100, $this->finance->settings('Alpha')->pickup_cents);
        $this->assertEquals(2500, $this->finance->settings('Beta')->pickup_cents);
        $this->postJson('/logistics/finance/settings', $data)->assertStatus(409);
    }

    public function test_invalid_amounts_and_nonfinancial_legacy_assignments_are_rejected_or_ignored(): void
    {
        foreach (['-1', '1.001', '1e3', 'NaN'] as $amount) {
            $this->actingAs($this->admin)->postJson('/logistics/finance/settings', ['pickup' => $amount, 'transfer' => '150', 'delivery' => '40', 'revision' => 1])->assertUnprocessable();
        }
        $shipment = Shipment::create(['logistics_company' => 'Alpha', 'shipping_status' => 'delivered']);
        DeliveryAssignment::create(['shipment_id' => $shipment->id, 'courier_id' => $this->rider->id, 'leg' => 'delivery', 'status' => 'delivered']);
        $this->assertDatabaseCount('logistics_compensations', 0);
    }

    public function test_database_constraint_prevents_duplicate_compensation(): void
    {
        $this->assignment();
        $row = (array) DB::table('logistics_compensations')->first();
        $row['id'] = (string) Str::uuid();
        $this->expectException(QueryException::class);
        DB::table('logistics_compensations')->insert($row);
    }

    public function test_stale_assignment_cannot_restore_the_previous_riders_snapshot(): void
    {
        $assignment = $this->assignment();
        $stale = $assignment->fresh();
        $next = $this->person('rider');
        $assignment->update(['courier_id' => $next->id]);
        try {
            $this->finance->snapshotAssignment($stale);
            $this->fail('Stale assignment must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertDatabaseHas('logistics_compensations', ['rider_id' => $next->id, 'status' => 'pending']);
        $this->assertDatabaseHas('logistics_compensations', ['rider_id' => $this->rider->id, 'status' => 'cancelled']);
    }

    public function test_finance_page_lists_only_own_company_snapshots(): void
    {
        $this->assignment();
        $other = $this->person('admin', 'Beta');
        $this->actingAs($other)->get('/logistics/finance')->assertOk()->assertViewHas('snapshots', fn ($rows) => $rows->isEmpty());
        $response=$this->actingAs($this->admin)->get('/logistics/finance');
        $response->assertOk()->assertSee('Assignment compensation')->assertViewHas('snapshots', fn ($rows) => $rows->count() === 1)
            ->assertDontSee('aria-label="Financial workspaces"',false)->assertSee('Reference: assignment rate snapshots')->assertSee('Compensation settings');
        foreach(['logistics.cash-desk','logistics.finance.payouts','logistics.finance.riders','logistics.finance.reconciliation'] as $route){
            $response->assertSee(route($route),false);
        }
    }
}
