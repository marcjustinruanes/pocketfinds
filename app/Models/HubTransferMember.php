<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HubTransferMember extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['received_at' => 'datetime'];

    public function leg()
    {
        return $this->belongsTo(ShipmentHubLeg::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
