<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One physical vehicle in a logistics company's fleet.
 *
 * Workflow:
 *   1. Logistics admin submits a vehicle, assigning it to one of their hubs.
 *   2. Platform admin (PocketFinds) reviews it — `platform_status` is the gate.
 *   3. Once platform-approved, hub staff can toggle `is_available` (maintenance) without
 *      triggering another review. Riders only see vehicles that are platform-approved AND
 *      is_available = true at their specific hub.
 *
 * `status` / `reviewed_by` / `reviewed_at` / `status_reason` are kept as the logistics
 * admin's own internal condition note (e.g. "marked for service"), separate from the
 * platform approval gate.
 */
class CompanyVehicle extends Model
{
    protected $fillable = [
        'company_name', 'logistics_hub_id', 'vehicle_type', 'brand', 'model', 'plate_number',
        // Logistics-admin internal status (condition notes — not the public rider gate)
        'status', 'status_reason', 'is_available',
        'submitted_by', 'reviewed_by', 'reviewed_at',
        // Platform-admin approval gate
        'platform_status', 'platform_status_reason', 'platform_reviewed_by', 'platform_reviewed_at',
    ];

    protected $casts = [
        'is_available'         => 'boolean',
        'reviewed_at'          => 'datetime',
        'platform_reviewed_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function hub()
    {
        return $this->belongsTo(LogisticsHub::class, 'logistics_hub_id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function platformReviewer()
    {
        return $this->belongsTo(User::class, 'platform_reviewed_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    /** Vehicles visible to riders: platform-approved and currently available (not in maintenance). */
    public function scopeRiderVisible($query)
    {
        return $query->where('platform_status', 'approved')->where('is_available', true);
    }

    // ── Static helpers ─────────────────────────────────────────────

    /**
     * True if the given hub has at least one platform-approved, available vehicle of $vehicleType.
     * Used server-side to gate the rider registration vehicle-type picker.
     */
    public static function typeAvailableAtHub(int $hubId, string $vehicleType): bool
    {
        return static::where('logistics_hub_id', $hubId)
            ->where('vehicle_type', $vehicleType)
            ->where('platform_status', 'approved')
            ->where('is_available', true)
            ->exists();
    }

    /**
     * All vehicle_type slugs with at least one platform-approved + available vehicle at this hub.
     * Passed to the rider registration form to disable unavailable vehicle-type cards.
     */
    public static function availableTypesForHub(int $hubId): array
    {
        return static::where('logistics_hub_id', $hubId)
            ->where('platform_status', 'approved')
            ->where('is_available', true)
            ->distinct()
            ->pluck('vehicle_type')
            ->values()
            ->all();
    }
}
