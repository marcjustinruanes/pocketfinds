<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['buyer_id', 'status', 'total'];

    public function buyer() { return $this->belongsTo(User::class, 'buyer_id'); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function shipment() { return $this->hasOne(Shipment::class); }
    public function shippingAddress() { return $this->belongsTo(UserAddress::class, 'shipping_address_id'); }
}
