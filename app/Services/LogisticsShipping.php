<?php

namespace App\Services;

use App\Models\GcashPurchase;
use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LogisticsShipping
{
    public function installed(): bool
    {
        return Schema::hasTable('logistics_shipping_payments') && Schema::hasColumn('logistics_financial_transactions', 'shipping_obligation_id');
    }

    private function fail(string $message, string $field = 'shipping'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function gcash(Order $order): bool
    {
        return filled($order->gcash_purchase_id) || strcasecmp(trim((string) $order->payment_method), 'GCash') === 0
            || ($order->payment_method_id && strcasecmp(trim((string) PaymentMethod::find($order->payment_method_id)?->name), 'GCash') === 0);
    }

    public function configure(User $actor, array $input): void
    {
        $actor = app(LogisticsFinance::class)->admin($actor);
        if (is_string($input['account_name'] ?? null)) $input['account_name'] = trim($input['account_name']);
        $data = Validator::make($input, ['revision' => 'required|integer|min:0', 'account_name' => 'required|string|min:2|max:150',
            'account_number' => ['required', 'regex:/^09[0-9]{9}$/D']])->validate();
        DB::transaction(function () use ($actor, $data) {
            $actor = app(LogisticsFinance::class)->admin(User::whereKey($actor->id)->lockForUpdate()->firstOrFail());
            app(LogisticsFinance::class)->settings($actor->business_name);
            DB::table('logistics_finance_settings')->where('company_name', $actor->business_name)->lockForUpdate()->first();
            $row = DB::table('logistics_shipping_accounts')->where('company_name', $actor->business_name)->lockForUpdate()->first();
            abort_unless((int) $data['revision'] === (int) ($row?->revision ?? 0), 409, 'Account settings changed. Refresh before saving.');
            DB::table('logistics_shipping_accounts')->updateOrInsert(['company_name' => $actor->business_name], [
                'account_name' => trim($data['account_name']), 'account_number' => $data['account_number'], 'revision' => ($row?->revision ?? 0) + 1,
                'updated_by' => $actor->id, 'created_at' => $row?->created_at ?? now(), 'updated_at' => now(),
            ]);
            $this->event($actor->business_name, 'shipping_account_updated', 'shipping_account', $actor->business_name,
                ['revision' => ($row?->revision ?? 0) + 1, 'existing_instructions_changed' => false], $actor->id);
        }, 3);
    }

    public function publish(User $actor, array $input): void
    {
        if (is_string($input['region'] ?? null)) $input['region'] = trim($input['region']);
        $data = Validator::make($input, ['region' => 'required|string|max:150', 'revision' => 'required|integer|min:0',
            'first_kg' => 'required|string', 'additional_kg' => 'required|string'])->validate();
        $first = app(LogisticsFinance::class)->cents($data['first_kg']);
        $additional = app(LogisticsFinance::class)->cents($data['additional_kg']);
        DB::transaction(function () use ($actor, $data, $first, $additional) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            app(LogisticsFinance::class)->admin($actor);
            app(LogisticsFinance::class)->settings($actor->business_name);
            DB::table('logistics_finance_settings')->where('company_name', $actor->business_name)->lockForUpdate()->first();
            $region = trim($data['region']);
            $before = DB::table('logistics_shipping_rates')->where('company_name', $actor->business_name)->where('region', $region)->orderByDesc('revision')->lockForUpdate()->first();
            abort_unless((int) $data['revision'] === (int) ($before?->revision ?? 0), 409, 'This regional rate changed. Refresh before publishing.');
            DB::table('logistics_shipping_rates')->where('company_name', $actor->business_name)->where('region', $region)->where('is_active', true)->update(['is_active' => false]);
            $id = (string) Str::uuid();
            DB::table('logistics_shipping_rates')->insert(['id' => $id, 'company_name' => $actor->business_name, 'region' => $region,
                'revision' => ($before?->revision ?? 0) + 1, 'first_kg_cents' => $first, 'additional_kg_cents' => $additional,
                'approved_by' => $actor->id, 'is_active' => true, 'created_at' => now()]);
            $this->event($actor->business_name, 'shipping_rate_approved', 'shipping_rate', $id,
                ['region' => $region, 'first_kg_cents' => $first, 'additional_kg_cents' => $additional, 'replaces' => $before?->id], $actor->id);
        }, 3);
    }

    private function kilograms(array $items): int
    {
        $grams = 0;
        foreach ($items as $item) {
            $weight = $item['weight_grams'] ?? null; $qty = $item['qty'] ?? null;
            if (! is_numeric($weight) || (float) $weight <= 0 || ! is_numeric($qty) || (int) $qty < 1 || (float) $qty !== (float) (int) $qty) {
                $this->fail('Shipping weight is missing or invalid in this order snapshot. Shipping review is required before carrier handoff.');
            }
            $grams += (float) $weight * (int) $qty;
        }
        if (! $items || ! is_finite((float) $grams) || $grams > 1000000000) $this->fail('Shipping weight requires review.');
        return max(1, (int) ceil($grams / 1000));
    }

    private function rate(string $company, string $region, bool $lock = false): ?object
    {
        return DB::table('logistics_shipping_rates')->where('company_name', $company)->where('region', trim($region))
            ->where('is_active', true)->when($lock, fn ($q) => $q->lockForUpdate())->first();
    }

    public function preflight(User $seller, array $address, array $quote, bool $preview): ?string
    {
        if (! $this->installed() || ($quote['shipping_pending'] ?? false)) return null;
        $companies = LogisticsHub::companiesServicing($seller->municipality, $address['municipality'] ?? null, $seller->province, $address['province'] ?? null);
        if ($companies->isEmpty()) return 'Carrier coverage and approved shipping rates will be checked during seller preparation.';
        $kg = $this->kilograms($quote['items']); $gross = app(LogisticsCashDesk::class)->amount((string) $quote['shipping_amount']);
        $known = 0;
        foreach ($companies as $company) {
            $rate = $this->rate($company, $address['region'] ?? '');
            if (! $rate) continue;
            $known++;
            if ($gross === (int) $rate->first_kg_cents + ($kg - 1) * (int) $rate->additional_kg_cents) return null;
        }
        if ($known === $companies->count()) {
            $message = 'The quoted shipping charge does not match any approved carrier serving this route. Contact the seller before paying; your total has not been changed.';
            if (! $preview) $this->fail($message);
            return $message;
        }
        return 'Carrier-specific shipping rates are not fully approved yet. Seller preparation may be held for rate review; your total will not be changed automatically.';
    }

    private function voucherFunding(Order $order): array
    {
        $snapshot = $order->commerce_snapshot;
        if (is_array($snapshot)) {
            $funding = [];
            foreach ($snapshot['vouchers'] ?? [] as $v) {
                if (($v['kind'] ?? '') !== 'shipping') continue;
                if (! isset($v['funding_source'])) {
                    // Existing commerce snapshots identify the voucher; ownership determines its approved issuer funding.
                    $source = Schema::hasTable('commerce_vouchers') ? DB::table('commerce_vouchers')->where('id', $v['id'] ?? null)->lockForUpdate()->first() : null;
                    if (! $source || $source->kind !== 'shipping' || ($source->seller_id !== null && (int) $source->seller_id !== (int) $order->seller_id)) {
                        $this->fail('Shipping voucher funding cannot be verified. Request financial review before handoff.');
                    }
                    $v['funding_source'] = $source->seller_id === null ? 'platform' : 'seller';
                }
                if (! in_array($v['funding_source'], ['seller', 'platform'], true)) $this->fail('Shipping voucher funding requires review.');
                $funding[] = ['voucher_id' => $v['id'], 'code' => $v['code'], 'source' => $v['funding_source'],
                    'amount_cents' => app(LogisticsCashDesk::class)->amount((string) $v['amount'])];
            }
            return $funding;
        }
        $purchase = $order->gcash_purchase_id ? GcashPurchase::find($order->gcash_purchase_id) : null;
        if ($purchase && array_key_exists('shipping_voucher_funding', $purchase->snapshot)) return $purchase->snapshot['shipping_voucher_funding'];
        if ($purchase && ! empty($purchase->snapshot['voucher_id'])) $this->fail('This older voucher snapshot needs funding review before a new shipment can be created.');
        if (filled($order->voucher_code)) $this->fail('This older voucher snapshot needs funding review before a new shipment can be created.');
        return [];
    }

    public function createObligation(Shipment $shipment): void
    {
        if (! $this->installed()) return;
        if ($shipment->shipping_status !== 'ready_for_pickup' || $shipment->pickup_approved_at) $this->fail('A new financial shipment must start awaiting pickup approval.');
        $order = Order::lockFresh($shipment->order_id);
        $seller = User::findOrFail($order->seller_id);
        $companies = LogisticsHub::companiesServicing($seller->municipality, $order->shipping_address['municipality'] ?? null, $seller->province, $order->shipping_address['province'] ?? null);
        if (! $companies->contains($shipment->logistics_company)) $this->fail('The selected carrier no longer covers this route.');
        $rate = $this->rate($shipment->logistics_company, $order->shipping_address['region'] ?? '', true);
        if (! $rate) $this->fail('This carrier has no approved rate for the destination region. Select another carrier or request rate review.');
        $kg = $this->kilograms($order->items ?? []);
        $gross = app(LogisticsCashDesk::class)->amount((string) $order->shipping_amount);
        $approved = (int) $rate->first_kg_cents + ($kg - 1) * (int) $rate->additional_kg_cents;
        if ($gross !== $approved) $this->fail('The carrier-approved shipping rate does not match the original shipping charge. Handoff is held for rate review; the buyer total has not changed.');
        $gcash = $this->gcash($order);
        $account = $gcash && $gross > 0 ? DB::table('logistics_shipping_accounts')->where('company_name', $shipment->logistics_company)->lockForUpdate()->first() : null;
        if ($gcash && $gross > 0 && ! $account) $this->fail('The carrier must publish its company GCash receiving account before shipping payment can be requested.');
        $funding = $this->voucherFunding($order);
        $claim = array_sum(array_column(array_filter($funding, fn ($v) => $v['source'] === 'platform'), 'amount_cents'));
        if (array_sum(array_column($funding, 'amount_cents')) > $gross) $this->fail('Shipping voucher allocations exceed the original shipping charge.');
        $id = (string) Str::uuid();
        DB::table('logistics_shipping_obligations')->insert(['id' => $id, 'shipment_id' => $shipment->id, 'order_id' => $order->id,
            'company_name' => $shipment->logistics_company, 'seller_id' => $order->seller_id, 'rate_id' => $rate->id,
            'chargeable_kg' => $kg, 'amount_cents' => $gross, 'platform_claim_cents' => $claim, 'claim_status' => $claim > 0 ? 'unverified' : 'not_applicable',
            'voucher_funding' => json_encode($funding, JSON_THROW_ON_ERROR),
            'instructions' => $account ? json_encode(['name' => $account->account_name, 'number' => $account->account_number, 'revision' => $account->revision]) : null,
            'collection_mode' => $gcash ? 'gcash' : (app(CodCollection::class)->isCod($order) ? 'cod' : 'other'),
            'status' => $gcash ? ($gross > 0 ? 'pending' : 'not_required') : 'unsettled', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->event($shipment->logistics_company, 'shipping_obligation_created', 'shipping_obligation', $id,
            ['rate_id' => $rate->id, 'amount_cents' => $gross, 'chargeable_kg' => $kg, 'voucher_funding' => $funding, 'platform_claim_cents' => $claim], auth()->id());
    }

    public function gate(Shipment $shipment): void
    {
        if (! $this->installed() || (int) $shipment->shipping_finance_version !== 1) return;
        $obligation = DB::table('logistics_shipping_obligations')->where('shipment_id', $shipment->id)->lockForUpdate()->first();
        if (! $obligation || $obligation->company_name !== $shipment->logistics_company || $obligation->order_id !== $shipment->order_id) $this->fail('This new shipment requires a valid shipping obligation.');
        if ($obligation->collection_mode !== 'gcash') return;
        if ((int) $obligation->amount_cents === 0 && $obligation->status === 'not_required') return;
        $transaction = DB::table('logistics_financial_transactions')->where('shipping_obligation_id', $obligation->id)->lockForUpdate()->first();
        if ($obligation->status !== 'verified' || ! $transaction || $transaction->kind !== 'gcash_shipping'
            || $transaction->verification_state !== 'verified' || (int) $transaction->amount_cents !== (int) $obligation->amount_cents) {
            $this->fail('Verify the seller’s full GCash shipping payment before approving pickup.');
        }
    }

    public function reserveReference(string $reference, string $type, string $id): void
    {
        $reference = strtoupper(trim($reference));
        if (! preg_match('/^[A-Z0-9-]{6,64}$/D', $reference)) $this->fail('Use 6 to 64 letters, numbers or hyphens for the actual GCash reference.', 'reference');
        $existing = DB::table('logistics_payment_references')->where('reference', $reference)->lockForUpdate()->first();
        if ($existing) {
            if ($existing->subject_type !== $type || $existing->subject_id !== $id) $this->fail('This GCash reference is already recorded for another payment.', 'reference');
            return;
        }
        DB::table('logistics_payment_references')->insert(['reference' => $reference, 'subject_type' => $type, 'subject_id' => $id, 'created_at' => now()]);
    }

    public function participant(User $actor, object $obligation): User
    {
        $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        abort_unless($actor->status === 'approved' && ! $actor->is_admin, 403);
        if ($actor->account_type === 'seller' && ! $actor->is_logistics) {
            abort_unless((int) $actor->id === (int) $obligation->seller_id, 403);
        } else {
            app(LogisticsFinance::class)->admin($actor);
            abort_unless($actor->business_name === $obligation->company_name, 403);
        }
        return $actor;
    }

    public function submit(User $actor, string $id, array $input, UploadedFile $file): void
    {
        $data = Validator::make($input + ['proof' => $file], ['key' => 'required|uuid', 'reference' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{6,64}$/D'],
            'amount' => 'required|string', 'revision' => 'required|integer|min:1', 'proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120'])->validate();
        $amount = app(LogisticsCashDesk::class)->amount($data['amount']);
        $hash = hash_file('sha256', $file->getRealPath()); $reference = strtoupper(trim($data['reference']));
        try {
            DB::transaction(function () use ($actor, $id, $data, $amount, $hash, $reference, $file) {
                $peek = DB::table('logistics_shipping_obligations')->where('id', $id)->firstOrFail();
                GcashLocks::operation([$peek->shipment_id], [], [$actor->id]);
                $ob = DB::table('logistics_shipping_obligations')->where('id', $id)->lockForUpdate()->firstOrFail();
                $actor = $this->participant($actor, $ob); abort_unless($actor->account_type === 'seller', 403);
                $old = DB::table('logistics_shipping_payments')->where('submission_key', $data['key'])->lockForUpdate()->first();
                if ($old) {
                    abort_unless($old->obligation_id === $id && (int) $old->submitted_by === (int) $actor->id && $old->reference === $reference
                        && (int) $old->amount_cents === $amount && hash_equals($old->proof_hash, $hash), 409, 'This submission key has different payment details.');
                    return;
                }
                $shipment = Shipment::whereKey($ob->shipment_id)->lockForUpdate()->firstOrFail(); $order = Order::whereKey($ob->order_id)->lockForUpdate()->firstOrFail();
                if ($shipment->shipping_status !== 'ready_for_pickup' || $order->status !== 'ready_for_pickup') $this->fail('This shipment cannot accept a new shipping payment.');
                abort_unless((int) $ob->revision === (int) $data['revision'], 409, 'Shipping payment details changed. Refresh before submitting.');
                if ($ob->collection_mode !== 'gcash' || ! in_array($ob->status, ['pending', 'rejected'], true)) $this->fail('This obligation cannot accept another payment.');
                if ($amount !== (int) $ob->amount_cents) $this->fail('Submit the exact gross shipping fee. Vouchers do not reduce the carrier entitlement.', 'amount');
                $payment = (string) Str::uuid(); $this->reserveReference($reference, 'shipping', $payment);
                $path = app(MediaLifecycle::class)->store($file, 'logistics-shipping-proofs', 'local');
                DB::table('logistics_shipping_payments')->insert(['id' => $payment, 'obligation_id' => $id, 'reference' => $reference,
                    'amount_cents' => $amount, 'submission_key' => $data['key'], 'proof_hash' => $hash, 'proof_path' => $path,
                    'proof_mime' => $file->getMimeType(), 'submitted_by' => $actor->id, 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('logistics_shipping_obligations')->where('id', $id)->update(['status' => 'proof_submitted', 'revision' => $ob->revision + 1, 'updated_at' => now()]);
                $this->event($ob->company_name, 'shipping_payment_submitted', 'shipping_obligation', $id, ['payment_id' => $payment, 'reference' => $reference, 'amount_cents' => $amount], $actor->id);
            }, 3);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062 || (($e->errorInfo[1] ?? null) === 19 && str_contains(strtolower($e->getMessage()), 'unique constraint failed'))) abort(409, 'This payment reference, receipt or submission is already recorded.');
            throw $e;
        }
    }

    public function review(User $actor, string $id, array $input): void
    {
        $data = Validator::make($input, ['payment_id' => 'required|uuid', 'revision' => 'required|integer|min:1', 'accept' => 'required|boolean',
            'amount' => 'required_if:accept,1|nullable|string', 'reference' => 'required_if:accept,1|nullable|string|max:64', 'notes' => 'required|string|min:3|max:2000'])->validate();
        DB::transaction(function () use ($actor, $id, $data) {
            $peek = DB::table('logistics_shipping_obligations')->where('id', $id)->firstOrFail();
            GcashLocks::operation([$peek->shipment_id], [], [$actor->id]);
            $ob = DB::table('logistics_shipping_obligations')->where('id', $id)->lockForUpdate()->firstOrFail();
            $actor = $this->participant($actor, $ob); app(LogisticsFinance::class)->admin($actor);
            $payment = DB::table('logistics_shipping_payments')->where('id', $data['payment_id'])->where('obligation_id', $id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $ob->revision === (int) $data['revision'], 409, 'This payment was already reviewed. Refresh before continuing.');
            if ($payment->status !== 'submitted' || $ob->status !== 'proof_submitted') $this->fail('Only a pending shipping payment may be reviewed.');
            $accept = (bool) $data['accept'];
            $shipment = Shipment::whereKey($ob->shipment_id)->lockForUpdate()->firstOrFail(); $order = Order::whereKey($ob->order_id)->lockForUpdate()->firstOrFail();
            if ($accept && ($shipment->shipping_status === 'cancelled' || $order->status === 'cancelled')) $this->fail('This cancelled shipment requires financial exception review; do not settle its payment here.');
            if ($accept && (app(LogisticsCashDesk::class)->amount($data['amount']) !== (int) $ob->amount_cents
                || strtoupper(trim($data['reference'])) !== $payment->reference)) $this->fail('Confirm the exact received amount and the submitted GCash reference.');
            if ($accept) {
                DB::table('logistics_financial_transactions')->insert(['id' => (string) Str::uuid(), 'company_name' => $ob->company_name,
                    'kind' => 'gcash_shipping', 'order_id' => $ob->order_id, 'shipment_id' => $ob->shipment_id, 'shipping_obligation_id' => $id,
                    'actor_id' => $actor->id, 'amount_cents' => $ob->amount_cents, 'currency' => 'PHP', 'status' => 'recorded',
                    'verification_state' => 'verified', 'idempotency_key' => $payment->id, 'request_hash' => hash('sha256', $payment->reference.'|'.$id),
                    'details' => json_encode(['payment_id' => $payment->id, 'reference' => $payment->reference, 'notes' => $data['notes']]),
                    'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('logistics_shipping_payments')->where('id', $payment->id)->update(['status' => $accept ? 'verified' : 'rejected',
                'reviewed_by' => $actor->id, 'review_notes' => $data['notes'], 'reviewed_at' => now(), 'updated_at' => now()]);
            DB::table('logistics_shipping_obligations')->where('id', $id)->update(['status' => $accept ? 'verified' : 'rejected', 'revision' => $ob->revision + 1, 'updated_at' => now()]);
            $this->event($ob->company_name, $accept ? 'shipping_payment_verified' : 'shipping_payment_rejected', 'shipping_obligation', $id,
                ['payment_id' => $payment->id, 'notes' => $data['notes'], 'amount_cents' => $payment->amount_cents, 'platform_claim_verified' => false], $actor->id);
        }, 3);
    }

    private function event(string $company, string $event, string $type, string $id, array $details, ?int $actor): void
    {
        app(LogisticsFinance::class)->event($company, $event, $type, $id, $details, $actor);
    }
}
