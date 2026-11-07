<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DeliveryAssignment extends Model
{
    public function save(array $options = [])
    {
        return \Illuminate\Support\Facades\DB::transaction(fn () => parent::save($options));
    }

    protected $keyType      = 'string';
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = [
        'id', 'shipment_id', 'courier_id', 'status', 'leg',
        'requested_at', 'accepted_at', 'picked_up_at', 'delivered_at', 'expires_at', 'declined_at',
        'revision', 'reassignment',
    ];

    protected $casts = [
        'revision' => 'integer', 'reassignment' => 'array',
        'requested_at' => 'datetime',
        'expires_at' => 'datetime', 'declined_at' => 'datetime',
        'accepted_at'  => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid());
        static::saved(function ($assignment) {
            $acceptedOffer=$assignment->wasChanged('status')
                && in_array($assignment->getOriginal('status'),['offered','requested'],true)
                && in_array($assignment->status,['accepted','assigned_to_rider'],true);
            if ($assignment->wasRecentlyCreated || $assignment->wasChanged('courier_id') || $acceptedOffer) {
                app(\App\Services\LogisticsFinance::class)->snapshotAssignment($assignment);
            }
        });
    }

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function courier()  { return $this->belongsTo(User::class, 'courier_id'); }
}
