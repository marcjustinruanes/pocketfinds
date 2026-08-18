<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'placed_at'  => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function buyer()      { return $this->belongsTo(User::class, 'buyer_id'); }
    public function items()      { return $this->hasMany(OrderItem::class, 'order_id'); }
    public function shipment()   { return $this->hasOne(Shipment::class, 'order_id'); }
    public function history()    { return $this->hasMany(OrderStatusHistory::class, 'order_id'); }
}
