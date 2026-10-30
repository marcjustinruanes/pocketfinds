<?php

namespace App\Services;

use App\Models\CompanyVehicle;
use App\Models\DeliveryAssignment;
use App\Models\HubTransferBatch;
use App\Models\HubTransferEvent;
use App\Models\HubTransferMember;
use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentHubLeg;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HubTransferWorkflow
{
    public function scope(User $user)
    {
        $this->authorize($user);

        return HubTransferBatch::where('company_name', $user->business_name)
            ->when($user->isHubStaff(), fn ($q) => $q->where(fn ($q) => $q->where('origin_hub_id', $user->logistics_hub_id)->orWhere('destination_hub_id', $user->logistics_hub_id)));
    }

    public function legacyBatch(User $user, string $legId): HubTransferBatch
    {
        return DB::transaction(function () use ($user, $legId) {
            $peekLeg = ShipmentHubLeg::forCompany($user->business_name)->findOrFail($legId);
            GcashLocks::operation([$peekLeg->shipment_id], [], [$user->id]);
            $actor = $this->actor($user);
            $leg = ShipmentHubLeg::forCompany($actor->business_name)->findOrFail($legId);
            $shipment = Shipment::whereKey($leg->shipment_id)->lockForUpdate()->firstOrFail();
            app(LogisticsRecovery::class)->assertNotRecovered($shipment->id);
            $leg = ShipmentHubLeg::whereKey($legId)->lockForUpdate()->firstOrFail();
            if ($leg->transfer_batch_id) {
                return $this->scope($actor)->findOrFail($leg->transfer_batch_id);
            }
            $findHub = function ($town, $province) use ($actor) {
                $rows = LogisticsHub::where('company_name', $actor->business_name)->whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim((string) $town))])->whereRaw('LOWER(province) = ?', [mb_strtolower(trim((string) $province))])->get();
                abort_unless($rows->count() === 1, 422, 'The legacy transfer has missing or ambiguous hub information.');

                return $rows->first();
            };
            $origin = $findHub($leg->from_hub, $leg->from_province);
            $destination = $findHub($leg->to_hub, $leg->to_province);
            if ($actor->isHubStaff()) {
                abort_unless(in_array((int) $actor->logistics_hub_id, [$origin->id, $destination->id], true), 404);
            }
            abort_unless($leg->requested_at || in_array($leg->status, ['in_transit', 'completed'], true), 422, 'Request this leg before approval or dispatch.');
            $approver = $leg->approved_by ? User::find($leg->approved_by) : null;
            $approved = $leg->approved_at && $approver && $approver->account_type === 'logistics' && ! $approver->is_admin && $approver->isLogisticsAdmin() && $approver->business_name === $actor->business_name;
            $status = in_array($leg->status, ['in_transit', 'completed'], true) ? $leg->status : ($approved ? 'approved' : 'pending');
            $batch = HubTransferBatch::create(['company_name' => $actor->business_name, 'origin_hub_id' => $origin->id, 'destination_hub_id' => $destination->id,
                'from_hub' => $leg->from_hub, 'from_province' => $leg->from_province, 'to_hub' => $leg->to_hub, 'to_province' => $leg->to_province,
                'status' => $status, 'requested_at' => $leg->requested_at, 'requested_by' => $leg->requested_by, 'approved_at' => $leg->approved_at, 'approved_by' => $leg->approved_by,
                'dispatched_at' => $leg->started_at, 'received_at' => $leg->completed_at, 'rider_id' => $leg->rider_id, 'legacy_import' => true]);
            HubTransferMember::create(['batch_id' => $batch->id, 'leg_id' => $leg->id, 'shipment_id' => $shipment->id, 'tracking_number' => $shipment->tracking_number, 'received_at' => $leg->completed_at]);
            $leg->update(['transfer_batch_id' => $batch->id]);

            return $batch;
        });
    }

    public function confirmPickup(User $user, string $legId): void
    {
        DB::transaction(function () use ($user, $legId) {
            $peekLeg = ShipmentHubLeg::where('rider_id', $user->id)->whereKey($legId)->firstOrFail();
            GcashLocks::operation([$peekLeg->shipment_id], $peekLeg->transfer_batch_id ? [$peekLeg->transfer_batch_id] : [], [$user->id]);
            $rider = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($rider->account_type === 'rider' && ! $rider->is_admin && $rider->status === 'approved', 403);
            $leg = ShipmentHubLeg::where('rider_id', $rider->id)->whereKey($legId)->firstOrFail();
            $batch = HubTransferBatch::where('company_name', $rider->business_name)->where('rider_id', $rider->id)->whereKey($leg->transfer_batch_id)->lockForUpdate()->firstOrFail();
            abort_unless($batch->status === 'in_transit' && $batch->dispatched_at, 422);
            $shipment = Shipment::whereKey($leg->shipment_id)->lockForUpdate()->firstOrFail();
            app(LogisticsRecovery::class)->assertNotRecovered($shipment->id);
            $leg = ShipmentHubLeg::whereKey($legId)->lockForUpdate()->firstOrFail();
            abort_unless($leg->status === 'in_transit' && ! $leg->rider_confirmed_pickup_at && $leg->rider_id === $rider->id && $shipment->shipping_status === 'hub_transfer', 422, 'This pickup is not available for confirmation.');
            $leg->update(['rider_confirmed_pickup_at' => now()]);
            DeliveryAssignment::where('shipment_id', $leg->shipment_id)->where('courier_id', $rider->id)->where('leg', 'hub_transfer')->update(['picked_up_at' => now()]);
            $this->event($batch, $rider, 'pickup_confirmed', 'in_transit', null, $leg->shipment_id);
            foreach (User::where('business_name', $batch->company_name)->where('account_type', 'logistics')->where('is_logistics', true)->where('is_admin', false)->where('logistics_role', 'hub_staff')->where('status', 'approved')->where('logistics_hub_id', $batch->destination_hub_id)->pluck('id') as $staffId) {
                Order::notifyUser($staffId, 'Hub Transfer On The Way', 'A parcel in an incoming transfer has been collected by its assigned rider.', 'hub_transfer_pickup_confirmed', $shipment->id);
            }
        });
    }

    private function authorize(User $user, bool $admin = false): void
    {
        abort_unless($user->account_type === 'logistics' && $user->is_logistics && ! $user->is_admin && $user->status === 'approved'
            && filled($user->business_name) && ($user->isLogisticsAdmin() || $user->isHubStaff()), 403);
        if ($admin) {
            abort_unless($user->isLogisticsAdmin(), 403, 'Only Logistics Admin can decide or dispatch hub transfers.');
        }
        if ($user->isHubStaff()) {
            app(LogisticsAccess::class)->hub($user);
        }
    }

    private function actor(User $user, bool $admin = false): User
    {
        $fresh = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        $this->authorize($fresh, $admin);

        return $fresh;
    }

    private function hubs(User $user, $originId, $destinationId, bool $requireOriginOperational = true): array
    {
        $hubs = LogisticsHub::where('company_name', $user->business_name)->whereIn('id', [$originId, $destinationId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $origin = $hubs->get($originId);
        $destination = $hubs->get($destinationId);
        abort_unless($origin && $destination && $origin->id !== $destination->id && $destination->is_operational
            && (! $requireOriginOperational || $origin->is_operational), 422, 'Choose distinct operational hubs belonging to your company.');
        foreach ([$origin, $destination] as $hub) {
            $count = LogisticsHub::where('company_name', $user->business_name)
                ->whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim($hub->municipality))])
                ->whereRaw('LOWER(province) = ?', [mb_strtolower(trim($hub->province))])->count();
            abort_unless($count === 1, 422, 'This municipality/province has ambiguous hub records; resolve the hub records first.');
        }

        return [$origin, $destination];
    }

    private function event(HubTransferBatch $batch, User $actor, string $action, ?string $from, ?string $reason = null, ?string $shipmentId = null): void
    {
        HubTransferEvent::create(['batch_id' => $batch->id, 'actor_id' => $actor->id, 'action' => $action, 'from_status' => $from,
            'to_status' => $batch->status, 'reason' => $reason, 'shipment_id' => $shipmentId, 'created_at' => now()]);
    }

    private function parcels(User $actor, array $ids)
    {
        abort_unless($ids && count($ids) === count(array_unique($ids)), 422, 'Select distinct parcels.');
        $parcels = Shipment::where('logistics_company', $actor->business_name)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        abort_unless($parcels->count() === count($ids), 422, 'One or more parcels are unavailable in this company.');
        foreach ($parcels as $parcel) app(LogisticsRecovery::class)->assertNotRecovered($parcel->id);

        return $parcels;
    }

    private function nextEligible(Shipment $shipment, LogisticsHub $origin, LogisticsHub $destination): ShipmentHubLeg
    {
        abort_unless($shipment->needsHubTransfer() && in_array($shipment->shipping_status, ['at_sorting_center', 'hub_transfer'], true), 422, 'The parcel is not awaiting a hub transfer.');
        if ($shipment->order_id) {
            $order = Order::whereKey($shipment->order_id)->lockForUpdate()->firstOrFail();
            abort_unless($order->status === $shipment->shipping_status, 422, 'The order and parcel stages do not agree.');
        }
        $legs = $shipment->hubLegs()->lockForUpdate()->get();
        abort_if($legs->contains('status', 'in_transit') || $legs->groupBy('sequence')->contains(fn ($group) => $group->count() > 1), 422, 'The parcel has a conflicting transfer leg.');
        $next = $legs->firstWhere('status', 'pending');
        abort_unless($next && $legs->where('sequence', '<', $next->sequence)->every(fn ($leg) => $leg->status === 'completed')
            && app(LogisticsAccess::class)->matches($origin, $next->from_hub, $next->from_province)
            && app(LogisticsAccess::class)->matches($destination, $next->to_hub, $next->to_province), 422, 'The selected route is not the parcel’s next eligible leg.');

        return $next;
    }

    public function request(User $user, int $originId, int $destinationId, array $parcelIds): HubTransferBatch
    {
        return DB::transaction(function () use ($user, $originId, $destinationId, $parcelIds) {
            GcashLocks::operation($parcelIds, [], [$user->id], [$originId, $destinationId]);
            $actor = $this->actor($user);
            [$origin, $destination] = $this->hubs($actor, $originId, $destinationId);
            if ($actor->isHubStaff()) {
                abort_unless((int) $actor->logistics_hub_id === $origin->id, 403, 'Request transfers only from your assigned hub.');
            }
            $parcels = $this->parcels($actor, $parcelIds);
            $selected = $parcels->map(function ($s) use ($origin, $destination) {
                $leg = $this->nextEligible($s, $origin, $destination);
                abort_if($leg->transfer_batch_id || $leg->requested_at || $leg->approved_at, 422, 'This parcel already belongs to a transfer request.');

                return $leg;
            });
            $batch = HubTransferBatch::create(['company_name' => $actor->business_name, 'origin_hub_id' => $origin->id, 'destination_hub_id' => $destination->id,
                'from_hub' => $origin->municipality, 'from_province' => $origin->province, 'to_hub' => $destination->municipality, 'to_province' => $destination->province,
                'status' => 'pending', 'requested_by' => $actor->id, 'requested_at' => now()]);
            foreach ($selected as $leg) {
                $leg->update(['transfer_batch_id' => $batch->id, 'requested_by' => $actor->id, 'requested_at' => $batch->requested_at]);
                HubTransferMember::create(['batch_id' => $batch->id, 'leg_id' => $leg->id, 'shipment_id' => $leg->shipment_id, 'tracking_number' => $parcels->firstWhere('id', $leg->shipment_id)->tracking_number]);
            }
            $this->event($batch, $actor, 'requested', null);
            foreach (User::where('business_name', $actor->business_name)->where('account_type', 'logistics')->where('is_logistics', true)->where('is_admin', false)->where('status', 'approved')
                ->where(fn ($q) => $q->where('logistics_role', 'admin')->orWhereNull('logistics_role'))->pluck('id') as $adminId) {
                Order::notifyUser($adminId, 'Hub Transfer Request', 'A transfer batch is waiting for Logistics Admin approval.', 'hub_transfer_requested', $batch->id);
            }

            return $batch;
        });
    }

    private function currentMembers(HubTransferBatch $batch, User $actor, LogisticsHub $origin, LogisticsHub $destination)
    {
        $members = $batch->members()->orderBy('shipment_id')->lockForUpdate()->get();
        $parcels = $this->parcels($actor, $members->pluck('shipment_id')->all())->keyBy('id');
        foreach ($members as $member) {
            $leg = $this->nextEligible($parcels[$member->shipment_id], $origin, $destination);
            abort_unless($leg->id === $member->leg_id && $leg->transfer_batch_id === $batch->id && $leg->requested_at, 422, 'Transfer membership or parcel eligibility changed.');
        }

        return $members;
    }

    public function decide(User $user, string $id, string $decision, ?string $reason = null): HubTransferBatch
    {
        return DB::transaction(function () use ($user, $id, $decision, $reason) {
            GcashLocks::operation([], [$id], [$user->id]);
            $actor = $this->actor($user, $decision !== 'cancelled');
            $batch = $this->scope($actor)->lockForUpdate()->findOrFail($id);
            abort_unless(in_array($decision, ['approved', 'rejected', 'cancelled'], true), 422);
            if ($decision === 'cancelled') {
                abort_unless(in_array($batch->status, $actor->isLogisticsAdmin() ? ['pending', 'approved'] : ['pending'], true), 422, 'This transfer cannot be cancelled at its current stage.');
                if ($actor->isHubStaff()) {
                    abort_unless((int) $batch->origin_hub_id === (int) $actor->logistics_hub_id, 403);
                }
            } else {
                abort_unless($batch->status === 'pending', 422, 'Only pending requests can be decided.');
            }
            abort_if($decision !== 'approved' && ! filled(trim((string) $reason)), 422, 'A reason is required.');
            $from = $batch->status;
            if ($decision === 'approved') {
                [$origin, $destination] = $this->hubs($actor, $batch->origin_hub_id, $batch->destination_hub_id);
                $members = $this->currentMembers($batch, $actor, $origin, $destination);
                $batch->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
                ShipmentHubLeg::whereIn('id', $members->pluck('leg_id'))->update(['approved_by' => $actor->id, 'approved_at' => $batch->approved_at]);
            } else {
                $action = $decision === 'rejected' ? 'rejected' : 'cancelled';
                $batch->update(['status' => $decision, $action.'_by' => $actor->id, $action.'_at' => now(), ($action === 'rejected' ? 'rejection_reason' : 'cancellation_reason') => trim($reason)]);
                // Immutable members/events retain the request. Release only its still-pending legs.
                $ids = $batch->members()->orderBy('shipment_id')->lockForUpdate()->pluck('shipment_id')->all();
                Shipment::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                ShipmentHubLeg::where('transfer_batch_id', $batch->id)->where('status', 'pending')->lockForUpdate()->get()->each(fn ($leg) => $leg->update(['transfer_batch_id' => null, 'requested_at' => null, 'requested_by' => null, 'approved_at' => null, 'approved_by' => null]));
            }
            $this->event($batch, $actor, $decision, $from, $reason);
            if ($batch->requested_by && User::whereKey($batch->requested_by)->where('business_name', $batch->company_name)->exists()) {
                Order::notifyUser($batch->requested_by, 'Hub Transfer Decision', 'Your transfer request is now '.$decision.'.', 'hub_transfer_'.$decision, $batch->id);
            }

            return $batch;
        });
    }

    public function dispatch(User $user, string $id, int $riderId, ?int $vehicleId = null): HubTransferBatch
    {
        return DB::transaction(function () use ($user, $id, $riderId, $vehicleId) {
            $vehicleIds = CompanyVehicle::where('company_name', $user->business_name)->pluck('id')->all();
            $footprint = GcashLocks::operation([], [$id], [$user->id, $riderId], [], $vehicleIds);
            $actor = $this->actor($user, true);
            $batch = $this->scope($actor)->lockForUpdate()->findOrFail($id);
            abort_unless($batch->status === 'approved' && $batch->approved_at && $batch->approved_by, 422, 'Only an approved, undispatched batch can be dispatched.');
            [$origin, $destination] = $this->hubs($actor, $batch->origin_hub_id, $batch->destination_hub_id);
            $rider = User::where('business_name', $actor->business_name)->whereKey($riderId)->lockForUpdate()->firstOrFail();
            abort_unless($rider->account_type === 'rider' && ! $rider->is_admin && ! $rider->is_logistics && $rider->status === 'approved'
                && (int) $rider->logistics_hub_id === $origin->id && $rider->vehicle_type === 'four_wheels' && in_array($rider->vehicle_ownership, ['own', 'company'], true), 422, 'Choose an approved four-wheel transfer rider assigned to the origin hub.');
            abort_if(HubTransferBatch::where('rider_id', $rider->id)->where('status', 'in_transit')->lockForUpdate()->first(['id'])
                || app(AssignmentWork::class)->blocksRider($rider->id), 422, 'The rider has active or ambiguous work requiring review.');
            $vehicle = null;
            if ($rider->vehicle_ownership === 'company') {
                $query = CompanyVehicle::where('company_name', $actor->business_name)->where('logistics_hub_id', $origin->id)->where('vehicle_type', 'four_wheels')->riderVisible();
                if ($vehicleId) {
                    $query->whereKey($vehicleId);
                } else {
                    $query->whereNotIn('id', HubTransferBatch::where('status', 'in_transit')->whereNotNull('vehicle_id')->select('vehicle_id'));
                }
                $vehicle = $query->whereIn('id', $footprint['vehicles'])->orderBy('id')->lockForUpdate()->first();
                abort_unless($vehicle, 422, 'No approved available matching company vehicle was selected.');
                abort_if(HubTransferBatch::where('vehicle_id', $vehicle->id)->where('status', 'in_transit')->lockForUpdate()->first(['id']), 422, 'This vehicle is already carrying another batch.');
            } else {
                abort_if($vehicleId, 422, 'A personal-vehicle rider cannot reserve a company vehicle.');
            }
            $members = $this->currentMembers($batch, $actor, $origin, $destination);
            $now = now();
            $batch->update(['status' => 'in_transit', 'dispatched_by' => $actor->id, 'dispatched_at' => $now, 'rider_id' => $rider->id, 'vehicle_id' => $vehicle?->id]);
            app(LogisticsFinance::class)->snapshotTransfer($batch);
            foreach ($members as $member) {
                $leg = ShipmentHubLeg::findOrFail($member->leg_id);
                $shipment = Shipment::findOrFail($member->shipment_id);
                $leg->update(['status' => 'in_transit', 'rider_id' => $rider->id, 'started_at' => $now, 'rider_confirmed_pickup_at' => null]);
                $shipment->update(['shipping_status' => 'hub_transfer', 'hub_transfer_rider_id' => $rider->id, 'hub_transfer_started_at' => $shipment->hub_transfer_started_at ?: $now]);
                if ($shipment->order_id) {
                    $order = Order::findOrFail($shipment->order_id);
                    $order->update(['status' => 'hub_transfer']);
                    $order->logStatus('hub_transfer', $actor->id);
                }
                DeliveryAssignment::updateOrCreate(['shipment_id' => $shipment->id, 'leg' => 'hub_transfer'], ['courier_id' => $rider->id, 'status' => 'hub_transfer', 'accepted_at' => $now, 'picked_up_at' => null, 'delivered_at' => null]);
            }
            Order::notifyUser($rider->id, 'Hub Transfer Dispatched', 'A hub transfer batch has been assigned to you. Confirm pickup in My Hub Transfers.', 'hub_transfer_assigned', null);
            $this->event($batch, $actor, 'dispatched', 'approved');

            return $batch;
        });
    }

    public function receive(User $user, string $id, ?array $shipmentIds = null): HubTransferBatch
    {
        return DB::transaction(function () use ($user, $id, $shipmentIds) {
            GcashLocks::operation([], [$id], [$user->id]);
            $actor = $this->actor($user);
            $batch = $this->scope($actor)->lockForUpdate()->findOrFail($id);
            [, $destination] = $this->hubs($actor, $batch->origin_hub_id, $batch->destination_hub_id, false);
            app(LogisticsAccess::class)->receivingStaff($actor, $destination->municipality, $destination->province);
            abort_unless((int) $actor->logistics_hub_id === (int) $batch->destination_hub_id, 403, 'Only the destination hub can receive this batch.');
            abort_unless($batch->status === 'in_transit' && $batch->dispatched_at, 422, 'Only a dispatched batch can be received.');
            $members = $batch->members()->orderBy('shipment_id')->lockForUpdate()->get();
            $selected = $shipmentIds === null ? $members->whereNull('received_at') : $members->whereIn('shipment_id', $shipmentIds);
            abort_unless($selected->isNotEmpty() && ($shipmentIds === null || (count($shipmentIds) === count(array_unique($shipmentIds)) && $selected->count() === count($shipmentIds))), 422, 'Select distinct parcels in this batch.');
            $parcels = $this->parcels($actor, $selected->pluck('shipment_id')->all())->keyBy('id');
            foreach ($selected as $member) {
                $shipment = $parcels[$member->shipment_id];
                $leg = ShipmentHubLeg::whereKey($member->leg_id)->lockForUpdate()->firstOrFail();
                abort_unless($shipment->hubLegs()->where('status', 'in_transit')->count() === 1
                    && ! $shipment->hubLegs()->where('sequence', '<', $leg->sequence)->where('status', '!=', 'completed')->exists(), 422, 'The parcel has conflicting route state.');
                if ($shipment->order_id) {
                    $order = Order::whereKey($shipment->order_id)->lockForUpdate()->firstOrFail();
                    abort_unless($order->status === 'hub_transfer', 422, 'The order is not in hub transfer.');
                }
                abort_unless(! $member->received_at && $leg->transfer_batch_id === $batch->id && $leg->status === 'in_transit' && $leg->rider_id === $batch->rider_id
                    && $leg->rider_confirmed_pickup_at && $shipment->shipping_status === 'hub_transfer' && $shipment->hub_transfer_rider_id === $batch->rider_id, 422, 'This parcel is not ready for receipt; confirm rider pickup first.');
                $leg->update(['status' => 'completed', 'completed_at' => now()]);
                $member->update(['received_by' => $actor->id, 'received_at' => now()]);
                DeliveryAssignment::where('shipment_id', $shipment->id)->where('leg', 'hub_transfer')->where('courier_id', $batch->rider_id)->update(['status' => 'completed', 'delivered_at' => now()]);
                $next = $shipment->nextPendingHubLeg();
                $updates = ['hub_transfer_rider_id' => null];
                if (! $next) {
                    $updates += ['shipping_status' => 'sorted', 'hub_transfer_completed_at' => now(), 'sorted_at' => now(), 'sorted_area' => $shipment->destination_hub];
                }
                $shipment->update($updates);
                if (! $next && $shipment->order_id) {
                    $order = Order::findOrFail($shipment->order_id);
                    $order->update(['status' => 'sorted']);
                    $order->logStatus('sorted', $actor->id);
                }
                $this->event($batch, $actor, 'parcel_received', 'in_transit', null, $shipment->id);
            }
            if ($batch->members()->whereNull('received_at')->lockForUpdate()->get(['id'])->isEmpty()) {
                $batch->update(['status' => 'completed', 'received_by' => $actor->id, 'received_at' => now()]);
                $this->event($batch, $actor, 'received', 'in_transit');
                app(RiderCompensation::class)->batchCompleted($batch->id);
            }

            return $batch;
        });
    }

    public function discrepancy(User $user, string $id, string $reason, ?string $shipmentId): HubTransferBatch
    {
        return DB::transaction(function () use ($user, $id, $reason, $shipmentId) {
            GcashLocks::operation([], [$id], [$user->id]);
            $actor = $this->actor($user);
            $batch = $this->scope($actor)->lockForUpdate()->findOrFail($id);
            if ($actor->isHubStaff()) {
                abort_unless((int) $actor->logistics_hub_id === (int) $batch->destination_hub_id, 403);
            }
            abort_unless(in_array($batch->status, ['in_transit', 'completed'], true) && filled(trim($reason)), 422, 'Report discrepancies only for dispatched transfers.');
            if ($shipmentId) {
                abort_unless($batch->members()->where('shipment_id', $shipmentId)->exists(), 422, 'This parcel is not in this batch.');
            }
            $this->event($batch, $actor, 'discrepancy', $batch->status, trim($reason), $shipmentId);

            return $batch;
        });
    }
}
