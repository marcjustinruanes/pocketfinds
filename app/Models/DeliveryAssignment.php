<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DeliveryAssignment extends Model
{
    protected $keyType      = 'string';
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = [
        'id', 'shipment_id', 'courier_id', 'status',
        'requested_at', 'accepted_at', 'picked_up_at', 'delivered_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'accepted_at'  => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid());
    }

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function courier()  { return $this->belongsTo(User::class, 'courier_id'); }
}
