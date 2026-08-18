<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAssignment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['shipment_id', 'courier_id', 'status'];

    protected $casts = [
        'requested_at' => 'datetime',
        'accepted_at'  => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function shipment() { return $this->belongsTo(Shipment::class, 'shipment_id'); }
    public function courier()  { return $this->belongsTo(User::class, 'courier_id'); }
}
