<?php

namespace App\Services;

use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BuyerCommerce
{
    public static function installed(): bool
    {
        return Schema::hasTable('commerce_checkouts');
    }

    public function fail(string $message, string $field = 'checkout'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function available(User $seller): void
    {
        if ($seller->status !== 'approved' || $seller->is_on_vacation) {
            $this->fail('This shop is unavailable or on vacation.');
        }
    }

    public function flash(Product $product, bool $lock = false): ?object
    {
        if (! self::installed()) {
            return null;
        }
        $q = DB::table('commerce_flash_sales')->where('product_id', $product->id)->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>', now());
        $sale = ($lock ? $q->lockForUpdate() : $q)->first();
        if ($sale) {
            $sale->remaining = max(0, $sale->quantity - DB::table('commerce_allocations')->where('flash_sale_id', $sale->id)->whereIn('state', ['held', 'used'])->sum('quantity'));
        }

        return $sale && $sale->remaining > 0 ? $sale : null;
    }

    public function unitPrice(Product $p, float $base): float
    {
        return (float) ($this->flash($p)?->price ?? $base);
    }

    public function quote(User $buyer, array $ids, ?int $addressId, array $codes = [], bool $preview = false): array
    {
        $cart = CartItem::where('buyer_id', $buyer->id)->whereIn('id', array_unique($ids))->orderBy('id')->lockForUpdate()->get();
        if (! $ids || $cart->count() !== count(array_unique($ids))) {
            $this->fail('Select available items from your cart.');
        }
        $address = $addressId ? BuyerAddress::where('buyer_id', $buyer->id)->findOrFail($addressId)->toShippingArray() : collect(['region', 'postal_code', 'province', 'municipality', 'barangay', 'house_no', 'street', 'contact_no'])->mapWithKeys(fn ($f) => [$f => $buyer->$f])->all();
        $warnings = [];
        foreach (['region', 'postal_code', 'province', 'municipality', 'barangay'] as $f) {
            if (! filled($address[$f] ?? null)) {
                if (! $preview) {
                    $this->fail('Complete your delivery address, including region and postal code.', 'delivery_address_id');
                }
                $warnings[] = 'Complete your delivery address, including region and postal code, to calculate shipping.';
            }
        }
        $addressIncomplete = ! empty($warnings);
        $orders = [];
        $legacyShippingFees = [];
        $flashQuantities = [];
        $requestedStock = [];
        $products = Product::whereIn('id', $cart->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($cart as $row) {
            $p = $products->get($row->product_id);
            if (! $p || $p->status !== 'active') {
                $this->fail('A selected product is unavailable.');
            }
            $seller = User::whereKey($p->seller_id)->lockForUpdate()->firstOrFail();
            $this->available($seller);
            $legacyShippingFees[$seller->id] = (float) ($seller->shipping_fee ?? 0);
            $stockKey = json_encode([$p->id, $row->variation_group ?: null, $row->variation_value ?: null]);
            $requestedStock[$stockKey] = ($requestedStock[$stockKey] ?? 0) + (int) $row->qty;
            if ($row->qty < 1 || $p->availableStock($row->variation_group ?: null, $row->variation_value ?: null) < $requestedStock[$stockKey]) {
                $this->fail('Insufficient stock for '.$p->name.'.');
            }
            $base = (float) ($p->discount_price ?? $p->price);
            if ($p->variations) {
                $found = false;
                foreach ($p->variations as $g) {
                    foreach ($g['options'] ?? [] as $o) {
                        if ($g['name'] === $row->variation_group && $o['value'] === $row->variation_value) {
                            $base = (float) ($o['price'] ?? $base);
                            $found = true;
                        }
                    }
                }if (! $found) {
                    $this->fail('Select a valid variation for '.$p->name.'.');
                }
            }
            $flash = $this->flash($p, true);
            $price = $flash ? (float) $flash->price : $base;
            $flashQty = $flash ? min((int) $row->qty, max(0, $flash->remaining - ($flashQuantities[$flash->id] ?? 0))) : 0;
            $item = ['product_id' => $p->id, 'name' => $p->name, 'price' => $base, 'qty' => (int) $row->qty, 'variation_group' => $row->variation_group ?: null, 'variation_value' => $row->variation_value ?: null, 'img' => $row->img, 'cart_id' => $row->id, 'cart_qty' => (int) $row->qty, 'cart_price' => $row->price, 'seller_slug' => $seller->username, 'flash_sale_id' => null, 'weight_grams' => (float) $p->weight_grams];
            if ($flashQty) {
                $flashQuantities[$flash->id] = ($flashQuantities[$flash->id] ?? 0) + $flashQty;
                $orders[$seller->id]['items'][] = array_replace($item, ['price' => $price, 'qty' => $flashQty, 'flash_sale_id' => $flash->id]);
            }
            if ($row->qty > $flashQty) {
                $orders[$seller->id]['items'][] = array_replace($item, ['qty' => $row->qty - $flashQty]);
            }
        }
        ksort($orders);
        foreach ($orders as $sellerId => &$order) {
            $rate = DB::table('commerce_shipping_rates')->where('seller_id', $sellerId)->where('region', $address['region'] ?? '')->where('is_active', true)->first();
            $hasRegionalRates = DB::table('commerce_shipping_rates')->where('seller_id', $sellerId)->exists();
            // Sellers created before regional rates were introduced used one flat
            // shipping fee (or free shipping when it was unset). Keep those shops
            // checkout-compatible until they configure their first regional rate.
            // Once a seller has configured any region, an absent/inactive matching
            // region remains an intentional service restriction.
            $usesLegacyFlatRate = ! $rate && ! $hasRegionalRates;
            $shippingPending = (! $rate && ! $usesLegacyFlatRate) || $addressIncomplete;
            if (! $rate && ! $usesLegacyFlatRate) {
                if (! $preview) {
                    $this->fail('This shop does not deliver to the selected region. Choose another address.', 'delivery_address_id');
                }
                $warnings[] = 'Shipping is pending: the seller must configure a rate for your delivery region.';
            }
            foreach ($order['items'] as $item) {
                if ($item['weight_grams'] <= 0) {
                    if (! $preview) {
                        $this->fail('Shipping weight is missing for '.$item['name'].'. Contact the seller.');
                    }
                    $shippingPending = true;
                    $warnings[] = 'Shipping is pending: the seller must add product weights.';
                }
            }
            $kg = max(1, (int) ceil(array_sum(array_map(fn ($i) => $i['weight_grams'] * $i['qty'], $order['items'])) / 1000));
            $shippingAmount = $usesLegacyFlatRate
                ? ($legacyShippingFees[$sellerId] ?? 0)
                : ($rate ? $rate->first_kg + max(0, $kg - 1) * $rate->additional_kg : 0);
            $order += ['subtotal' => round(array_sum(array_map(fn ($i) => $i['price'] * $i['qty'], $order['items'])), 2), 'shipping_amount' => $shippingPending ? 0 : round($shippingAmount, 2), 'discount_amount' => 0, 'seller_discount' => 0, 'vouchers' => [], 'shipping_rate_id' => $rate?->id, 'chargeable_kg' => $kg, 'shipping_pending' => $shippingPending];
        }unset($order);
        // One selected value per type replaces its predecessor. Store selections are keyed by seller.
        $selection = [];
        foreach ($codes as $code) {
            if (filled($code)) {
                $v = DB::table('commerce_vouchers')->where('code', strtoupper(trim($code)))->lockForUpdate()->first();
                if (! $v) {
                    $this->fail('Voucher code not found: '.$code, 'vouchers');
                }
                $selection[$v->kind === 'store' ? 'store:'.$v->seller_id : $v->kind] = $v;
            }
        }
        foreach ($selection as $v) {
            if (! $v->is_active || now()->lt($v->starts_at) || now()->gte($v->ends_at)) {
                $this->fail('Voucher '.$v->code.' is inactive, not started, or expired.', 'vouchers');
            }
            $uses = DB::table('commerce_allocations')->where('voucher_id', $v->id)->whereIn('state', ['held', 'used']);
            $legacyUsed = (int) ($v->legacy_used_count ?? 0);
            if ($v->legacy_store_id ?? null) {
                $legacy = Voucher::whereKey($v->legacy_store_id)->lockForUpdate()->first();
                if (! $legacy || ! $legacy->isUsable()) {
                    $this->fail('Voucher '.$v->code.' is unavailable.', 'vouchers');
                }$legacyUsed = (int) $legacy->used_count + GcashInventory::voucherSlots($legacy->id);
            }
            if ((clone $uses)->distinct()->count('usage_key') + $legacyUsed >= $v->usage_limit) {
                $this->fail('Voucher '.$v->code.' has reached its usage limit.', 'vouchers');
            }
            if ((clone $uses)->where('buyer_id', $buyer->id)->distinct()->count('usage_key') >= $v->per_buyer_limit) {
                $this->fail('You have already used voucher '.$v->code.'.', 'vouchers');
            }
            $eligible = $v->seller_id !== null ? array_intersect_key($orders, [$v->seller_id => true]) : $orders;
            if (! $eligible) {
                $this->fail('Voucher '.$v->code.' does not match these shops.', 'vouchers');
            }
            $subtotal = array_sum(array_column($eligible, 'subtotal'));
            if ($subtotal < (float) $v->minimum_spend) {
                $this->fail('Spend ₱'.number_format($v->minimum_spend - $subtotal, 2).' more for '.$v->code.'.', 'vouchers');
            }
            $basis = $v->kind === 'shipping' ? array_sum(array_column($eligible, 'shipping_amount')) : $subtotal;
            if ($v->kind === 'shipping' && in_array(true, array_column($eligible, 'shipping_pending'), true)) {
                $warnings[] = 'Your shipping voucher will be calculated once shipping is available.';
            }
            $amount = $v->discount_type === 'percent' ? $basis * $v->amount / 100 : (float) $v->amount;
            if ($v->cap !== null) {
                $amount = min($amount, (float) $v->cap);
            }
            $amount = min($basis, round($amount, 2));
            $shares = $this->split((int) round($amount * 100), array_map(fn ($o) => (int) round(($v->kind === 'shipping' ? $o['shipping_amount'] : $o['subtotal']) * 100), $eligible));
            foreach ($shares as $id => $cents) {
                $order = &$orders[$id];
                $order['vouchers'][] = ['id' => $v->id, 'code' => $v->code, 'kind' => $v->kind, 'amount' => $cents / 100];
                $order['discount_amount'] += $cents / 100;
                if ($v->kind === 'store') {
                    $order['seller_discount'] += $cents / 100;
                }unset($order);
            }
        }
        foreach ($orders as &$order) {
            $remainingMerch = $order['subtotal'];
            $remainingShipping = $order['shipping_amount'];
            $order['seller_discount'] = 0;
            usort($order['vouchers'], fn ($a, $b) => array_search($a['kind'], ['store', 'platform', 'shipping']) <=> array_search($b['kind'], ['store', 'platform', 'shipping']));
            foreach ($order['vouchers'] as &$voucher) {
                if ($voucher['kind'] === 'shipping') {
                    $voucher['amount'] = min($remainingShipping, $voucher['amount']);
                    $remainingShipping -= $voucher['amount'];
                } else {
                    $voucher['amount'] = min($remainingMerch, $voucher['amount']);
                    $remainingMerch -= $voucher['amount'];
                }
                if ($voucher['kind'] === 'store') {
                    $order['seller_discount'] += $voucher['amount'];
                }
            }unset($voucher);
            $merch = min($order['subtotal'], array_sum(array_column(array_filter($order['vouchers'], fn ($v) => $v['kind'] !== 'shipping'), 'amount')));
            $shipping = min($order['shipping_amount'], array_sum(array_column(array_filter($order['vouchers'], fn ($v) => $v['kind'] === 'shipping'), 'amount')));
            $order['discount_amount'] = round($merch + $shipping, 2);
            $order['total'] = round($order['subtotal'] + $order['shipping_amount'] - $order['discount_amount'], 2);
        }unset($order);

        return ['orders' => $orders, 'address' => $address, 'total' => round(array_sum(array_column($orders, 'total')), 2), 'warnings' => array_values(array_unique($warnings))];
    }

    public function split(int $cents, array $weights): array
    {
        $sum = array_sum($weights);
        $result = [];
        foreach ($weights as $id => $weight) {
            $result[$id] = $sum ? intdiv($cents * $weight, $sum) : 0;
        }
        if ($weights) {
            $largest = array_keys($weights, max($weights))[0];
            $result[$largest] += $cents - array_sum($result);
        }

        return $result;
    }

    public function allocate(string $subject, User $buyer, array $order, bool $held, ?string $usageKey = null): void
    {
        foreach ($order['vouchers'] as $v) {
            DB::table('commerce_allocations')->insert(['subject_id' => $subject, 'usage_key' => $usageKey ?? $subject, 'buyer_id' => $buyer->id, 'voucher_id' => $v['id'], 'state' => $held ? 'held' : 'used', 'expires_at' => $held ? now()->addHour() : null]);
        }
        foreach ($order['items'] as $item) {
            if ($item['flash_sale_id'] ?? null) {
                DB::table('commerce_allocations')->insert(['subject_id' => $subject, 'usage_key' => $usageKey ?? $subject, 'buyer_id' => $buyer->id, 'flash_sale_id' => $item['flash_sale_id'], 'quantity' => $item['qty'], 'state' => $held ? 'held' : 'used', 'expires_at' => $held ? now()->addHour() : null]);
            }
        }
    }

    public function release(string $subject): void
    {
        if (self::installed()) {
            DB::table('commerce_allocations')->where('subject_id', $subject)->whereIn('state', ['held', 'used'])->update(['state' => 'released']);
        }
    }

    public function sweep(): void
    {
        if (! self::installed()) {
            return;
        }
        foreach (DB::table('commerce_reminders as r')->join('commerce_flash_sales as f', 'f.id', '=', 'r.flash_sale_id')->whereNull('r.sent_at')->where('f.is_active', true)->where('f.starts_at', '<=', now()->addMinutes(5))->where('f.ends_at', '>', now())->select('r.*', 'f.product_id')->get() as $r) {
            DB::transaction(function () use ($r) {
                if (DB::table('commerce_reminders')->where('id', $r->id)->whereNull('sent_at')->lockForUpdate()->first()) {
                    Order::notifyUser($r->buyer_id, 'Flash sale starting', 'Your saved flash sale is starting soon.', 'flash_sale', $r->product_id);
                    DB::table('commerce_reminders')->where('id', $r->id)->update(['sent_at' => now()]);
                }
            });
        }
        foreach (DB::table('commerce_returns')->where('status', 'pending')->where('created_at', '<=', now()->subDays(3))->get(['id', 'order_id']) as $request) {
            DB::transaction(function () use ($request) {
                $order = Order::whereKey($request->order_id)->lockForUpdate()->first();
                if (! $order) {
                    return;
                }
                $pending = DB::table('commerce_returns')->where('id', $request->id)->where('status', 'pending')->lockForUpdate()->first();
                if (! $pending) {
                    return;
                }
                DB::table('commerce_returns')->where('id', $request->id)->update(['status' => 'approved', 'decision_reason' => 'Automatically approved: no seller response within three days.', 'decided_at' => now(), 'updated_at' => now()]);
                foreach ([$pending->buyer_id, $pending->seller_id] as $recipient) {
                    Order::notifyUser($recipient, 'Return automatically approved', 'The return for '.$order->order_number.' was approved after three days without a seller response.', 'order_status', $order->id);
                }
            }, 3);
        }
    }
}
