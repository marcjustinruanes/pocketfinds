<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\User;
use App\Services\LogisticsFinance;
use App\Services\LogisticsTreasury;
use App\Services\StaffSalary;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class StaffSalaryTest extends TestCase
{
    use WorkflowSchema;

    protected User $admin;
    protected User $staff;
    protected User $otherStaff;
    protected LogisticsHub $hub;
    protected StaffSalary $flow;
    protected LogisticsTreasury $treasury;
    protected object $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00:00', 'Asia/Manila'));
        $this->createWorkflowSchema();

        foreach ([
            '2026_10_14_000004_add_gcash_purchases',
            '2026_10_16_000001_add_registration_business_details',
            '2026_10_16_000002_add_logistics_registration_details',
            '2026_10_17_000001_add_buyer_commerce_features',
            '2026_10_28_000001_add_logistics_financial_foundation',
            '2026_10_28_000002_add_logistics_financial_transactions',
            '2026_10_29_000001_add_logistics_cash_desk',
            '2026_10_29_000002_add_logistics_shipping_settlement',
            '2026_10_29_000003_add_cod_seller_payouts',
            '2026_10_29_000004_add_rider_compensation_payouts',
            '2026_10_30_000001_add_hub_staff_salary',
            '2026_10_30_000002_add_staff_salary_payment_date',
        ] as $file) {
            (require database_path('migrations/' . $file . '.php'))->up();
        }

        Storage::fake('local');

        $this->hub = LogisticsHub::create([
            'company_name' => 'Alpha', 'municipality' => 'Santa Cruz',
            'province' => 'Laguna', 'is_operational' => true,
        ]);

        $this->admin = $this->person('admin');
        $this->staff = $this->person('hub_staff');
        $this->otherStaff = $this->person('hub_staff');
        $this->flow = app(StaffSalary::class);
        $this->treasury = app(LogisticsTreasury::class);

        $this->treasury->createAccount($this->admin, [
            'channel' => 'gcash', 'institution' => 'GCash',
            'account_name' => 'Alpha Treasury', 'account_number' => '09123456789',
        ]);
        $this->account = DB::table('logistics_treasury_accounts')->first();
    }

    protected function person(string $role, string $company = 'Alpha', ?int $hub = null): User
    {
        if ($company !== 'Alpha' && $hub === null) {
            $hub = LogisticsHub::create([
                'company_name' => $company, 'municipality' => 'Other',
                'province' => 'Other', 'is_operational' => true,
            ])->id;
        }
        return User::create([
            'username'        => (string) Str::uuid(),
            'account_type'    => in_array($role, ['admin', 'hub_staff']) ? 'logistics' : $role,
            'business_name'   => $company,
            'status'          => 'approved',
            'is_admin'        => false,
            'is_logistics'    => in_array($role, ['admin', 'hub_staff']),
            'logistics_role'  => in_array($role, ['admin', 'hub_staff']) ? $role : null,
            'logistics_hub_id' => $hub ?? $this->hub->id,
            'given_names'     => 'Staff',
            'last_name'       => 'Test',
            'municipality'    => 'Santa Cruz',
            'province'        => 'Laguna',
            'region'          => 'Test Region',
        ]);
    }

    protected function proof(string $text): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('evidence.pdf', "%PDF-1.4\n" . $text);
    }

    protected function invalid(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    protected function configure(int $monthly = 30000): void
    {
        $this->flow->configure($this->admin, [
            'staff_id' => $this->staff->id,
            'monthly'  => number_format($monthly / 100, 2, '.', ''),
            'revision' => 0,
        ]);
    }

    protected function addDestination(): object
    {
        $this->flow->destination($this->staff, [
            'channel' => 'gcash', 'institution' => 'GCash',
            'account_name' => 'Staff Test', 'account_number' => '09554433221', 'confirmed' => '1',
        ]);
        return DB::table('logistics_staff_destinations')->where('staff_id', $this->staff->id)->first();
    }

    protected function fund(string $amount = '300', string $reference = 'STAFFUND001'): void
    {
        $this->flow->funding($this->admin, [
            'account_id' => $this->account->id,
            'key'        => (string) Str::uuid(),
            'amount'     => $amount,
            'reference'  => $reference,
            'notes'      => 'Staff payroll contribution verified',
            'received'   => '1',
        ], $this->proof($reference));
    }

    protected function generatePeriod(int $year = 2026, int $month = 10, int $half = 1): object
    {
        return $this->flow->generatePeriod($this->admin, [
            'staff_id'     => $this->staff->id,
            'period_year'  => $year,
            'period_month' => $month,
            'period_half'  => $half,
        ]);
    }

    protected function period(): object
    {
        return DB::table('logistics_staff_salary_periods')
            ->where('staff_id', $this->staff->id)->first();
    }

    protected function payout(): object
    {
        return DB::table('logistics_staff_payouts')
            ->where('staff_id', $this->staff->id)->first();
    }

    protected function prepare(): void
    {
        $dest = $this->addDestination();
        $period = $this->period();
        $this->flow->prepare($this->admin, [
            'period_id'      => $period->id,
            'account_id'     => $this->account->id,
            'destination_id' => $dest->id,
        ]);
    }

    protected function verify(): void
    {
        $p = $this->payout();
        $this->flow->action($this->admin, $p->id, [
            'revision' => $p->revision,
            'action'   => 'verify',
            'notes'    => 'Destination and funds reviewed',
        ]);
    }

    protected function pay(): void
    {
        $p = $this->payout();
        $this->flow->pay($this->admin, $p->id, [
            'revision'  => $p->revision,
            'amount'    => number_format((int) $p->amount_cents / 100, 2, '.', ''),
            'reference' => 'STAFFPAY001',
            'sent'      => '1',
            'payment_date' => '2026-10-01',
            'notes'     => 'Actual GCash transfer confirmed',
        ], $this->proof('Staff payment'));
    }

    // ── Period calculation tests ──────────────────────────────────────────────

    public function test_half_1_pay_date_is_15th(): void
    {
        $date = $this->flow->payDate(2026, 10, 1);
        $this->assertSame('2026-10-15', $date->toDateString());
        $this->assertSame('Asia/Manila', $date->tzName);
    }

    public function test_half_2_pay_date_is_30th_or_earlier_month_end(): void
    {
        $this->assertSame('2026-10-30', $this->flow->payDate(2026, 10, 2)->toDateString());
        $this->assertSame('2026-02-28', $this->flow->payDate(2026, 2, 2)->toDateString());
        $this->assertSame('2024-02-29', $this->flow->payDate(2024, 2, 2)->toDateString()); // leap year
        $this->assertSame('2026-04-30', $this->flow->payDate(2026, 4, 2)->toDateString());
        $this->assertSame('2026-11-30', $this->flow->payDate(2026, 11, 2)->toDateString());
    }

    public function test_half_cents_floors_odd_monthly(): void
    {
        $this->assertSame(15000, $this->flow->halfCents(30000));
        $this->assertSame(15000, $this->flow->halfCents(30001)); // floor
        $this->assertSame(7500, $this->flow->halfCents(15001));
    }

    // ── Salary configuration ──────────────────────────────────────────────────

    public function test_admin_can_configure_salary_and_history_is_preserved(): void
    {
        $this->configure(30000);
        $salary = DB::table('logistics_staff_salaries')->where('staff_id', $this->staff->id)->first();
        $this->assertSame(30000, (int) $salary->monthly_cents);
        $this->assertNull($salary->superseded_at);
        $this->assertSame(1, (int) $salary->revision);

        // Update rate — old row is superseded, new row is active.
        $this->flow->configure($this->admin, [
            'staff_id' => $this->staff->id,
            'monthly'  => '400.00',
            'revision' => 1,
        ]);
        $this->assertSame(2, DB::table('logistics_staff_salaries')->where('staff_id', $this->staff->id)->count());
        $active = DB::table('logistics_staff_salaries')->where('staff_id', $this->staff->id)->whereNull('superseded_at')->first();
        $this->assertSame(40000, (int) $active->monthly_cents);
        $this->assertSame(2, (int) $active->revision);
    }

    public function test_stale_revision_prevents_concurrent_salary_update(): void
    {
        $this->configure();
        $this->invalid(fn () => $this->flow->configure($this->admin, [
            'staff_id' => $this->staff->id, 'monthly' => '400.00', 'revision' => 0,
        ]));
        $this->assertSame(1, DB::table('logistics_staff_salaries')->count());
    }

    public function test_zero_salary_is_rejected(): void
    {
        $this->invalid(fn () => $this->flow->configure($this->admin, [
            'staff_id' => $this->staff->id, 'monthly' => '0.00', 'revision' => 0,
        ]));
    }

    public function test_hub_staff_cannot_configure_salary(): void
    {
        $this->actingAs($this->staff)
            ->post(route('logistics.finance.staff.configure'), [
                'staff_id' => $this->staff->id, 'monthly' => '300.00', 'revision' => 0,
            ])->assertForbidden();
    }

    // ── Period generation ─────────────────────────────────────────────────────

    public function test_period_generation_snapshots_active_rate(): void
    {
        $this->configure(30000);
        $period = $this->generatePeriod(2026, 10, 1);
        $this->assertSame(15000, (int) $period->amount_cents);
        $this->assertSame('2026-10-15', $period->pay_date);
        $this->assertSame('pending', $period->status);
    }

    public function test_period_generation_is_idempotent(): void
    {
        $this->configure();
        $this->generatePeriod(2026, 10, 1);
        $this->generatePeriod(2026, 10, 1); // second call returns existing
        $this->assertSame(1, DB::table('logistics_staff_salary_periods')->count());
    }

    public function test_duplicate_period_unique_constraint_prevents_two_rows(): void
    {
        $this->configure();
        $this->generatePeriod(2026, 10, 1);
        // Direct insert attempt must fail on the unique constraint.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('logistics_staff_salary_periods')->insert([
            'id' => (string) Str::uuid(), 'company_name' => 'Alpha',
            'staff_id' => $this->staff->id, 'salary_id' => DB::table('logistics_staff_salaries')->value('id'),
            'period_year' => 2026, 'period_month' => 10, 'period_half' => 1,
            'pay_date' => '2026-10-15', 'amount_cents' => 15000, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_rate_change_does_not_alter_existing_period_amount(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        // Change rate after period was generated.
        $this->flow->configure($this->admin, [
            'staff_id' => $this->staff->id, 'monthly' => '60000', 'revision' => 1,
        ]);
        $this->assertSame(15000, (int) $this->period()->amount_cents);
    }

    public function test_period_without_salary_rate_is_rejected(): void
    {
        $this->invalid(fn () => $this->generatePeriod(2026, 10, 1));
    }

    // ── Destination ───────────────────────────────────────────────────────────

    public function test_staff_can_add_gcash_destination(): void
    {
        $dest = $this->addDestination();
        $this->assertSame('gcash', $dest->channel);
        $this->assertSame('09554433221', $dest->account_number);
        $this->assertSame((int) $this->staff->id, (int) $dest->staff_id);
    }

    public function test_admin_cannot_add_staff_destination(): void
    {
        $this->actingAs($this->admin)
            ->post(route('logistics.my-salary.destination'), [
                'channel' => 'gcash', 'institution' => 'GCash',
                'account_name' => 'Admin Test', 'account_number' => '09123456789', 'confirmed' => '1',
            ])->assertForbidden();
    }

    // ── Funding ───────────────────────────────────────────────────────────────

    public function test_funding_credits_staff_payroll_bucket_only(): void
    {
        $this->fund('300.00', 'STAFFUND001');
        $this->assertSame(30000, $this->treasury->balance($this->account->id, 'staff_payroll'));
        $this->assertSame(0, $this->treasury->balance($this->account->id, 'rider_payroll'));
        $this->assertSame(0, $this->treasury->balance($this->account->id, 'cod'));
    }

    public function test_duplicate_funding_key_is_rejected(): void
    {
        $key = (string) Str::uuid();
        $data = ['account_id' => $this->account->id, 'key' => $key, 'amount' => '300.00',
            'reference' => 'STAFFUND002', 'notes' => 'First contribution', 'received' => '1'];
        $this->flow->funding($this->admin, $data, $this->proof('First'));
        $this->actingAs($this->admin)
            ->post(route('logistics.finance.staff.funding'), $data + ['proof' => $this->proof('Duplicate')])
            ->assertStatus(409);
        $this->assertSame(30000, $this->treasury->balance($this->account->id, 'staff_payroll'));
    }

    // ── Full payout workflow ──────────────────────────────────────────────────

    public function test_full_payout_workflow_and_acknowledgement(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        $this->fund('300.00');
        $this->prepare();

        $this->assertSame('prepared', $this->payout()->status);
        $this->assertSame('prepared', $this->period()->status);
        $this->assertSame(15000, $this->flow->available($this->account->id));

        $this->verify();
        $this->assertSame('verified', $this->payout()->status);

        $this->pay();
        $this->assertSame('paid', $this->payout()->status);
        $this->assertSame('paid', $this->period()->status);
        $this->assertSame(15000, $this->treasury->balance($this->account->id, 'staff_payroll'));

        $this->assertNull($this->payout()->acknowledged_at);
        $this->flow->acknowledge($this->staff, $this->payout()->id);
        $this->flow->acknowledge($this->staff, $this->payout()->id); // idempotent
        $this->assertNotNull($this->payout()->acknowledged_at);
    }

    public function test_staff_payroll_does_not_touch_rider_cod_or_seller_buckets(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        $this->fund('300.00');
        $this->prepare();
        $this->verify();
        $this->pay();

        $this->assertSame(0, $this->treasury->balance($this->account->id, 'cod'));
        $this->assertSame(0, $this->treasury->balance($this->account->id, 'rider_payroll'));
        $this->assertSame(0, $this->treasury->balance($this->account->id, 'adjustment'));
        $this->assertDatabaseCount('logistics_rider_payouts', 0);
        $this->assertDatabaseCount('logistics_seller_payouts', 0);
    }

    public function test_insufficient_funds_prevent_prepare(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        // No funding added.
        $this->invalid(fn () => $this->prepare());
        $this->assertDatabaseCount('logistics_staff_payouts', 0);
    }

    public function test_duplicate_payout_for_same_period_is_rejected(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        $this->fund('300.00');
        $this->prepare();

        $dest = DB::table('logistics_staff_destinations')->where('staff_id', $this->staff->id)->first();
        $this->actingAs($this->admin)
            ->post(route('logistics.finance.staff.prepare'), [
                'period_id'      => $this->period()->id,
                'account_id'     => $this->account->id,
                'destination_id' => $dest->id,
            ])->assertStatus(409);

        $this->assertSame(1, DB::table('logistics_staff_payouts')->count());
    }

    public function test_hold_prevents_payment_and_cancel_releases_reservation(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        $this->fund('300.00');
        $this->prepare();

        $this->flow->action($this->admin, $this->payout()->id, [
            'revision' => 1, 'action' => 'hold', 'notes' => 'Reviewing receiving account',
        ]);
        $this->invalid(fn () => $this->verify());
        $this->assertSame(15000, $this->flow->available($this->account->id));

        $this->flow->action($this->admin, $this->payout()->id, [
            'revision' => 2, 'action' => 'release', 'notes' => 'Account confirmed',
        ]);
        $this->verify();

        $this->flow->action($this->admin, $this->payout()->id, [
            'revision' => 4, 'action' => 'cancel', 'notes' => 'No transfer sent',
        ]);
        $this->assertSame(30000, $this->flow->available($this->account->id));
        $this->assertSame('pending', $this->period()->status);
    }

    public function test_exact_amount_required_for_payment(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        $this->fund('300.00');
        $this->prepare();
        $this->verify();

        foreach (['149.99', '150.01', '0'] as $wrong) {
            $this->invalid(fn () => $this->flow->pay($this->admin, $this->payout()->id, [
                'revision' => $this->payout()->revision, 'amount' => $wrong,
                'reference' => 'STAFFPAY001', 'sent' => '1', 'payment_date' => '2026-10-01', 'notes' => 'Wrong amount',
            ], $this->proof($wrong)));
        }
        $this->assertSame('verified', $this->payout()->status);
    }

    public function test_acknowledgement_before_payment_is_rejected(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        $this->fund('300.00');
        $this->prepare();

        $this->actingAs($this->staff)
            ->post(route('logistics.my-salary.acknowledge', $this->payout()->id), ['received' => '1'])
            ->assertStatus(422);
    }

    public function test_wrong_staff_cannot_acknowledge_another_members_payout(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        $this->fund('300.00');
        $this->prepare();
        $this->verify();
        $this->pay();

        $this->actingAs($this->otherStaff)
            ->post(route('logistics.my-salary.acknowledge', $this->payout()->id), ['received' => '1'])
            ->assertNotFound();
    }

    public function test_other_company_admin_cannot_access_salary_data(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        $betaAdmin = $this->person('admin', 'Beta');
        $this->actingAs($betaAdmin)->get(route('logistics.finance.staff'))->assertOk();
        // Beta admin sees no Alpha periods.
        $this->assertSame(0, DB::table('logistics_staff_salary_periods')
            ->where('company_name', 'Beta')->count());
    }

    public function test_evidence_accessible_by_staff_member_and_admin_not_others(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        $this->fund('300.00');
        $this->prepare();
        $this->verify();
        $this->pay();

        $evidenceId = DB::table('logistics_finance_evidence')
            ->where('source_type', 'staff_payout')->value('id');

        $this->actingAs($this->admin)->get(route('finance.evidence', $evidenceId))->assertOk();
        $this->actingAs($this->staff)->get(route('finance.evidence', $evidenceId))->assertOk();
        $this->actingAs($this->otherStaff)->get(route('finance.evidence', $evidenceId))->assertForbidden();
    }

    public function test_admin_pages_render_without_errors(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        $this->fund('300.00');
        $this->prepare();

        $this->actingAs($this->admin)
            ->get(route('logistics.finance.staff'))
            ->assertOk()
            ->assertSee('Hub Staff Salaries')
            ->assertSee('PHP 150.00')
            ->assertDontSee('@include');
    }

    public function test_staff_my_salary_page_renders(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        $this->addDestination();

        $this->actingAs($this->staff)
            ->get(route('logistics.my-salary'))
            ->assertOk()
            ->assertSee('My Salary')
            ->assertSee('PHP 300.00')
            ->assertSee('PHP 150.00')
            ->assertDontSee('@include');
    }

    public function test_audit_failure_rolls_back_payment_and_treasury_debit(): void
    {
        $this->configure(30000);
        $this->generatePeriod(2026, 10, 1);
        $this->fund('300.00');
        $this->prepare();
        $this->verify();

        DB::statement("CREATE TRIGGER fail_staff_audit BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'staff_payout_paid' BEGIN SELECT RAISE(ABORT, 'audit failed'); END");
        try {
            $this->pay();
            $this->fail('Expected audit failure');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('audit failed', $e->getMessage());
        }

        $this->assertSame('verified', $this->payout()->status);
        $this->assertSame(30000, $this->treasury->balance($this->account->id, 'staff_payroll'));
        $this->assertSame(0, DB::table('logistics_finance_evidence')->where('source_type', 'staff_payout')->count());
    }
    public function test_odd_centavo_installments_total_the_monthly_snapshot(): void
    {
        $this->configure(30001);
        $first = $this->generatePeriod();
        $this->travelTo(CarbonImmutable::parse('2026-10-16 09:00', 'Asia/Manila'));
        $second = $this->generatePeriod(2026, 10, 2);
        $this->assertSame(15000, (int) $first->amount_cents);
        $this->assertSame(15001, (int) $second->amount_cents);
        $this->assertSame(30001, (int) $first->amount_cents + (int) $second->amount_cents);
    }

    public function test_generation_rejects_pre_enablement_and_future_periods(): void
    {
        $this->configure();
        $this->invalid(fn () => $this->generatePeriod(2026, 9, 2));
        $this->invalid(fn () => $this->generatePeriod(2026, 10, 2));
        $this->assertDatabaseCount('logistics_staff_salary_periods', 0);
    }

    public function test_late_generation_uses_historical_rate_not_current_revision(): void
    {
        $this->configure(30001);
        $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00', 'Asia/Manila'));
        $this->flow->configure($this->admin, ['staff_id'=>$this->staff->id,'monthly'=>'500.00','revision'=>1]);
        $this->assertSame(15001, (int) $this->generatePeriod(2026, 10, 2)->amount_cents);
        $this->assertSame(15000, (int) $this->generatePeriod(2026, 11, 1)->amount_cents);
        $this->travelTo(CarbonImmutable::parse('2026-11-16 09:00', 'Asia/Manila'));
        $this->assertSame(25000, (int) $this->generatePeriod(2026, 11, 2)->amount_cents);
    }

    public function test_destination_confirmation_and_original_payment_destination_are_preserved(): void
    {
        $this->invalid(fn () => $this->flow->destination($this->staff, ['channel'=>'gcash','institution'=>'GCash','account_name'=>'Staff Test','account_number'=>'09554433221']));
        $this->configure(); $this->generatePeriod(); $this->fund(); $this->prepare();
        $original = $this->payout()->destination_id;
        $this->flow->destination($this->staff, ['channel'=>'gcash','institution'=>'GCash','account_name'=>'New Account','account_number'=>'09998887776','confirmed'=>'1']);
        $this->verify(); $this->pay();
        $this->assertSame($original, $this->payout()->destination_id);
        $this->assertSame('2026-10-01', $this->payout()->payment_date);
    }

    public function test_cancelled_period_can_be_prepared_again_from_the_admin_page(): void
    {
        $this->configure(); $this->generatePeriod(); $this->fund(); $this->prepare();
        $p=$this->payout();
        $this->flow->action($this->admin,$p->id,['revision'=>$p->revision,'action'=>'cancel','notes'=>'Not sent externally']);
        $this->actingAs($this->admin)->get(route('logistics.finance.staff'))->assertOk()->assertSee('Prepare payout');
        $this->prepare();
        $this->assertDatabaseCount('logistics_staff_payouts',1);
        $this->assertSame('prepared',$this->payout()->status);
    }

    public function test_reconciliation_recognizes_staff_funding_reservations_and_payment(): void
    {
        $this->configure(); $this->generatePeriod(); $this->fund(); $this->prepare();
        $report=app(\App\Services\LogisticsReconciliation::class)->snapshot($this->admin,'2026-10-01','2026-10-31');
        $this->assertSame(30000,$report['buckets']['staff_payroll']);
        $this->assertSame(15000,$report['reserved']['staff_payroll']);
        $this->verify(); $this->pay();
        $report=app(\App\Services\LogisticsReconciliation::class)->snapshot($this->admin,'2026-10-01','2026-10-31');
        $this->assertSame(15000,$report['buckets']['staff_payroll']);
        $this->assertSame(0,$report['reserved']['staff_payroll']);
        $this->assertCount(1,$report['rows']['staff_payouts']);
        $this->assertEmpty(collect($report['rows']['exceptions'])->whereIn('code',['unknown_bucket','movement_source','staff_payment']));
    }

    public function test_payment_date_cannot_be_missing_future_or_before_instruction(): void
    {
        $this->configure(); $this->generatePeriod(); $this->fund(); $this->prepare(); $this->verify();
        foreach ([null,'2026-10-02','2026-09-30'] as $date) {
            $this->invalid(fn () => $this->flow->pay($this->admin,$this->payout()->id,[
                'revision'=>$this->payout()->revision,'amount'=>'150.00','reference'=>'STAFFDATE001',
                'sent'=>'1','notes'=>'Date validation check','payment_date'=>$date,
            ],$this->proof('Date check')));
        }
        $this->assertSame('verified',$this->payout()->status);
        $this->assertSame(30000,$this->treasury->balance($this->account->id,'staff_payroll'));
    }

    public function test_salary_routes_and_changed_staff_status_are_guarded(): void
    {
        foreach ([$this->staff,$this->person('buyer'),$this->person('seller'),$this->person('rider')] as $actor) {
            $response=$this->actingAs($actor)->post(route('logistics.finance.staff.configure'),[
                'staff_id'=>$this->staff->id,'monthly'=>'500','revision'=>0,
            ]);
            if ($actor->isHubStaff()) $response->assertForbidden();
            else $response->assertRedirect(route('login'));
            $this->assertDatabaseCount('logistics_staff_salaries',0);
        }
        $this->configure(); $this->generatePeriod(); $this->fund(); $this->prepare(); $this->verify();
        DB::table('users')->where('id',$this->staff->id)->update(['status'=>'suspended']);
        try { $this->pay(); $this->fail('Suspended staff payment should require review'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        $this->assertSame('verified',$this->payout()->status);
        $this->actingAs($this->staff)->get(route('logistics.my-salary'))->assertForbidden();
    }

    public function test_existing_account_page_has_masked_own_salary_details(): void
    {
        $this->addDestination();
        $this->actingAs($this->staff)->get(route('logistics.account'))->assertOk()
            ->assertSee('Salary GCash account')->assertSee('*******3221')->assertDontSee('09554433221');
        $this->actingAs($this->otherStaff)->get(route('logistics.account'))->assertOk()->assertDontSee('*******3221');
    }

}
