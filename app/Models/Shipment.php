<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Shipment extends Model
{
    protected $keyType      = 'string';
    public    $incrementing = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'id', 'order_id', 'tracking_number', 'courier_id', 'shipping_status',
        'scheduled_pickup_at', 'picked_up_at', 'in_transit_at', 'out_for_delivery_at', 'delivered_at',
    ];

    protected $casts = [
        'scheduled_pickup_at' => 'datetime',
        'picked_up_at'        => 'datetime',
        'in_transit_at'       => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'delivered_at'        => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid());
    }

    public function order()      { return $this->belongsTo(Order::class); }
    public function courier()    { return $this->belongsTo(User::class, 'courier_id'); }
    public function assignment() { return $this->hasOne(DeliveryAssignment::class); }
}
