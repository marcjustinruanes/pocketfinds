<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One hop of a shipment's hub-to-hub journey. A same-province transfer is
 * always exactly one 'direct' leg (unchanged from before this table existed).
 * A cross-province transfer expands into up to three: 'dispatch' (origin
 * municipality hub -> origin's own regional hub), 'relay' (regional hub ->
 * regional hub, the long-haul leg), and 'delivery' (destination regional hub
 * -> destination municipality hub) — dispatch/delivery legs are skipped
 * whenever a side's municipality hub already IS that province's regional hub,
 * so a company with only one hub per province never sees the extra hops. See
 * LogisticsHub::buildRoute() for how the route is computed.
 */
class ShipmentHubLeg extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'id', 'shipment_id', 'sequence', 'leg_type', 'from_hub', 'to_hub',
        'from_province', 'to_province', 'rider_id', 'status', 'started_at', 'rider_confirmed_pickup_at', 'completed_at',
        'requested_at', 'requested_by', 'approved_at', 'approved_by', 'transfer_batch_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'rider_confirmed_pickup_at' => 'datetime',
        'completed_at' => 'datetime',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            $m->id = $m->id ?: (string) Str::uuid();
            $shipment = $m->shipment;
            if ($shipment) {
                $m->from_province ??= $m->leg_type === 'delivery' ? $shipment->destination_province : $shipment->origin_province;
                $m->to_province ??= $m->leg_type === 'dispatch' ? $shipment->origin_province : $shipment->destination_province;
            }
        });
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function transferBatch()
    {
        return $this->belongsTo(HubTransferBatch::class, 'transfer_batch_id');
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Legs belonging to shipments of this company — the same scoping every LogisticsController query uses. */
    public function scopeForCompany($query, string $companyName)
    {
        return $query->whereHas('shipment', fn ($q) => $q->where('logistics_company', $companyName));
    }

    /** Case-insensitive — hub names come from PSGC but casing isn't worth being strict about. */
    public function scopeFromHub($query, string $municipality, ?string $province = null)
    {
        return $query->whereRaw('LOWER(from_hub) = ?', [mb_strtolower(trim($municipality))])
            ->when($province, fn ($q) => $q->whereRaw('LOWER(from_province) = ?', [mb_strtolower(trim($province))]));
    }
}
