<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $casts = [
        'picked_up_at'        => 'datetime',
        'in_transit_at'       => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'delivered_at'        => 'datetime',
        'created_at'          => 'datetime',
    ];

    public function order()      { return $this->belongsTo(Order::class, 'order_id'); }
    public function courier()    { return $this->belongsTo(User::class, 'courier_id'); }
    public function assignment() { return $this->hasOne(DeliveryAssignment::class, 'shipment_id'); }
}
