<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'buyer_id', 'seller_id', 'status', 'items', 'subtotal',
        'shipping_amount', 'discount_amount', 'total', 'shipping_address', 'payment_method_id',
        'payment_method',
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
}
