<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffSalary
{
    private const TZ = 'Asia/Manila';

    public function installed(): bool
    {
        return Schema::hasTable('logistics_staff_salaries')
            && Schema::hasTable('logistics_staff_salary_periods')
            && Schema::hasTable('logistics_staff_destinations')
            && Schema::hasTable('logistics_staff_payouts')
            && Schema::hasColumn('logistics_staff_payouts', 'payment_date');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['salary' => $message]);
    }

    // ── Period helpers ────────────────────────────────────────────────────────

    /**
     * Returns the pay date for a given year/month/half in Asia/Manila.
     * Half 1 → 15th. Half 2: 30th, or the last day when earlier.
     */
    public function payDate(int $year, int $month, int $half): CarbonImmutable
    {
        $base = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TZ);
        return $half === 1
            ? $base->setDay(15)
            : $base->setDay(min(30, $base->daysInMonth));
    }

    /**
     * Returns [year, month, half] for the current or next upcoming period
     * based on Asia/Manila now.
     */
    public function currentPeriod(): array
    {
        $now = CarbonImmutable::now(self::TZ);
        $half = $now->day <= 15 ? 1 : 2;
        return [(int) $now->year, (int) $now->month, $half];
    }

    /** First half rounds down; second half receives the remaining centavo. */
    public function halfCents(int $monthlyCents, int $half = 1): int
    {
        return intdiv($monthlyCents, 2) + ($half === 2 ? $monthlyCents % 2 : 0);
    }

    // ── Actor guards ──────────────────────────────────────────────────────────

    public function admin(User $actor): User
    {
        abort_unless($this->installed(), 503, 'The staff salary migration is required.');
        return app(LogisticsFinance::class)->admin(
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail()
        );
    }

    public function staff(User $actor): User
    {
        abort_unless($this->installed(), 503, 'The staff salary migration is required.');
        $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        abort_unless(
            $actor->isHubStaff() && $actor->status === 'approved'
            && ! $actor->is_admin && filled($actor->business_name),
            403,
            'Only approved Hub Staff may access salary records.'
        );
        return $actor;
    }

    // ── Salary configuration ──────────────────────────────────────────────────

    /**
     * Admin sets or updates a staff member's monthly salary.
     * Supersedes the previous active rate; preserves history.
     */
    public function configure(User $actor, array $input): void
    {
        $d = Validator::make($input, [
            'staff_id'     => 'required|integer',
            'monthly'      => 'required|string',
            'revision'     => 'required|integer|min:0',
        ])->validate();

        $monthly = app(LogisticsFinance::class)->cents((string) $d['monthly']);
        if ($monthly <= 0) $this->fail('Monthly salary must be greater than zero.');

        DB::transaction(function () use ($actor, $d, $monthly) {
            $actor = $this->admin($actor);

            $member = User::whereKey($d['staff_id'])->lockForUpdate()->firstOrFail();
            abort_unless(
                $member->isHubStaff() && $member->status === 'approved'
                && $member->business_name === $actor->business_name,
                403, 'Staff member not found in your company.'
            );

            // Optimistic lock: revision = count of existing salary rows for this staff.
            $existing = DB::table('logistics_staff_salaries')
                ->where('staff_id', $member->id)
                ->lockForUpdate()->get();

            if ((int) $d['revision'] !== $existing->count()) {
                $this->fail('Salary configuration changed. Refresh before saving.');
            }

            $now = now();

            // Supersede the current active rate.
            DB::table('logistics_staff_salaries')
                ->where('staff_id', $member->id)
                ->whereNull('superseded_at')
                ->update(['superseded_at' => $now]);

            $id = (string) Str::uuid();
            DB::table('logistics_staff_salaries')->insert([
                'id'             => $id,
                'company_name'   => $actor->business_name,
                'staff_id'       => $member->id,
                'monthly_cents'  => $monthly,
                'revision'       => $existing->count() + 1,
                'set_by'         => $actor->id,
                'effective_from' => $now,
                'created_at'     => $now,
                'updated_at'     => $now,
                ...(app(HubStaffSalary::class)->installed() ? ['salary_source'=>'individual','period_effective'=>($existing->where('monthly_cents','>',0)->isEmpty()?app(HubStaffSalary::class)->periodStart():app(HubStaffSalary::class)->nextPeriod())->toDateString()] : []),
            ]);

            app(LogisticsFinance::class)->event(
                $actor->business_name, 'staff_salary_configured', 'staff_salary', $id,
                ['staff_id' => $member->id, 'monthly_cents' => $monthly, 'revision' => $existing->count() + 1],
                $actor->id
            );
        }, 3);
    }

    // ── Period generation ─────────────────────────────────────────────────────

    /**
     * Admin generates an elapsed or current salary period from its historical rate.
     * Idempotent: returns existing row if already generated.
     */
    public function generatePeriod(User $actor, array $input): object
    {
        $d = Validator::make($input, [
            'staff_id'     => 'required|integer',
            'period_year'  => 'required|integer|min:2020|max:2100',
            'period_month' => 'required|integer|min:1|max:12',
            'period_half'  => 'required|integer|in:1,2',
        ])->validate();

        return DB::transaction(function () use ($actor, $d) {
            $actor = $this->admin($actor);

            $member = User::whereKey($d['staff_id'])->lockForUpdate()->firstOrFail();
            abort_unless(
                $member->isHubStaff() && $member->status === 'approved'
                && $member->business_name === $actor->business_name,
                403, 'Staff member not found in your company.'
            );

            // Idempotent: return existing period if already generated.
            $existing = DB::table('logistics_staff_salary_periods')
                ->where('staff_id', $member->id)
                ->where('period_year', $d['period_year'])
                ->where('period_month', $d['period_month'])
                ->where('period_half', $d['period_half'])
                ->lockForUpdate()->first();

            if ($existing) {
                abort_unless($existing->company_name === $actor->business_name, 403, 'This period belongs to another company.');
                return $existing;
            }

            $terms = $this->periodTerms($member->id, $actor->business_name, $d);
            $salary = (object) ['id'=>$terms['salary_id'], 'monthly_cents'=>$terms['monthly_cents']];
            $payDate = CarbonImmutable::parse($terms['pay_date'], self::TZ);

            $id = (string) Str::uuid();
            DB::table('logistics_staff_salary_periods')->insert([
                'id'           => $id,
                'company_name' => $actor->business_name,
                'staff_id'     => $member->id,
                'salary_id'    => $salary->id,
                'period_year'  => (int) $d['period_year'],
                'period_month' => (int) $d['period_month'],
                'period_half'  => (int) $d['period_half'],
                'pay_date'     => $payDate->toDateString(),
                'amount_cents' => $this->halfCents((int) $salary->monthly_cents, (int) $d['period_half']),
                'status'       => 'pending',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            app(LogisticsFinance::class)->event(
                $actor->business_name, 'staff_period_generated', 'staff_salary_period', $id,
                ['staff_id' => $member->id, 'year' => $d['period_year'], 'month' => $d['period_month'],
                 'half' => $d['period_half'], 'amount_cents' => $this->halfCents((int) $salary->monthly_cents, (int) $d['period_half']),
                 'pay_date' => $payDate->toDateString()],
                $actor->id
            );

            return DB::table('logistics_staff_salary_periods')->where('id', $id)->first();
        }, 3);
    }

    // ── Staff GCash destination ───────────────────────────────────────────────

    public function periodTerms(int $staffId, string $company, array $d): array
    {
        $start = CarbonImmutable::create((int)$d['period_year'], (int)$d['period_month'], (int)$d['period_half'] === 1 ? 1 : 16, 0, 0, 0, self::TZ);
        if ($start->greaterThan(CarbonImmutable::now(self::TZ))) $this->fail('Future payroll periods cannot be generated yet.');
        $enhanced=app(HubStaffSalary::class)->installed();
        $first = DB::table('logistics_staff_salaries')->where('staff_id',$staffId)->where('company_name',$company)->orderBy('revision')->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->first();
        if (!$first) $this->fail('No salary rate is configured for this staff member.');
        $enabled = CarbonImmutable::parse($first->effective_from, config('app.timezone'))->setTimezone(self::TZ);
        if ($start->lessThan($enabled->startOfMonth()->setDay($enabled->day <= 15 ? 1 : 16))) $this->fail('Periods before salary enablement cannot be generated.');
        $salaryQuery=DB::table('logistics_staff_salaries')->where('staff_id',$staffId)->where('company_name',$company);
        if($enhanced) $salaryQuery->where(fn($q)=>$q->where('period_effective','<=',$start->toDateString())->orWhere(fn($q)=>$q->whereNull('period_effective')->where('effective_from','<=',$start->setTimezone(config('app.timezone'))->toDateTimeString())));
        else $salaryQuery->where('effective_from','<=',$start->setTimezone(config('app.timezone'))->toDateTimeString());
        $salary=$salaryQuery->orderByDesc('revision')->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->first();
        if(!$salary && (!$enhanced || !$first->period_effective)) $salary=$first;
        if(!$salary || (int)$salary->monthly_cents<=0) $this->fail('Missing Salary Configuration for this hub and payroll period.');
        return ['salary_id'=>$salary->id,'monthly_cents'=>(int)$salary->monthly_cents,
            'amount_cents'=>$this->halfCents((int)$salary->monthly_cents,(int)$d['period_half']),
            'pay_date'=>$this->payDate((int)$d['period_year'],(int)$d['period_month'],(int)$d['period_half'])->toDateString()];
    }

    public function destination(User $actor, array $input): void
    {
        Validator::make($input, ['confirmed' => 'required|accepted'])->validate();
        $data = app(LogisticsTreasury::class)->accountData($input);
        DB::transaction(function () use ($actor, $data) {
            $actor = $this->staff($actor);
            $id = (string) Str::uuid();
            DB::table('logistics_staff_destinations')->insert(
                $data + ['id' => $id, 'staff_id' => $actor->id, 'created_at' => now()]
            );
            app(LogisticsFinance::class)->event(
                $actor->business_name, 'staff_destination_added', 'staff_destination', $id,
                ['staff_id' => $actor->id], $actor->id
            );
        }, 3);
    }

    // ── Payroll funding ───────────────────────────────────────────────────────

    public function funding(User $actor, array $input, UploadedFile $file): void
    {
        $t = app(LogisticsTreasury::class);
        $data = $t->receiptData($input, $file)
            + Validator::make($input, ['account_id' => 'required|uuid', 'key' => 'required|uuid'])->validate();

        $amount = app(LogisticsCashDesk::class)->amount($data['amount']);
        if ($amount <= 0) $this->fail('Staff payroll funding must be positive.');

        DB::transaction(function () use ($actor, $data, $file, $amount, $t) {
            $actor = $this->admin($actor);
            $account = $t->account($data['account_id'], $actor->business_name);
            $t->requireGcash($account);

            abort_if(
                DB::table('logistics_treasury_movements')
                    ->where('source_type', 'staff_funding')
                    ->where('source_id', $data['key'])
                    ->lockForUpdate()->exists(),
                409, 'This payroll contribution was already recorded.'
            );

            $reference = $t->reference($account, $data['reference'], 'staff_funding', $data['key']);
            $t->evidence($actor, 'staff_funding', $data['key'], $file);
            $t->move($actor, $account, 'staff_payroll', $amount, 'staff_funding', $data['key'], $reference);

            app(LogisticsFinance::class)->event(
                $actor->business_name, 'staff_funding_verified', 'staff_funding', $data['key'],
                ['amount_cents' => $amount, 'account_id' => $account->id, 'reference' => $reference, 'notes' => $data['notes']],
                $actor->id
            );
        }, 3);
    }

    public function available(string $account, ?string $except = null): int
    {
        $reserved = DB::table('logistics_staff_payouts')
            ->where('treasury_account_id', $account)
            ->whereIn('status', ['prepared', 'verified'])
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->lockForUpdate()->get(['amount_cents'])->sum('amount_cents');

        return app(LogisticsTreasury::class)->balance($account, 'staff_payroll') - (int) $reserved;
    }

    // ── Prepare payout ────────────────────────────────────────────────────────

    public function prepare(User $actor, array $input): void
    {
        $d = Validator::make($input, [
            'period_id'      => 'required|uuid',
            'account_id'     => 'required|uuid',
            'destination_id' => 'required|uuid',
        ])->validate();

        DB::transaction(function () use ($actor, $d) {
            $t = app(LogisticsTreasury::class);
            $actor = $this->admin($actor);

            $period = DB::table('logistics_staff_salary_periods')
                ->where('id', $d['period_id'])
                ->where('company_name', $actor->business_name)
                ->lockForUpdate()->firstOrFail();

            // Check for an existing non-cancelled payout first so the duplicate
            // guard (409) fires before the period-status check (422).
            $old = DB::table('logistics_staff_payouts')
                ->where('period_id', $period->id)
                ->lockForUpdate()->first();

            if ($old && $old->status !== 'cancelled') abort(409, 'This salary period already has a payout.');

            abort_unless(in_array($period->status, ['pending']), 422,
                'Only a pending salary period can be prepared for payment.');
            abort_unless((int) $period->amount_cents > 0, 422,
                'Zero-amount salary periods cannot be paid.');

            $member = User::whereKey($period->staff_id)->lockForUpdate()->firstOrFail();
            abort_unless($member->isHubStaff() && $member->status === 'approved' && $member->business_name === $actor->business_name, 403);
            $account = $t->account($d['account_id'], $actor->business_name);
            $t->requireGcash($account);

            $destination = DB::table('logistics_staff_destinations')
                ->where('id', $d['destination_id'])
                ->where('staff_id', $period->staff_id)
                ->lockForUpdate()->firstOrFail();
            $t->requireGcash($destination);

            if ($this->available($account->id) < (int) $period->amount_cents) {
                $this->fail('Insufficient separately verified staff-payroll funds.');
            }

            $id = $old?->id ?? (string) Str::uuid();
            $v = [
                'company_name'        => $period->company_name,
                'period_id'           => $period->id,
                'staff_id'            => $period->staff_id,
                'treasury_account_id' => $account->id,
                'destination_id'      => $destination->id,
                'amount_cents'        => $period->amount_cents,
                'status'              => 'prepared',
                'revision'            => ($old?->revision ?? 0) + 1,
                'prepared_by'         => $actor->id,
                'verified_by'         => null,
                'verified_at'         => null,
                'hold_reason'         => null,
                'notes'               => null,
                'updated_at'          => now(),
            ];

            if ($old) {
                DB::table('logistics_staff_payouts')->where('id', $id)->update($v);
            } else {
                DB::table('logistics_staff_payouts')->insert($v + ['id' => $id, 'created_at' => now()]);
            }

            DB::table('logistics_staff_salary_periods')
                ->where('id', $period->id)
                ->update(['status' => 'prepared', 'updated_at' => now()]);

            app(LogisticsFinance::class)->event(
                $actor->business_name, 'staff_payout_prepared', 'staff_payout', $id,
                ['period_id' => $period->id, 'amount_cents' => (int) $period->amount_cents,
                 'account_id' => $account->id, 'destination_id' => $destination->id],
                $actor->id
            );
        }, 3);
    }

    // ── Review actions (verify / hold / release / cancel) ────────────────────

    private function locked(User $actor, string $id, int $revision): array
    {
        $t = app(LogisticsTreasury::class);
        $actor = $this->admin($actor);

        $p = DB::table('logistics_staff_payouts')
            ->where('id', $id)
            ->where('company_name', $actor->business_name)
            ->lockForUpdate()->firstOrFail();

        abort_unless((int) $p->revision === $revision, 409,
            'Payout changed. Refresh before continuing.');

        $account = $t->account($p->treasury_account_id, $actor->business_name);

        return [$actor, $account, $p];
    }

    private function recheck(object $p): void
    {
        $member = User::whereKey($p->staff_id)->lockForUpdate()->firstOrFail();
        abort_unless($member->isHubStaff() && $member->status === 'approved' && $member->business_name === $p->company_name, 403);
        $t = app(LogisticsTreasury::class);
        $t->requireGcash($t->account($p->treasury_account_id, $p->company_name));
        $t->requireGcash(
            DB::table('logistics_staff_destinations')
                ->where('id', $p->destination_id)->firstOrFail()
        );
        if ($p->hold_reason) $this->fail('Release the documented hold before continuing.');
        if ($this->available($p->treasury_account_id, $p->id) < (int) $p->amount_cents) {
            $this->fail('Verified staff-payroll funds are unavailable.');
        }
    }

    public function action(User $actor, string $id, array $input): void
    {
        $d = Validator::make($input, [
            'revision' => 'required|integer|min:1',
            'action'   => 'required|in:verify,hold,release,cancel',
            'notes'    => 'required|string|min:3|max:2000',
        ])->validate();

        DB::transaction(function () use ($actor, $id, $d) {
            [$actor, $account, $p] = $this->locked($actor, $id, (int) $d['revision']);
            abort_unless(in_array($p->status, ['prepared', 'verified']), 409);

            $v = ['revision' => $p->revision + 1, 'updated_at' => now()];

            if ($d['action'] === 'verify') {
                abort_unless($p->status === 'prepared', 409);
                $this->recheck($p);
                $v += ['status' => 'verified', 'verified_by' => $actor->id, 'verified_at' => now()];
            } elseif ($d['action'] === 'hold') {
                $v['hold_reason'] = $d['notes'];
            } elseif ($d['action'] === 'release') {
                $v['hold_reason'] = null;
            } else {
                // cancel
                $v += ['status' => 'cancelled', 'notes' => $d['notes']];
                DB::table('logistics_staff_salary_periods')
                    ->where('id', $p->period_id)
                    ->update(['status' => 'pending', 'updated_at' => now()]);
            }

            DB::table('logistics_staff_payouts')->where('id', $id)->update($v);

            app(LogisticsFinance::class)->event(
                $actor->business_name, 'staff_payout_' . $d['action'], 'staff_payout', $id,
                ['notes' => $d['notes'], 'revision' => $v['revision']], $actor->id
            );
        }, 3);
    }

    // ── Record actual payment ─────────────────────────────────────────────────

    public function pay(User $actor, string $id, array $input, UploadedFile $file): void
    {
        $d = Validator::make($input, [
            'revision'  => 'required|integer|min:1',
            'amount'    => 'required|string',
            'reference' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{6,64}$/D'],
            'sent'      => 'required|accepted',
            'payment_date' => 'required|date_format:Y-m-d',
            'notes'     => 'required|string|min:3|max:2000',
        ])->validate();

        $amount = app(LogisticsCashDesk::class)->amount($d['amount']);
        $paymentDate = CarbonImmutable::parse($d['payment_date'], self::TZ);
        if ($paymentDate->greaterThan(CarbonImmutable::now(self::TZ)->endOfDay())) $this->fail('Payment date cannot be in the future.');

        DB::transaction(function () use ($actor, $id, $d, $file, $amount, $paymentDate) {
            [$actor, $account, $p] = $this->locked($actor, $id, (int) $d['revision']);
            abort_unless($p->status === 'verified', 409, 'Verify the payout instruction first.');
            $this->recheck($p);
            if ($paymentDate->toDateString() < CarbonImmutable::parse($p->created_at, config('app.timezone'))->setTimezone(self::TZ)->toDateString()) $this->fail('Payment date cannot precede its prepared instruction.');

            $t = app(LogisticsTreasury::class);
            if ($amount !== (int) $p->amount_cents) {
                $this->fail('Confirm the exact snapshotted salary amount.');
            }

            $destination = DB::table('logistics_staff_destinations')
                ->where('id', $p->destination_id)
                ->where('staff_id', $p->staff_id)
                ->lockForUpdate()->firstOrFail();

            $reference = $t->reference($account, $d['reference'], 'staff_payout', $id);
            $t->reference($destination, $d['reference'], 'staff_payout', $id);
            $t->evidence($actor, 'staff_payout', $id, $file);
            $t->move($actor, $account, 'staff_payroll', -$amount, 'staff_payout', $id, $reference);

            DB::table('logistics_staff_payouts')->where('id', $id)->update([
                'status'    => 'paid',
                'reference' => $reference,
                'paid_by'   => $actor->id,
                'paid_at'   => now(),
                'payment_date' => $d['payment_date'],
                'notes'     => $d['notes'],
                'revision'  => $p->revision + 1,
                'updated_at' => now(),
            ]);

            DB::table('logistics_staff_salary_periods')
                ->where('id', $p->period_id)
                ->update(['status' => 'paid', 'updated_at' => now()]);

            app(LogisticsFinance::class)->event(
                $actor->business_name, 'staff_payout_paid', 'staff_payout', $id,
                ['amount_cents' => $amount, 'reference' => $reference,
                 'notes' => $d['notes'], 'payment_date' => $d['payment_date'], 'destination_id' => $p->destination_id],
                $actor->id
            );
        }, 3);
    }

    // ── Hub Staff acknowledgement ─────────────────────────────────────────────

    public function acknowledge(User $actor, string $id): void
    {
        DB::transaction(function () use ($actor, $id) {
            $actor = $this->staff($actor);
            $p = DB::table('logistics_staff_payouts')
                ->where('id', $id)
                ->where('staff_id', $actor->id)
                ->where('company_name', $actor->business_name)
                ->lockForUpdate()->firstOrFail();

            abort_unless($p->status === 'paid', 422,
                'Only actual recorded payments can be acknowledged.');

            if ($p->acknowledged_at) return;

            DB::table('logistics_staff_payouts')
                ->where('id', $id)
                ->update(['acknowledged_at' => now(), 'updated_at' => now()]);

            app(LogisticsFinance::class)->event(
                $p->company_name, 'staff_payout_acknowledged', 'staff_payout', $id,
                ['staff_id' => $actor->id], $actor->id
            );
        }, 3);
    }
}
