<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tracks a parcel through the ERP pipeline:
 *   placed -> confirmed -> preparing -> ready_for_pickup (seller handed off; stays
 *   invisible to pickup riders until the company admin sets pickup_approved_at — see
 *   LogisticsController::approveRequest()) -> picked_up (needs BOTH seller_confirmed_pickup_at
 *   AND rider_confirmed_pickup_at — see maybeAdvancePastPickupConfirmation() — before it
 *   actually advances; pickup rider carries seller -> origin hub) -> at_sorting_center ->
 *   [hub_transfer if origin_hub != destination_hub, else skipped] -> sorted ->
 *   assigned_to_rider -> out_for_delivery (delivery rider, destination hub -> buyer) ->
 *   delivered -> completed
 * with delivery_failed/returned as a failure branch off out_for_delivery.
 *
 * `courier_id` is the DELIVERY rider (destination hub -> buyer) — kept under its original
 * name since every existing FK/relation/view already points at it correctly as that leg.
 * `pickup_rider_id` is the PICKUP rider (seller -> origin hub) leg.
 * `hub_transfer_rider_id` mirrors whichever rider is CURRENTLY carrying the active
 * hub-to-hub leg — only ever set when origin_hub and destination_hub differ. The
 * shipment stays at the single 'hub_transfer' status for the whole hub-to-hub
 * journey; the actual hop-by-hop detail (one hop for a same-province transfer, up to
 * three for a cross-province one — local hub -> regional hub -> regional hub -> local
 * hub) lives in ShipmentHubLeg (see hubLegs()/buildHubLegs()). `logistics_company`
 * picks which company's own hub network (see LogisticsHub) is servicing this
 * shipment, and scopes every rider/staff query to that one company.
 */
class Shipment extends Model
{
    protected $keyType      = 'string';
    public    $incrementing = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /** The full pipeline, in order. Kept here as the single source of truth for every leg. */
    const STATUSES = [
        'ready_for_pickup', 'picked_up', 'at_sorting_center', 'hub_transfer', 'sorted', 'assigned_to_rider',
        'out_for_delivery', 'delivered', 'completed', 'delivery_failed', 'returned', 'cancelled',
    ];

    protected $fillable = [
        'id', 'order_id', 'tracking_number', 'courier_id', 'pickup_rider_id',
        'logistics_company', 'origin_hub', 'origin_province', 'destination_hub', 'destination_province',
        'hub_transfer_rider_id', 'shipping_status', 'sorted_area', 'delivery_failed_reason',
        'scheduled_pickup_at', 'pickup_approved_at', 'seller_confirmed_pickup_at', 'rider_confirmed_pickup_at',
        'picked_up_at', 'at_sorting_center_at', 'sorted_at', 'assigned_at',
        'in_transit_at', 'out_for_delivery_at', 'delivered_at', 'delivery_failed_at', 'returned_at',
        'hub_transfer_started_at', 'hub_transfer_completed_at',
    ];

    protected $casts = [
        'scheduled_pickup_at'        => 'datetime',
        'pickup_approved_at'         => 'datetime',
        'seller_confirmed_pickup_at' => 'datetime',
        'rider_confirmed_pickup_at'  => 'datetime',
        'picked_up_at'         => 'datetime',
        'at_sorting_center_at' => 'datetime',
        'sorted_at'            => 'datetime',
        'assigned_at'          => 'datetime',
        'in_transit_at'        => 'datetime',
        'out_for_delivery_at'  => 'datetime',
        'delivered_at'         => 'datetime',
        'delivery_failed_at'   => 'datetime',
        'returned_at'          => 'datetime',
        'hub_transfer_started_at'   => 'datetime',
        'hub_transfer_completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid());
    }

    public function order()       { return $this->belongsTo(Order::class); }
    /** The delivery-leg rider (destination hub -> buyer). */
    public function courier()     { return $this->belongsTo(User::class, 'courier_id'); }
    /** The pickup-leg rider (seller -> origin hub). */
    public function pickupRider() { return $this->belongsTo(User::class, 'pickup_rider_id'); }
    /** The hub-to-hub rider (origin hub -> destination hub) — null when both hubs are the same city. */
    public function hubTransferRider() { return $this->belongsTo(User::class, 'hub_transfer_rider_id'); }

    /**
     * Once BOTH the rider (receiving the parcel) and the seller (handing it over) have
     * confirmed the pickup, actually advance the shipment to 'picked_up'. The rider confirms
     * first — SellerController::confirmPickup() won't even let the seller confirm until
     * rider_confirmed_pickup_at is set — so in practice this only ever fires from the
     * seller's side. Mirrors the delivery leg's own two-sided confirmation (rider marks
     * delivered, buyer confirms receipt).
     */
    public function maybeAdvancePastPickupConfirmation(): bool
    {
        if ($this->shipping_status === 'ready_for_pickup' && $this->seller_confirmed_pickup_at && $this->rider_confirmed_pickup_at) {
            $this->update(['shipping_status' => 'picked_up', 'picked_up_at' => now()]);
            return true;
        }
        return false;
    }

    /** True only when this shipment actually needs a hub-to-hub relay leg. */
    public function needsHubTransfer(): bool
    {
        return $this->origin_hub && $this->destination_hub
            && mb_strtolower(trim($this->origin_hub)) !== mb_strtolower(trim($this->destination_hub));
    }

    /** Every hop of this shipment's hub-to-hub journey, in order — see ShipmentHubLeg. */
    public function hubLegs() { return $this->hasMany(ShipmentHubLeg::class)->orderBy('sequence'); }

    /** The leg a rider is currently carrying, if any. */
    public function activeHubLeg(): ?ShipmentHubLeg
    {
        return $this->hubLegs()->where('status', 'in_transit')->first();
    }

    /** The next leg still waiting for a rider to be assigned to it, if any — regardless of request/approval state. */
    public function nextPendingHubLeg(): ?ShipmentHubLeg
    {
        return $this->hubLegs()->where('status', 'pending')->first();
    }

    /**
     * The next leg that's actually ready for a rider — it's been requested by the
     * origin hub's staff AND approved by the company admin (see
     * LogisticsController::requestHubTransfer()/approveHubTransferRequest()).
     * assignHubTransferRider() gates on this, not on nextPendingHubLeg() alone, so a
     * leg can never be assigned before the admin has signed off on it.
     */
    public function nextAssignableHubLeg(): ?ShipmentHubLeg
    {
        return $this->hubLegs()->where('status', 'pending')->whereNotNull('approved_at')->first();
    }

    /**
     * Builds this shipment's hub-to-hub route (see LogisticsHub::buildRoute())
     * and persists it as ShipmentHubLeg rows — called once, when the parcel is
     * first received at the origin hub. A no-op if the legs already exist.
     */
    public function buildHubLegs(): void
    {
        if (!$this->needsHubTransfer() || $this->hubLegs()->exists()) {
            return;
        }

        $route = LogisticsHub::buildRoute(
            $this->logistics_company, $this->origin_hub, $this->origin_province,
            $this->destination_hub, $this->destination_province
        );

        foreach ($route as $index => $leg) {
            $this->hubLegs()->create([
                'sequence' => $index + 1,
                'leg_type' => $leg['leg_type'],
                'from_hub' => $leg['from_hub'],
                'to_hub'   => $leg['to_hub'],
            ]);
        }
    }

    /** @deprecated alias for deliveryAssignment() — kept so existing views ($shipment->assignment) still work. */
    public function assignment()          { return $this->hasOne(DeliveryAssignment::class)->where('leg', 'delivery'); }
    public function pickupAssignment()    { return $this->hasOne(DeliveryAssignment::class)->where('leg', 'pickup'); }
    public function deliveryAssignment()  { return $this->hasOne(DeliveryAssignment::class)->where('leg', 'delivery'); }
    public function hubTransferAssignment() { return $this->hasOne(DeliveryAssignment::class)->where('leg', 'hub_transfer'); }
}
