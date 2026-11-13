<?php

namespace App\Models;

use App\Services\GcashLocks;
use App\Services\GcashWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    /** Sort cards by their latest order, payment, or refund activity. */
    public function scopeLatestActivity(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $schema = \Illuminate\Support\Facades\Schema::class;
        $fallback = 'COALESCE(orders.updated_at, orders.created_at)';
        $timestamps = [$fallback];
        if ($schema::hasTable('order_status_history')) {
            $timestamps[] = "COALESCE((SELECT MAX(activity.created_at) FROM order_status_history AS activity WHERE activity.order_id = orders.id), $fallback)";
        }
        if ($schema::hasColumn('orders', 'gcash_purchase_id') && $schema::hasTable('gcash_purchase_history')) {
            $timestamps[] = "COALESCE((SELECT MAX(activity.created_at) FROM gcash_purchase_history AS activity WHERE activity.purchase_id = orders.gcash_purchase_id), $fallback)";
        }
        foreach (['cancellation_refunds', 'commerce_returns'] as $table) {
            if ($schema::hasColumn($table, 'updated_at')) {
                $timestamps[] = "COALESCE((SELECT MAX(activity.updated_at) FROM $table AS activity WHERE activity.order_id = orders.id), $fallback)";
            }
        }
        $latest = count($timestamps) === 1 ? $fallback : (DB::connection()->getDriverName() === 'sqlite' ? 'MAX' : 'GREATEST').'('.implode(', ', $timestamps).')';
        return $query->reorder()->orderByRaw($latest.' DESC')->orderByDesc('orders.created_at')->orderByDesc('orders.id');
    }

    public function scopeVisibleToSeller(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('gcash_purchases', 'review_flow')) return $query;
        return $query->whereDoesntHave('gcashPurchase', fn($payment) => $payment->where('review_flow','admin')->whereNull('verified_at'));
    }

    public static function sellerNotificationsQuery(int $sellerId): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('notifications')->where('user_id',$sellerId);
        if (!\Illuminate\Support\Facades\Schema::hasColumn('gcash_purchases','review_flow')) return $query;
        return $query->whereNotExists(fn($payment) => $payment->selectRaw('1')->from('gcash_purchases as p')
            ->whereColumn('p.id','notifications.reference_id')->where('notifications.notification_type','gcash_purchase')
            ->where('p.review_flow','admin')->whereNull('p.verified_at'));
    }

    public function gcashPurchase()
    {
        return $this->belongsTo(GcashPurchase::class);
    }

    public function awaitingAdminPayment(): bool
    {
        return $this->gcash_purchase_id && $this->gcashPurchase?->usesAdminReview()
            && (!$this->gcashPurchase->verified_at || $this->gcashPurchase->payment_state !== 'order_placed');
    }
    // `orders.id` is a UUID string. Without these, Eloquent assumes an
    // auto-incrementing int PK: with $incrementing left true it mangles the
    // in-memory id after create() (casts the generated uuid down to (int) 0);
    // with it declared false, Eloquent skips fetching the id back at all. So
    // the id is generated here, same as Product/Review/Shipment.
    protected $keyType = 'string';

    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($order) {
            $order->id = $order->id ?: (string) Str::uuid();
            $policy = $order->commerce_snapshot['delivery_policy'] ?? null;
            if ($policy && \App\Services\DeliveryGuarantee::installed()) {
                $order->delivery_guaranteed_from = $policy['from'];
                $order->delivery_guaranteed_by = $policy['by'];
            }
        });
    }

    public function save(array $options = [])
    {
        if (! $this->exists || ! $this->gcash_purchase_id) {
            return DB::transaction(fn () => parent::save($options), 3);
        }

        return DB::transaction(function () use ($options) {
            $locked = static::lockFresh($this->id);
            $newHandoff = $this->isDirty('status') && in_array($this->status, ['confirmed', 'preparing', 'ready_for_pickup', 'picked_up']) && in_array($locked->status, ['placed', 'confirmed', 'preparing', 'ready_for_pickup']);
            app(GcashWorkflow::class)->assertOrderOperation($locked, $newHandoff);

            return parent::save($options);
        }, 3);
    }

    protected static function booted(): void
    {
        static::created(fn ($order) => app(\App\Services\CommissionLedger::class)->captureOrder($order));
        static::updating(function ($order) {
            if ($order->isDirty(['shipping_amount','subtotal','discount_amount','total','items','commerce_snapshot','seller_id','gcash_purchase_id','payment_method','payment_method_id'])
                && app(\App\Services\CommissionLedger::class)->installed()
                && DB::table('commission_obligations')->where('order_id', $order->id)->exists()) {
                throw ValidationException::withMessages(['commission' => 'The commission financial snapshot is immutable. Use the refund workflow for adjustments.']);
            }
            if ($order->isDirty(['shipping_amount', 'subtotal', 'discount_amount', 'total', 'items', 'shipping_address', 'commerce_snapshot', 'seller_id', 'payment_method', 'payment_method_id', 'gcash_purchase_id'])
                && app(\App\Services\LogisticsShipping::class)->installed()
                && DB::table('logistics_shipping_obligations')->where('order_id', $order->id)->exists()) {
                throw ValidationException::withMessages(['shipping' => 'This order has a snapshotted shipping obligation. Its financial basis cannot be silently changed.']);
            }
            if ($order->isDirty('status') && in_array($order->status, ['delivered', 'completed'], true)
                && !($order->getOriginal('status') === 'delivered' && $order->status === 'completed')) {
                app(\App\Services\CodCollection::class)->assertRecorded($order);
            }
            if ($order->gcash_purchase_id && $order->isDirty('status') && in_array($order->status, ['confirmed', 'preparing', 'ready_for_pickup'])) {
                $purchase = GcashPurchase::whereKey($order->gcash_purchase_id)->lockForUpdate()->firstOrFail();
                if ($purchase->payment_state === 'manual_resolution') {
                    throw ValidationException::withMessages(['gcash' => 'This prepaid purchase requires manual resolution. Cancel through the existing order workflow before releasing its stock.']);
                }
            }
        });
        static::saved(function ($order) {
            app(\App\Services\CommissionLedger::class)->onOrderSaved($order);
            if ($order->gcash_purchase_id && in_array($order->status, ['delivered', 'completed'])) {
                $count = DB::table('gcash_stock_allocations')->where('purchase_id', $order->gcash_purchase_id)->where('state', 'transferred')->update(['state' => 'fulfilled', 'updated_at' => now()]);
                if ($count) {
                    DB::table('gcash_purchase_history')->insert(['purchase_id' => $order->gcash_purchase_id, 'actor_id' => auth()->id(), 'event' => 'allocation_fulfilled', 'before' => json_encode(['allocation' => 'transferred']), 'after' => json_encode(['allocation' => 'fulfilled', 'order_id' => $order->id, 'order_status' => $order->status]), 'reason' => 'The delivered order no longer depends on the listing.', 'created_at' => now()]);
                }
            }
        });
    }

    /**
     * The full order lifecycle. 'placed'/'confirmed'/'preparing' are seller-side, pre-shipment
     * stages; everything from 'ready_for_pickup' onward mirrors the order's Shipment 1:1 (see
     * Shipment::STATUSES) — once a shipment exists, order.status is kept in lockstep with it.
     */
    const STATUSES = [
        'placed', 'confirmed', 'preparing',
        'ready_for_pickup', 'picked_up', 'at_sorting_center', 'hub_transfer', 'sorted', 'assigned_to_rider',
        'out_for_delivery', 'delivered', 'completed', 'delivery_failed', 'returned', 'cancelled',
    ];

    /** Between "picked up from seller" and "out for delivery" — grouped under one seller-facing tab. */
    const IN_TRANSIT_STATUSES = ['picked_up', 'at_sorting_center', 'hub_transfer', 'sorted', 'assigned_to_rider'];

    /** Buyer-facing groupings — buyers don't need the seller/logistics-level granularity. */
    const BUYER_TO_SHIP_STATUSES = ['placed', 'confirmed', 'preparing', 'ready_for_pickup'];

    const BUYER_IN_TRANSIT_STATUSES = ['picked_up', 'at_sorting_center', 'hub_transfer', 'sorted', 'assigned_to_rider', 'delivery_failed'];

    protected $fillable = [
        'order_number', 'buyer_id', 'seller_id', 'status', 'items', 'subtotal',
        'shipping_amount', 'discount_amount', 'voucher_code', 'total', 'shipping_address',
        'buyer_note', 'payment_method_id', 'payment_method', 'gcash_purchase_id',
        'cancellation_reason', 'cancellation_note',
        'commerce_snapshot',
    ];

    protected $casts = [
        'items' => 'array',
        'commerce_snapshot' => 'array',
        'shipping_address' => 'array',
        'subtotal' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'delivery_guaranteed_from' => 'datetime',
        'delivery_guaranteed_by' => 'datetime',
    ];

    /** A parcel the courier marked delivered auto-completes if the buyer never confirms receipt within this many days. */
    const AUTO_COMPLETE_DAYS = 7;

    /** Statuses before a shipment exists — the only ones an order can still be cancelled from. */
    const CANCELLABLE_STATUSES = ['placed', 'confirmed', 'preparing'];

    /** This order's row, locked for the rest of the surrounding DB::transaction — re-read fresh so status checks can't act on a stale copy. */
    public static function lockFresh(string $id): self
    {
        return GcashLocks::order($id);
    }

    /**
     * Puts this order's items back into stock. Stock only leaves inventory once the seller
     * confirms (SellerController::confirmOrder()), so call this only for an order that got
     * past 'placed'. Must run inside a DB::transaction.
     */
    public function restoreStock(): void
    {
        if ($this->gcash_purchase_id) {
            app(GcashWorkflow::class)->releaseOrder($this);

            return;
        }
        foreach ($this->items ?? [] as $item) {
            $product = Product::where('id', $item['product_id'] ?? null)->lockForUpdate()->first();
            $product?->deductStock(-(int) ($item['qty'] ?? 0), ($item['variation_group'] ?? null) ?: null, ($item['variation_value'] ?? null) ?: null);
        }
    }

    public function logStatus(string $status, ?int $actorId, ?string $notes = null): void
    {
        DB::table('order_status_history')->insert([
            'id' => (string) Str::uuid(), 'order_id' => $this->id, 'status' => $status,
            'changed_by' => $actorId, 'notes' => $notes, 'created_at' => now(),
        ]);
    }

    public static function notifyUser(?int $userId, string $title, string $message, string $type, ?string $orderId): void
    {
        if (! $userId) {
            return;
        }
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $userId, 'title' => $title, 'message' => $message,
            'notification_type' => $type, 'reference_id' => $orderId, 'is_read' => false, 'created_at' => now(),
        ]);
    }

    /**
     * Cancels this order and, when its stock had already been deducted, gives it back.
     * Run inside a DB::transaction on a row from lockFresh(). Returns false — changing
     * nothing — if the order is no longer at one of $allowedFrom.
     */
    public function cancelAndRestock(string $reason, ?string $note, ?int $actorId, array $allowedFrom = self::CANCELLABLE_STATUSES): bool
    {
        if (! in_array($this->status, $allowedFrom, true)) {
            return false;
        }
        $hadDeductedStock = $this->status !== 'placed' || $this->gcash_purchase_id || $this->commerce_snapshot;

        $this->update(['status' => 'cancelled', 'cancellation_reason' => $reason, 'cancellation_note' => $note]);
        if ($hadDeductedStock) {
            $this->restoreStock();
        }
        $this->logStatus('cancelled', $actorId, $reason);
        if(\App\Services\BuyerCommerce::installed()) app(\App\Services\BuyerCommerce::class)->release($this->gcash_purchase_id?:$this->id);
        app(\App\Services\CancellationRefund::class)->initiate($this);

        return true;
    }

    /** Completes every delivered order the buyer never confirmed within AUTO_COMPLETE_DAYS. Safe to call repeatedly. */
    public static function autoCompleteDelivered(): int
    {
        $ids = static::where('status', 'delivered')
            ->where('updated_at', '<', now()->subDays(self::AUTO_COMPLETE_DAYS))->pluck('id');

        $completed = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$completed) {
                $order = static::lockFresh($id);
                if ($order->status !== 'delivered') {
                    return;
                }
                if(\App\Services\BuyerCommerce::installed() && DB::table('commerce_returns')->where('order_id',$order->id)->whereIn('status',['pending','approved','escalated','received','refunded'])->exists()) return;
                $shipment = Shipment::where('order_id', $order->id)->lockForUpdate()->first();
                if ($order->completeDelivered($shipment, null, 'Auto-completed: buyer did not confirm receipt within '.self::AUTO_COMPLETE_DAYS.' days of delivery.')) {
                    $completed++;
                }
            });
        }

        return $completed;
    }

    /** autoCompleteDelivered(), but at most once per 10 minutes — for pages to call, since nothing here runs a cron scheduler. */
    public static function autoCompleteDeliveredThrottled(): void
    {
        try {
            if (Cache::add('orders-auto-complete-sweep', 1, 600)) {
                static::autoCompleteDelivered();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Complete a delivered order and its shipment together. Call from a transaction
     * after locking the order and shipment rows. A missing shipment is tolerated for
     * legacy delivered orders; an existing shipment must still be in the delivered
     * stage or nothing is changed.
     */
    public function completeDelivered(?Shipment $shipment, ?int $actorId, string $notes, bool $buyerConfirmed = false): bool
    {
        if ($this->status !== 'delivered') {
            return false;
        }
        if ($shipment && ((string) $shipment->order_id !== (string) $this->id || $shipment->shipping_status !== 'delivered')) {
            return false;
        }
        if ($buyerConfirmed && (int) $actorId !== (int) $this->buyer_id) {
            return false;
        }

        $completedAt = now();
        if ($shipment) {
            $shipment->update([
                'shipping_status' => 'completed',
                'delivered_at' => $shipment->delivered_at ?: $completedAt,
            ]);
        }
        $orderUpdates = ['status' => 'completed'];
        if ($buyerConfirmed && app(\App\Services\LogisticsTreasury::class)->installed()) {
            // This timestamp is intentionally not mass assignable from request data.
            // Set it directly in this trusted, locked completion transition so COD
            // payout eligibility can distinguish buyer confirmation from auto-completion.
            $this->setAttribute('buyer_confirmed_at', $completedAt);
        }
        $this->update($orderUpdates);
        $this->logStatus('completed', $actorId, $notes);

        static::notifyUser($this->buyer_id, 'Order Completed', 'Order #'.$this->order_number.' is now completed.', 'order_status', $this->id);
        static::notifyUser($this->seller_id, 'Order Completed', 'Order #'.$this->order_number.' has been completed.', 'order_delivered', $this->id);

        return true;
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    /**
     * This order's items, with each one's image guaranteed to be a real, working
     * URL. `items` is a point-in-time JSON snapshot taken at checkout — some
     * older orders were saved with no image at all (a since-fixed cart bug), and
     * older snapshots carry a local "/storage/..." path. Missing images fall back to
     * the product's current photo instead of ever showing a broken image.
     */
    public function itemsWithImages(): array
    {
        $items = $this->items ?? [];
        $productIds = collect($items)->pluck('product_id')->filter()->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        return collect($items)->map(function ($item) use ($products) {
            $img = $item['img'] ?? null;
            if (! $img || (! str_starts_with($img, 'http') && ! str_starts_with($img, '/storage/'))) {
                $product = $products->get($item['product_id'] ?? null);
                $variantImage = null;
                foreach ($product?->variations ?? [] as $group) {
                    if (($group['name'] ?? null) !== ($item['variation_group'] ?? null)) continue;
                    foreach ($group['options'] ?? [] as $option) {
                        if (($option['value'] ?? null) === ($item['variation_value'] ?? $item['color'] ?? null)) {
                            $variantImage = $option['image'] ?? null;
                            break 2;
                        }
                    }
                }
                $path = $img ?: ($variantImage ?: ($product?->image ?: ($product?->images[0] ?? null)));
                $item['img'] = $path ? (str_starts_with($path, 'http') || str_starts_with($path, '/storage/') ? $path : rtrim(config('filesystems.disks.public.url'), '/').'/'.ltrim($path, '/')) : null;
            }

            return $item;
        })->all();
    }
}
