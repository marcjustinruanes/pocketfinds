<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAssignment extends Model
{
    protected $fillable = ['shipment_id', 'courier_id', 'status', 'requested_at'];

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function courier() { return $this->belongsTo(User::class, 'courier_id'); }
}
