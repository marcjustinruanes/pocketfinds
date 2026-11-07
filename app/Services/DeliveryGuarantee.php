<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\BuyerAddress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DeliveryGuarantee
{
    public const FREE_SHIPPING_CODE = 'PFSHIPFREE';
    public const LATE_VOUCHER_AMOUNT = 40;
    public const TIMEZONE = 'Asia/Manila';

    public static function installed(): bool
    {
        return Schema::hasTable('delivery_late_vouchers') && Schema::hasColumn('orders', 'delivery_guaranteed_by');
    }

    public static function active(): bool
    {
        return self::installed() && DB::table('commerce_vouchers')->where('code', self::FREE_SHIPPING_CODE)
            ->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>', now())->exists();
    }

    public static function regionalPoliciesInstalled(): bool
    {
        return Schema::hasColumn('commerce_shipping_rates', 'free_shipping');
    }

    public static function sellerVouchersInstalled(): bool
    {
        return Schema::hasColumn('commerce_shipping_rates', 'late_voucher_enabled');
    }

    public static function window(int $minDays = 1, int $maxDays = 7, float $lateVoucherAmount = self::LATE_VOUCHER_AMOUNT): array
    {
        $today = now()->timezone(self::TIMEZONE)->startOfDay();
        $from = $today->copy()->addDays($minDays)->toDateTimeString();
        $by = $today->copy()->addDays($maxDays)->endOfDay()->toDateTimeString();

        return [
            'from' => $from,
            'by' => $by,
            'label' => self::label($from, $by),
            'late_voucher_amount' => round($lateVoucherAmount, 2),
        ];
    }

    public static function forProduct(int $sellerId, ?User $buyer): array
    {
        if (! $buyer || $buyer->account_type !== 'buyer') return ['label' => 'Select a delivery address to see delivery dates'];
        $saved = BuyerAddress::where('buyer_id', $buyer->id)->orderByDesc('is_default')->orderBy('id')->first();
        $address = $saved ? $saved->toShippingArray() : $buyer->only(['region', 'province', 'municipality', 'postal_code']);
        if (empty($address['region'])) return ['label' => 'Select a delivery address to see delivery dates'];
        $rates = DB::table('commerce_shipping_rates')->where('seller_id', $sellerId);
        $rate = (clone $rates)->where('region', $address['region'])->where('is_active', true)->first();
        if (! $rate && $rates->exists()) return ['label' => 'Delivery is unavailable for your address'];
        $window = self::window((int) ($rate->delivery_days_min ?? 1), (int) ($rate->delivery_days_max ?? 7));

        return ['label' => 'Delivery '.$window['label'], 'from' => $window['from'], 'by' => $window['by']];
    }

    public static function label(string $from, string $by): string
    {
        $start = Carbon::parse($from, config('app.timezone'))->timezone(self::TIMEZONE);
        $end = Carbon::parse($by, config('app.timezone'))->timezone(self::TIMEZONE);

        return $start->format('Y-m') === $end->format('Y-m')
            ? $start->format('j').' – '.$end->format('j M')
            : $start->format('j M').' – '.$end->format('j M');
    }

    public static function voucherBelongsTo(int $voucherId, int $buyerId): bool
    {
        if (! self::installed()) return true;
        $owner = DB::table('delivery_late_vouchers')->where('voucher_id', $voucherId)->value('buyer_id');

        return $owner === null || (int) $owner === $buyerId;
    }

    public function sweep(?int $buyerId = null): int
    {
        if (! self::installed()) return 0;
        $issued = 0;
        Order::whereNotNull('delivery_guaranteed_by')->where('delivery_guaranteed_by', '<', now())
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->when($buyerId, fn ($query) => $query->where('buyer_id', $buyerId))
            ->whereNotIn('id', DB::table('delivery_late_vouchers')->select('order_id'))
            ->select('id')->chunkById(100, function ($orders) use (&$issued) {
                foreach ($orders as $candidate) {
                    $issued += DB::transaction(function () use ($candidate) {
                        $order = Order::whereKey($candidate->id)->lockForUpdate()->first();
                        if (! $order || in_array($order->status, ['cancelled', 'returned']) || ! $order->delivery_guaranteed_by || $order->delivery_guaranteed_by->gte(now())) return 0;
                        if (DB::table('delivery_late_vouchers')->where('order_id', $order->id)->exists()) return 0;
                        // Honor the seller's offer saved at checkout, even after settings change.
                        $amount = (float) ($order->commerce_snapshot['delivery_policy']['late_voucher_amount'] ?? self::LATE_VOUCHER_AMOUNT);
                        if ($amount <= 0) return 0;
                        // Delivery time, not the later buyer-confirmation time, decides lateness.
                        $delivered = DB::table('shipments')->where('order_id', $order->id)->value('delivered_at');
                        $history = DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'delivered')->min('created_at');
                        $times = array_filter([$delivered, $history]);
                        $arrived = $times ? Carbon::parse(min($times), config('app.timezone')) : null;
                        if ($arrived && $arrived->lte($order->delivery_guaranteed_by)) return 0;
                        if (! $arrived && in_array($order->status, ['delivered', 'completed'])) return 0;
                        $code = 'LATE'.strtoupper(Str::random(8));
                        $voucherSeller = $order->commerce_snapshot['delivery_policy']['late_voucher_seller_id'] ?? null;
                        $voucherId = DB::table('commerce_vouchers')->insertGetId([
                            'code' => $code, 'kind' => $voucherSeller ? 'store' : 'platform', 'seller_id' => $voucherSeller,
                            'discount_type' => 'fixed', 'amount' => $amount,
                            'minimum_spend' => 0, 'starts_at' => now(), 'ends_at' => '2037-12-31 23:59:59',
                            'usage_limit' => 1, 'per_buyer_limit' => 1, 'is_active' => true,
                        ]);
                        DB::table('delivery_late_vouchers')->insert(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'voucher_id' => $voucherId, 'issued_at' => now()]);
                        DB::table('commerce_claims')->insert(['buyer_id' => $order->buyer_id, 'voucher_id' => $voucherId]);
                        $amountLabel = number_format($amount, $amount == floor($amount) ? 0 : 2);
                        Order::notifyUser($order->buyer_id, '₱'.$amountLabel.' late-delivery voucher', 'Order '.$order->order_number.' missed its delivery deadline. Your ₱'.$amountLabel.' voucher '.$code.' is in My Vouchers.', 'delivery_voucher', $order->id);

                        return 1;
                    }, 3);
                }
            });

        return $issued;
    }
}
