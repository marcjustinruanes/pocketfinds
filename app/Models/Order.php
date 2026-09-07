<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    // `orders.id` is a Postgres uuid. Without these, Eloquent assumes an
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

    public function buyer() { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller() { return $this->belongsTo(User::class, 'seller_id'); }
    public function paymentMethod() { return $this->belongsTo(PaymentMethod::class); }
    public function shipment() { return $this->hasOne(Shipment::class); }
    public function review() { return $this->hasOne(Review::class); }

    /**
     * This order's items, with each one's image guaranteed to be a real, working
     * URL. `items` is a point-in-time JSON snapshot taken at checkout — some
     * older orders were saved with no image at all (a since-fixed cart bug), and
     * some predate the move to Supabase Storage and still carry a local
     * "/storage/..." path that no longer resolves. Both cases fall back here to
     * the product's current photo instead of ever showing a broken image.
     */
    public function itemsWithImages(): array
    {
        $items = $this->items ?? [];
        $productIds = collect($items)->pluck('product_id')->filter()->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        return collect($items)->map(function ($item) use ($products) {
            $img = $item['img'] ?? null;
            if (!$img || !str_starts_with($img, 'http')) {
                $product = $products->get($item['product_id'] ?? null);
                $path = $product?->image ?: ($product?->images[0] ?? null);
                $item['img'] = $path ? rtrim(config('filesystems.disks.supabase.url'), '/') . '/' . ltrim($path, '/') : null;
            }
            return $item;
        })->all();
    }
}
