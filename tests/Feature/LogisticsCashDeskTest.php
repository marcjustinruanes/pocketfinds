<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\User;
use App\Services\LogisticsCashDesk;
use App\Services\LogisticsFinance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class LogisticsCashDeskTest extends TestCase
{
    use WorkflowSchema;

    protected User $rider;
    protected User $staff;
    protected User $admin;
    protected LogisticsHub $hub;
    protected LogisticsHub $otherHub;
    protected object $collection;
    protected LogisticsCashDesk $desk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        foreach (['2026_10_28_000001_add_logistics_financial_foundation.php', '2026_10_28_000002_add_logistics_financial_transactions.php', '2026_10_29_000001_add_logistics_cash_desk.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->hub = LogisticsHub::create(['company_name' => 'Alpha', 'municipality' => 'Santa Cruz', 'province' => 'Laguna', 'is_operational' => true]);
        $this->otherHub = LogisticsHub::create(['company_name' => 'Alpha', 'municipality' => 'Manila', 'province' => 'Metro Manila', 'is_operational' => true]);
        $this->rider = $this->person('rider');
        $this->staff = $this->person('hub_staff');
        $this->admin = $this->person('admin');
        $this->collection = $this->collection();
        $this->desk = app(LogisticsCashDesk::class);
    }

    protected function person(string $role, string $company = 'Alpha', ?int $hub = null): User
    {
        if ($company !== 'Alpha' && $hub === null) {
            $hub = LogisticsHub::create(['company_name' => $company, 'municipality' => 'Other Town', 'province' => 'Other Province', 'is_operational' => true])->id;
        }
        return User::create(['username' => (string) Str::uuid(), 'account_type' => $role === 'rider' ? 'rider' : 'logistics',
            'business_name' => $company, 'status' => 'approved', 'is_admin' => false, 'is_logistics' => $role !== 'rider',
            'logistics_role' => $role === 'rider' ? null : $role, 'logistics_hub_id' => $hub ?? $this->hub->id,
            'given_names' => 'Cash', 'last_name' => 'Test']);
    }

    protected function collection(?User $rider = null, ?int $hub = null): object
    {
        $rider ??= $this->rider;
        $id = (string) Str::uuid();
        DB::table('logistics_financial_transactions')->insert(['id' => $id, 'company_name' => $rider->business_name,
            'kind' => 'cod_collection', 'actor_id' => $rider->id, 'hub_id' => $hub ?? $this->hub->id, 'amount_cents' => 10000,
            'status' => 'recorded', 'verification_state' => 'pending', 'currency' => 'PHP', 'idempotency_key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', $id), 'details' => '{}', 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('logistics_financial_transactions')->where('id', $id)->first();
    }

    protected function submit(string $amount = '100.00'): object
    {
        return $this->desk->submit($this->rider, ['key' => (string) Str::uuid(), 'allocations' => [$this->collection->id => $amount]]);
    }

    protected function countCash(object $h, string $counted = '100.00', string $allocated = '100.00'): void
    {
        $this->desk->count($this->staff, $h->id, ['counted' => $counted, 'allocations' => [$this->collection->id => $allocated], 'notes' => 'Counted in person']);
    }

    protected function denied(callable $callback, int $status): void
    {
        try { $callback(); $this->fail('Expected operation to be denied.'); }
        catch (HttpException $e) { $this->assertSame($status, $e->getStatusCode()); }
        catch (\Illuminate\Database\RecordNotFoundException $e) { $this->assertSame(404, $status); }
    }

    public function test_submission_reserves_cash_and_count_verifies_once(): void
    {
        $input = ['key' => (string) Str::uuid(), 'allocations' => [$this->collection->id => '100.00']];
        $h = $this->desk->submit($this->rider, $input);
        $this->assertSame($h->id, $this->desk->submit($this->rider, $input)->id);
        $this->assertSame(0, $this->desk->custody($this->hub->id));
        $this->assertSame(10000, $this->desk->balances($this->collection)['reserved']);
        $this->countCash($h); $this->countCash($h);
        $this->assertSame(10000, $this->desk->custody($this->hub->id));
        $this->assertDatabaseCount('logistics_cash_movements', 1);
        $this->assertDatabaseHas('logistics_financial_transactions', ['id' => $this->collection->id, 'verification_state' => 'verified']);
        $this->assertSame(0, $this->desk->balances($this->collection)['outstanding']);
    }

    public function test_partial_remittance_leaves_balance_for_later_handover(): void
    {
        $h = $this->submit('40.00'); $this->countCash($h, '40.00', '40.00');
        $this->assertSame(6000, $this->desk->balances($this->collection)['available']);
        $this->assertDatabaseHas('logistics_financial_transactions', ['id' => $this->collection->id, 'verification_state' => 'partial']);
        $h2 = $this->submit('60.00'); $this->countCash($h2, '60.00', '60.00');
        $this->assertSame(10000, $this->desk->custody($this->hub->id));
        $this->assertSame(0, $this->desk->balances($this->collection)['outstanding']);
    }

    public function test_shortage_is_documented_and_does_not_forgive_outstanding_cash(): void
    {
        $h = $this->submit(); $this->countCash($h, '80.00', '80.00');
        $this->assertDatabaseHas('logistics_cash_handovers', ['id' => $h->id, 'status' => 'discrepancy']);
        $this->assertSame(8000, $this->desk->custody($this->hub->id));
        $this->assertSame(2000, $this->desk->balances($this->collection)['available']);
        $this->desk->resolve($this->admin, $h->id, 'Rider will submit the remaining cash separately.');
        $this->assertSame(2000, $this->desk->balances($this->collection)['outstanding']);
        $this->assertSame(8000, $this->desk->custody($this->hub->id));
        $h2 = $this->submit('20.00'); $this->countCash($h2, '20.00', '20.00');
        $this->assertSame(0, $this->desk->balances($this->collection)['outstanding']);
    }

    public function test_overage_increases_custody_without_increasing_verified_collection(): void
    {
        $h = $this->submit(); $this->countCash($h, '110.00', '100.00');
        $this->assertSame(11000, $this->desk->custody($this->hub->id));
        $this->assertSame(10000, $this->desk->balances($this->collection)['verified']);
        $this->assertDatabaseHas('logistics_cash_handovers', ['id' => $h->id, 'status' => 'discrepancy']);
    }

    public function test_duplicate_and_excess_allocations_cannot_reserve_cash_twice(): void
    {
        $this->submit();
        $this->denied(fn () => $this->submit(), 409);
        $this->assertDatabaseCount('logistics_cash_handovers', 1);
        $this->assertDatabaseCount('logistics_cash_allocations', 1);
    }

    public function test_same_key_with_changed_amount_is_rejected(): void
    {
        $key = (string) Str::uuid();
        $this->desk->submit($this->rider, ['key' => $key, 'allocations' => [$this->collection->id => '40']]);
        $this->denied(fn () => $this->desk->submit($this->rider, ['key' => $key, 'allocations' => [$this->collection->id => '50']]), 409);
    }

    public function test_only_assigned_hub_staff_may_count_cash(): void
    {
        $h = $this->submit();
        foreach ([$this->admin, $this->rider, $this->person('hub_staff', 'Alpha', $this->otherHub->id)] as $actor) {
            $this->denied(fn () => $this->desk->count($actor, $h->id, ['counted' => '100', 'allocations' => [$this->collection->id => '100']]), $actor->logistics_hub_id === $this->otherHub->id ? 404 : 403);
        }
        $this->assertSame(0, $this->desk->custody($this->hub->id));
    }

    public function test_other_rider_and_company_collections_cannot_be_submitted(): void
    {
        $foreign = $this->collection($this->person('rider'));
        $this->denied(fn () => $this->desk->submit($this->rider, ['key' => (string) Str::uuid(), 'allocations' => [$foreign->id => '100']]), 403);
        $other = $this->person('rider', 'Other');
        $this->denied(fn () => $this->desk->submit($other, ['key' => (string) Str::uuid(), 'allocations' => [$this->collection->id => '100']]), 403);
    }

    public function test_invalid_count_allocations_roll_back_every_financial_change(): void
    {
        $h = $this->submit();
        $this->denied(fn () => $this->countCash($h, '80', '100'), 422);
        $this->denied(fn () => $this->countCash($h, '120', '120'), 422);
        $this->denied(fn () => $this->desk->count($this->staff, $h->id, ['counted' => '100', 'allocations' => [(string) Str::uuid() => '100']]), 422);
        $this->denied(fn () => $this->desk->count($this->staff, $h->id, ['counted' => '90', 'allocations' => [$this->collection->id => '90']]), 422);
        $this->assertDatabaseCount('logistics_cash_movements', 0);
        $this->assertSame(10000, $this->desk->balances($this->collection)['reserved']);
    }

    protected function transferData(string $amount = '70'): array
    {
        return ['key' => (string) Str::uuid(), 'hub_id' => $this->hub->id, 'destination_hub_id' => $this->otherHub->id, 'amount' => $amount, 'notes' => 'Sealed bag 001; authorized hub transfer'];
    }

    public function test_transfer_moves_custody_only_on_release_and_destination_confirmation(): void
    {
        $h = $this->submit(); $this->countCash($h);
        $data = $this->transferData(); $t = $this->desk->authorizeTransfer($this->admin, $data);
        $this->assertSame($t->id, $this->desk->authorizeTransfer($this->admin, $data)->id);
        $this->assertSame(10000, $this->desk->custody($this->hub->id));
        $input = ['counted' => '70', 'notes' => 'Sealed bag confirmed'];
        $this->desk->transfer($this->staff, $t->id, 'dispatch', $input);
        $this->desk->transfer($this->staff, $t->id, 'dispatch', $input);
        $this->assertSame(3000, $this->desk->custody($this->hub->id));
        $this->assertSame(0, $this->desk->custody($this->otherHub->id));
        $destination = $this->person('hub_staff', 'Alpha', $this->otherHub->id);
        $this->desk->transfer($destination, $t->id, 'receive', $input);
        $this->desk->transfer($destination, $t->id, 'receive', $input);
        $this->assertSame(7000, $this->desk->custody($this->otherHub->id));
        $this->assertDatabaseCount('logistics_cash_movements', 3);
        $this->assertSame(10000, $this->desk->balances($this->collection)['verified']);
    }

    public function test_transfer_shortage_records_actual_custody_and_discrepancy(): void
    {
        $h = $this->submit(); $this->countCash($h);
        $t = $this->desk->authorizeTransfer($this->admin, $this->transferData());
        $this->desk->transfer($this->staff, $t->id, 'dispatch', ['counted' => '70', 'notes' => 'Released']);
        $destination = $this->person('hub_staff', 'Alpha', $this->otherHub->id);
        $this->desk->transfer($destination, $t->id, 'receive', ['counted' => '60', 'notes' => 'Short by PHP 10; admin investigation required']);
        $this->assertSame(6000, $this->desk->custody($this->otherHub->id));
        $this->assertDatabaseHas('logistics_cash_handovers', ['id' => $t->id, 'status' => 'discrepancy']);
    }

    public function test_transfers_enforce_authorization_hub_scope_and_available_cash(): void
    {
        $this->denied(fn () => $this->desk->authorizeTransfer($this->staff, $this->transferData()), 403);
        $t = $this->desk->authorizeTransfer($this->admin, $this->transferData());
        $destination = $this->person('hub_staff', 'Alpha', $this->otherHub->id);
        $this->denied(fn () => $this->desk->transfer($destination, $t->id, 'receive', ['counted' => '70', 'notes' => 'Early receipt']), 409);
        $this->denied(fn () => $this->desk->transfer($destination, $t->id, 'dispatch', ['counted' => '70', 'notes' => 'Wrong hub']), 403);
        $this->denied(fn () => $this->desk->transfer($this->staff, $t->id, 'dispatch', ['counted' => '70', 'notes' => 'No funds']), 422);
        $this->assertDatabaseCount('logistics_cash_movements', 0);
    }

    public function test_cash_desk_pages_are_scoped_and_render_all_three_roles(): void
    {
        $this->submit();
        $this->actingAs($this->rider)->get('/rider/remittances')->assertOk()->assertSee('Submit selected amounts')->assertDontSee('Receive & Count Cash');
        $this->actingAs($this->staff)->get('/logistics/cash-desk')->assertOk()->assertSee('Receive & Count Cash')->assertDontSee('Authorize hub cash transfer');
        $this->actingAs($this->admin)->get('/logistics/cash-desk')->assertOk()->assertSee('Authorize hub cash transfer')->assertDontSee('Receive & Count Cash');
        $outsider = $this->person('hub_staff', 'Alpha', $this->otherHub->id);
        $this->actingAs($outsider)->get('/logistics/cash-desk')->assertOk()->assertDontSee($this->collection->id);
    }

    public function test_cutoff_defaults_to_manila_end_of_day_and_is_admin_configurable(): void
    {
        $settings = app(LogisticsFinance::class)->settings('Alpha');
        $this->assertSame('23:59', $settings->remittance_cutoff);
        $this->collection->recorded_at = '2026-10-29 08:00:00';
        $this->assertSame('2026-10-29 23:59 +08:00', $this->desk->dueAt($this->collection)->format('Y-m-d H:i P'));
        $this->desk->cutoff($this->admin, ['cutoff' => '18:00', 'revision' => $settings->revision]);
        $this->assertSame('18:00', $this->desk->dueAt($this->collection)->format('H:i'));
        $this->denied(fn () => $this->desk->cutoff($this->staff, ['cutoff' => '17:00', 'revision' => 2]), 403);
        $this->denied(fn () => $this->desk->cutoff($this->admin, ['cutoff' => '17:00', 'revision' => 1]), 409);
    }

    public function test_cash_desk_explains_reservations_and_lists_only_operational_company_transfer_hubs(): void
    {
        $this->submit('60.00');
        LogisticsHub::create(['company_name'=>'Alpha', 'municipality'=>'Closed Hub', 'province'=>'Laguna', 'is_operational'=>false]);
        LogisticsHub::create(['company_name'=>'Foreign', 'municipality'=>'Foreign Hub', 'province'=>'Laguna', 'is_operational'=>true]);
        $response = $this->actingAs($this->admin)->get('/logistics/cash-desk');
        $response->assertOk()->assertSee('Awaiting Hub Verification')->assertSee('Available to submit')
            ->assertSee('60.00')->assertSee('40.00')->assertSee('data-on-hand="0"', false)
            ->assertSee('Choose origin hub')->assertSee('Inactive hub')->assertDontSee('Foreign Hub')
            ->assertDontSee('Receive & Count Cash')->assertSee('logistics-cash-desk.js');
        preg_match('/<select id="cash-hub_id".*?<\/select>/s', $response->getContent(), $options);
        $this->assertStringNotContainsString('Closed Hub', $options[0]);
        $this->assertStringContainsString('Santa Cruz', $options[0]);
        $this->assertSame(0, $this->desk->custody($this->hub->id));
    }

    public function test_invalid_cutoff_keeps_setting_and_audit_unchanged(): void
    {
        $settings = app(LogisticsFinance::class)->settings('Alpha');
        $events = DB::table('logistics_financial_events')->count();
        $this->actingAs($this->admin)->from('/logistics/cash-desk')
            ->post('/logistics/cash-desk/cutoff', ['cutoff'=>'25:70','revision'=>$settings->revision])
            ->assertRedirect('/logistics/cash-desk')->assertSessionHasErrors('cutoff');
        $this->assertSame('23:59', DB::table('logistics_finance_settings')->where('company_name','Alpha')->value('remittance_cutoff'));
        $this->assertSame($events, DB::table('logistics_financial_events')->count());
    }

    public function test_only_company_admin_can_resolve_discrepancies(): void
    {
        $h = $this->submit(); $this->countCash($h, '90', '90');
        $this->denied(fn () => $this->desk->resolve($this->staff, $h->id, 'Investigation'), 403);
        $this->denied(fn () => $this->desk->resolve($this->person('admin', 'Other'), $h->id, 'Investigation'), 404);
        $this->assertNull(DB::table('logistics_cash_handovers')->where('id', $h->id)->value('resolved_at'));
    }

    public function test_audit_failure_rolls_back_count_and_custody(): void
    {
        $h = $this->submit();
        DB::unprepared("CREATE TRIGGER fail_cash_audit BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'remittance_counted' BEGIN SELECT RAISE(ABORT, 'audit failed'); END");
        try { $this->countCash($h); $this->fail('Expected audit failure.'); } catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('audit failed', $e->getMessage()); }
        $this->assertDatabaseCount('logistics_cash_movements', 0);
        $this->assertDatabaseHas('logistics_cash_handovers', ['id' => $h->id, 'status' => 'submitted', 'counted_cents' => null]);
        $this->assertSame(10000, $this->desk->balances($this->collection)['reserved']);
    }

    public function test_withdrawal_releases_reservation_without_changing_cash_and_cannot_undo_count(): void
    {
        $h = $this->submit();
        $this->desk->withdraw($this->rider, $h->id); $this->desk->withdraw($this->rider, $h->id);
        $this->assertSame(10000, $this->desk->balances($this->collection)['available']);
        $this->assertSame(0, $this->desk->custody($this->hub->id));
        $h2 = $this->submit(); $this->countCash($h2);
        $this->denied(fn () => $this->desk->withdraw($this->rider, $h2->id), 409);
    }

    public function test_mixed_hubs_are_not_accepted_in_one_remittance(): void
    {
        $second = $this->collection(null, $this->otherHub->id);
        $this->denied(fn () => $this->desk->submit($this->rider, ['key' => (string) Str::uuid(), 'allocations' => [$this->collection->id => '100', $second->id => '100']]), 422);
        $this->assertDatabaseCount('logistics_cash_handovers', 0);
    }

    public function test_multiple_collection_shortage_requires_explicit_counted_allocation(): void
    {
        $second = $this->collection();
        $h = $this->desk->submit($this->rider, ['key' => (string) Str::uuid(), 'allocations' => [$this->collection->id => '100', $second->id => '100']]);
        $this->desk->count($this->staff, $h->id, ['counted' => '150', 'allocations' => [$this->collection->id => '100', $second->id => '50'], 'notes' => 'Short by PHP 50, allocated by receipt']);
        $this->assertSame(0, $this->desk->balances($this->collection)['outstanding']);
        $this->assertSame(5000, $this->desk->balances($second)['outstanding']);
        $this->assertSame(15000, $this->desk->custody($this->hub->id));
    }

    public function test_revoked_role_and_forged_company_hub_or_verified_amounts_are_not_trusted(): void
    {
        $this->actingAs($this->rider)->post('/rider/remittances', ['key' => (string) Str::uuid(), 'allocations' => [$this->collection->id => '100'],
            'hub_id' => $this->otherHub->id, 'company_name' => 'Other', 'verified_cents' => 10000, 'status' => 'verified'])->assertRedirect();
        $this->assertDatabaseHas('logistics_cash_handovers', ['hub_id' => $this->hub->id, 'company_name' => 'Alpha', 'status' => 'submitted']);
        $h = DB::table('logistics_cash_handovers')->first();
        DB::table('users')->where('id', $this->staff->id)->update(['status' => 'suspended']);
        $this->denied(fn () => $this->desk->count($this->staff, $h->id, ['counted' => '100', 'allocations' => [$this->collection->id => '100']]), 403);
        $this->assertSame(0, $this->desk->custody($this->hub->id));
    }

    public function test_transfer_overage_and_changed_repeat_do_not_duplicate_custody(): void
    {
        $h = $this->submit(); $this->countCash($h);
        $t = $this->desk->authorizeTransfer($this->admin, $this->transferData());
        $this->desk->transfer($this->staff, $t->id, 'dispatch', ['counted' => '70', 'notes' => 'Released']);
        $destination = $this->person('hub_staff', 'Alpha', $this->otherHub->id);
        $this->desk->transfer($destination, $t->id, 'receive', ['counted' => '80', 'notes' => 'Extra PHP 10 received; investigation required']);
        $this->denied(fn () => $this->desk->transfer($destination, $t->id, 'receive', ['counted' => '70', 'notes' => 'Changed count']), 409);
        $this->assertSame(8000, $this->desk->custody($this->otherHub->id));
        $this->assertDatabaseCount('logistics_cash_movements', 3);
    }

    public function test_cash_entry_errors_return_to_the_form_without_committing(): void
    {
        $h = $this->submit();
        $this->actingAs($this->staff)->from('/logistics/cash-desk')->post('/logistics/cash-desk/'.$h->id.'/count',
            ['counted' => '80', 'allocations' => [$this->collection->id => '100'], 'notes' => 'Short count'])
            ->assertRedirect('/logistics/cash-desk')->assertSessionHasErrors('cash');
        $this->assertDatabaseCount('logistics_cash_movements', 0);
    }

    public function test_overdue_flag_does_not_block_remittance_or_change_compensation(): void
    {
        DB::table('logistics_financial_transactions')->where('id', $this->collection->id)->update(['recorded_at' => now()->subDays(2)]);
        $this->actingAs($this->rider)->get('/rider/remittances')->assertOk()->assertSee('Overdue');
        $this->actingAs($this->rider)->post('/rider/remittances', ['key' => (string) Str::uuid(), 'allocations' => [$this->collection->id => '100']])->assertRedirect();
        $this->assertDatabaseCount('logistics_cash_handovers', 1);
        $this->assertDatabaseCount('logistics_compensations', 0);
    }
}
