<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesMessaging;
use App\Http\Controllers\Concerns\HandlesAccountUpdateRequests;
use App\Models\CompanyVehicle;
use App\Models\DeliveryAssignment;
use App\Models\LogisticsHub;
use App\Models\Message;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentHubLeg;
use App\Models\UnserviceableArea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LogisticsController extends Controller
{
    use HandlesMessaging;
    use HandlesAccountUpdateRequests;

    /** The full ERP pipeline — see Shipment::STATUSES, the single source of truth. */
    const STATUSES = Shipment::STATUSES;

    /**
     * Valid forward moves for a shipment's status, keyed by its current status.
     * A shipment can only ever move to one of these — never backward, never skipped
     * ahead. This is the single source of truth for both the Monitor page's status
     * dropdown and the Scan page's action buttons, and is enforced server-side in
     * updateStatus() so a crafted request can't bypass it either.
     *
     * 'ready_for_pickup' → 'picked_up' and 'sorted' → 'assigned_to_rider' are deliberately
     * absent: those moves only happen through the dedicated rider-accept / assignCourier()
     * flows, which also set pickup_rider_id / courier_id — flipping the status alone here
     * would leave a shipment "assigned" with nobody actually attached to it. 'picked_up' →
     * 'at_sorting_center' and 'at_sorting_center' → 'sorted' are likewise absent for the
     * same reason: receiveAtSortingCenter() / assignHubTransferRider() / completeHubTransfer()
     * own those moves so a hub-transfer leg can never be skipped by a raw status flip here.
     */
    private const STATUS_TRANSITIONS = [
        'assigned_to_rider' => ['out_for_delivery', 'delivery_failed'],
        'out_for_delivery'  => ['delivered', 'delivery_failed'],
        'delivered'         => ['completed'],
        'delivery_failed'   => ['assigned_to_rider', 'returned'],
    ];

    /** Human label for the primary (non-failure) target of each transition. */
    private const STAGE_LABELS = [
        'at_sorting_center' => 'Receive at Sorting Center',
        'sorted'            => 'Mark Sorted',
        'out_for_delivery'  => 'Mark Out for Delivery',
        'delivered'         => 'Mark Delivered',
        'completed'         => 'Mark Completed',
        'assigned_to_rider' => 'Reassign for Redelivery',
        'returned'          => 'Mark Returned to Seller',
    ];

    /** Every query below is scoped to the acting staff's own company — a hub network is only ever this company's own. */
    private function companyScope()
    {
        return Shipment::where('logistics_company', auth()->user()->business_name);
    }

    /** Same company-scoping, for queries that start from a leg instead of a shipment. */
    private function hubLegScope()
    {
        return ShipmentHubLeg::forCompany(auth()->user()->business_name);
    }

    /**
     * Every one of these count queries used to be its own separate `SELECT COUNT(*)`
     * round trip. Over a remote, latency-heavy connection like this one's (~300-400ms
     * per round trip, not per query — see eligibleRiders()) that adds up fast: the
     * Dashboard alone used to fire ~14 of them. Postgres' `FILTER` clause computes any
     * number of conditional counts against the same table in a single query, so this
     * takes whatever filters a caller needs and returns them all from one round trip.
     *
     * @param array<string,string> $filters  output key => raw SQL boolean condition
     * @return array<string,int>
     */
    private function shipmentCounts(array $filters): array
    {
        $select = collect($filters)
            ->map(fn ($sql, $key) => "count(*) filter (where {$sql}) as \"{$key}\"")
            ->implode(', ');

        $row = $this->companyScope()->selectRaw($select)->first();

        return collect($filters)->keys()
            ->mapWithKeys(fn ($key) => [$key => (int) ($row->{$key} ?? 0)])
            ->all();
    }

    /** The shipment-table filters every sidebar needs — pulled out so dashboard() can fold
     *  these same numbers into its own bigger single query instead of asking twice. */
    private function sidebarShipmentFilters(): array
    {
        return [
            'pendingDeliveries' => "shipping_status = 'ready_for_pickup' and pickup_approved_at is null",
            'activeDeliveries'  => "shipping_status in ('picked_up', 'at_sorting_center', 'hub_transfer', 'sorted', 'assigned_to_rider', 'out_for_delivery')",
            'unassigned'        => "shipping_status = 'sorted' and courier_id is null",
        ];
    }

    /** Sidebar/dashboard badge counts that live outside the shipments table (riders, hub staff,
     *  vehicles, hub-transfer legs) — also collapsed to one query per table instead of one per badge. */
    private function nonShipmentCounts(User $user): array
    {
        $counts = ['unreadNotifications' => 0];

        $userCounts = User::where('business_name', $user->business_name)->selectRaw("
                count(*) filter (where account_type = 'rider' and status = 'pending') as pending_riders,
                count(*) filter (where account_type = 'logistics' and logistics_role = 'hub_staff' and status = 'pending') as pending_staff
            ")->first();
        $counts['pendingRiders'] = (int) $userCounts->pending_riders;

        if ($user->isLogisticsAdmin()) {
            // Batches of legs a hub has requested, still waiting on this admin to approve.
            $counts['pendingStaff'] = (int) $userCounts->pending_staff;
            $counts['pendingHubTransferRequests'] = $this->hubLegScope()->where('status', 'pending')
                ->whereNotNull('requested_at')->whereNull('approved_at')->count();
            $counts['pendingVehicles'] = CompanyVehicle::where('company_name', $user->business_name)
                ->where('platform_status', 'pending')->count();
        } elseif ($user->isHubStaff() && $user->logisticsHub) {
            // Legs sitting at this staff member's own hub, ready to be requested onward.
            $counts['requestableHubLegs'] = $this->hubLegScope()->fromHub($user->logisticsHub->municipality)
                ->where('status', 'pending')->whereNull('requested_at')->count();
        }

        return $counts;
    }

    private function sidebarCounts(): array
    {
        return array_merge(
            $this->shipmentCounts($this->sidebarShipmentFilters()),
            $this->nonShipmentCounts(auth()->user())
        );
    }

    public function dashboard()
    {
        if (auth()->user()->isHubStaff()) {
            return $this->hubStaffDashboard();
        }

        // All shipment-table numbers this page needs — the 3 sidebar badges plus the
        // 5 dashboard tiles — in one query instead of 8 separate ones.
        $shipmentStats = $this->shipmentCounts(array_merge($this->sidebarShipmentFilters(), [
            'total'     => 'true',
            'forVerify' => "shipping_status = 'ready_for_pickup' and pickup_approved_at is not null and pickup_rider_id is null",
            'available' => "shipping_status = 'sorted'",
            'completed' => "shipping_status in ('delivered', 'completed')",
            'cancelled' => "shipping_status in ('cancelled', 'delivery_failed', 'returned')",
        ]));

        $counts = array_merge($shipmentStats, $this->nonShipmentCounts(auth()->user()));
        $recent = $this->companyScope()->with(['order.buyer', 'courier', 'pickupRider', 'hubTransferRider'])->latest('created_at')->take(8)->get();

        return view('logistics.dashboard', array_merge($counts, compact('recent'), [
            'pending' => $counts['pendingDeliveries'],
            'active'  => $counts['activeDeliveries'],
        ]));
    }

    /** Pickup requests fresh from a seller — "Confirm/approve/verify parcel pickup requests from seller". */
    public function requests()
    {
        $counts    = $this->sidebarCounts();
        $shipments = $this->companyScope()->with(['order.buyer'])
            ->where('shipping_status', 'ready_for_pickup')->whereNull('pickup_approved_at')
            ->latest('created_at')->get();
        return view('logistics.requests', array_merge($counts, compact('shipments')));
    }

    /** Approving a request doesn't change its status — it stays ready_for_pickup, now visible to pickup riders. */
    public function approveRequest(Request $request, $id)
    {
        $this->companyScope()->where('id', $id)->firstOrFail()->update(['pickup_approved_at' => now()]);
        return back();
    }

    public function rejectRequest(Request $request, $id)
    {
        $shipment = $this->companyScope()->with('order')->findOrFail($id);
        $shipment->update(['shipping_status' => 'cancelled']);
        $shipment->order?->update(['status' => 'cancelled']);
        return back();
    }

    /**
     * This company's approved riders eligible for a leg needing $vehicleType — the "CHECK
     * RIDER & VEHICLE" step in the delivery flow: the rider's registered vehicle type must
     * fit the leg (two_wheels for pickup/delivery, four_wheels for hub transfer), and a
     * rider on a company-provided vehicle additionally needs their own hub to actually have
     * a free (platform-approved, not-in-maintenance) unit of that type right now. A rider on
     * their own vehicle only needs the type match — already verified once, at registration.
     */
    private function eligibleRiders(string $vehicleType)
    {
        $riders = User::where('account_type', 'rider')->where('status', 'approved')
            ->where('business_name', auth()->user()->business_name)->where('vehicle_type', $vehicleType)
            ->orderBy('given_names')->get();

        // One query for every hub these riders need checked, instead of one query PER rider
        // (was N+1 — costly over a remote, latency-heavy DB connection like this one's).
        $hubIdsToCheck = $riders->where('vehicle_ownership', 'company')->pluck('logistics_hub_id')->filter()->unique();
        $hubsWithAvailableUnit = CompanyVehicle::whereIn('logistics_hub_id', $hubIdsToCheck)
            ->where('vehicle_type', $vehicleType)->riderVisible()
            ->pluck('logistics_hub_id')->unique();

        return $riders->filter(fn ($r) => $r->vehicle_ownership !== 'company' || !$r->logistics_hub_id
                || $hubsWithAvailableUnit->contains($r->logistics_hub_id))
            ->values();
    }

    /** Same check as eligibleRiders(), for a specific already-chosen rider — the server-side half of the same gate. */
    private function vehicleEligibilityError(User $rider, string $requiredType): ?string
    {
        $label = fn ($type) => str_replace('_', ' ', $type);
        if ($rider->vehicle_type !== $requiredType) {
            return "{$rider->given_names} is registered with a {$label($rider->vehicle_type)}, but this leg needs a {$label($requiredType)}.";
        }
        if ($rider->vehicle_ownership === 'company' && $rider->logistics_hub_id
            && !CompanyVehicle::typeAvailableAtHub($rider->logistics_hub_id, $requiredType)) {
            return "{$rider->given_names} uses a company-provided vehicle, but their hub has no available {$label($requiredType)} right now.";
        }
        return null;
    }

    /** Delivery-leg assignment: sorted parcels waiting for a rider to take them from the sorting center to the buyer. */
    public function assignments()
    {
        $counts    = $this->sidebarCounts();
        $shipments = $this->companyScope()->with(['order.buyer', 'deliveryAssignment.courier', 'courier', 'hubTransferAssignment.courier', 'hubTransferRider'])
            ->whereIn('shipping_status', ['at_sorting_center', 'hub_transfer', 'sorted', 'assigned_to_rider', 'out_for_delivery'])
            ->latest('created_at')->get();
        // Only this company's own riders, and only ones whose vehicle actually fits a delivery leg (two-wheeler).
        $couriers  = $this->eligibleRiders('two_wheels');
        return view('logistics.assign', array_merge($counts, compact('shipments', 'couriers')));
    }

    public function assignCourier(Request $request, $id)
    {
        $company  = auth()->user()->business_name;
        $data     = $request->validate(['courier_id' => 'required|integer|exists:users,id']);
        $shipment = $this->companyScope()->with('order')->findOrFail($id);
        $courier  = User::where('id', $data['courier_id'])->where('account_type', 'rider')->where('status', 'approved')->where('business_name', $company)->firstOrFail();

        if ($error = $this->vehicleEligibilityError($courier, 'two_wheels')) {
            if ($request->expectsJson() || $request->ajax()) {
                abort(422, $error);
            }
            return back()->withErrors(['courier_id' => $error])->withInput();
        }

        $shipment->update(['courier_id' => $courier->id, 'shipping_status' => 'assigned_to_rider', 'assigned_at' => now()]);
        $shipment->order?->update(['status' => 'assigned_to_rider']);
        DeliveryAssignment::updateOrCreate(
            ['shipment_id' => $shipment->id, 'leg' => 'delivery'],
            ['courier_id' => $courier->id, 'status' => 'assigned_to_rider', 'accepted_at' => now()]
        );

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $courier->id,
            'title' => 'New Delivery Assignment', 'message' => 'You were assigned to deliver order #' . ($shipment->order?->order_number ?? $shipment->id) . '.',
            'notification_type' => 'delivery_assigned', 'reference_id' => $shipment->order_id,
            'is_read' => false, 'created_at' => now(),
        ]);

        return back()->with('success', 'Rider assigned.');
    }

    /**
     * Assigns a same-company rider to carry a parcel to the next stop on its hub route —
     * the leg (or legs) that only exist when the seller's and buyer's cities are different
     * hubs (Shipment::needsHubTransfer()). A same-province transfer is one leg, same as
     * before; a cross-province one is up to three (local hub -> regional hub -> regional
     * hub -> local hub — see ShipmentHubLeg), and this same endpoint is called again for
     * each subsequent leg once the previous one is scanned in via completeHubTransfer().
     * Mirrors assignCourier()'s shape exactly, just per-leg instead of a single flat hop.
     *
     * A leg can't be assigned straight off arrival — the origin hub's staff must first
     * request it (requestHubTransfer()) and the company admin must approve that request
     * (approveHubTransferRequest()) before it becomes eligible here.
     */
    public function assignHubTransferRider(Request $request, $id)
    {
        $company  = auth()->user()->business_name;
        $data     = $request->validate(['courier_id' => 'required|integer|exists:users,id']);
        $shipment = $this->companyScope()->with('order')->findOrFail($id);
        abort_unless(in_array($shipment->shipping_status, ['at_sorting_center', 'hub_transfer'], true) && $shipment->needsHubTransfer(), 422, 'This parcel is not awaiting a hub transfer.');

        $leg = $shipment->nextAssignableHubLeg();
        if (!$leg) {
            $pending = $shipment->nextPendingHubLeg();
            abort(422, $pending
                ? ($pending->requested_at
                    ? 'This leg is still waiting on the admin to approve the transfer request.'
                    : 'Request this transfer from the hub dashboard before it can be assigned.')
                : 'This parcel has no pending hub-transfer leg.');
        }

        $courier = User::where('id', $data['courier_id'])->where('account_type', 'rider')->where('status', 'approved')->where('business_name', $company)->firstOrFail();
        if ($error = $this->vehicleEligibilityError($courier, 'four_wheels')) {
            abort(422, $error);
        }
        $now = now();

        $leg->update(['rider_id' => $courier->id, 'status' => 'in_transit', 'started_at' => $now]);

        $shipment->update([
            'hub_transfer_rider_id'   => $courier->id, // whoever is currently carrying the active leg
            'shipping_status'         => 'hub_transfer',
            'hub_transfer_started_at' => $shipment->hub_transfer_started_at ?: $now, // stamped once, on the first leg
        ]);
        $shipment->order?->update(['status' => 'hub_transfer']);
        DeliveryAssignment::updateOrCreate(
            ['shipment_id' => $shipment->id, 'leg' => 'hub_transfer'],
            ['courier_id' => $courier->id, 'status' => 'hub_transfer', 'accepted_at' => $now]
        );

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $courier->id,
            'title' => 'New Hub Transfer', 'message' => "Carry order #" . ($shipment->order?->order_number ?? $shipment->id) . " from {$leg->from_hub} to {$leg->to_hub}.",
            'notification_type' => 'hub_transfer_assigned', 'reference_id' => $shipment->order_id,
            'is_read' => false, 'created_at' => $now,
        ]);

        return back()->with('success', 'Hub transfer rider assigned.');
    }

    /**
     * Hub staff scans the parcel in once the current leg's rider arrives with it. If
     * another leg remains on the route, the parcel simply waits here for the next leg's
     * rider to be assigned (assignHubTransferRider() again) — the shipment stays at
     * 'hub_transfer' the whole time. Only once the LAST leg completes does it move on
     * to 'sorted' for local delivery assignment, exactly as before this had multiple legs.
     */
    public function completeHubTransfer(Request $request, $id)
    {
        $shipment = $this->companyScope()->with('order')->findOrFail($id);
        abort_unless($shipment->shipping_status === 'hub_transfer', 422, 'This parcel is not currently in hub transfer.');

        $leg = $shipment->activeHubLeg();
        abort_if(!$leg, 422, 'No hub-transfer leg is currently in progress for this parcel.');
        abort_unless($leg->rider_confirmed_pickup_at, 422, "The rider hasn't confirmed picking this up from {$leg->from_hub} yet.");

        // Only the DESTINATION hub's own staff physically receive it there — the origin hub
        // (or any other hub) scanning the same tracking number shouldn't be able to mark it
        // received somewhere they aren't. The admin still can, for any hub, same as elsewhere.
        $user = auth()->user();
        if ($user->isHubStaff()) {
            abort_unless($user->logisticsHub && mb_strtolower(trim($user->logisticsHub->municipality)) === mb_strtolower(trim($leg->to_hub)),
                403, "Only {$leg->to_hub}'s own hub staff can mark this received there.");
        }

        $now = now();
        $leg->update(['status' => 'completed', 'completed_at' => $now]);

        $nextLeg = $shipment->nextPendingHubLeg();
        if ($nextLeg) {
            $shipment->update(['hub_transfer_rider_id' => null]);
            return response()->json(['ok' => true, 'message' => "Arrived at {$leg->to_hub}. This hub's staff can now request the onward transfer to {$nextLeg->to_hub}."]);
        }

        $shipment->update([
            'shipping_status'           => 'sorted',
            'hub_transfer_completed_at' => $now,
            'sorted_at'                 => $now,
            'sorted_area'               => $shipment->destination_hub,
        ]);
        $shipment->order?->update(['status' => 'sorted']);

        DB::table('order_status_history')->insert([
            'id' => (string) Str::uuid(), 'order_id' => $shipment->order_id, 'status' => 'sorted',
            'changed_by' => auth()->id(), 'created_at' => $now,
        ]);

        return response()->json(['ok' => true, 'message' => "Parcel arrived at {$shipment->destination_hub} and is ready for local delivery assignment."]);
    }

    /**
     * A hub-staff-only dashboard: their own hub's identity, every parcel sitting there
     * ready to move on (grouped by destination, so one click requests the whole batch),
     * and the status of requests they've already sent up to the admin.
     */
    private function hubStaffDashboard()
    {
        $user = auth()->user();
        abort_unless($user->logisticsHub, 403, 'Your account isn\'t linked to a hub yet — contact your company admin.');
        $hub     = $user->logisticsHub;
        $counts  = $this->sidebarCounts();

        // Pickup requests the admin has already approved, still waiting for a rider —
        // scoped to parcels actually originating at THIS hub (a seller's nearest hub,
        // see LogisticsController::approveRequest()). Assignable right here, or a rider
        // can still self-accept from their own Pickup Requests page — whichever happens first.
        $pickupRequests = $this->companyScope()->with(['order.buyer', 'order.seller'])
            ->whereRaw('LOWER(origin_hub) = ?', [mb_strtolower(trim($hub->municipality))])
            ->where('shipping_status', 'ready_for_pickup')->whereNotNull('pickup_approved_at')->whereNull('pickup_rider_id')
            ->latest('created_at')->get();
        $pickupRiders = $this->eligibleRiders('two_wheels');

        // Both sides have already confirmed the handoff (Shipment::maybeAdvancePastPickupConfirmation())
        // and the rider is physically carrying it here — nothing to assign, just visibility
        // until it's actually scanned in on the Scan page (receiveAtSortingCenter()).
        $incomingPickups = $this->companyScope()->with(['order.buyer', 'order.seller', 'pickupRider'])
            ->whereRaw('LOWER(origin_hub) = ?', [mb_strtolower(trim($hub->municipality))])
            ->where('shipping_status', 'picked_up')
            ->latest('picked_up_at')->get();

        $requestable = $this->hubLegScope()->fromHub($hub->municipality)
            ->where('status', 'pending')->whereNull('requested_at')
            ->with('shipment.order')->get()
            ->groupBy('to_hub');

        $pendingApproval = $this->hubLegScope()->fromHub($hub->municipality)
            ->where('status', 'pending')->whereNotNull('requested_at')->whereNull('approved_at')
            ->with('shipment.order')->get()
            ->groupBy('to_hub');

        $readyToAssign = $this->hubLegScope()->fromHub($hub->municipality)
            ->where('status', 'pending')->whereNotNull('approved_at')
            ->with('shipment.order')->get();

        $incoming = $this->hubLegScope()->where('to_hub', $hub->municipality)
            ->whereIn('status', ['in_transit'])
            ->with(['shipment.order', 'rider'])->get();

        return view('logistics.hub-dashboard', array_merge($counts, compact(
            'hub', 'pickupRequests', 'pickupRiders', 'incomingPickups', 'requestable', 'pendingApproval', 'readyToAssign', 'incoming'
        )));
    }

    /**
     * Origin hub staff assign a rider to carry an approved pickup from the seller to their
     * own hub — the pickup-leg counterpart to assignCourier(), one stage earlier in the
     * pipeline. Hub staff can only assign a pickup that actually originates at their own
     * hub; the company admin (who oversees every hub) can assign any of them. A rider can
     * still self-accept the same request first (RiderController::acceptPickupRequest()) —
     * this uses the same conditional update so only one of the two ever wins.
     */
    public function assignPickupRider(Request $request, $id)
    {
        $user = auth()->user();
        $data = $request->validate(['courier_id' => 'required|integer|exists:users,id']);
        $shipment = $this->companyScope()->with('order')->where('shipping_status', 'ready_for_pickup')
            ->whereNotNull('pickup_approved_at')->whereNull('pickup_rider_id')->findOrFail($id);

        if ($user->isHubStaff()) {
            abort_unless($user->logisticsHub && mb_strtolower(trim((string) $shipment->origin_hub)) === mb_strtolower(trim($user->logisticsHub->municipality)),
                403, 'This pickup does not originate at your hub.');
        }

        $courier = User::where('id', $data['courier_id'])->where('account_type', 'rider')->where('status', 'approved')->where('business_name', $user->business_name)->firstOrFail();
        if ($error = $this->vehicleEligibilityError($courier, 'two_wheels')) {
            if ($request->expectsJson() || $request->ajax()) {
                abort(422, $error);
            }
            return back()->withErrors(['courier_id' => $error])->withInput();
        }

        // Same FCFS-safe conditional update the rider self-accept path uses — whichever
        // request (this one, or a rider claiming it themselves) hits the still-unclaimed
        // row first wins; the other finds 0 rows affected.
        $claimed = $this->companyScope()->where('id', $shipment->id)
            ->where('shipping_status', 'ready_for_pickup')->whereNotNull('pickup_approved_at')->whereNull('pickup_rider_id')
            ->update(['pickup_rider_id' => $courier->id]);
        abort_if($claimed === 0, 422, 'This pickup request was already accepted by a rider.');

        DeliveryAssignment::updateOrCreate(
            ['shipment_id' => $shipment->id, 'leg' => 'pickup'],
            ['courier_id' => $courier->id, 'status' => 'accepted', 'accepted_at' => now()]
        );

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $courier->id,
            'title' => 'New Pickup Assignment', 'message' => 'You were assigned to pick up order #' . ($shipment->order?->order_number ?? $shipment->id) . '.',
            'notification_type' => 'pickup_assigned', 'reference_id' => $shipment->order_id,
            'is_read' => false, 'created_at' => now(),
        ]);
        if ($shipment->order?->seller_id) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $shipment->order->seller_id,
                'title' => 'Pickup Rider Assigned', 'message' => 'A rider was assigned to pick up order #' . $shipment->order->order_number . '. They\'re on their way.',
                'notification_type' => 'order_status', 'reference_id' => $shipment->order_id,
                'is_read' => false, 'created_at' => now(),
            ]);
        }

        return back()->with('success', 'Pickup rider assigned.');
    }

    /**
     * Hub staff request that every pending leg from their own hub to one destination
     * hub be moved on — one request per (from_hub, to_hub) batch, however many
     * shipments it covers. Needs the admin's approval (approveHubTransferRequest())
     * before any of them can actually be assigned to a rider.
     */
    public function requestHubTransfer(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isHubStaff() && $user->logisticsHub, 403);

        $data = $request->validate(['to_hub' => 'required|string']);

        $updated = $this->hubLegScope()->fromHub($user->logisticsHub->municipality)
            ->whereRaw('LOWER(to_hub) = ?', [mb_strtolower(trim($data['to_hub']))])
            ->where('status', 'pending')->whereNull('requested_at')
            ->update(['requested_at' => now(), 'requested_by' => $user->id]);

        abort_if($updated === 0, 422, 'Nothing here is waiting to move to that hub.');

        // Notify the company admin(s) — mirrors every other "new thing to review" notification.
        $adminIds = User::where('is_logistics', true)->where('business_name', $user->business_name)
            ->where(fn ($q) => $q->where('logistics_role', 'admin')->orWhereNull('logistics_role'))
            ->pluck('id');
        foreach ($adminIds as $adminId) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $adminId,
                'title' => 'Hub Transfer Request',
                'message' => "{$user->logisticsHub->municipality} hub requests {$updated} parcel(s) be moved to {$data['to_hub']}.",
                'notification_type' => 'hub_transfer_requested', 'reference_id' => null,
                'is_read' => false, 'created_at' => now(),
            ]);
        }

        return back()->with('success', "Requested transfer of {$updated} parcel(s) to {$data['to_hub']}.");
    }

    /** Admin's queue: every hub's outstanding requests and handoffs, grouped for one-click batch approval. */
    public function hubRequests()
    {
        $counts = $this->sidebarCounts();

        $pendingTransferRequests = $this->hubLegScope()
            ->where('status', 'pending')->whereNotNull('requested_at')->whereNull('approved_at')
            ->with(['shipment.order', 'requestedBy'])->get()
            ->groupBy(fn ($leg) => $leg->from_hub . '|' . $leg->to_hub);

        // Legs sitting at a hub that hasn't requested them yet — normally the origin hub's own
        // staff do that (see requestHubTransfer()), but the admin oversees every hub and
        // shouldn't have to wait on staff to log in and click it, especially for a same-province
        // 'direct' leg (one hop, no gateway routing) that's just as real a transfer as a
        // cross-province one. adminRequestHubTransfer() below lets the admin push these straight
        // through — company-wide, any leg type, same or different province.
        $notYetRequested = $this->hubLegScope()
            ->where('status', 'pending')->whereNull('requested_at')
            ->with(['shipment.order'])->get()
            ->groupBy(fn ($leg) => $leg->from_hub . '|' . $leg->to_hub);

        return view('logistics.hub-requests', array_merge($counts, compact('pendingTransferRequests', 'notYetRequested')));
    }

    /**
     * Admin directly initiates (and, in the same action, approves) a hub-to-hub transfer for
     * one (from_hub, to_hub) batch, company-wide — the admin-side counterpart to
     * requestHubTransfer() (hub staff's own, single-hub version). Works for a same-province
     * 'direct' leg exactly the same as a cross-province dispatch/relay/delivery leg; there's
     * no province restriction here, or anywhere else in this pipeline.
     */
    public function adminRequestHubTransfer(Request $request)
    {
        $data = $request->validate(['from_hub' => 'required|string', 'to_hub' => 'required|string']);
        $now  = now();

        $legs = $this->hubLegScope()
            ->whereRaw('LOWER(from_hub) = ?', [mb_strtolower(trim($data['from_hub']))])
            ->whereRaw('LOWER(to_hub) = ?', [mb_strtolower(trim($data['to_hub']))])
            ->where('status', 'pending')->whereNull('requested_at')
            ->get();

        abort_if($legs->isEmpty(), 422, 'Nothing here is waiting to move to that hub.');

        foreach ($legs as $leg) {
            $leg->update([
                'requested_at' => $now, 'requested_by' => auth()->id(),
                'approved_at'  => $now, 'approved_by'  => auth()->id(),
            ]);
        }

        // Notify that hub's own staff — they're the ones who'll actually assign a rider to it
        // from the Scan page, same as if they'd requested it themselves.
        $company  = auth()->user()->business_name;
        $hubStaff = User::where('account_type', 'logistics')->where('logistics_role', 'hub_staff')
            ->where('business_name', $company)->whereHas('logisticsHub', fn ($q) => $q->whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim($data['from_hub']))]))
            ->pluck('id');
        foreach ($hubStaff as $staffId) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $staffId,
                'title' => 'Hub Transfer Ready', 'message' => "Your admin initiated and approved a transfer of {$legs->count()} parcel(s) to {$data['to_hub']} — assign a rider from the Scan page.",
                'notification_type' => 'hub_transfer_approved', 'reference_id' => null,
                'is_read' => false, 'created_at' => $now,
            ]);
        }

        return back()->with('success', "Requested and approved transfer of {$legs->count()} parcel(s) from {$data['from_hub']} to {$data['to_hub']}.");
    }

    /** Approves every pending, requested (not yet approved) leg for one (from_hub, to_hub) batch at once. */
    public function approveHubTransferRequest(Request $request)
    {
        $data = $request->validate(['from_hub' => 'required|string', 'to_hub' => 'required|string']);

        $legs = $this->hubLegScope()
            ->whereRaw('LOWER(from_hub) = ?', [mb_strtolower(trim($data['from_hub']))])
            ->whereRaw('LOWER(to_hub) = ?', [mb_strtolower(trim($data['to_hub']))])
            ->where('status', 'pending')->whereNotNull('requested_at')->whereNull('approved_at')
            ->get();

        abort_if($legs->isEmpty(), 422, 'No pending request matches that hub pair.');

        $now = now();
        foreach ($legs as $leg) {
            $leg->update(['approved_at' => $now, 'approved_by' => auth()->id()]);
            if ($leg->requested_by) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(), 'user_id' => $leg->requested_by,
                    'title' => 'Hub Transfer Approved',
                    'message' => "Approved: {$data['from_hub']} \u{2192} {$data['to_hub']}. You can now assign a rider from the Scan page.",
                    'notification_type' => 'hub_transfer_approved', 'reference_id' => $leg->shipment_id,
                    'is_read' => false, 'created_at' => $now,
                ]);
            }
        }

        return back()->with('success', "Approved transfer of {$legs->count()} parcel(s) from {$data['from_hub']} to {$data['to_hub']}.");
    }

    public function rejectHubTransferRequest(Request $request)
    {
        $data = $request->validate(['from_hub' => 'required|string', 'to_hub' => 'required|string']);

        $updated = $this->hubLegScope()
            ->whereRaw('LOWER(from_hub) = ?', [mb_strtolower(trim($data['from_hub']))])
            ->whereRaw('LOWER(to_hub) = ?', [mb_strtolower(trim($data['to_hub']))])
            ->where('status', 'pending')->whereNotNull('requested_at')->whereNull('approved_at')
            ->update(['requested_at' => null, 'requested_by' => null]);

        return back()->with('success', "Sent {$updated} parcel(s) back to {$data['from_hub']} hub to re-request.");
    }

    /** e.g. "Leg 2 of 3" — null when there's only one leg, since numbering it adds nothing. */
    private function hubLegProgress(Shipment $shipment): ?string
    {
        $total = $shipment->hubLegs()->count();
        if ($total <= 1) {
            return null;
        }
        $done = $shipment->hubLegs()->where('status', 'completed')->count();
        return 'Leg ' . min($done + 1, $total) . ' of ' . $total;
    }

    /** Scan-station entry point: barcode/QR camera scan or USB scanner keyboard input. */
    public function scan()
    {
        $counts = $this->sidebarCounts();
        return view('logistics.scan', $counts);
    }

    public function scanLookup(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:100']);
        $code = trim($data['code']);

        $shipment = $this->companyScope()->with(['order.buyer', 'courier', 'hubTransferRider'])
            ->where('tracking_number', $code)
            ->first();

        if (!$shipment) {
            $order = Order::where('order_number', $code)->first();
            if ($order) {
                $shipment = $this->companyScope()->with(['order.buyer', 'courier', 'hubTransferRider'])->where('order_id', $order->id)->first();
            }
        }

        if (!$shipment) {
            return response()->json(['ok' => false, 'message' => 'No shipment found for "' . $code . '".'], 404);
        }

        $buyer       = $shipment->order?->buyer;
        $addr        = $shipment->order?->shipping_address ?? [];
        $area        = $addr['municipality'] ?? null; // delivery area = the order's destination municipality
        $allowedNext = self::STATUS_TRANSITIONS[$shipment->shipping_status] ?? [];
        $forwardNext = collect($allowedNext)->first(fn ($s) => !in_array($s, ['delivery_failed', 'returned'], true));
        $canFail     = in_array('delivery_failed', $allowedNext, true);

        // Sorting-center stage this parcel is at: receive+sort it in (one scan, since sorting
        // is just an automatic address lookup), assign it to an area rider, or (once assigned)
        // advance it through the normal delivery statuses.
        $stage            = 'none';
        $areaRiders       = [];
        $areaMatched      = false;
        $areaServiceable  = true;
        $areaNote         = null;
        $hubTransferRiders = [];
        $currentHubLeg     = null;
        $hubLegProgress    = null;
        // Hub transfers ride on a four-wheeler — see eligibleRiders().
        $availableHubTransferRiders = fn () => $this->eligibleRiders('four_wheels')
            ->map(fn ($r) => ['id' => $r->id, 'name' => trim($r->given_names . ' ' . $r->last_name)])->values();

        if ($shipment->shipping_status === 'ready_for_pickup') {
            $stage = $shipment->pickup_rider_id ? 'awaiting_seller_confirm' : 'awaiting_pickup_rider';
        } elseif ($shipment->shipping_status === 'picked_up') {
            $stage = 'receive';
        } elseif ($shipment->shipping_status === 'at_sorting_center' && $shipment->needsHubTransfer()) {
            // Additive on top of the normal flow — only reached when this shipment's
            // origin and destination hub differ (see receiveAtSortingCenter()).
            $currentHubLeg  = $shipment->nextPendingHubLeg();
            $hubLegProgress = $this->hubLegProgress($shipment);
            if ($shipment->nextAssignableHubLeg()) {
                $stage             = 'assign_hub_transfer';
                $hubTransferRiders = $availableHubTransferRiders();
            } else {
                // Not requested/approved yet — this hub's staff need to request it
                // from their dashboard before a rider can be assigned here.
                $stage = 'awaiting_transfer_approval';
            }
        } elseif ($shipment->shipping_status === 'hub_transfer') {
            $activeLeg = $shipment->activeHubLeg();
            if ($activeLeg) {
                // Rider is already carrying it on the current leg — nothing to do
                // here until the receiving hub scans it in via completeHubTransfer().
                $stage         = 'in_hub_transfer';
                $currentHubLeg = $activeLeg;
            } else {
                // The previous leg was just completed and another one remains.
                $currentHubLeg = $shipment->nextPendingHubLeg();
                if ($shipment->nextAssignableHubLeg()) {
                    $stage             = 'assign_hub_transfer';
                    $hubTransferRiders = $availableHubTransferRiders();
                } else {
                    $stage = 'awaiting_transfer_approval';
                }
            }
            $hubLegProgress = $this->hubLegProgress($shipment);
        } elseif ($shipment->shipping_status === 'sorted') {
            $stage = 'assign';

            $unserviceable = $area
                ? UnserviceableArea::whereRaw('LOWER(municipality) = ?', [strtolower($area)])->first()
                : null;

            if ($unserviceable) {
                // Set from Logistics Settings — don't silently hand back a rider
                // list for an area the sorting center isn't currently dispatching to.
                $areaServiceable = false;
                $areaNote        = $unserviceable->note;
            } else {
                // Only this company's own riders whose vehicle actually fits a delivery leg (two-wheeler).
                $riders = $this->eligibleRiders('two_wheels');
                if ($area) {
                    $matched = $riders->filter(fn ($r) => mb_strtolower(trim((string) $r->municipality)) === mb_strtolower(trim($area)))->values();
                    if ($matched->isNotEmpty()) {
                        $riders      = $matched;
                        $areaMatched = true;
                    }
                }
                $areaRiders = $riders->map(fn ($r) => [
                    'id'           => $r->id,
                    'name'         => trim($r->given_names . ' ' . $r->last_name),
                    'municipality' => $r->municipality,
                ])->values();
            }
        } elseif ($forwardNext) {
            $stage = 'advance';
        }

        return response()->json([
            'ok'       => true,
            'shipment' => [
                'id'              => $shipment->id,
                'tracking_number' => $shipment->tracking_number ?? substr($shipment->id, 0, 8),
                'order_number'    => $shipment->order?->order_number,
                'buyer_name'      => trim(($buyer->given_names ?? '') . ' ' . ($buyer->last_name ?? '')) ?: null,
                'buyer_contact'   => $buyer->contact_no ?? null,
                'address'         => implode(', ', array_filter([
                    $addr['house_no'] ?? null, $addr['street'] ?? null, $addr['barangay'] ?? null,
                    $addr['municipality'] ?? null, $addr['province'] ?? null,
                ])) ?: null,
                'delivery_area'   => $area,
                'pickup_rider_name' => $shipment->pickupRider
                    ? trim($shipment->pickupRider->given_names . ' ' . $shipment->pickupRider->last_name)
                    : null,
                'courier_name'    => $shipment->courier
                    ? trim($shipment->courier->given_names . ' ' . $shipment->courier->last_name)
                    : null,
                'origin_hub'      => $shipment->origin_hub,
                'destination_hub' => $shipment->destination_hub,
                'hub_transfer_rider_name' => $shipment->hubTransferRider
                    ? trim($shipment->hubTransferRider->given_names . ' ' . $shipment->hubTransferRider->last_name)
                    : null,
                // The leg actually being worked right now — for a same-province transfer
                // this is the whole trip (from_hub/to_hub == origin_hub/destination_hub);
                // for a cross-province one it's just the current hop of up to three.
                'current_leg'     => $currentHubLeg ? [
                    'leg_type'        => $currentHubLeg->leg_type,
                    'from_hub'        => $currentHubLeg->from_hub,
                    'to_hub'          => $currentHubLeg->to_hub,
                    'requested'       => (bool) $currentHubLeg->requested_at,
                    'rider_confirmed' => (bool) $currentHubLeg->rider_confirmed_pickup_at,
                    // Only the destination hub's own staff (or the admin) can mark it received
                    // there — the origin hub, or any other hub, scanning the same tracking
                    // number shouldn't see that action at all.
                    'can_complete'    => !auth()->user()->isHubStaff() || (auth()->user()->logisticsHub
                        && mb_strtolower(trim(auth()->user()->logisticsHub->municipality)) === mb_strtolower(trim($currentHubLeg->to_hub))),
                ] : null,
                'hub_leg_progress' => $hubLegProgress,
                'status'          => $shipment->shipping_status,
                'status_label'    => ucfirst(str_replace('_', ' ', $shipment->shipping_status)),
                'updated_at'      => optional($shipment->updated_at)->format('M d, Y H:i'),
            ],
            'stage'             => $stage,
            'area_riders'       => $areaRiders,
            'area_matched'      => $areaMatched,
            'area_serviceable'  => $areaServiceable,
            'area_note'         => $areaNote,
            'hub_transfer_riders' => $hubTransferRiders,
            'next_status'  => $forwardNext,
            'next_label'   => $forwardNext ? (self::STAGE_LABELS[$forwardNext] ?? ucfirst(str_replace('_', ' ', $forwardNext))) : null,
            'can_fail'     => $canFail,
        ]);
    }

    /**
     * One scan action covers both "Receive Parcel" and "Sort Parcel According to Destination
     * Area" from the doc's sorting-center flow — sorting here is just an automatic address
     * lookup the system already does (no separate manual step needed for it). The one
     * exception, additive on top of that: when the seller's and buyer's cities are different
     * hubs for this shipment's company (needsHubTransfer()), the parcel pauses here at
     * 'at_sorting_center' for a hub-transfer rider to be assigned (assignHubTransferRider())
     * instead of jumping straight to 'sorted' — everything else about this flow is unchanged.
     */
    public function receiveAtSortingCenter(Request $request, $id)
    {
        $shipment = $this->companyScope()->with('order')->findOrFail($id);
        abort_unless($shipment->shipping_status === 'picked_up', 422, 'This parcel has not been picked up from the seller yet.');

        $area = $shipment->order?->shipping_address['municipality'] ?? null;
        $now  = now();

        if ($shipment->needsHubTransfer()) {
            $shipment->buildHubLegs();
            $shipment->update(['shipping_status' => 'at_sorting_center', 'at_sorting_center_at' => $now]);
            $shipment->order?->update(['status' => 'at_sorting_center']);

            DB::table('order_status_history')->insert([
                'id' => (string) Str::uuid(), 'order_id' => $shipment->order_id, 'status' => 'at_sorting_center',
                'changed_by' => auth()->id(), 'created_at' => $now,
            ]);

            $firstLeg = $shipment->nextPendingHubLeg();
            return response()->json(['ok' => true, 'message' => "Received at {$shipment->origin_hub}. This hub's staff can now request a transfer to " . ($firstLeg->to_hub ?? $shipment->destination_hub) . " from their dashboard."]);
        }

        $shipment->update([
            'shipping_status'      => 'sorted',
            'at_sorting_center_at' => $now,
            'sorted_at'            => $now,
            'sorted_area'          => $area,
        ]);
        $shipment->order?->update(['status' => 'sorted']);

        DB::table('order_status_history')->insert([
            'id' => (string) Str::uuid(), 'order_id' => $shipment->order_id, 'status' => 'at_sorting_center',
            'changed_by' => auth()->id(), 'created_at' => $now,
        ]);
        DB::table('order_status_history')->insert([
            'id' => (string) Str::uuid(), 'order_id' => $shipment->order_id, 'status' => 'sorted',
            'changed_by' => auth()->id(), 'created_at' => $now,
        ]);

        return response()->json(['ok' => true, 'message' => 'Parcel received and sorted' . ($area ? " to {$area}" : '') . '.']);
    }

    public function monitor()
    {
        $counts      = $this->sidebarCounts();
        $shipments   = $this->companyScope()->with(['order.buyer', 'courier', 'pickupRider', 'hubTransferRider', 'deliveryAssignment'])
            ->whereIn('shipping_status', ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'hub_transfer', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed'])
            ->latest('created_at')->get();
        $transitions = self::STATUS_TRANSITIONS;
        return view('logistics.monitor', array_merge($counts, compact('shipments', 'transitions')));
    }

    /** Shipment stage → the timestamp column on `shipments` to stamp. */
    private const STAGE_TIMESTAMPS = [
        'at_sorting_center' => 'at_sorting_center_at',
        'sorted'            => 'sorted_at',
        'assigned_to_rider' => 'assigned_at',
        'out_for_delivery'  => 'out_for_delivery_at',
        'delivered'         => 'delivered_at',
        'delivery_failed'   => 'delivery_failed_at',
        'returned'          => 'returned_at',
    ];

    /**
     * Order.status now shares the exact same vocabulary as Shipment::shipping_status (see
     * both models' STATUSES const), so every shipment transition rolls straight onto the
     * order unchanged — no remapping table needed. The one deliberate exception is baked
     * into the pipeline itself, not handled here: 'delivered' is a shipment/order status in
     * its own right, never auto-advanced to 'completed' — that only happens via the buyer's
     * own confirmReceipt() (or logistics manually closing it out through this same method).
     */
    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', self::STATUSES),
            'reason' => 'nullable|string|max:500',
        ]);
        $shipment = $this->companyScope()->with('order')->findOrFail($id);
        $status   = $data['status'];

        // Guard the transition server-side too — the UI only ever offers valid next
        // statuses, but this stops a crafted request from skipping stages or moving
        // a shipment backward.
        $allowed = self::STATUS_TRANSITIONS[$shipment->shipping_status] ?? [];
        if (!in_array($status, $allowed, true)) {
            return back()->withErrors(['status' => 'Cannot move this shipment from '
                . ucfirst(str_replace('_', ' ', $shipment->shipping_status)) . ' to '
                . ucfirst(str_replace('_', ' ', $status)) . '.']);
        }

        // "Out for delivery" is the assigned rider's own call to make (they're the one
        // actually leaving with it) — from their own "My Deliveries" page (RiderController::advance()),
        // not something hub staff mark on the rider's behalf from the Scan page.
        abort_if($status === 'out_for_delivery', 422, 'The assigned rider marks this themselves from their own account when they head out.');

        $updates = ['shipping_status' => $status];
        if (isset(self::STAGE_TIMESTAMPS[$status])) {
            $updates[self::STAGE_TIMESTAMPS[$status]] = now();
        }
        if ($status === 'delivery_failed') {
            $updates['delivery_failed_reason'] = $data['reason'] ?? 'No reason given.';
        }
        $shipment->update($updates);

        if ($shipment->order) {
            $shipment->order->update(['status' => $status]);

            DB::table('notifications')->insert([
                'id'                => (string) Str::uuid(),
                'user_id'           => $shipment->order->buyer_id,
                'title'             => 'Order Update',
                'message'           => 'Your order #' . $shipment->order->order_number . ' is now ' . str_replace('_', ' ', $status) . '.',
                'notification_type' => 'order_status',
                'reference_id'      => $shipment->order_id,
                'is_read'           => false,
                'created_at'        => now(),
            ]);

            // Let the seller know the moment the parcel is marked delivered — the order
            // itself still waits on the buyer's own confirmation to close out.
            if ($status === 'delivered') {
                DB::table('notifications')->insert([
                    'id'                => (string) Str::uuid(),
                    'user_id'           => $shipment->order->seller_id,
                    'title'             => 'Courier Marked as Delivered',
                    'message'           => 'Order #' . $shipment->order->order_number . ' was marked delivered by the courier — awaiting the buyer\'s confirmation.',
                    'notification_type' => 'order_delivered',
                    'reference_id'      => $shipment->order_id,
                    'is_read'           => false,
                    'created_at'        => now(),
                ]);
            }

            if ($status === 'delivery_failed') {
                DB::table('notifications')->insert([
                    'id'                => (string) Str::uuid(),
                    'user_id'           => $shipment->order->seller_id,
                    'title'             => 'Delivery Attempt Failed',
                    'message'           => 'Order #' . $shipment->order->order_number . ' delivery failed: ' . ($data['reason'] ?? 'No reason given.'),
                    'notification_type' => 'delivery_failed',
                    'reference_id'      => $shipment->order_id,
                    'is_read'           => false,
                    'created_at'        => now(),
                ]);
            }
        }

        DB::table('order_status_history')->insert([
            'id' => (string) Str::uuid(), 'order_id' => $shipment->order_id, 'status' => $status,
            'changed_by' => auth()->id(), 'notes' => $data['reason'] ?? null, 'created_at' => now(),
        ]);

        return back();
    }

    public function issues()
    {
        $counts    = $this->sidebarCounts();
        $shipments = $this->companyScope()->with(['order.buyer', 'courier'])
            ->whereIn('shipping_status', ['delivery_failed', 'returned', 'cancelled'])
            ->latest('created_at')->get();
        return view('logistics.issues', array_merge($counts, compact('shipments')));
    }

    public function history()
    {
        $counts    = $this->sidebarCounts();
        $shipments = $this->companyScope()->with(['order.buyer', 'courier'])
            ->whereIn('shipping_status', ['delivered', 'completed'])
            ->latest('delivered_at')->get();
        return view('logistics.history', array_merge($counts, compact('shipments')));
    }

    public function reports()
    {
        $counts    = $this->sidebarCounts();
        $company   = auth()->user()->business_name;
        $total     = $this->companyScope()->count();
        $completed = $this->companyScope()->whereIn('shipping_status', ['delivered', 'completed'])->count();
        $cancelled = $this->companyScope()->where('shipping_status', 'cancelled')->count();
        $failed    = $this->companyScope()->whereIn('shipping_status', ['delivery_failed', 'returned'])->count();
        $couriers  = User::where('account_type', 'rider')->where('status', 'approved')->where('business_name', $company)->count();

        $courierStats = User::where('account_type', 'rider')
            ->where('status', 'approved')->where('business_name', $company)
            ->selectRaw("users.*, (SELECT COUNT(*) FROM shipments WHERE shipments.courier_id = users.id AND shipping_status IN ('delivered','completed')) AS delivered_count")
            ->orderByDesc('delivered_count')
            ->take(10)->get();

        return view('logistics.reports', array_merge($counts, compact(
            'total', 'completed', 'cancelled', 'failed', 'couriers', 'courierStats'
        )));
    }

    // ── Rider management: "approve/disapprove rider/courier application; activate/deactivate" ──
    // Per the ERP spec, courier applications wait on the Logistics/Sorting Center's approval,
    // not the Admin's — unlike buyer/seller/logistics, which stay on AdminController.

    public function riders(Request $request)
    {
        $counts = $this->sidebarCounts();
        $company = auth()->user()->business_name;
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected', 'suspended'], true)
            ? $request->query('status') : 'pending';
        // A company's own roster only — riders who joined this exact company at registration.
        $riders = User::where('account_type', 'rider')->where('business_name', $company)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()->get();
        $statusCounts = User::where('account_type', 'rider')->where('business_name', $company)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        return view('logistics.riders', array_merge($counts, compact('riders', 'status', 'statusCounts')));
    }

    private function rider(int $id): User
    {
        return User::where('account_type', 'rider')->where('business_name', auth()->user()->business_name)->findOrFail($id);
    }

    /** Mirrors AdminController::notifyRegistrationDecision() — riders get the same email promise. */
    private function notifyRiderDecision(User $rider, bool $approved): void
    {
        if (!$rider->email) return;
        try {
            \Illuminate\Support\Facades\Mail::raw(
                $approved
                    ? "Good news! Your PocketFinds Courier account has been approved by the Logistics/Sorting Center team. You can now log in at " . url('/login') . "."
                    : "Your PocketFinds Courier account application was not approved. If you believe this is a mistake, please contact support.",
                fn ($m) => $m->to($rider->email)->subject($approved ? 'PocketFinds — Courier Application Approved' : 'PocketFinds — Courier Application Update')
            );
        } catch (\Exception $e) {
            // Status is already saved either way — a mail hiccup shouldn't block the action.
        }
    }

    public function approveRider($id)
    {
        $rider = $this->rider($id);
        $rider->update(['status' => 'approved']);
        $this->notifyRiderDecision($rider, true);
        return back()->with('success', 'Courier application approved.');
    }

    public function rejectRider($id)
    {
        $rider = $this->rider($id);
        $rider->update(['status' => 'rejected']);
        $this->notifyRiderDecision($rider, false);
        return back()->with('success', 'Courier application rejected.');
    }

    public function activateRider($id)
    {
        $this->rider($id)->update(['status' => 'approved']);
        return back()->with('success', 'Courier activated.');
    }

    public function suspendRider($id)
    {
        $this->rider($id)->update(['status' => 'suspended']);
        return back()->with('success', 'Courier suspended.');
    }

    /**
     * Hub staff applications for this company — mirrors riders() exactly. Hub staff join an
     * existing company the same way a rider does, so it's this company's own admin who
     * reviews them, not the platform admin (see AdminController::registrations()).
     */
    /**
     * Every hub staff application, fetched once — the Pending/Interview/Approved/... tabs
     * filter this same set client-side (see the shared [data-tabs] handler in admin.js),
     * so switching tabs no longer round-trips to the server and re-renders the whole page.
     */
    public function staff(Request $request)
    {
        $counts  = $this->sidebarCounts();
        $company = auth()->user()->business_name;
        $staff = User::with('logisticsHub')->where('account_type', 'logistics')->where('logistics_role', 'hub_staff')
            ->where('business_name', $company)
            ->latest()->get();
        $statusCounts = $staff->countBy('status');
        return view('logistics.staff', array_merge($counts, compact('staff', 'statusCounts')));
    }

    private function staffMember(int $id): User
    {
        return User::where('account_type', 'logistics')->where('logistics_role', 'hub_staff')
            ->where('business_name', auth()->user()->business_name)->findOrFail($id);
    }

    /** Mirrors notifyRiderDecision() — hub staff get the same email promise, one per stage of the review. */
    private function notifyStaffStatus(User $staff, string $status, ?string $reason = null): void
    {
        if (!$staff->email) return;
        $reasonLine = $reason ? "\n\nReason: {$reason}" : '';
        [$subject, $body] = match ($status) {
            'interview' => [
                'PocketFinds — Face-to-Face Interview Invitation',
                "Good news! {$staff->business_name} would like to move forward with your Hub Staff application.\n\n"
                    . "You're invited to a face-to-face interview:\n"
                    . "  Date: " . $staff->interview_scheduled_at?->format('l, F j, Y') . "\n"
                    . "  Time: " . $staff->interview_scheduled_at?->format('g:i A') . "\n"
                    . "  Location: {$staff->interview_location}\n\n"
                    . "Once that's completed and confirmed, your account will be enabled and you'll be able to log in.\n\n"
                    . "If you have any questions or need to reschedule, please reach out to {$staff->business_name} directly.",
            ],
            'approved' => [
                'PocketFinds — Hub Staff Application Approved',
                "Good news! Your PocketFinds Hub Staff account for {$staff->business_name} has been approved following your interview. "
                    . "You can now log in at " . url('/login') . ".",
            ],
            'rejected' => [
                'PocketFinds — Hub Staff Application Update',
                "Your PocketFinds Hub Staff application was not approved. If you believe this is a mistake, please contact support.{$reasonLine}",
            ],
            'suspended' => [
                'PocketFinds — Hub Staff Account Suspended',
                "Your PocketFinds Hub Staff account for {$staff->business_name} has been suspended and you will not be able to log in until it's reactivated. "
                    . "If you believe this is a mistake, please contact {$staff->business_name} or PocketFinds support.{$reasonLine}",
            ],
            'activated' => [
                'PocketFinds — Hub Staff Account Reactivated',
                "Good news! Your PocketFinds Hub Staff account for {$staff->business_name} has been reactivated. "
                    . "You can now log in again at " . url('/login') . ".",
            ],
            default => [null, null],
        };
        if (!$subject) return;

        try {
            \Illuminate\Support\Facades\Mail::raw($body, fn ($m) => $m->to($staff->email)->subject($subject));
        } catch (\Exception $e) {
            // Status is already saved either way — a mail hiccup shouldn't block the action.
        }
    }

    /**
     * "Approve" on a fresh application doesn't grant login — it invites the applicant to a
     * face-to-face interview and holds the account at 'interview' (still blocked from
     * logging in, same as 'pending') until confirmStaffApproval() gives the real, final
     * approval once that interview has actually happened.
     */
    public function inviteStaffInterview(Request $request, $id)
    {
        $staff = $this->staffMember($id);
        abort_unless($staff->status === 'pending', 422, 'Only a pending application can be invited to interview.');

        $data = $request->validate([
            'interview_date'     => 'required|date|after_or_equal:today',
            'interview_time'     => 'required|date_format:H:i',
            'interview_location' => 'required|string|max:255',
        ]);

        $staff->update([
            'status'                 => 'interview',
            'interview_scheduled_at' => "{$data['interview_date']} {$data['interview_time']}",
            'interview_location'     => $data['interview_location'],
        ]);
        $this->notifyStaffStatus($staff, 'interview');
        return back()->with('success', 'Interview invitation sent — the applicant is on hold until you confirm after the interview.');
    }

    /** The actual, final approval — only meant to be clicked after the face-to-face interview has happened. */
    public function confirmStaffApproval($id)
    {
        $staff = $this->staffMember($id);
        abort_unless(in_array($staff->status, ['interview', 'pending'], true), 422, 'This application is not awaiting approval.');
        $staff->update(['status' => 'approved', 'status_reason' => null]);
        $this->notifyStaffStatus($staff, 'approved');
        return back()->with('success', 'Hub staff application approved — they can now log in.');
    }

    public function rejectStaff(Request $request, $id)
    {
        $staff  = $this->staffMember($id);
        $reason = $this->resolveStaffReason($request);
        $staff->update(['status' => 'rejected', 'status_reason' => $reason]);
        $this->notifyStaffStatus($staff, 'rejected', $reason);
        return back()->with('success', 'Hub staff application rejected.');
    }

    public function activateStaff($id)
    {
        $staff = $this->staffMember($id);
        $staff->update(['status' => 'approved', 'status_reason' => null]);
        $this->notifyStaffStatus($staff, 'activated');
        return back()->with('success', 'Hub staff account reactivated.');
    }

    public function suspendStaff(Request $request, $id)
    {
        $staff  = $this->staffMember($id);
        $reason = $this->resolveStaffReason($request);
        $staff->update(['status' => 'suspended', 'status_reason' => $reason]);
        $this->notifyStaffStatus($staff, 'suspended', $reason);
        return back()->with('success', 'Hub staff account suspended.');
    }

    /**
     * Mirrors AdminController::resolveReason() exactly — a preset radio pick plus an
     * always-fillable details textarea, required only when "Other" is the pick. Shared
     * by the same admin.partials.reason-modals markup Reject/Suspend already use elsewhere.
     */
    private function resolveStaffReason(Request $request): ?string
    {
        $request->validate([
            'reason_preset'  => 'required|string|max:150',
            'reason_details' => $request->input('reason_preset') === 'other' ? 'required|string|max:1000' : 'nullable|string|max:1000',
        ]);
        $preset  = $request->input('reason_preset');
        $details = trim((string) $request->input('reason_details'));
        if ($preset === 'other') return $details;
        return $details !== '' ? "{$preset} — {$details}" : $preset;
    }

    /** Hub management — view all company hubs, staff counts, and toggle hiring status. */
    public function hubs(Request $request)
    {
        $counts  = $this->sidebarCounts();
        $company = auth()->user()->business_name;

        $search       = trim($request->query('search', ''));
        $hiringFilter = $request->query('hiring', ''); // 'all', 'hiring', 'not_hiring'

        $hubsQuery = \App\Models\LogisticsHub::where('company_name', $company);

        if ($search) {
            $hubsQuery->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(municipality) LIKE ?', ['%' . mb_strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(province) LIKE ?', ['%' . mb_strtolower($search) . '%']);
            });
        }

        if ($hiringFilter === 'hiring') {
            $hubsQuery->where('is_hiring', true);
        } elseif ($hiringFilter === 'not_hiring') {
            $hubsQuery->where('is_hiring', false);
        }

        $allHubs = $hubsQuery->orderBy('province')->orderBy('municipality')->get();

        // Staff counts per hub
        $staffPerHub = User::where('account_type', 'logistics')
            ->where('business_name', $company)
            ->where('status', 'approved')
            ->whereNotNull('logistics_hub_id')
            ->selectRaw('logistics_hub_id, count(*) as count')
            ->groupBy('logistics_hub_id')
            ->pluck('count', 'logistics_hub_id');

        // Metrics for summary cards
        $totalHubs         = \App\Models\LogisticsHub::where('company_name', $company)->count();
        $regionalHubsCount = \App\Models\LogisticsHub::where('company_name', $company)->where('is_regional_hub', true)->count();
        $hiringHubsCount   = \App\Models\LogisticsHub::where('company_name', $company)->where('is_hiring', true)->count();
        $notHiringHubsCount = $totalHubs - $hiringHubsCount;

        // Group hubs by province
        $groupedHubs = $allHubs->groupBy('province');

        return view('logistics.hubs', array_merge($counts, compact(
            'groupedHubs', 'totalHubs', 'regionalHubsCount', 'hiringHubsCount', 'notHiringHubsCount',
            'staffPerHub', 'search', 'hiringFilter'
        )));
    }

    public function toggleHubHiring(Request $request, $id)
    {
        $company = auth()->user()->business_name;
        $hub = \App\Models\LogisticsHub::where('company_name', $company)->findOrFail($id);

        if ($request->has('is_hiring')) {
            $hub->is_hiring = filter_var($request->input('is_hiring'), FILTER_VALIDATE_BOOLEAN);
        } else {
            $hub->is_hiring = !$hub->is_hiring;
        }
        $hub->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'   => true,
                'is_hiring' => $hub->is_hiring,
                'message'   => "{$hub->municipality} hub hiring status updated to " . ($hub->is_hiring ? 'Hiring' : 'Not Hiring') . ".",
            ]);
        }

        return back()->with('success', "{$hub->municipality} hub hiring status updated to " . ($hub->is_hiring ? 'Hiring' : 'Not Hiring') . ".");
    }

    /**
     * Company-wide vehicle fleet — logistics admin submits vehicles here (assigning them
     * to a specific hub), then the platform admin reviews each one. Vehicles only appear
     * to riders once the platform admin approves them.
     */
    public function fleet(Request $request)
    {
        $counts  = $this->sidebarCounts();
        $company = auth()->user()->business_name;

        $vehicles     = \App\Models\CompanyVehicle::with(['hub', 'submitter'])
            ->where('company_name', $company)->latest()->get();
        $platformCounts = $vehicles->countBy('platform_status');
        $hubs         = \App\Models\LogisticsHub::where('company_name', $company)
            ->orderBy('province')->orderBy('municipality')->get();
        $vehicleTypes = DB::table('vehicle_types')->orderBy('id')->get();

        return view('logistics.fleet', array_merge($counts, compact('vehicles', 'platformCounts', 'hubs', 'vehicleTypes')));
    }

    private function fleetVehicle(int $id): \App\Models\CompanyVehicle
    {
        return \App\Models\CompanyVehicle::where('company_name', auth()->user()->business_name)->findOrFail($id);
    }

    /** Logistics admin submits a vehicle — assigns it to one of their hubs, then waits for platform admin to approve. */
    public function storeVehicle(Request $request)
    {
        abort_unless(auth()->user()->isLogisticsAdmin(), 403);
        $company = auth()->user()->business_name;

        $data = $request->validate([
            'logistics_hub_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('logistics_hubs', 'id')
                ->where('company_name', $company)],
            'vehicle_type'     => 'required|string|exists:vehicle_types,slug',
            'brand'            => 'required|string|max:100',
            'model'            => 'required|string|max:100',
            'plate_number'     => 'required|string|max:20|unique:company_vehicles,plate_number',
        ]);

        \App\Models\CompanyVehicle::create([
            'company_name'     => $company,
            'logistics_hub_id' => $data['logistics_hub_id'],
            'vehicle_type'     => $data['vehicle_type'],
            'brand'            => $data['brand'],
            'model'            => $data['model'],
            'plate_number'     => strtoupper($data['plate_number']),
            'submitted_by'     => auth()->id(),
            'platform_status'  => 'pending',
            'status'           => 'pending',
        ]);

        return back()->with('success', 'Vehicle submitted to PocketFinds for review. It will be available for rider assignment once approved.');
    }

    /** Logistics admin removes a vehicle they submitted (only while still pending platform review). */
    public function destroyVehicle($id)
    {
        abort_unless(auth()->user()->isLogisticsAdmin(), 403);
        $vehicle = $this->fleetVehicle($id);
        abort_unless($vehicle->platform_status === 'pending', 422, 'Only pending vehicles can be removed.');
        $vehicle->delete();
        return back()->with('success', 'Vehicle removed.');
    }

    /** Hub staff's own view of their hub's vehicles — read-only list + availability toggle. */
    public function hubVehicles()
    {
        abort_unless(auth()->user()->isHubStaff(), 403);
        $counts = $this->sidebarCounts();
        $hub    = auth()->user()->logisticsHub;
        abort_unless($hub, 422, 'Your account has no hub linked.');

        $vehicles = \App\Models\CompanyVehicle::where('logistics_hub_id', $hub->id)->latest()->get();

        return view('logistics.hub-vehicles', array_merge($counts, compact('hub', 'vehicles')));
    }

    /** Hub staff toggles an already platform-approved vehicle in/out of maintenance. */
    public function toggleVehicleAvailability($id)
    {
        abort_unless(auth()->user()->isHubStaff(), 403);
        $hub     = auth()->user()->logisticsHub;
        $vehicle = \App\Models\CompanyVehicle::where('logistics_hub_id', $hub?->id)
            ->where('platform_status', 'approved')->findOrFail($id);
        $vehicle->update(['is_available' => !$vehicle->is_available]);
        $msg = $vehicle->is_available
            ? "{$vehicle->brand} {$vehicle->model} marked available again."
            : "{$vehicle->brand} {$vehicle->model} marked as in maintenance — it won't be offered to new riders until it's back.";
        return back()->with('success', $msg);
    }

    /** Enrich a set of contacts with their latest message + unread count against this staff member. */
    private function withThreadPreview($users)
    {
        $myId    = auth()->id();
        $threads = Message::where('sender_id', $myId)->orWhere('receiver_id', $myId)
            ->latest('created_at')->get()
            ->groupBy(fn ($m) => $m->sender_id === $myId ? $m->receiver_id : $m->sender_id);

        return $users->map(function ($u) use ($threads, $myId) {
            $thread          = $threads->get($u->id);
            $u->last_message = $thread?->first();
            $u->unread_count = $thread ? $thread->where('receiver_id', $myId)->where('read', false)->count() : 0;
            return $u;
        })->sortByDesc(fn ($u) => $u->last_message?->created_at ?? \Carbon\Carbon::createFromTimestamp(0))->values();
    }

    private function allowedContacts(): array
    {
        return [
            'admins'   => $this->withThreadPreview(User::where('is_admin', true)->get()),
            'couriers' => $this->withThreadPreview(User::where('account_type', 'rider')->where('status', 'approved')->get()),
            'sellers'  => $this->withThreadPreview(User::where('account_type', 'seller')->where('status', 'approved')->get()),
        ];
    }

    protected function isAllowedContact(User $user): bool
    {
        return $user->is_admin
            || $user->account_type === 'rider'
            || $user->account_type === 'seller';
    }

    public function messages()
    {
        $counts     = $this->sidebarCounts();
        $activeUser = null;
        $messages   = collect();
        return view('logistics.messages', array_merge($counts, $this->allowedContacts(), compact('activeUser', 'messages')));
    }

    public function messagesThread($userId)
    {
        $counts     = $this->sidebarCounts();
        $activeUser = User::findOrFail($userId);
        abort_if(!$this->isAllowedContact($activeUser), 403);
        Message::where('sender_id', $activeUser->id)->where('receiver_id', auth()->id())->where('read', false)->update(['read' => true]);
        $messages = Message::where(fn($q) => $q->where('sender_id', auth()->id())->where('receiver_id', $activeUser->id))
            ->orWhere(fn($q) => $q->where('sender_id', $activeUser->id)->where('receiver_id', auth()->id()))
            ->oldest()->get();
        return view('logistics.messages', array_merge($counts, $this->allowedContacts(), compact('activeUser', 'messages')));
    }

    public function account()
    {
        $counts = $this->sidebarCounts();
        $companyName = auth()->user()->business_name;
        $companyPolicy = $companyName ? \App\Models\LogisticsCompany::policyFor($companyName) : null;
        return view('logistics.account', array_merge($counts, [
            'company'        => $companyName,
            'companyPolicy'  => $companyPolicy,
            'pendingRequest' => $this->pendingAccountUpdateRequest(),
            'lastRequest'    => $this->lastAccountUpdateRequest(),
        ]));
    }

    /**
     * A logistics staff member submits an edit to their own company's Terms &
     * Conditions. It only becomes the live, registrant-facing version once an
     * admin approves it (see AdminController::approveCompanyPolicy()).
     */
    public function updateCompanyPolicy(Request $request)
    {
        $companyName = auth()->user()->business_name;
        abort_unless($companyName, 404);
        $data = $request->validate(['content' => 'required|string|max:20000']);

        $policy = \App\Models\LogisticsCompany::policyFor($companyName)
            ?? \App\Models\Policy::create(['type' => 'logistics_company_terms', 'company_name' => $companyName, 'title' => $companyName . ' — Terms & Conditions']);
        $policy->submitPending($data['content'], auth()->id());

        return back()->with('policy_success', 'Submitted — an admin will review it before it goes live.');
    }

    /**
     * The profile picture is applied immediately (purely cosmetic, self-service);
     * every other field is submitted as a pending request — nothing else changes
     * on the account until an admin approves it.
     */
    public function accountUpdate(Request $request)
    {
        $request->validate([
            'given_names'      => 'required|string|max:255',
            'last_name'        => 'required|string|max:255',
            'middle_name'      => ['nullable', 'regex:/^[A-Za-z]$/'],
            'contact_no'       => ['nullable', 'regex:/^09[0-9]{9}$/'],
            'sex'              => 'nullable|in:male,female,other',
            'profile_picture'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'middle_name.regex' => 'Middle name must be a single letter.',
            'contact_no.regex'  => 'Contact number must start with 09 and be exactly 11 digits.',
        ]);

        if ($request->hasFile('profile_picture')) {
            $user = auth()->user();
            if ($user->profile_picture) {
                Storage::disk('profile_images')->delete($user->profile_picture);
            }
            $user->update(['profile_picture' => $request->file('profile_picture')->store('avatars', 'profile_images')]);
        }

        return $this->submitAccountUpdateRequest(
            $request,
            ['given_names', 'last_name', 'middle_name', 'contact_no', 'sex'],
            [],
            'profile_success'
        );
    }

    /** Submits an address-change request — nothing changes on the account until admin approves it. */
    public function accountAddressUpdate(Request $request)
    {
        $request->validate([
            'province'     => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay'     => 'required|string|max:255',
            'house_no'     => 'nullable|string|max:255',
            'street'       => 'nullable|string|max:255',
        ]);

        return $this->submitAccountUpdateRequest(
            $request,
            ['province', 'municipality', 'barangay', 'house_no', 'street'],
            [],
            'address_success'
        );
    }

    public function passwordUpdate(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);
        if (!Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }
        auth()->user()->update(['password' => Hash::make($request->password)]);
        return back()->with('password_success', 'Password updated.');
    }
}
