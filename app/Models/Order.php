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
}
