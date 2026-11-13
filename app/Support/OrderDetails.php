<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Schema};

/** Read-only presentation of recorded money. Never recognizes or transfers funds. */
class OrderDetails
{
    public function forOrders(iterable $orders): Collection
    {
        $orders = collect($orders);
        if ($orders->isEmpty()) return collect();
        $ids = $orders->pluck('id');
        $rows = fn(string $table) => Schema::hasTable($table)
            ? DB::table($table)->whereIn('order_id', $ids)->get() : collect();
        $purchases = Schema::hasTable('gcash_purchases')
            ? DB::table('gcash_purchases')->whereIn('id', $orders->pluck('gcash_purchase_id')->filter())->get()->keyBy('id') : collect();
        $history = Schema::hasTable('gcash_purchase_history')
            ? DB::table('gcash_purchase_history')->whereIn('purchase_id', $purchases->keys())->orderBy('id')->get()->groupBy('purchase_id') : collect();
        $transactions = $rows('logistics_financial_transactions')->where('status', 'recorded')->groupBy('order_id');
        $commissions = $rows('commission_obligations')->keyBy('order_id');
        $commissionReceipts = Schema::hasTable('commission_allocations')
            ? DB::table('commission_allocations')->whereIn('obligation_id', $commissions->pluck('id'))->get()->groupBy('obligation_id') : collect();
        $recoveries = Schema::hasTable('commission_recovery_allocations')
            ? DB::table('commission_recovery_allocations')->whereIn('obligation_id', $commissions->pluck('id'))->get()->groupBy('obligation_id') : collect();
        $payouts = $rows('logistics_seller_payouts')->keyBy('order_id');
        $shipping = $rows('logistics_shipping_obligations')->keyBy('order_id');
        $returns = $rows('commerce_returns')->keyBy('order_id');
        $refunds = $rows('cancellation_refunds')->keyBy('order_id');
        $shipments = Schema::hasTable('shipments') ? DB::table('shipments')->whereIn('order_id', $ids)->get()->keyBy('order_id') : collect();
        $earnings = Schema::hasTable('logistics_compensations')
            ? DB::table('logistics_compensations as c')->join('delivery_assignments as a', 'a.id', '=', 'c.source_id')
                ->where('c.source_type', 'assignment')->whereIn('a.shipment_id', $shipments->pluck('id'))
                ->where('c.leg', 'delivery')->where('c.status', '!=', 'cancelled')->get(['c.*', 'a.shipment_id'])->groupBy('shipment_id') : collect();
        $riderPayouts = Schema::hasTable('logistics_rider_payouts')
            ? DB::table('logistics_rider_payouts')->whereIn('compensation_id', $earnings->flatten(1)->pluck('id'))->get()->keyBy('compensation_id') : collect();
        $deductions = Schema::hasTable('commission_cod_settlements')
            ? DB::table('commission_cod_settlements')->whereIn('payout_id', $payouts->pluck('id'))->pluck('commission_cents', 'payout_id') : collect();
        $verifiedCash = Schema::hasTable('logistics_cash_allocations')
            ? DB::table('logistics_cash_allocations as a')->join('logistics_cash_handovers as h', 'h.id', '=', 'a.handover_id')
                ->whereIn('a.collection_id', $transactions->flatten(1)->pluck('id'))->whereNotNull('h.counted_at')
                ->get(['a.collection_id', 'a.verified_cents'])->groupBy('collection_id') : collect();

        return $orders->mapWithKeys(function (Order $order) use ($purchases, $history, $transactions, $commissions, $commissionReceipts, $recoveries, $payouts, $shipping, $returns, $refunds, $shipments, $earnings, $riderPayouts, $deductions, $verifiedCash) {
            $purchase = $purchases->get($order->gcash_purchase_id);
            $collection = $transactions->get($order->id, collect())->firstWhere('kind', 'cod_collection');
            $total = $this->cents($order->total);
            $applied = 0; $received = 0; $receivedAt = null; $reference = null;
            $status = 'Payment not recorded'; $receiver = null; $excess = null;
            if ($purchase) {
                $status = match ($purchase->payment_state) {
                    'awaiting_payment', 'awaiting_seller' => 'Awaiting payment',
                    'proof_submitted' => 'Payment proof under review',
                    'underpaid' => 'Partial payment — top-up required',
                    'rejected' => 'Payment proof rejected',
                    'manual_resolution' => 'Payment requires review',
                    default => 'Awaiting payment verification',
                };
                // verified_amount also records confirmed partial receipts. Uploaded proof alone is never payment.
                $received = $this->cents($purchase->verified_amount ?? 0);
                $applied = min($received, $total);
                $receiver = ($purchase->review_flow ?? null) === 'admin' ? 'PocketFinds platform account' : 'Seller account';
                if ($purchase->verified_at) {
                    $receivedAt = $purchase->verified_at;
                    $reference = $purchase->payment_reference;
                    if ($applied >= $total && $total > 0) $status = 'Payment verified';
                    $excessEvent = $history->get($purchase->id, collect())->whereIn('event', ['overpayment_pending', 'overpayment_refunded'])->last();
                    if ($excessEvent) {
                        $pending = $history->get($purchase->id, collect())->where('event', 'overpayment_pending')->last();
                        $meta = json_decode($pending?->before ?? '{}', true) ?: [];
                        $received = isset($meta['received_amount']) ? $this->cents($meta['received_amount']) : $received;
                        $excess = ['amount' => $this->cents($meta['refund_amount'] ?? 0), 'status' => $excessEvent->event === 'overpayment_refunded' ? 'Returned' : 'Awaiting refund'];
                    }
                }
            } elseif ($collection) {
                $received = $this->centsFromInteger($collection->amount_cents);
                $applied = min($received, $total);
                $receivedAt = $collection->recorded_at;
                $reference = $collection->id;
                $receiver = 'Assigned logistics rider';
                $status = 'Cash collection recorded';
            } elseif (app(\App\Services\CodCollection::class)->isCod($order)) {
                $status = 'Cash on delivery — collection not recorded';
            }
            $commission = $commissions->get($order->id);
            $payout = $payouts->get($order->id);
            $shipment = $shipments->get($order->id);
            $rider = $earnings->get($shipment?->id, collect())->first(fn($e) => (int)$e->rider_id === (int)($shipment?->courier_id));
            $cashVerified = $collection ? (int)$verifiedCash->get($collection->id, collect())->sum('verified_cents') : 0;
            $vouchers = $order->commerce_snapshot['vouchers'] ?? null;
            if ($vouchers === [] && $this->cents($order->discount_amount) > 0) $vouchers = null;
            $shippingDiscount = is_array($vouchers) ? min($this->cents($order->shipping_amount), collect($vouchers)->where('kind', 'shipping')->sum(fn($v) => $this->cents($v['amount']))) : null;
            return [$order->id => [
                'title' => ($purchase ? (bool)$purchase->verified_at : (bool)$collection) && $applied >= $total && $total > 0 ? 'Payment receipt' : 'Order summary',
                'payment_status' => $status, 'received_cents' => $received, 'applied_cents' => $applied,
                'remaining_cents' => max(0, $total - $applied), 'received_at' => $receivedAt, 'reference' => $reference, 'receiver' => $receiver,
                'admin_payment' => ($purchase->review_flow ?? null) === 'admin', 'excess' => $excess,
                'shipping_discount_cents' => $shippingDiscount,
                'product_discount_cents' => $shippingDiscount === null ? null : max(0, $this->cents($order->discount_amount) - $shippingDiscount),
                'commission' => $commission, 'commission_cents' => $commission ? max(0, (int)$commission->commission_cents - (int)$commission->reversed_cents) : null,
                'commission_received_cents' => $commission ? (int)$commissionReceipts->get($commission->id, collect())->sum('allocated_cents') + (int)$recoveries->get($commission->id, collect())->sum('amount_cents') : 0,
                'payout' => $payout, 'payout_cents' => $payout ? (int)$payout->product_cents + (int)$payout->adjustment_cents - (int)$deductions->get($payout->id, 0) : null,
                'shipping' => $shipping->get($order->id), 'cash_collected_cents' => $collection ? (int)$collection->amount_cents : 0,
                'cash_verified_cents' => $cashVerified,
                'rider_earning' => $rider, 'rider_payout' => $rider ? $riderPayouts->get($rider->id) : null,
                'return' => $returns->get($order->id), 'cancellation_refund' => $refunds->get($order->id),
            ]];
        });
    }

    private function cents(mixed $value): int
    {
        return app(\App\Services\CommissionLedger::class)->cents($value ?? 0);
    }

    private function centsFromInteger(mixed $value): int { return max(0, (int)$value); }
}
