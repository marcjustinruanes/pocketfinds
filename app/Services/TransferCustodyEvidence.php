<?php

namespace App\Services;

use App\Models\{HubTransferBatch, HubTransferMember, LogisticsHub, Shipment, ShipmentHubLeg};
use Illuminate\Support\Facades\DB;

/** Validate existing physical receipt evidence; never infer or repair legacy witnesses. */
class TransferCustodyEvidence
{
    public function problem(Shipment $shipment, ShipmentHubLeg $next, LogisticsHub $origin): ?string
    {
        $missing = 'Custody needs review: authoritative previous-hub receipt evidence is missing or inconsistent for '.$shipment->tracking_number.'.';
        $previous = $shipment->hubLegs()->where('sequence', '<', $next->sequence)->orderByDesc('sequence')->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->first();
        if ($previous) {
            $batch = HubTransferBatch::whereKey($previous->transfer_batch_id)->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->first();
            $member = $batch?->members()->where('leg_id', $previous->id)->where('shipment_id', $shipment->id)->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->first();
            if (! $batch || $batch->company_name !== $shipment->logistics_company
                || (int) $batch->destination_hub_id !== (int) $origin->id
                || ! in_array($batch->status, ['in_transit', 'completed'], true)
                || ! $batch->dispatched_at || ! $batch->rider_id || (int) $batch->rider_id !== (int) $previous->rider_id
                || $previous->status !== 'completed' || ! $previous->rider_confirmed_pickup_at || ! $previous->completed_at
                || ! $member?->received_by || ! $member->received_at
                || ! app(LogisticsAccess::class)->matches($origin, $previous->to_hub, $previous->to_province)
                || $previous->rider_confirmed_pickup_at->lt($batch->dispatched_at)
                || $previous->completed_at->lt($previous->rider_confirmed_pickup_at)
                || $member->received_at->lt($previous->completed_at)) return $missing;

            return null; // A received member may advance while other members are still in transit.
        }
        if (! app(LogisticsAccess::class)->matches($origin, $shipment->origin_hub, $shipment->origin_province)
            || ! $shipment->at_sorting_center_at || ! $shipment->picked_up_at
            || ! $shipment->seller_confirmed_pickup_at || ! $shipment->rider_confirmed_pickup_at
            || $shipment->at_sorting_center_at->lt($shipment->picked_up_at)
            || $shipment->picked_up_at->lt($shipment->seller_confirmed_pickup_at)
            || $shipment->picked_up_at->lt($shipment->rider_confirmed_pickup_at)) return $missing;

        $receipt = DB::table('order_status_history')->where('order_id', $shipment->order_id)
            ->where('status', 'at_sorting_center')->where('created_at', $shipment->at_sorting_center_at)
            ->whereNotNull('changed_by')->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->get();
        foreach ($receipt as $history) {
            if (DB::table('logistics_activity_logs')->where('company_name', $shipment->logistics_company)
                ->where('hub_id', $origin->id)->where('actor_id', $history->changed_by)
                ->where('action', 'logistics.scan.receive')->where('record_id', $shipment->id)
                ->where('created_at', '>=', $shipment->at_sorting_center_at)->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->exists()) return null;
        }

        return $missing;
    }

    public function review(HubTransferBatch $batch): array
    {
        if (! in_array($batch->status, ['pending', 'approved'], true)) return [];
        $origin = $batch->origin;
        if (! $origin) return ['Custody needs review: origin hub is unavailable.'];

        return $batch->members()->with(['leg', 'shipment'])->get()->map(function (HubTransferMember $member) use ($origin) {
            return $member->leg && $member->shipment ? $this->problem($member->shipment, $member->leg, $origin)
                : 'Custody needs review: transfer membership evidence is unavailable.';
        })->filter()->values()->all();
    }
}
