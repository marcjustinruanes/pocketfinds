<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = ['order_id', 'courier_id', 'shipping_status', 'delivered_at'];

    public function order() { return $this->belongsTo(Order::class); }
    public function courier() { return $this->belongsTo(User::class, 'courier_id'); }
    public function assignment() { return $this->hasOne(DeliveryAssignment::class); }
}
