<?php

namespace App\Services;

use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\GcashPurchase;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GcashWorkflow
{
    private function transaction(\Closure $operation, int $attempts = 1): mixed
    {
        try {
            return DB::transaction($operation, $attempts);
        } catch (QueryException $error) {
            $code = $error->errorInfo[1] ?? null;
            if ($code === 1062 || ($code === 19 && str_contains($error->errorInfo[2] ?? '', 'UNIQUE constraint failed'))) {
                abort(409, 'This payment or purchase identifier is already in use. Refresh before retrying.');
            }
            Log::warning('GCash transaction database failure', ['sqlstate' => $error->getCode()]);
            abort(503, 'Unable to complete the GCash operation. Refresh and retry; no payment was automatically verified.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['gcash' => $message]);
    }

    public function eligible(User $actor, string $role): void
    {
        $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        if ($actor->account_type !== $role || $actor->status !== 'approved' || $actor->is_admin) {
            abort(403, 'An approved '.$role.' account is required.');
        }
    }

    private function owner(User $actor, GcashPurchase $p, string $role): void
    {
        $this->eligible($actor, $role);
        abort_unless((int) $p->{$role.'_id'} === (int) $actor->id, 403);
    }

    private function cents($amount): int
    {
        if ($amount !== null && (! is_numeric($amount) || ! is_finite((float) $amount) || (float) $amount < 0 || (float) $amount > 9999999999.99)) {
            $this->fail('Amount exceeds the supported currency range.');
        }

        return (int) round((float) $amount * 100);
    }

    private function money(int $cents): string
    {
        if ($cents < 0 || $cents > 999999999999) {
            $this->fail('The purchase total exceeds the supported currency range.');
        }

        return number_format($cents / 100, 2, '.', '');
    }

    private function price(Product $p, ?string $group, ?string $value): int
    {
        if (empty($p->variations)) {
            if ($group || $value) {
                $this->fail('This product no longer has the selected variation.');
            }

            return $this->cents($p->discount_price ?? $p->price);
        }
        $found = [];
        foreach ($p->variations as $g) {
            foreach ($g['options'] ?? [] as $o) {
                if (($g['name'] ?? null) === $group && ($o['value'] ?? null) === $value) {
                    $found[] = $o;
                }
            }
        }
        if (count($found) !== 1) {
            $this->fail('Select one unambiguous current variation.');
        }

        return $this->cents($found[0]['price'] ?? ($p->discount_price ?? $p->price));
    }

    private function event(GcashPurchase $p, string $event, ?User $actor, array $before = [], ?string $reason = null): void
    {
        DB::table('gcash_purchase_history')->insert(['purchase_id' => $p->id, 'actor_id' => $actor?->id, 'event' => $event, 'before' => json_encode($before), 'after' => json_encode(['payment' => $p->payment_state, 'reservation' => $p->reservation_state, 'revision' => $p->revision, 'generation' => $p->generation]), 'reason' => $reason, 'created_at' => now()]);
    }

    private function state(GcashPurchase $p): array
    {
        return ['payment' => $p->payment_state, 'reservation' => $p->reservation_state, 'revision' => $p->revision, 'generation' => $p->generation];
    }

    private function notify(GcashPurchase $p, string $title, string $message, bool $buyer = true, bool $seller = true): void
    {
        foreach (array_filter([$buyer ? $p->buyer_id : null, $seller ? $p->seller_id : null]) as $id) {
            Order::notifyUser($id, $title, $message, 'gcash_purchase', $p->id);
        }
    }

    private function products(array $items)
    {
        return Product::whereIn('id', array_column($items, 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    private function locked(string $id): array
    {
        return GcashLocks::purchase($id);
    }

    private function freshPrices(GcashPurchase $p, $products): void
    {
        $seller = User::whereKey($p->seller_id)->lockForUpdate()->firstOrFail();
        $buyer = User::whereKey($p->buyer_id)->lockForUpdate()->firstOrFail();
        $this->eligible($seller, 'seller');
        $this->eligible($buyer, 'buyer');
        foreach ($p->snapshot['items'] as $item) {
            $product = $products->get($item['product_id']);
            if (! $product || $product->seller_id != $p->seller_id || $product->status !== 'active' || $this->price($product, $item['variation_group'], $item['variation_value']) !== $this->cents($item['price'])) {
                $this->fail('Product availability or agreed price changed. Explicit payment reconciliation is required.');
            }
        }
        if ($this->cents($seller->shipping_fee) !== $this->cents($p->snapshot['shipping_amount'])) {
            $this->fail('Shipping amount changed. Explicit payment reconciliation is required.');
        }
    }

    public function refreshCartPrices(User $buyer, array $ids, ?string $acknowledgement = null): array
    {
        $this->eligible($buyer, 'buyer');

        return $this->transaction(function () use ($buyer, $ids, $acknowledgement) {
            $peek = CartItem::where('buyer_id', $buyer->id)->whereIn('id', $ids)->get();
            $products = GcashLocks::resources($peek->map(fn ($row) => ['product_id' => $row->product_id])->all(), [$buyer->id]);
            $this->eligible($buyer, 'buyer');
            $cart = CartItem::where('buyer_id', $buyer->id)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($cart->count() !== count(array_unique($ids)) || $cart->isEmpty()) {
                $this->fail('Select your current cart items.');
            }
            $rows = [];
            foreach ($cart as $item) {
                $product = $products->get($item->product_id);
                if (! $product || $product->status !== 'active') {
                    $this->fail('An item is unavailable. Refresh the cart.');
                }
                $rows[] = ['id' => $item->id, 'product_id' => $item->product_id, 'name' => $product->name, 'qty' => $item->qty, 'group' => $item->variation_group, 'value' => $item->variation_value, 'old_price' => $this->money($this->cents($item->price)), 'new_price' => $this->money($this->price($product, $item->variation_group ?: null, $item->variation_value ?: null))];
            }
            if ($acknowledgement !== null) {
                try {
                    $accepted = json_decode(Crypt::decryptString($acknowledgement), true, flags: JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    $this->fail('Invalid price acknowledgement. Review prices again.');
                }
                if (($accepted['buyer'] ?? null) !== $buyer->id || ($accepted['until'] ?? 0) < now()->timestamp || ($accepted['rows'] ?? []) !== $rows) {
                    $this->fail('Prices or cart changed after review. Review and acknowledge again.');
                }
                foreach ($rows as $row) {
                    CartItem::whereKey($row['id'])->where('buyer_id', $buyer->id)->update(['price' => $row['new_price']]);
                }

                return ['acknowledged' => true, 'changes' => $rows];
            }

            return ['acknowledged' => false, 'changes' => $rows, 'acknowledgement' => Crypt::encryptString(json_encode(['buyer' => $buyer->id, 'until' => now()->addMinutes(10)->timestamp, 'rows' => $rows]))];
        }, 3);
    }

    public function create(User $buyer, array $ids, int $method, string $checkoutId, ?string $code = null, ?int $addressId = null, ?string $note = null): array
    {
        $this->eligible($buyer, 'buyer');
        abort_unless(Str::isUuid($checkoutId), 422, 'A checkout retry identifier is required.');
        $ids = array_values(array_unique($ids));
        sort($ids);
        $hash = hash('sha256', json_encode([$ids, $method, strtoupper($code ?? ''), $addressId, $note]));

        return $this->transaction(function () use ($buyer, $ids, $method, $checkoutId, $code, $addressId, $note, $hash) {
            $prior = GcashPurchase::where('buyer_id', $buyer->id)->where('checkout_id', $checkoutId)->get();
            $preview = CartItem::where('buyer_id', $buyer->id)->whereIn('id', $ids)->get();
            $itemsToLock = $prior->isNotEmpty() ? $prior->flatMap(fn ($p) => $p->snapshot['items'])->all() : $preview->map(fn ($row) => ['product_id' => $row->product_id])->all();
            $productPeek = Product::whereIn('id', array_column($itemsToLock, 'product_id'))->get();
            $products = GcashLocks::resources($itemsToLock, array_merge([$buyer->id], $productPeek->pluck('seller_id')->all()));
            $buyer = User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            $this->eligible($buyer, 'buyer');
            $existing = GcashPurchase::where('buyer_id', $buyer->id)->where('checkout_id', $checkoutId)->orderBy('id')->lockForUpdate()->get();
            if ($existing->isNotEmpty()) {
                if ($existing->contains(fn ($p) => $p->request_hash !== $hash)) {
                    $this->fail('This checkout identifier belongs to different items or instructions.');
                }
                foreach ($existing as $purchase) {
                    DB::afterCommit(fn () => $this->activate($purchase->id));
                }

                return $existing->all();
            }
            $pm = PaymentMethod::whereKey($method)->lockForUpdate()->firstOrFail();
            if ($pm->buyerCheckoutKind() !== 'gcash') {
                $this->fail('Choose an active GCash payment method.');
            }
            $cart = CartItem::where('buyer_id', $buyer->id)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($cart->isEmpty() || $cart->count() !== count($ids) || $cart->contains(fn ($row) => ! $products->has($row->product_id))) {
                $this->fail('Your cart changed. Refresh before retrying.');
            }
            $address = $addressId ? BuyerAddress::where('buyer_id', $buyer->id)->findOrFail($addressId)->toShippingArray() : collect(['house_no', 'street', 'barangay', 'municipality', 'province'])->mapWithKeys(fn ($f) => [$f => $buyer->$f])->all();
            $missing = fn ($value) => ! filled($value) || strcasecmp(trim((string) $value), 'Not provided') === 0;
            foreach (['barangay', 'municipality', 'province'] as $f) {
                if ($missing($address[$f] ?? null)) {
                    $this->fail('Complete your delivery address.');
                }
            }
            if ($missing($address['street'] ?? null) && $missing($address['house_no'] ?? null)) {
                $this->fail('A street or house number is required.');
            }
            $grouped = [];
            foreach ($cart as $row) {
                $product = $products->get($row->product_id);
                if (! $product || $product->status !== 'active') {
                    $this->fail('A selected product is unavailable.');
                }
                $seller = User::whereKey($product->seller_id)->lockForUpdate()->firstOrFail();
                $this->eligible($seller, 'seller');
                if ($row->seller_slug !== $seller->username || $row->qty < 1 || $row->qty > 99) {
                    $this->fail('Invalid cart seller or quantity.');
                }
                $group = $row->variation_group ?: null;
                $value = $row->variation_value ?: null;
                $price = $this->price($product, $group, $value);
                if ($price !== $this->cents($row->price)) {
                    $this->fail('The cart price changed. Refresh and acknowledge the new amount before reserving.');
                }
                $grouped[$seller->id][] = ['product_id' => $product->id, 'name' => $product->name, 'price' => $this->money($price), 'qty' => (int) $row->qty, 'variation_group' => $group, 'variation_value' => $value, 'img' => $row->img, 'seller_slug' => $seller->username, 'cart_id' => $row->id];
            }
            $created = [];
            $voucherApplied = false;
            ksort($grouped);
            foreach ($grouped as $sellerId => $items) {
                $seller = User::whereKey($sellerId)->lockForUpdate()->firstOrFail();
                $subtotal = array_sum(array_map(fn ($i) => $this->cents($i['price']) * $i['qty'], $items));
                $shipping = $this->cents($seller->shipping_fee);
                $discount = 0;
                $voucher = null;
                if ($code) {
                    $voucher = Voucher::where('seller_id', $sellerId)->where('code', strtoupper($code))->lockForUpdate()->first();
                    if ($voucher) {
                        if (! in_array($voucher->type, ['amount', 'discount', 'free_shipping']) || ! $voucher->isUsable() || $subtotal < $this->cents($voucher->minimum_spend)) {
                            $this->fail('Voucher unavailable for this reservation.');
                        } $voucherApplied = true;
                        $discount = $voucher->isFreeShipping() ? $shipping : min($subtotal, $this->cents($voucher->discount_amount));
                    }
                }
                $snapshot = ['items' => $items, 'shipping_address' => $address, 'buyer_note' => $note, 'subtotal' => $this->money($subtotal), 'shipping_amount' => $this->money($shipping), 'discount_amount' => $this->money($discount), 'voucher_id' => $voucher?->id, 'voucher_code' => $voucher?->code];
                $p = GcashPurchase::create(['id' => (string) Str::uuid(), 'buyer_id' => $buyer->id, 'seller_id' => $sellerId, 'checkout_id' => $checkoutId, 'request_hash' => $hash, 'payment_method_id' => $pm->id, 'snapshot' => $snapshot, 'amount_due' => $this->money(max(0, $subtotal + $shipping - $discount)), 'payment_state' => 'awaiting_payment', 'reservation_state' => 'initializing']);
                $this->allocate($p, $products, $voucher);
                $this->event($p, 'created', $buyer);
                $created[] = $p;
                // The deadline begins only after the outermost transaction commits, never before upload/other middleware completion.
                DB::afterCommit(fn () => $this->activate($p->id, now()));
            }
            if ($code && ! $voucherApplied) {
                $this->fail('That voucher does not apply to these sellers.');
            }

            return $created;
        }, 3);
    }

    public function activate(string $id, ?CarbonInterface $committedAt = null): bool
    {
        try {
            $this->transaction(function () use ($id, $committedAt) {
                $p = GcashPurchase::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($p->reservation_state === 'initializing' && ! $p->activated_at) {
                    $start = $committedAt ?? $p->created_at;
                    $p->update(['activated_at' => $start, 'expires_at' => $start->copy()->addMinutes(15)]);
                }
            }, 3);

            return $this->transaction(function () use ($id) {
                [$p, $products] = $this->locked($id);
                if ($p->reservation_state !== 'initializing') {
                    return $p->reservation_state === 'held';
                }
                if ($this->expireLocked($p, $products)) {
                    return false;
                }
                $before = $this->state($p);
                // An interrupted process can recover, but never receive a new fifteen-minute window.
                $start = $p->activated_at ?? now();
                $p->update(['activated_at' => $start, 'expires_at' => $start->copy()->addMinutes(15), 'reservation_state' => 'held']);
                $this->event($p, 'reservation_started', null, $before);
                DB::table('messages')->insert(['sender_id' => $p->buyer_id, 'receiver_id' => $p->seller_id, 'gcash_purchase_id' => $p->id, 'body' => 'GCash payment for '.$p->productLabel().' - amount due PHP '.$p->amount_due.'. Stock reserved for 15 minutes. Awaiting seller payment instructions.', 'read' => false, 'created_at' => now(), 'updated_at' => now()]);
                $this->notify($p, 'GCash reservation started', 'Purchase '.$p->id.' is reserved for 15 minutes. No payment has been verified.');

                return true;
            }, 3);
        } catch (\Throwable $error) {
            Log::warning('Committed GCash purchase awaits activation', ['purchase_id' => $id, 'exception_type' => $error::class]);

            return false;
        }
    }

    private function allocate(GcashPurchase $p, $products, ?Voucher $voucher): void
    {
        $combined = [];
        foreach ($p->snapshot['items'] as $item) {
            $key = $item['product_id'].':'.hash('sha256', json_encode([$item['variation_group'], $item['variation_value']]));
            if (! isset($combined[$key])) {
                $combined[$key] = $item;
            } else {
                $combined[$key]['qty'] += $item['qty'];
            }
        }
        foreach ($combined as $item) {
            GcashInventory::move($products[$item['product_id']], $item['qty'], $item['variation_group'], $item['variation_value']);
            DB::table('gcash_stock_allocations')->insert(['purchase_id' => $p->id, 'product_id' => $item['product_id'], 'product_reference' => $item['product_id'], 'generation' => $p->generation, 'option_key' => hash('sha256', json_encode([$item['variation_group'], $item['variation_value']])), 'variation_group' => $item['variation_group'], 'variation_value' => $item['variation_value'], 'quantity' => $item['qty'], 'state' => 'held', 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($voucher) {
            DB::table('gcash_voucher_allocations')->insert(['purchase_id' => $p->id, 'voucher_id' => $voucher->id, 'voucher_reference' => $voucher->id, 'generation' => $p->generation, 'state' => 'held', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function release(GcashPurchase $p, $products): void
    {
        $rows = DB::table('gcash_stock_allocations')->where('purchase_id', $p->id)->whereIn('state', ['held', 'paid', 'transferred'])->lockForUpdate()->get();
        foreach ($rows as $row) {
            $product = $products->get($row->product_id);
            if (! $product) {
                $this->fail('Allocated product is missing. Manual inventory reconciliation is required.');
            }
            GcashInventory::move($product, -$row->quantity, $row->variation_group, $row->variation_value);
            DB::table('gcash_stock_allocations')->where('id', $row->id)->update(['state' => 'released', 'released_at' => now(), 'updated_at' => now()]);
        }
        $this->releaseVoucher($p);
    }

    private function releaseVoucher(GcashPurchase $p): void
    {
        $id = $p->snapshot['voucher_id'] ?? null;
        if (! $id) {
            return;
        }
        $voucher = Voucher::whereKey($id)->lockForUpdate()->first();
        $active = DB::table('gcash_voucher_allocations')->where('purchase_id', $p->id)->where('state', 'held')->lockForUpdate()->get();
        if ($active->isEmpty()) {
            return;
        }
        if (! $voucher) {
            $this->fail('The allocated voucher is missing. Manual reconciliation is required.');
        }
        DB::table('gcash_voucher_allocations')->where('purchase_id', $p->id)->where('state', 'held')->update(['state' => 'released', 'updated_at' => now()]);
    }

    private function expireLocked(GcashPurchase $p, $products): bool
    {
        if (! in_array($p->reservation_state, ['held', 'initializing'])) {
            return false;
        }
        $expired = $p->expires_at ? $p->expires_at->lte(now()) : $p->created_at->lte(now()->subMinutes(15));
        if (! $expired) {
            return false;
        }
        $before = $this->state($p);
        $this->release($p, $products);
        $p->update(['reservation_state' => 'expired', 'payment_state' => in_array($p->payment_state, ['proof_submitted', 'manual_resolution']) ? 'manual_resolution' : $p->payment_state, 'revision' => $p->revision + 1]);
        $this->event($p, 'expired', null, $before);
        $this->notify($p, 'GCash reservation expired', 'Stock released. If money was sent, report it for manual resolution. No refund is assumed.');

        return true;
    }

    public function expire(string $id): bool
    {
        return $this->transaction(function () use ($id) {
            [$p,$products] = $this->locked($id);

            return $this->expireLocked($p, $products);
        }, 3);
    }

    public function sweep(): int
    {
        $n = 0;
        foreach (GcashPurchase::where('reservation_state', 'initializing')->pluck('id') as $initializing) {
            $this->activate($initializing);
        }
        foreach (GcashPurchase::whereIn('reservation_state', ['held', 'initializing'])->where(fn ($q) => $q->where('expires_at', '<=', now())->orWhere(fn ($r) => $r->whereNull('expires_at')->where('created_at', '<=', now()->subMinutes(15))))->pluck('id') as $id) {
            if ($this->expire($id)) {
                $n++;
            }
        }

        return $n;
    }

    public function instructions(User $seller, string $id, int $revision, string $name, string $number): GcashPurchase
    {
        return $this->transaction(function () use ($seller, $id, $revision, $name, $number) {
            [$p,$products] = $this->locked($id);
            $this->owner($seller, $p, 'seller');
            $this->expireLocked($p, $products);
            if ($p->revision !== $revision || $p->reservation_state !== 'held' || ! in_array($p->payment_state, ['awaiting_payment', 'rejected'])) {
                $this->fail('Refresh this purchase before sending instructions.');
            }
            if (! preg_match('/^09[0-9]{9}$/D', $number)) {
                throw ValidationException::withMessages(['number' => 'Enter a valid 11-digit Philippine mobile number starting with 09.']);
            }
            if ($p->instructions) {
                if ($p->instructions['name'] === trim($name) && $p->instructions['number'] === $number) {
                    return $p;
                }
                $this->fail('Payment instructions already exist. Reconcile the original payment before changing its destination.');
            }
            // Existing instructions above remain immutable, including legacy names.
            if (mb_strlen(trim($name)) > 150 || ! preg_match('/^[\p{L}\p{N}]+(?: [\p{L}\p{N}]+)*$/uD', trim($name))) {
                throw ValidationException::withMessages(['name' => 'Use letters, numbers and single spaces for the GCash account name (up to 150 characters).']);
            }
            $before = $this->state($p);
            $p->update(['instructions' => ['name' => trim($name), 'number' => $number, 'amount' => $p->amount_due], 'revision' => $p->revision + 1]);
            $this->event($p, 'instructions', $seller, $before);
            $this->notify($p, 'GCash payment instructions', 'The seller provided payment details for purchase '.$p->id.'.', true, false);

            return $p;
        }, 3);
    }

    public function proof(User $buyer, string $id, string $submission, string $path, string $mime, int $size, string $contentHash): GcashPurchase
    {
        return $this->transaction(function () use ($buyer, $id, $submission, $path, $mime, $size, $contentHash) {
            [$p,$products] = $this->locked($id);
            $this->owner($buyer, $p, 'buyer');
            $this->expireLocked($p, $products);
            $existing = DB::table('gcash_proofs')->where('purchase_id', $id)->where('submission_id', $submission)->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->content_hash, $contentHash)) {
                    $this->fail('This submission identifier was used for another receipt.');
                }

                return $p;
            }
            if (! Str::isUuid($submission) || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']) || $size < 1 || $size > 5 * 1024 * 1024 || ! preg_match('#^gcash-proofs/[A-Za-z0-9-]+\.(jpg|jpeg|png|webp|pdf)$#D', $path)) {
                $this->fail('Invalid private receipt metadata.');
            }
            if (in_array($p->payment_state, ['verified', 'order_placed']) || $p->reservation_state === 'initializing') {
                $this->fail('This purchase cannot accept new proof.');
            }
            if ($p->reservation_state === 'held' && ! $p->instructions) {
                $this->fail('Wait for seller payment instructions.');
            }
            DB::table('gcash_proofs')->insert(['id' => (string) Str::uuid(), 'purchase_id' => $id, 'submission_id' => $submission, 'submitted_by' => $buyer->id, 'content_hash' => $contentHash, 'path' => $path, 'mime' => $mime, 'size' => $size, 'created_at' => now()]);
            $before = $this->state($p);
            $p->update(['payment_state' => $p->reservation_state !== 'held' ? 'manual_resolution' : 'proof_submitted', 'revision' => $p->revision + 1]);
            $this->event($p, 'proof_submitted', $buyer, $before);
            $this->notify($p, 'GCash proof submitted', 'Receipt recorded; actual receipt of money still requires seller verification.');

            return $p;
        }, 3);
    }

    public function reportPaid(User $buyer, string $id, string $reason): GcashPurchase
    {
        return $this->transaction(function () use ($buyer, $id, $reason) {
            [$p,$products] = $this->locked($id);
            $this->owner($buyer, $p, 'buyer');
            $this->expireLocked($p, $products);
            if ($p->payment_state === 'manual_resolution') {
                return $p;
            }
            if ($p->reservation_state !== 'expired') {
                $this->fail('For a live purchase, submit payment proof instead.');
            }
            $before = $this->state($p);
            $p->update(['payment_state' => 'manual_resolution', 'revision' => $p->revision + 1]);
            $this->event($p, 'payment_reported', $buyer, $before, $reason);
            $this->notify($p, 'GCash payment needs resolution', 'Buyer reports money sent after expiry. Check actual receipt; stock is not reserved.');

            return $p;
        }, 3);
    }

    public function decide(User $seller, string $id, int $revision, bool $accept, ?string $amount = null, ?string $reason = null, ?string $reference = null): GcashPurchase
    {
        $reference = $reference === null ? null : strtoupper(trim($reference));
        // Expiration must commit even if the following attempted decision is rejected.
        $this->owner($seller, GcashPurchase::findOrFail($id), 'seller');
        $this->expire($id);

        return $this->transaction(function () use ($seller, $id, $revision, $accept, $amount, $reason, $reference) {
            [$p,$products] = $this->locked($id);
            $this->owner($seller, $p, 'seller');
            if ($p->revision !== $revision || $p->reservation_state !== 'held' || $p->expires_at->lte(now()) || $p->payment_state !== 'proof_submitted') {
                $this->fail('Stale, expired or unresolved payment. Refresh and use manual resolution where required.');
            }
            if ($accept && $this->cents($amount) !== $this->cents($p->amount_due)) {
                throw ValidationException::withMessages(['amount' => 'The actual amount received must exactly match the agreed amount.']);
            }
            if (! $accept && ! filled($reason)) {
                $this->fail('A rejection reason is required.');
            }
            if ($accept) {
                $this->reference($p, $reference);
                $this->freshPrices($p, $products);
            }
            $before = $this->state($p);
            $p->update(['payment_state' => $accept ? 'verified' : 'rejected', 'reservation_state' => $accept ? 'paid' : 'held', 'payment_reference' => $accept ? $reference : null, 'verified_amount' => $accept ? $p->amount_due : null, 'verified_by' => $accept ? $seller->id : null, 'verified_at' => $accept ? now() : null, 'revision' => $p->revision + 1]);
            if ($accept) {
                DB::table('gcash_stock_allocations')->where('purchase_id', $id)->where('state', 'held')->update(['state' => 'paid', 'updated_at' => now()]);
            }
            $this->event($p, $accept ? 'verified' : 'rejected', $seller, $before, $reason);
            $this->notify($p, $accept ? 'GCash payment verified' : 'GCash proof rejected', $accept ? 'Seller confirmed actual receipt. You may place this linked order.' : 'Seller rejected proof: '.$reason);

            return $p;
        }, 3);
    }

    public function resolve(User $seller, string $id, int $revision, string $amount, string $reason, bool $reacquire, string $reference): GcashPurchase
    {
        $reference = strtoupper(trim($reference));

        return $this->transaction(function () use ($seller, $id, $revision, $amount, $reason, $reacquire, $reference) {
            [$p,$products] = $this->locked($id);
            $this->owner($seller, $p, 'seller');
            if ($p->revision !== $revision || $p->payment_state !== 'manual_resolution' || ! in_array($p->reservation_state, ['expired', 'released']) || ! filled($reason)) {
                $this->fail('Refresh the unresolved expired purchase and provide a resolution reason.');
            }
            if ($this->cents($amount) !== $this->cents($p->amount_due)) {
                $this->fail('Amount mismatch requires manual repayment reconciliation.');
            }
            $this->reference($p, $reference);
            $p->payment_reference = $reference;
            $before = $this->state($p);
            if ($reacquire) {
                if ($p->reservation_state !== 'expired' || Order::where('gcash_purchase_id', $p->id)->exists()) {
                    $this->fail('An existing order cannot be recreated. Record manual repayment resolution without reacquiring stock.');
                }
                $this->freshPrices($p, $products);
                $p->generation++;
                $voucher = null;
                $id = $p->snapshot['voucher_id'] ?? null;
                if ($id) {
                    $voucher = Voucher::whereKey($id)->lockForUpdate()->firstOrFail();
                    if ((int) $voucher->seller_id !== (int) $p->seller_id || ! in_array($voucher->type, ['amount', 'discount', 'free_shipping']) || ! $voucher->isUsable() || $this->cents($p->snapshot['subtotal']) < $this->cents($voucher->minimum_spend) || ($voucher->isFreeShipping() ? $this->cents($p->snapshot['shipping_amount']) : min($this->cents($p->snapshot['subtotal']), $this->cents($voucher->discount_amount))) !== $this->cents($p->snapshot['discount_amount'])) {
                        $this->fail('The original discount cannot be reallocated. Explicit amount reconciliation is required.');
                    }
                }
                $this->allocate($p, $products, $voucher);
                DB::table('gcash_stock_allocations')->where('purchase_id', $p->id)->where('state', 'held')->update(['state' => 'paid', 'updated_at' => now()]);
                $p->reservation_state = 'paid';
                $p->payment_state = 'verified';
            }
            $p->verified_amount = $p->amount_due;
            $p->verified_by = $seller->id;
            $p->verified_at = now();
            $p->revision++;
            $p->save();
            $this->event($p, $reacquire ? 'stock_reacquired' : 'repayment_required', $seller, $before, $reason);
            $this->notify($p, 'GCash manual resolution', $reacquire ? 'Seller confirmed receipt and explicitly reacquired stock at the original amount.' : 'Seller confirmed receipt but fulfillment is unresolved. Manual repayment is required; no refund is recorded.');

            return $p;
        }, 3);
    }

    private function assertPlacement(GcashPurchase $p, $products, int $revision): void
    {
        if ($p->revision !== $revision || $p->payment_state !== 'verified' || $p->reservation_state !== 'paid' || ! $p->verified_at || (int) $p->verified_by !== (int) $p->seller_id || ! $p->payment_reference || $this->cents($p->verified_amount) !== $this->cents($p->amount_due)) {
            $this->fail('Only this verified, allocated purchase can be placed.');
        }
        $this->freshPrices($p, $products);
        $allocations = DB::table('gcash_stock_allocations')->where('purchase_id', $p->id)->where('generation', $p->generation)->where('state', 'paid')->lockForUpdate()->get();
        $expected = [];
        foreach ($p->snapshot['items'] as $item) {
            $key = $item['product_id'].':'.hash('sha256', json_encode([$item['variation_group'], $item['variation_value']]));
            $expected[$key] = ($expected[$key] ?? 0) + $item['qty'];
        }
        $actual = [];
        foreach ($allocations as $a) {
            $actual[$a->product_id.':'.$a->option_key] = $a->quantity;
        }
        ksort($expected);
        ksort($actual);
        if ($expected !== $actual) {
            $this->fail('The paid allocation does not match every agreed item. Manual inventory reconciliation is required.');
        }
    }

    public function placement(User $buyer, string $id): array
    {
        return $this->transaction(function () use ($buyer, $id) {
            [$p,$products] = $this->locked($id);
            $this->owner($buyer, $p, 'buyer');
            try {
                $this->assertPlacement($p, $products, $p->revision);

                return ['eligible' => true, 'reason' => null];
            } catch (ValidationException $e) {
                return ['eligible' => false, 'reason' => $e->errors()['gcash'][0] ?? 'Payment is not ready for order placement.'];
            }
        }, 3);
    }

    public function place(User $buyer, string $id, int $revision): Order
    {
        $this->owner($buyer, GcashPurchase::findOrFail($id), 'buyer');
        $this->expire($id);

        return $this->transaction(function () use ($buyer, $id, $revision) {
            [$p,$products] = $this->locked($id);
            $this->owner($buyer, $p, 'buyer');
            $existing = Order::where('gcash_purchase_id', $id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $this->assertPlacement($p, $products, $revision);
            $snapshot = $p->snapshot;
            $pm = PaymentMethod::findOrFail($p->payment_method_id);
            $order = Order::create(['order_number' => 'PF-'.now()->format('YmdHis').'-'.strtoupper(Str::random(5)), 'buyer_id' => $p->buyer_id, 'seller_id' => $p->seller_id, 'status' => 'placed', 'items' => $snapshot['items'], 'subtotal' => $snapshot['subtotal'], 'shipping_amount' => $snapshot['shipping_amount'], 'discount_amount' => $snapshot['discount_amount'], 'voucher_code' => $snapshot['voucher_code'], 'total' => $p->amount_due, 'shipping_address' => $snapshot['shipping_address'], 'buyer_note' => $snapshot['buyer_note'], 'payment_method_id' => $pm->id, 'payment_method' => 'GCash', 'gcash_purchase_id' => $id]);
            $voucherId = $snapshot['voucher_id'] ?? null;
            if ($voucherId) {
                $voucher = Voucher::whereKey($voucherId)->lockForUpdate()->firstOrFail();
                $allocation = DB::table('gcash_voucher_allocations')->where('purchase_id', $id)->where('generation', $p->generation)->where('state', 'held')->lockForUpdate()->first();
                if (! $allocation) {
                    $this->fail('Voucher allocation missing.');
                }$voucher->increment('used_count');
                DB::table('gcash_voucher_allocations')->where('id', $allocation->id)->update(['state' => 'used', 'updated_at' => now()]);
            }
            DB::table('gcash_stock_allocations')->where('purchase_id', $id)->where('state', 'paid')->update(['state' => 'transferred', 'updated_at' => now()]);
            $before = $this->state($p);
            $p->update(['payment_state' => 'order_placed', 'reservation_state' => 'transferred', 'revision' => $p->revision + 1]);
            $order->logStatus('placed', $buyer->id, 'GCash prepayment verified by seller.');
            $this->event($p, 'order_placed', $buyer, $before);
            $this->notify($p, 'GCash order placed', 'Order '.$order->order_number.' created from this purchase.');
            // Remove only unchanged cart rows; later cart edits never alter the paid snapshot.
            foreach ($snapshot['items'] as $item) {
                $cart = CartItem::where('buyer_id', $buyer->id)->where('id', $item['cart_id'])->where('product_id', $item['product_id'])->where('qty', $item['qty'])->where('price', $item['price']);
                foreach (['variation_group', 'variation_value'] as $field) {
                    $item[$field] === null ? $cart->where(fn ($q) => $q->whereNull($field)->orWhere($field, '')) : $cart->where($field, $item[$field]);
                }
                $cart->delete();
            }

            return $order;
        }, 3);
    }

    private function reference(GcashPurchase $p, ?string $reference): void
    {
        if ($p->payment_reference && $p->payment_reference !== $reference) {
            $this->fail('The recorded GCash transaction reference is immutable.');
        }
        if (! $reference || ! preg_match('/^[A-Za-z0-9-]{6,64}$/D', $reference)) {
            throw ValidationException::withMessages(['reference' => 'Use 6 to 64 letters, numbers or hyphens for the actual GCash transaction reference.']);
        }
        if (GcashPurchase::where('payment_reference', $reference)->where('id', '!=', $p->id)->exists()) {
            throw ValidationException::withMessages(['reference' => 'This reference number has already been submitted. Check your transaction details.']);
        }
    }

    public function abandon(User $buyer, string $id, int $revision): GcashPurchase
    {
        return $this->transaction(function () use ($buyer, $id, $revision) {
            [$p,$products] = $this->locked($id);
            $this->owner($buyer, $p, 'buyer');
            if (in_array($p->reservation_state, ['released', 'expired'])) {
                return $p;
            }
            if ($p->revision !== $revision || ! in_array($p->reservation_state, ['held', 'initializing'])) {
                $this->fail('A verified/ordered purchase requires manual payment resolution, not abandonment.');
            }
            $before = $this->state($p);
            $this->release($p, $products);
            $p->update(['reservation_state' => 'released', 'payment_state' => $p->payment_state === 'proof_submitted' ? 'manual_resolution' : $p->payment_state, 'revision' => $p->revision + 1]);
            $this->event($p, 'abandoned', $buyer, $before);
            $this->notify($p, 'GCash purchase abandoned', 'Allocation released. Report any money already sent for manual resolution.');

            return $p;
        }, 3);
    }

    public function requestResolution(User $actor, string $id, int $revision, string $reason): GcashPurchase
    {
        return $this->transaction(function () use ($actor, $id, $revision, $reason) {
            [$p,$products] = $this->locked($id);
            $role = $actor->account_type;
            if (! in_array($role, ['buyer', 'seller'])) {
                abort(403);
            }$this->owner($actor, $p, $role);
            if ($p->payment_state === 'manual_resolution') {
                return $p;
            }
            if ($p->revision !== $revision || ! in_array($p->reservation_state, ['held', 'paid', 'expired', 'released']) || ! filled($reason)) {
                $this->fail('Refresh this purchase and supply a reconciliation reason. Placed orders use the existing cancellation workflow.');
            }
            $before = $this->state($p);
            $this->release($p, $products);
            $p->update(['reservation_state' => 'expired', 'payment_state' => 'manual_resolution', 'revision' => $p->revision + 1]);
            $this->event($p, 'resolution_requested', $actor, $before, $reason);
            $this->notify($p, 'GCash manual resolution requested', 'Allocation released; payment history preserved. No refund is assumed.');

            return $p;
        }, 3);
    }

    public function assertOrderOperation(Order $order, bool $handoff = false): void
    {
        if (! $order->gcash_purchase_id) {
            return;
        }
        $purchase = GcashPurchase::whereKey($order->gcash_purchase_id)->lockForUpdate()->firstOrFail();
        if (auth()->check() && in_array(auth()->user()->account_type, ['buyer', 'seller'])) {
            $this->owner(auth()->user(), $purchase, auth()->user()->account_type);
        }
        if ($handoff && ($purchase->payment_state !== 'order_placed' || $purchase->reservation_state !== 'transferred')) {
            $this->fail('This prepaid purchase is unresolved. New pickup and handoff are blocked; use the existing cancellation or manual-resolution workflow.');
        }
    }

    public function releaseOrder(Order $order): void
    {
        if (! $order->gcash_purchase_id) {
            return;
        }
        [$p,$products] = $this->locked($order->gcash_purchase_id);
        if ($p->reservation_state === 'released') {
            return;
        }$before = $this->state($p);
        $this->release($p, $products);
        $p->update(['reservation_state' => 'released', 'payment_state' => 'manual_resolution', 'revision' => $p->revision + 1]);
        $this->event($p, 'order_stock_released', auth()->user(), $before, 'Order cancelled or returned. Payment/refund remains unresolved.');
    }

    public function productRejected(Product $product): void
    {
        $ids = DB::table('gcash_stock_allocations')->where('product_id', $product->id)->whereIn('state', ['held', 'paid', 'transferred'])->pluck('purchase_id')->unique();
        foreach ($ids as $id) {
            [$p,$products] = $this->locked($id);
            if ($p->payment_state === 'manual_resolution') {
                continue;
            }$before = $this->state($p);
            if ($p->reservation_state !== 'transferred') {
                $this->release($p, $products);
                $p->reservation_state = 'expired';
            }$p->payment_state = 'manual_resolution';
            $p->revision++;
            $p->save();
            $this->event($p, 'product_rejected', auth()->user(), $before, 'Listing rejected; preserve payment evidence and reconcile manually.');
            $this->notify($p, 'GCash purchase requires resolution', 'A linked listing was rejected. No payment or refund is assumed.');
        }
    }
}
