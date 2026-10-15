<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HubTransferBatch extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public const STATUSES = ['pending', 'approved', 'rejected', 'in_transit', 'completed', 'cancelled'];

    protected $casts = ['requested_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'dispatched_at' => 'datetime', 'received_at' => 'datetime', 'cancelled_at' => 'datetime', 'legacy_import' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(fn ($batch) => $batch->id ??= (string) Str::uuid());
    }

    public function members()
    {
        return $this->hasMany(HubTransferMember::class, 'batch_id');
    }

    public function events()
    {
        return $this->hasMany(HubTransferEvent::class, 'batch_id')->orderBy('id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(CompanyVehicle::class, 'vehicle_id');
    }

    public function origin()
    {
        return $this->belongsTo(LogisticsHub::class, 'origin_hub_id');
    }

    public function destination()
    {
        return $this->belongsTo(LogisticsHub::class, 'destination_hub_id');
    }

    public function label(): string
    {
        return ['pending' => 'Pending approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'in_transit' => 'Dispatched / in transit', 'completed' => 'Received', 'cancelled' => 'Cancelled'][$this->status] ?? $this->status;
    }
}
