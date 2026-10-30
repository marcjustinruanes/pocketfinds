<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CodCollection
{
    public function installed(): bool
    {
        return app(LogisticsFinance::class)->installed() && Schema::hasTable('logistics_financial_transactions');
    }

    public function isCod(?Order $order): bool
    {
        if (! $order || $order->gcash_purchase_id) {
            return false;
        }
        $method = $order->payment_method_id ? PaymentMethod::find($order->payment_method_id) : null;
        // Classification must survive later deactivation of a checkout payment method.
        return $method?->type === 'cod' || in_array(strtolower(trim((string) ($order->payment_method ?: $method?->name))), ['cod', 'cash on delivery'], true);
    }

    public function record(User $actor, Shipment $shipment, array $input): object
    {
        abort_unless($this->installed(), 503, 'COD collection records are unavailable.');
        $data = Validator::make($input, [
            'cod_amount' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D'],
            'cod_confirmed' => 'required|accepted', 'collection_key' => 'required|uuid',
        ])->validate();
        try {
            return DB::transaction(function () use ($actor, $shipment, $data) {
                // Callers take GcashLocks before entering the delivery transaction.
                $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
                $order = Order::lockFresh($shipment->order_id);
                $actor = User::whereKey($actor->id)->lockForUpdate()->first();
                abort_unless($actor && $actor->account_type === 'rider' && ! $actor->is_admin && ! $actor->is_logistics
                    && $actor->status === 'approved' && $actor->business_name === $shipment->logistics_company
                    && (int) $shipment->courier_id === (int) $actor->id, 403, 'Only the assigned delivery rider can record this collection.');
                abort_unless($this->isCod($order), 422, 'This order does not require a COD cash collection.');
                $finance = app(LogisticsFinance::class);
                $amount = $this->amount($data['cod_amount']);
                if ($amount !== $this->amount((string) $order->total)) {
                    throw ValidationException::withMessages(['cod_amount' => 'Collected cash must exactly match the final order total.']);
                }
                $existing = $this->find($shipment->id);
                if ($existing) {
                    abort_unless((int) $existing->actor_id === (int) $actor->id && (int) $existing->amount_cents === $amount
                        && $existing->order_id === $order->id && $existing->status === 'recorded', 409, 'This shipment already has a different cash collection.');
                    return $existing;
                }
                abort_unless($shipment->shipping_status === 'out_for_delivery', 422, 'Cash collection is recorded during delivery handover.');
                $assignment = DeliveryAssignment::where('shipment_id', $shipment->id)->where('leg', 'delivery')
                    ->where('courier_id', $actor->id)->where('status', 'out_for_delivery')->lockForUpdate()->first();
                abort_unless($assignment, 422, 'The active delivery assignment is required.');
                $snapshot = DB::table('logistics_compensations')->where('source_type', 'assignment')->where('source_id', $assignment->id)
                    ->where('rider_id', $actor->id)->where('status', '!=', 'cancelled')->first();
                $hub = LogisticsHub::whereKey($snapshot?->hub_id ?? $actor->logistics_hub_id)->lockForUpdate()->first();
                abort_unless($hub && $hub->is_operational && $hub->company_name === $shipment->logistics_company
                    && strcasecmp(trim($hub->municipality), trim((string) $shipment->destination_hub)) === 0
                    && strcasecmp(trim($hub->province), trim((string) $shipment->destination_province)) === 0, 422, 'A valid assigned destination hub is required for cash remittance.');
                $hash = hash('sha256', json_encode([$shipment->id, $order->id, $actor->id, $hub->id, $amount]));
                abort_if(DB::table('logistics_financial_transactions')->where('company_name', $shipment->logistics_company)
                    ->where('idempotency_key', $data['collection_key'])->exists(), 409, 'This confirmation key was already used for another transaction.');
                $id = (string) Str::uuid();
                DB::table('logistics_financial_transactions')->insert([
                    'id' => $id, 'company_name' => $shipment->logistics_company, 'kind' => 'cod_collection',
                    'order_id' => $order->id, 'shipment_id' => $shipment->id, 'collection_shipment_id' => $shipment->id, 'collection_order_id' => $order->id,
                    'hub_id' => $hub->id, 'actor_id' => $actor->id, 'amount_cents' => $amount, 'currency' => 'PHP',
                    'status' => 'recorded', 'verification_state' => 'pending', 'idempotency_key' => $data['collection_key'], 'request_hash' => $hash,
                    'details' => json_encode(['assignment_id' => $assignment->id, 'buyer_total' => (string) $order->total,
                        'shipping_amount' => (string) $order->shipping_amount, 'cash_confirmation' => true]),
                    'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $finance->event($shipment->logistics_company, 'cod_collected', 'transaction', $id,
                    ['shipment_id' => $shipment->id, 'order_id' => $order->id, 'hub_id' => $hub->id, 'amount_cents' => $amount, 'verification_state' => 'pending'], $actor->id);

                return $this->find($shipment->id);
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062 || (($e->errorInfo[1] ?? null) === 19 && str_contains(strtolower($e->getMessage()), 'unique constraint failed'))) {
                abort(409, 'This cash collection or confirmation key has already been recorded. Refresh the delivery.');
            }
            throw $e;
        }
    }

    private function amount(string $amount): int
    {
        abort_unless(preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D', $amount, $parts), 422, 'Invalid cash amount.');

        return ((int) $parts[1]) * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public function find(string $shipment): ?object
    {
        return $this->installed() ? DB::table('logistics_financial_transactions')->where('collection_shipment_id', $shipment)->where('kind', 'cod_collection')->first() : null;
    }

    public function assertRecorded(?Order $order, ?string $shipment = null): void
    {
        if (! $this->installed() || ! $this->isCod($order)) {
            return;
        }
        $record = DB::table('logistics_financial_transactions')->where('order_id', $order->id)->where('kind', 'cod_collection')
            ->where('status', 'recorded')->when($shipment, fn ($q) => $q->where('collection_shipment_id', $shipment))->first();
        abort_unless($record && (int) $record->amount_cents === $this->amount((string) $order->total), 422,
            'The assigned rider must confirm the exact COD collection before delivery is completed. Hub cash verification remains separate.');
    }
}
