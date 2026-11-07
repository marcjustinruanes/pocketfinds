<?php

namespace App\Services;

use App\Models\{GcashPurchase, Order};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Frozen product-only charges. All mutations run under the source/order lock. */
class CommissionLedger
{
    public function installed(): bool { return Schema::hasTable('commission_obligations'); }

    public function fail(string $message): never
    {
        throw ValidationException::withMessages(['commission' => $message]);
    }

    public function cents(mixed $value): int
    {
        $v = (string) $value;
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $v)) $this->fail('Invalid financial amount.');
        [$whole, $fraction] = array_pad(explode('.', $v), 2, '');
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public function activation(): ?CarbonImmutable
    {
        return app(CommissionEnrollment::class)->activation();
    }

    /** Largest remainder allocation, stable by the original line order. */
    public function split(int $amount, array $weights): array
    {
        $sum = array_sum($weights); $out = array_fill_keys(array_keys($weights), 0);
        if (!$amount || !$sum) return $out;
        $remainders = [];
        foreach ($weights as $k => $w) { [$out[$k], $remainders[$k]] = $this->ratio($amount, $w, $sum); }
        arsort($remainders, SORT_NUMERIC);
        $left = $amount - array_sum($out);
        foreach ($remainders as $k => $_) { if (!$left--) break; $out[$k]++; }
        return $out;
    }

    /** Exact quotient/remainder without floating point or an overflowing product. */
    private function ratio(int $a, int $b, int $divisor): array
    {
        if (!$b) return [0,0];
        if ($a<=intdiv(PHP_INT_MAX,$b)) return [intdiv($a*$b,$divisor),($a*$b)%$divisor];
        if ($divisor>intdiv(PHP_INT_MAX,2) || $b>$divisor) $this->fail('Allocation exceeds the supported financial range.');
        $q=$r=0;
        foreach(str_split(decbin($a)) as $bit){
            $q*=2;$r*=2;
            if($r>=$divisor){$r-=$divisor;$q++;}
            if($bit==='1'){$r+=$b;if($r>=$divisor){$r-=$divisor;$q++;}}
        }
        return [$q,$r];
    }

    public function calculate(array $snapshot): array
    {
        $items = $snapshot['items'] ?? []; $weights = [];
        foreach ($items as $k => $item) {
            $qty = (int) ($item['qty'] ?? 0);
            if ($qty < 1 || $qty > 99999) $this->fail('Invalid commission item quantity.');
            $weights[$k] = $this->cents($item['price']) * $qty;
        }
        if (!$items || array_sum($weights) !== $this->cents($snapshot['subtotal'])) $this->fail('Product snapshot does not reconcile with the subtotal.');
        $commerce = $snapshot['commerce'] ?? $snapshot['commerce_snapshot'] ?? null;
        $discount = 0;
        if ($commerce !== null) {
            foreach ($commerce['vouchers'] ?? [] as $v) {
                if (($v['kind'] ?? '') === 'shipping') continue;
                if (!in_array($v['funding_source'] ?? null, ['seller', 'platform'], true)) $this->fail('Product voucher funding must be explicit for commission.');
                if ($v['funding_source'] === 'seller') $discount += $this->cents($v['amount']);
            }
        } elseif (!empty($snapshot['voucher_id']) || !empty($snapshot['voucher_code'])) {
            // Legacy checkout vouchers are seller-issued; their stored type is authoritative.
            $v = DB::table('vouchers')->where('id', $snapshot['voucher_id'] ?? '')->first();
            if (!$v) $this->fail('Legacy voucher funding cannot be established.');
            if ($v->type !== 'free_shipping') $discount = $this->cents($snapshot['discount_amount']);
        } elseif ($this->cents($snapshot['discount_amount'] ?? 0) !== 0) {
            $this->fail('A discount without a funding snapshot requires review.');
        }
        if ($discount > array_sum($weights)) $this->fail('Seller discounts exceed product value.');
        $shares = $this->split($discount, $weights); $basis = [];
        foreach ($weights as $k => $w) $basis[$k] = $w - $shares[$k];
        $fee = intdiv(array_sum($basis) + 5, 10);
        $fees = $this->split($fee, $basis); $lines = [];
        foreach ($items as $k => $i) $lines[] = ['line_key' => $k, 'product_reference' => $i['product_id'] ?? null, 'item_snapshot' => json_encode($i, JSON_THROW_ON_ERROR), 'label' => mb_substr($i['name'] ?? 'Product', 0, 255), 'quantity' => (int) $i['qty'], 'selling_cents' => $weights[$k], 'seller_discount_cents' => $shares[$k], 'basis_cents' => $basis[$k], 'commission_cents' => $fees[$k]];
        return ['basis_cents' => array_sum($basis), 'commission_cents' => $fee, 'lines' => $lines];
    }

    public function capturePurchase(GcashPurchase $p): void
    {
        $this->capture('purchase:'.$p->id, $p->seller_id, 'gcash', $p->snapshot, $p->created_at, null, $p->id);
    }

    public function captureOrder(Order $o): void
    {
        if (!$this->installed()) return;
        if ($o->gcash_purchase_id) {
            DB::table('commission_obligations')->where('purchase_id', $o->gcash_purchase_id)->whereNull('order_id')->update(['order_id' => $o->id, 'updated_at' => now()]);
            return; // Never enroll a historical prepaid purchase at order placement.
        }
        if (!app(CodCollection::class)->isCod($o)) return;
        $snapshot = $o->toArray();
        if ($o->voucher_code && !$o->commerce_snapshot) {
            $snapshot['voucher_id'] = DB::table('vouchers')->where('seller_id', $o->seller_id)->where('code', $o->voucher_code)->value('id');
        }
        $this->capture('order:'.$o->id, $o->seller_id, 'cod', $snapshot, $o->created_at, $o->id, null);
    }

    private function capture(string $key, int $seller, string $method, array $snapshot, $created, ?string $order, ?string $purchase): void
    {
        DB::transaction(fn()=> $this->captureLocked($key,$seller,$method,$snapshot,$created,$order,$purchase),3);
    }

    private function captureLocked(string $key, int $seller, string $method, array $snapshot, $created, ?string $order, ?string $purchase): void
    {
        if (!$this->installed() || !($activation = app(CommissionEnrollment::class)->activation(true)) || now()->lt($activation) || CarbonImmutable::parse($created)->lt($activation)) return;
        if (DB::table('commission_obligations')->where('source_key', $key)->exists()) return;
        $calc = $this->calculate($snapshot); $id = (string) Str::uuid();
        DB::table('commission_obligations')->insert(['id' => $id, 'source_key' => $key, 'seller_id' => $seller, 'method' => $method, 'order_id' => $order, 'purchase_id' => $purchase, 'basis_cents' => $calc['basis_cents'], 'commission_cents' => $calc['commission_cents'], 'activation_at' => $activation, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($calc['lines'] as $line) DB::table('commission_lines')->insert($line + ['obligation_id' => $id]);
        $this->event($id, 'snapshot:'.$key, 'snapshotted', $calc['commission_cents'], ['policy' => 'seller-net-products-v1', 'rate_bps' => 1000]);
    }

    public function event(string $id, string $key, string $kind, int $amount, array $details = [], ?int $actorId = null): void
    {
        DB::table('commission_events')->insert(['obligation_id' => $id, 'event_key' => $key, 'kind' => $kind, 'amount_cents' => $amount, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'actor_id' => $actorId ?? auth()->id(), 'created_at' => now()]);
    }

    public function net(object $o): int { return max(0, (int) $o->commission_cents - (int) $o->reversed_cents); }

    public function collected(string $id): int
    {
        // A plain aggregate can use an older InnoDB repeatable-read snapshot after
        // waiting for the obligation lock. Financial decisions need a current read.
        $amount=(int) DB::table('commission_allocations')->where('obligation_id', $id)->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->get(['allocated_cents'])->sum('allocated_cents');
        if(Schema::hasTable('commission_recovery_allocations'))$amount+=(int)DB::table('commission_recovery_allocations')->where('obligation_id',$id)->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->get(['amount_cents'])->sum('amount_cents');
        return $amount;
    }

    public function due(object $o): int
    {
        if (!$o->earned_at || $o->cancelled_at || $this->held($o)) return 0;
        return max(0, ($o->method === 'cod' ? min((int) $o->withheld_cents, $this->net($o)) : $this->net($o)) - $this->collected($o->id));
    }

    public function held(object $o): bool
    {
        if (!$o->order_id) return false;
        if (DB::table('orders')->where('id',$o->order_id)->whereNotIn('status',['delivered','completed'])->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->first()) return true;
        if (Schema::hasTable('complaints') && DB::table('complaints')->where('order_id',$o->order_id)->where(fn($q)=>$q->whereNull('status')->orWhere('status','!=','resolved'))->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->first()) return true;
        if (Schema::hasTable('commerce_returns')) foreach(DB::table('commerce_returns')->where('order_id',$o->order_id)->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->get() as $r) {
            if ($r->status!=='refunded' && ($r->status!=='rejected' || !$r->decided_at || CarbonImmutable::parse($r->decided_at)->addDays(3)->isFuture())) return true;
        }
        return false;
    }

    public function onOrderSaved(Order $order): void
    {
        if (!$this->installed()) return;
        if ($order->status === 'cancelled') $this->reverse($order->id, 'cancel:'.$order->id, null, 'Order cancelled');
    }

    public function earn(Order $order): ?object
    {
        if (!$this->installed()) return null;
        $o = DB::table('commission_obligations')->where('order_id', $order->id)->lockForUpdate()->first();
        if (!$o || $o->earned_at || $o->cancelled_at) return $o;
        if (!in_array($order->status, ['delivered', 'completed'])) return $o;
        if (Schema::hasTable('complaints') && DB::table('complaints')->where('order_id', $order->id)->where(fn($q) => $q->whereNull('status')->orWhere('status', '!=', 'resolved'))->exists()) return $o;
        if (Schema::hasTable('commerce_returns')) foreach (DB::table('commerce_returns')->where('order_id', $order->id)->get() as $r) {
            if ($r->status !== 'refunded' && ($r->status !== 'rejected' || !$r->decided_at || CarbonImmutable::parse($r->decided_at)->addDays(3)->isFuture())) return $o;
        }
        $shipment = DB::table('shipments')->where('order_id', $order->id)->first();
        $delivered = $shipment->delivered_at ?? DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'delivered')->min('created_at');
        $confirmed = $order->buyer_confirmed_at || DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'completed')->where('changed_by', $order->buyer_id)->exists();
        if (!$delivered || (!$confirmed && CarbonImmutable::parse($delivered)->addHours(168)->isFuture())) return $o;
        if ($o->method === 'gcash' && !DB::table('gcash_purchases')->where('id', $o->purchase_id)->whereNotNull('verified_at')->whereNotNull('payment_reference')->exists()) return $o;
        DB::table('commission_obligations')->where('id', $o->id)->update(['earned_at' => now(), 'due_at' => now()->addDays(config('commission.due_days', 7)), 'updated_at' => now()]);
        $this->event($o->id, 'earned:'.$o->id, 'earned', $this->net($o));
        if ($o->method === 'gcash') Order::notifyUser($o->seller_id, 'Seller commission due', 'Your product commission for '.$order->order_number.' is PHP '.LogisticsReconciliation::money($this->net($o)).'. Review Commission Statements; payment is due in seven days.', 'commission', $order->id);
        return DB::table('commission_obligations')->find($o->id);
    }

    public function sweep(): int
    {
        if (!$this->installed()) return 0;
        $count = 0;
        DB::table('commission_obligations')->whereNull('cancelled_at')->orderBy('id')->chunkById(100, function($rows) use (&$count) {
            foreach ($rows as $o) $count += DB::transaction(function() use ($o) {
                if (!$o->order_id) return 0;
                $order = Order::whereKey($o->order_id)->lockForUpdate()->first();
                if (!$order) return 0;
                // The chunk may predate a concurrent sweep. Count transitions from
                // the current locked row, and only after the transaction commits.
                $current = DB::table('commission_obligations')->where('id', $o->id)->lockForUpdate()->first();
                $this->onOrderSaved($order);
                // Catch bulk status writes and already-recorded refund decisions.
                if (Schema::hasTable('commerce_returns')) foreach (DB::table('commerce_returns')->where('order_id', $order->id)->where('status', 'refunded')->get() as $r) $this->reverse($order->id, 'return:'.$r->id, json_decode($r->item_keys, true), 'Recorded product refund');
                $earned = $this->earn($order);
                if ($earned && $earned->method==='gcash' && $earned->due_at && CarbonImmutable::parse($earned->due_at)->isPast() && $this->due($earned)>0) {
                    $key='reminder:'.$earned->id.':'.now()->toDateString();
                    if (!DB::table('commission_events')->where('event_key',$key)->exists()) {
                        $this->event($earned->id,$key,'overdue_reminder',0);
                        Order::notifyUser($earned->seller_id,'Seller commission overdue','Review your outstanding commission in Commission Statements. Pending proofs require Platform Admin verification.','commission',$order->id);
                    }
                }
                return !$current->earned_at && $earned?->earned_at ? 1 : 0;
            }, 3);
        });
        return $count;
    }

    public function reverse(string $orderId, string $key, ?array $lineKeys, string $reason): void
    {
        if (!$this->installed()) return;
        $o = DB::table('commission_obligations')->where('order_id', $orderId)->lockForUpdate()->first();
        if (!$o || DB::table('commission_events')->where('event_key', $key)->lockForUpdate()->first()) return;
        $lines = DB::table('commission_lines')->where('obligation_id', $o->id)->when($lineKeys !== null, fn($q) => $q->whereIn('line_key', $lineKeys))->get();
        $amount = 0;
        foreach ($lines as $line) {
            $lineKey = 'reverse-line:'.$line->id;
            if (DB::table('commission_events')->where('event_key', $lineKey)->lockForUpdate()->first()) continue;
            $amount += (int) $line->commission_cents;
            $this->event($o->id, $lineKey, 'line_reversed', -(int) $line->commission_cents, ['reason' => $reason]);
        }
        $newReversed = (int) $o->reversed_cents + $amount;
        DB::table('commission_obligations')->where('id', $o->id)->update(['reversed_cents' => $newReversed, 'cancelled_at' => $lineKeys === null ? now() : $o->cancelled_at, 'updated_at' => now()]);
        $this->event($o->id, $key, 'reversed', -$amount, ['reason' => $reason, 'line_keys' => $lineKeys]);
        $net = (int) $o->commission_cents - $newReversed; $collected = $this->collected($o->id);
        $platformCredit = max(0, $collected - $net);
        $logisticsCredit = $o->method === 'cod' ? max(0, (int) $o->withheld_cents - max($net, $collected)) : 0;
        foreach (['platform' => $platformCredit, 'logistics' => $logisticsCredit] as $payer => $required) {
            $prior = (int) DB::table('commission_credits')->where('obligation_id', $o->id)->where('payer', $payer)->where('kind', 'reversal')->lockForUpdate()->get(['amount_cents'])->sum('amount_cents');
            if ($required > $prior) DB::table('commission_credits')->insert(['id' => (string) Str::uuid(), 'obligation_id' => $o->id, 'source_key' => $key.':'.$payer, 'payer' => $payer, 'amount_cents' => $required - $prior, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function codCommission(Order $order): int
    {
        $o = $this->earn($order);
        if (!$o) return 0;
        if (!$o->earned_at || $o->cancelled_at || $this->held($o)) $this->fail('Commission is awaiting delivery or dispute review.');
        return $this->net($o);
    }

    public function platformSubsidy(Order $order): int
    {
        $amount=0;
        foreach($order->commerce_snapshot['vouchers'] ?? [] as $v) if(($v['funding_source'] ?? null)==='platform') $amount += $this->cents($v['amount']);
        return $amount;
    }

    public function prepareCod(string $payoutId, Order $order, object $account, int $fee): void
    {
        if (!$this->installed()) return;
        $o = DB::table('commission_obligations')->where('order_id', $order->id)->lockForUpdate()->first();
        if (!$o) return;
        DB::table('commission_cod_settlements')->updateOrInsert(['payout_id' => $payoutId], ['obligation_id' => $o->id, 'commission_cents' => $fee]);
        DB::table('commission_obligations')->where('id', $o->id)->update(['company_name' => $account->company_name, 'treasury_account_id' => $account->id, 'updated_at' => now()]);
    }

    public function deduction(string $payoutId): int
    {
        return $this->installed() ? (int) DB::table('commission_cod_settlements')->where('payout_id', $payoutId)->value('commission_cents') : 0;
    }

    public function withhold(object $p, object $account, $actor): void
    {
        if (!$this->installed()) return;
        $link = DB::table('commission_cod_settlements')->where('payout_id', $p->id)->lockForUpdate()->first();
        if (!$link || $link->withheld_at) return;
        $o = DB::table('commission_obligations')->where('id', $link->obligation_id)->lockForUpdate()->first();
        if ($this->net($o) !== (int) $link->commission_cents) $this->fail('Commission changed. Cancel and prepare this payout again.');
        if ($link->commission_cents > 0) app(LogisticsTreasury::class)->move($actor, $account, 'commission', (int) $link->commission_cents, 'commission_withheld', $p->id);
        DB::table('commission_obligations')->where('id', $o->id)->update(['withheld_cents' => $link->commission_cents, 'updated_at' => now()]);
        DB::table('commission_cod_settlements')->where('payout_id', $p->id)->update(['withheld_at' => now()]);
        $this->event($o->id, 'withheld:'.$p->id, 'withheld', (int) $link->commission_cents, ['payout_id' => $p->id],$actor->id);
    }
}
