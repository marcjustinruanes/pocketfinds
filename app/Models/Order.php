<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Order extends Model
{
    // `orders.id` is a UUID string. Without these, Eloquent assumes an
    // auto-incrementing int PK: with $incrementing left true it mangles the
    // in-memory id after create() (casts the generated uuid down to (int) 0);
    // with it declared false, Eloquent skips fetching the id back at all. So
    // the id is generated here, same as Product/Review/Shipment.
    protected $keyType      = 'string';
    public    $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid());
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
    const BUYER_TO_SHIP_STATUSES     = ['placed', 'confirmed', 'preparing', 'ready_for_pickup'];
    const BUYER_IN_TRANSIT_STATUSES  = ['picked_up', 'at_sorting_center', 'hub_transfer', 'sorted', 'assigned_to_rider'];

    protected $fillable = [
        'order_number', 'buyer_id', 'seller_id', 'status', 'items', 'subtotal',
        'shipping_amount', 'discount_amount', 'voucher_code', 'total', 'shipping_address',
        'buyer_note', 'payment_method_id', 'payment_method',
        'cancellation_reason', 'cancellation_note',
    ];

    protected $casts = [
        'items' => 'array',
        'shipping_address' => 'array',
        'subtotal' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /** A parcel the courier marked delivered auto-completes if the buyer never confirms receipt within this many days. */
    const AUTO_COMPLETE_DAYS = 3;

    /** Statuses before a shipment exists — the only ones an order can still be cancelled from. */
    const CANCELLABLE_STATUSES = ['placed', 'confirmed', 'preparing'];

    /** This order's row, locked for the rest of the surrounding DB::transaction — re-read fresh so status checks can't act on a stale copy. */
    public static function lockFresh(string $id): self
    {
        return static::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Puts this order's items back into stock. Stock only leaves inventory once the seller
     * confirms (SellerController::confirmOrder()), so call this only for an order that got
     * past 'placed'. Must run inside a DB::transaction.
     */
    public function restoreStock(): void
    {
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
        if (!$userId) return;
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
        if (!in_array($this->status, $allowedFrom, true)) {
            return false;
        }
        $hadDeductedStock = $this->status !== 'placed';

        $this->update(['status' => 'cancelled', 'cancellation_reason' => $reason, 'cancellation_note' => $note]);
        if ($hadDeductedStock) {
            $this->restoreStock();
        }
        $this->logStatus('cancelled', $actorId, $reason);
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
                if ($order->status !== 'delivered') return;

                $order->update(['status' => 'completed']);
                $order->shipment?->update(['delivered_at' => $order->shipment->delivered_at ?: now()]);
                $order->logStatus('completed', null, 'Auto-completed: buyer did not confirm receipt within ' . self::AUTO_COMPLETE_DAYS . ' days of delivery.');
                static::notifyUser($order->buyer_id, 'Order Completed', 'Order #' . $order->order_number . ' was automatically marked completed.', 'order_status', $order->id);
                static::notifyUser($order->seller_id, 'Order Completed', 'Order #' . $order->order_number . ' was automatically marked completed — the buyer did not respond after delivery.', 'order_delivered', $order->id);
                $completed++;
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

    public function buyer() { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller() { return $this->belongsTo(User::class, 'seller_id'); }
    public function paymentMethod() { return $this->belongsTo(PaymentMethod::class); }
    public function shipment() { return $this->hasOne(Shipment::class); }
    public function review() { return $this->hasOne(Review::class); }

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
            if (!$img || (!str_starts_with($img, 'http') && !str_starts_with($img, '/storage/'))) {
                $product = $products->get($item['product_id'] ?? null);
                $path = $product?->image ?: ($product?->images[0] ?? null);
                $item['img'] = $path ? rtrim(config('filesystems.disks.public.url'), '/') . '/' . ltrim($path, '/') : null;
            }
            return $item;
        })->all();
    }
}
