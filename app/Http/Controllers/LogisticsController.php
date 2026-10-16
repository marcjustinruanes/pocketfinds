<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesAccountUpdateRequests;
use App\Http\Controllers\Concerns\HandlesMessaging;
use App\Models\CompanyVehicle;
use App\Models\DeliveryAssignment;
use App\Models\LogisticsCompany;
use App\Models\LogisticsHub;
use App\Models\Message;
use App\Models\Order;
use App\Models\Policy;
use App\Models\Shipment;
use App\Models\ShipmentHubLeg;
use App\Models\UnserviceableArea;
use App\Models\User;
use App\Services\GcashLocks;
use App\Services\GcashWorkflow;
use App\Services\HubHiring;
use App\Services\HubTransferWorkflow;
use App\Services\LogisticsAccess;
use App\Services\MediaLifecycle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LogisticsController extends Controller
{
    use HandlesAccountUpdateRequests;
    use HandlesMessaging;

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
        'out_for_delivery' => ['delivered', 'delivery_failed'],
        'delivered' => ['completed'],
        'delivery_failed' => ['assigned_to_rider', 'returned'],
    ];

    /** Human label for the primary (non-failure) target of each transition. */
    private const STAGE_LABELS = [
        'at_sorting_center' => 'Receive at Sorting Center',
        'sorted' => 'Mark Sorted',
        'out_for_delivery' => 'Mark Out for Delivery',
        'delivered' => 'Mark Delivered',
        'completed' => 'Mark Completed',
        'assigned_to_rider' => 'Reassign for Redelivery',
        'returned' => 'Mark Returned to Seller',
    ];

    /** Every query below is scoped to the acting staff's own company — a hub network is only ever this company's own. */
    protected function companyScope()
    {
        return app(LogisticsAccess::class)->shipments(auth()->user());
    }

    /** Same company-scoping, for queries that start from a leg instead of a shipment. */
    private function hubLegScope()
    {
        return app(LogisticsAccess::class)->legs(auth()->user())
            ->where(function ($q) {
                $q->where('status', '!=', 'pending')->orWhereNotExists(function ($q) {
                    $q->selectRaw('1')->from('shipment_hub_legs as prior')
                        ->whereColumn('prior.shipment_id', 'shipment_hub_legs.shipment_id')
                        ->whereColumn('prior.sequence', '<', 'shipment_hub_legs.sequence')->where('prior.status', '!=', 'completed');
                });
            });
    }

    /**
     * Every one of these count queries used to be its own separate `SELECT COUNT(*)`
     * round trip. Over a remote, latency-heavy connection like this one's (~300-400ms
     * per round trip, not per query — see eligibleRiders()) that adds up fast: the
     * Dashboard alone used to fire ~14 of them. Conditional aggregates compute any
     * number of conditional counts against the same table in a single query, so this
     * takes whatever filters a caller needs and returns them all from one round trip.
     *
     * @param  array<string,string>  $filters  output key => raw SQL boolean condition
     * @return array<string,int>
     */
    private function shipmentCounts(array $filters): array
    {
        $select = collect($filters)
            ->map(fn ($sql, $key) => 'COUNT(CASE WHEN '.$sql.' THEN 1 END) AS '.DB::connection()->getQueryGrammar()->wrap($key))
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
            'activeDeliveries' => "shipping_status in ('picked_up', 'at_sorting_center', 'hub_transfer', 'sorted', 'assigned_to_rider', 'out_for_delivery')",
            'unassigned' => "shipping_status = 'sorted' and courier_id is null",
        ];
    }

    /** Sidebar/dashboard badge counts that live outside the shipments table (riders, hub staff,
     *  vehicles, hub-transfer legs) — also collapsed to one query per table instead of one per badge. */
    private function nonShipmentCounts(User $user): array
    {
        $counts = ['unreadNotifications' => 0];

        $userCounts = User::where('business_name', $user->business_name)->selectRaw("
                COUNT(CASE WHEN account_type = 'rider' and status = 'pending' THEN 1 END) as pending_riders,
                COUNT(CASE WHEN account_type = 'logistics' and logistics_role = 'hub_staff' and status = 'pending' THEN 1 END) as pending_staff
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
            $counts['requestableHubLegs'] = $this->hubLegScope()->fromHub($user->logisticsHub->municipality, $user->logisticsHub->province)
                ->where('status', 'pending')->whereNull('requested_at')->count();
        }

        return $counts;
    }

    protected function sidebarCounts(): array
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
            'total' => 'true',
            'forVerify' => "shipping_status = 'ready_for_pickup' and pickup_approved_at is not null and pickup_rider_id is null",
            'available' => "shipping_status = 'sorted'",
            'completed' => "shipping_status in ('delivered', 'completed')",
            'cancelled' => "shipping_status in ('cancelled', 'delivery_failed', 'returned')",
            'inTransit' => "shipping_status in ('picked_up','at_sorting_center','hub_transfer')",
            'inProgress' => "shipping_status in ('assigned_to_rider','out_for_delivery')",
            'failed' => "shipping_status = 'delivery_failed'",
        ]));

        $counts = array_merge($shipmentStats, $this->nonShipmentCounts(auth()->user()));
        $recent = $this->companyScope()->with(['order.buyer', 'courier', 'pickupRider', 'hubTransferRider'])->latest('created_at')->take(8)->get();

        $activeRiders = User::where('business_name', auth()->user()->business_name)->where('account_type', 'rider')->where('status', 'approved')->count();
        $municipalHubs = LogisticsHub::where('company_name', auth()->user()->business_name)->count();
        $recentActivities = DB::table('logistics_activity_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.actor_id')
            ->where('l.company_name', auth()->user()->business_name)->select('l.action', 'l.record_id', 'l.created_at', 'u.username')->orderByDesc('l.id')->limit(8)->get();

        return view('logistics.dashboard', array_merge($counts, compact('recent', 'activeRiders', 'municipalHubs', 'recentActivities'), [
            'pending' => $counts['pendingDeliveries'],
            'active' => $counts['activeDeliveries'],
        ]));
    }

    /** Pickup requests fresh from a seller — "Confirm/approve/verify parcel pickup requests from seller". */
    public function requests()
    {
        $counts = $this->sidebarCounts();
        $shipments = $this->companyScope()->with(['order.buyer'])
            ->where('shipping_status', 'ready_for_pickup')->whereNull('pickup_approved_at')
            ->latest('created_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('logistics.requests', array_merge($counts, compact('shipments')));
    }

    /** Approving a request doesn't change its status — it stays ready_for_pickup, now visible to pickup riders. */
    public function approveRequest(Request $request, $id)
    {
        DB::transaction(function () use ($id) {
            GcashLocks::shipment((string) $id);
            $shipment = $this->companyScope()->with('order')->lockForUpdate()->findOrFail($id);
            if ($shipment->order) {
                app(GcashWorkflow::class)->assertOrderOperation($shipment->order, $shipment->shipping_status === 'ready_for_pickup');
            }
            abort_unless($shipment->shipping_status === 'ready_for_pickup' && ! $shipment->pickup_approved_at, 422, 'This pickup request was already handled.');
            $shipment->update(['pickup_approved_at' => now()]);
            Order::notifyUser($shipment->order?->seller_id, 'Pickup Approved', $shipment->logistics_company.' approved the pickup for order #'.$shipment->order?->order_number.'. A rider will be assigned shortly.', 'order_status', $shipment->order_id);
        }, 3);

        return back();
    }

    /**
     * Declining a pickup request ends the order (the seller picked this company, and it can't
     * carry the parcel). Stock had already been deducted when the seller confirmed the order,
     * so it's given back here; the buyer and seller are both told why.
     */
    public function rejectRequest(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:200']);
        $reason = trim((string) ($data['reason'] ?? '')) ?: 'The logistics company could not take this pickup';

        $rejected = DB::transaction(function () use ($id, $reason) {
            GcashLocks::shipment((string) $id);
            $shipment = $this->companyScope()->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($shipment->shipping_status !== 'ready_for_pickup' || $shipment->pickup_approved_at) {
                return false;
            }
            $order = Order::lockFresh($shipment->order_id);
            if (! $order->cancelAndRestock('Rejected by logistics: '.$reason, null, auth()->id(), ['ready_for_pickup'])) {
                return false;
            }
            $shipment->update(['shipping_status' => 'cancelled']);

            $msg = 'Order #'.$order->order_number.' was cancelled — '.$shipment->logistics_company.' could not take the pickup ('.$reason.').';
            Order::notifyUser($order->buyer_id, 'Order Cancelled', $msg, 'order_status', $order->id);
            Order::notifyUser($order->seller_id, 'Pickup Rejected', $msg.' Your stock for it has been restored.', 'order_status', $order->id);

            return true;
        });

        return $rejected ? back() : back()->withErrors(['status' => 'This pickup request was already handled.']);
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
            ->whereHas('logisticsHub', fn ($q) => $q->where('company_name', auth()->user()->business_name)->where('is_operational', true))
            ->when(auth()->user()->isHubStaff(), fn ($q) => $q->where('logistics_hub_id', auth()->user()->logistics_hub_id))
            ->orderBy('given_names')->get();

        // One query for every hub these riders need checked, instead of one query PER rider
        // (was N+1 — costly over a remote, latency-heavy DB connection like this one's).
        $hubIdsToCheck = $riders->where('vehicle_ownership', 'company')->pluck('logistics_hub_id')->filter()->unique();
        $hubsWithAvailableUnit = CompanyVehicle::whereIn('logistics_hub_id', $hubIdsToCheck)
            ->where('vehicle_type', $vehicleType)->riderVisible()
            ->pluck('logistics_hub_id')->unique();

        return $riders->filter(fn ($r) => $r->vehicle_ownership !== 'company' || ! $r->logistics_hub_id
                || $hubsWithAvailableUnit->contains($r->logistics_hub_id))
            ->values();
    }

    /** Same check as eligibleRiders(), for a specific already-chosen rider — the server-side half of the same gate. */
    private function vehicleEligibilityError(User $rider, string $requiredType): ?string
    {
        if ($rider->status !== 'approved' || $rider->business_name !== auth()->user()->business_name) {
            return 'This rider is not active in your company.';
        }
        if (! $rider->logisticsHub || ! $rider->logisticsHub->is_operational || $rider->logisticsHub->company_name !== $rider->business_name) {
            return 'This rider needs an operational hub in your company.';
        }
        if (! in_array($rider->vehicle_ownership, ['own', 'company'], true)) {
            return 'This rider has no verified vehicle ownership setting.';
        }
        $label = fn ($type) => str_replace('_', ' ', $type);
        if ($rider->vehicle_type !== $requiredType) {
            return "{$rider->given_names} is registered with a {$label($rider->vehicle_type)}, but this leg needs a {$label($requiredType)}.";
        }
        if ($rider->vehicle_ownership === 'company' && $rider->logistics_hub_id
            && ! CompanyVehicle::typeAvailableAtHub($rider->logistics_hub_id, $requiredType)) {
            return "{$rider->given_names} uses a company-provided vehicle, but their hub has no available {$label($requiredType)} right now.";
        }

        return null;
    }

    /** Delivery-leg assignment: sorted parcels waiting for a rider to take them from the sorting center to the buyer. */
    public function assignments()
    {
        $counts = $this->sidebarCounts();
        $shipments = $this->companyScope()->with(['order.buyer', 'deliveryAssignment.courier', 'courier', 'hubTransferAssignment.courier', 'hubTransferRider', 'hubLegs'])
            ->whereIn('shipping_status', ['at_sorting_center', 'hub_transfer', 'sorted', 'assigned_to_rider', 'out_for_delivery'])
            ->latest('created_at')->orderByDesc('id')->paginate(20)->withQueryString();
        $assignmentCounts = $this->companyScope()->selectRaw('shipping_status, COUNT(*) as total')->groupBy('shipping_status')->pluck('total', 'shipping_status');
        // Only this company's own riders, and only ones whose vehicle actually fits a delivery leg (two-wheeler).
        $couriers = $this->eligibleRiders('two_wheels');

        return view('logistics.assign', array_merge($counts, compact('shipments', 'couriers', 'assignmentCounts')));
    }

    public function assignCourier(Request $request, $id)
    {
        $company = auth()->user()->business_name;
        $data = $request->validate(['courier_id' => 'required|integer|exists:users,id']);
        GcashLocks::operation([(string) $id], [], [auth()->id(), $data['courier_id']]);
        $shipment = $this->companyScope()->with('order')->lockForUpdate()->findOrFail($id);
        app(LogisticsAccess::class)->requireLocation(auth()->user(), $shipment->destination_hub, $shipment->destination_province);
        $courier = User::where('id', $data['courier_id'])->where('account_type', 'rider')->where('status', 'approved')->where('business_name', $company)->lockForUpdate()->firstOrFail();

        $requiredMunicipality = $shipment->shipping_status === 'ready_for_pickup' ? $shipment->origin_hub : $shipment->destination_hub;
        $requiredProvince = $shipment->shipping_status === 'ready_for_pickup' ? $shipment->origin_province : $shipment->destination_province;
        abort_unless($courier->logisticsHub && app(LogisticsAccess::class)->matches($courier->logisticsHub, $requiredMunicipality, $requiredProvince), 422, 'Choose a rider assigned to the parcel hub.');
        if ($error = $this->vehicleEligibilityError($courier, 'two_wheels')) {
            if ($request->expectsJson() || $request->ajax()) {
                abort(422, $error);
            }

            return back()->withErrors(['courier_id' => $error])->withInput();
        }

        // Only a sorted parcel can be given its delivery rider. Claimed with a conditional
        // update (same pattern as assignPickupRider()) so a parcel still in transit between
        // hubs, already with another rider, delivered or cancelled — or a double-submit — is
        // never overwritten, and a cancelled order can't be revived.
        if ($shipment->order) {
            app(GcashWorkflow::class)->assertOrderOperation($shipment->order, $shipment->shipping_status === 'ready_for_pickup');
        }
        $claimed = $this->companyScope()->where('id', $shipment->id)->where('shipping_status', 'sorted')
            ->update(['courier_id' => $courier->id, 'shipping_status' => 'assigned_to_rider', 'assigned_at' => now()]);
        if ($claimed === 0) {
            $message = 'This parcel is no longer waiting for a delivery rider.';
            if ($request->expectsJson() || $request->ajax()) {
                abort(422, $message);
            }

            return back()->withErrors(['courier_id' => $message]);
        }

        $shipment->order?->update(['status' => 'assigned_to_rider']);
        $shipment->order?->logStatus('assigned_to_rider', auth()->id());
        DeliveryAssignment::updateOrCreate(
            ['shipment_id' => $shipment->id, 'leg' => 'delivery'],
            ['courier_id' => $courier->id, 'status' => 'assigned_to_rider', 'accepted_at' => now()]
        );

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $courier->id,
            'title' => 'New Delivery Assignment', 'message' => 'You were assigned to deliver order #'.($shipment->order?->order_number ?? $shipment->id).'.',
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
        abort_unless(auth()->user()->isLogisticsAdmin(), 403, 'Only Logistics Admin can dispatch hub transfers.');
        $data = $request->validate(['courier_id' => 'required|integer', 'vehicle_id' => 'nullable|integer']);
        GcashLocks::operation([(string) $id], [], [auth()->id(), $data['courier_id']], [], CompanyVehicle::where('company_name', auth()->user()->business_name)->pluck('id')->all());
        $shipment = $this->companyScope()->findOrFail($id);
        $leg = $shipment->nextAssignableHubLeg();
        abort_unless($leg, 422, 'This parcel has no approved next transfer leg.');
        $flow = app(HubTransferWorkflow::class);
        $batch = $flow->legacyBatch(auth()->user(), $leg->id);
        $batch = $flow->dispatch(auth()->user(), $batch->id, $data['courier_id'], $data['vehicle_id'] ?? null);
        $request->attributes->set('logistics_record_ids', $batch->members()->pluck('leg_id')->all());

        return back()->with('success', 'Approved transfer batch dispatched.');
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
        GcashLocks::shipment((string) $id);
        $shipment = $this->companyScope()->findOrFail($id);
        $leg = $shipment->activeHubLeg();
        abort_unless($leg, 422, 'No dispatched transfer leg is active for this parcel.');
        app(LogisticsAccess::class)->requireLocation(auth()->user(), $leg->to_hub, $leg->to_province);
        $flow = app(HubTransferWorkflow::class);
        $batch = $flow->legacyBatch(auth()->user(), $leg->id);
        $batch = $flow->receive(auth()->user(), $batch->id, [$shipment->id]);
        $request->attributes->set('logistics_record_id', $leg->id);

        return response()->json(['ok' => true, 'batch_id' => $batch->id, 'message' => 'Parcel received at the destination hub.']);
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
        $hub = $user->logisticsHub;
        $counts = $this->sidebarCounts();

        // Pickup requests the admin has already approved, still waiting for a rider —
        // scoped to parcels actually originating at THIS hub (a seller's nearest hub,
        // see LogisticsController::approveRequest()). Assignable right here, or a rider
        // can still self-accept from their own Pickup Requests page — whichever happens first.
        $pickupRequests = $this->companyScope()->with(['order.buyer', 'order.seller'])
            ->whereRaw('LOWER(origin_hub) = ?', [mb_strtolower(trim($hub->municipality))])->whereRaw('LOWER(origin_province) = ?', [mb_strtolower(trim($hub->province))])
            ->where('shipping_status', 'ready_for_pickup')->whereNotNull('pickup_approved_at')->whereNull('pickup_rider_id')
            ->latest('created_at')->orderByDesc('id')->paginate(20, ['*'], 'pickup_page')->withQueryString();
        $pickupRiders = $this->eligibleRiders('two_wheels');

        // Both sides have already confirmed the handoff (Shipment::maybeAdvancePastPickupConfirmation())
        // and the rider is physically carrying it here — nothing to assign, just visibility
        // until it's actually scanned in on the Scan page (receiveAtSortingCenter()).
        $incomingPickups = $this->companyScope()->with(['order.buyer', 'order.seller', 'pickupRider'])
            ->whereRaw('LOWER(origin_hub) = ?', [mb_strtolower(trim($hub->municipality))])->whereRaw('LOWER(origin_province) = ?', [mb_strtolower(trim($hub->province))])
            ->where('shipping_status', 'picked_up')
            ->latest('picked_up_at')->orderByDesc('id')->paginate(20, ['*'], 'arrivals_page')->withQueryString();

        [$requestable, $requestablePages, $requestableCount] = $this->transferBatches(
            $this->hubLegScope()->fromHub($hub->municipality, $hub->province)->where('status', 'pending')->whereNull('requested_at'),
            ['to_hub', 'to_province'], ['shipment.order'], 'request_batch_page');

        [$pendingApproval, $pendingApprovalPages, $pendingApprovalCount] = $this->transferBatches(
            $this->hubLegScope()->fromHub($hub->municipality, $hub->province)->where('status', 'pending')->whereNotNull('requested_at')->where(fn ($q) => $q->whereNull('approved_at')->orWhereHas('transferBatch', fn ($q) => $q->where('status', 'pending'))),
            ['to_hub', 'to_province'], ['shipment.order'], 'approval_batch_page');

        $readyToAssign = $this->hubLegScope()->fromHub($hub->municipality, $hub->province)
            ->where('status', 'pending')->whereNotNull('approved_at')->whereHas('transferBatch', fn ($q) => $q->where('status', 'approved'))
            ->with('shipment.order')->orderBy('id')->paginate(20, ['*'], 'transfer_page')->withQueryString();

        $incoming = $this->hubLegScope()->where('to_hub', $hub->municipality)->where('to_province', $hub->province)
            ->whereIn('status', ['in_transit'])
            ->with(['shipment.order', 'rider'])->orderBy('id')->paginate(20, ['*'], 'incoming_page')->withQueryString();

        return view('logistics.hub-dashboard', array_merge($counts, compact(
            'hub', 'pickupRequests', 'pickupRiders', 'incomingPickups', 'requestable', 'requestablePages', 'requestableCount', 'pendingApproval', 'pendingApprovalPages', 'pendingApprovalCount', 'readyToAssign', 'incoming'
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
        GcashLocks::operation([(string) $id], [], [auth()->id(), $data['courier_id']]);
        $shipment = $this->companyScope()->with('order')->where('shipping_status', 'ready_for_pickup')
            ->whereNotNull('pickup_approved_at')->whereNull('pickup_rider_id')->lockForUpdate()->findOrFail($id);

        app(LogisticsAccess::class)->requireLocation($user, $shipment->origin_hub, $shipment->origin_province);
        $courier = User::where('id', $data['courier_id'])->where('account_type', 'rider')->where('status', 'approved')->where('business_name', $user->business_name)->lockForUpdate()->firstOrFail();
        $requiredMunicipality = $shipment->shipping_status === 'ready_for_pickup' ? $shipment->origin_hub : $shipment->destination_hub;
        $requiredProvince = $shipment->shipping_status === 'ready_for_pickup' ? $shipment->origin_province : $shipment->destination_province;
        abort_unless($courier->logisticsHub && app(LogisticsAccess::class)->matches($courier->logisticsHub, $requiredMunicipality, $requiredProvince), 422, 'Choose a rider assigned to the parcel hub.');
        if ($error = $this->vehicleEligibilityError($courier, 'two_wheels')) {
            if ($request->expectsJson() || $request->ajax()) {
                abort(422, $error);
            }

            return back()->withErrors(['courier_id' => $error])->withInput();
        }

        // Same FCFS-safe conditional update the rider self-accept path uses — whichever
        // request (this one, or a rider claiming it themselves) hits the still-unclaimed
        // row first wins; the other finds 0 rows affected.
        if ($shipment->order) {
            app(GcashWorkflow::class)->assertOrderOperation($shipment->order, $shipment->shipping_status === 'ready_for_pickup');
        }
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
            'title' => 'New Pickup Assignment', 'message' => 'You were assigned to pick up order #'.($shipment->order?->order_number ?? $shipment->id).'.',
            'notification_type' => 'pickup_assigned', 'reference_id' => $shipment->order_id,
            'is_read' => false, 'created_at' => now(),
        ]);
        if ($shipment->order?->seller_id) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $shipment->order->seller_id,
                'title' => 'Pickup Rider Assigned', 'message' => 'A rider was assigned to pick up order #'.$shipment->order->order_number.'. They\'re on their way.',
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
        abort_unless(auth()->user()->isHubStaff(), 403);
        $data = $request->validate(['to_hub' => 'required|string|max:255', 'to_province' => 'required|string|max:255']);
        $origin = app(LogisticsAccess::class)->hub(auth()->user());
        $destination = $this->transferHub($data['to_hub'], $data['to_province']);
        $ids = $this->hubLegScope()->fromHub($origin->municipality, $origin->province)
            ->whereRaw('LOWER(to_hub) = ?', [mb_strtolower(trim($destination->municipality))])->whereRaw('LOWER(to_province) = ?', [mb_strtolower(trim($destination->province))])
            ->where('status', 'pending')->whereNull('requested_at')->whereNull('transfer_batch_id')->pluck('shipment_id')->all();
        $batch = app(HubTransferWorkflow::class)->request(auth()->user(), $origin->id, $destination->id, $ids);
        $request->attributes->set('logistics_record_ids', $batch->members()->pluck('leg_id')->all());

        return redirect()->route('logistics.transfers.show', $batch->id)->with('success', 'Transfer requested; Logistics Admin approval is required.');
    }

    private function transferHub(string $town, string $province): LogisticsHub
    {
        $hubs = LogisticsHub::where('company_name', auth()->user()->business_name)->whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim($town))])
            ->whereRaw('LOWER(province) = ?', [mb_strtolower(trim($province))])->get();
        abort_unless($hubs->count() === 1, 422, 'Select a valid unambiguous company hub.');

        return $hubs->first();
    }

    /** Admin's queue: every hub's outstanding requests and handoffs, grouped for one-click batch approval. */
    /** Paginate route groups first, then load every leg of the selected complete batches. */
    private function transferBatches(Builder $query, array $columns, array $relations, string $page): array
    {
        $cast = DB::connection()->getDriverName() === 'mysql' ? 'BINARY' : 'BLOB';
        $grammar = DB::connection()->getQueryGrammar();
        $expressions = array_map(fn ($column) => 'CAST(COALESCE('.$grammar->wrap($column).", '') AS $cast)", $columns);
        $groups = (clone $query)->reorder()->select([]);
        foreach ($columns as $index => $column) {
            $groups->selectRaw($expressions[$index].' AS '.$grammar->wrap($column))->groupByRaw($expressions[$index])->orderByRaw($expressions[$index]);
        }
        $pages = $groups->paginate(10, ['*'], $page)->withQueryString();
        $legs = (clone $query)->with($relations)->where(function ($query) use ($pages, $columns, $expressions, $cast) {
            if ($pages->isEmpty()) {
                $query->whereRaw('1 = 0');

                return;
            }
            foreach ($pages as $group) {
                $query->orWhere(function ($query) use ($group, $columns, $expressions, $cast) {
                    foreach ($columns as $index => $column) {
                        $query->whereRaw($expressions[$index]." = CAST(? AS $cast)", [$group->getAttribute($column)]);
                    }
                });
            }
        })->orderBy('id')->get()->groupBy(fn ($leg) => implode('|', array_map(fn ($column) => $leg->getAttribute($column), $columns)));

        return [$legs, $pages, (clone $query)->count()];
    }

    public function hubRequests()
    {
        $counts = $this->sidebarCounts();

        [$pendingTransferRequests, $pendingTransferPages] = $this->transferBatches(
            $this->hubLegScope()->where('status', 'pending')->whereNotNull('requested_at')->where(fn ($q) => $q->whereNull('approved_at')->orWhereHas('transferBatch', fn ($q) => $q->where('status', 'pending'))),
            ['from_hub', 'from_province', 'to_hub', 'to_province'], ['shipment.order', 'requestedBy'], 'pending_batch_page');

        // Legs sitting at a hub that hasn't requested them yet — normally the origin hub's own
        // staff do that (see requestHubTransfer()), but the admin oversees every hub and
        // shouldn't have to wait on staff to log in and click it, especially for a same-province
        // 'direct' leg (one hop, no gateway routing) that's just as real a transfer as a
        // cross-province one. adminRequestHubTransfer() below lets the admin push these straight
        // through — company-wide, any leg type, same or different province.
        [$notYetRequested, $notYetRequestedPages] = $this->transferBatches(
            $this->hubLegScope()->where('status', 'pending')->whereNull('requested_at'),
            ['from_hub', 'from_province', 'to_hub', 'to_province'], ['shipment.order'], 'unrequested_batch_page');

        return view('logistics.hub-requests', array_merge($counts, compact('pendingTransferRequests', 'pendingTransferPages', 'notYetRequested', 'notYetRequestedPages')));
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
        abort_unless(auth()->user()->isLogisticsAdmin(), 403);
        $data = $request->validate(['from_hub' => 'required|string|max:255', 'from_province' => 'required|string|max:255', 'to_hub' => 'required|string|max:255', 'to_province' => 'required|string|max:255']);
        $origin = $this->transferHub($data['from_hub'], $data['from_province']);
        $destination = $this->transferHub($data['to_hub'], $data['to_province']);
        $ids = $this->hubLegScope()->fromHub($origin->municipality, $origin->province)->whereRaw('LOWER(to_hub) = ?', [mb_strtolower(trim($destination->municipality))])
            ->whereRaw('LOWER(to_province) = ?', [mb_strtolower(trim($destination->province))])->where('status', 'pending')->whereNull('requested_at')->whereNull('transfer_batch_id')->pluck('shipment_id')->all();
        $batch = app(HubTransferWorkflow::class)->request(auth()->user(), $origin->id, $destination->id, $ids);
        $request->attributes->set('logistics_record_ids', $batch->members()->pluck('leg_id')->all());

        return redirect()->route('logistics.transfers.show', $batch->id)->with('success', 'Transfer requested. Review and approve it before dispatch.');
    }

    private function decideTransferRoute(Request $request, string $decision)
    {
        abort_unless(auth()->user()->isLogisticsAdmin(), 403);
        $data = $request->validate(['from_hub' => 'required|string|max:255', 'from_province' => 'required|string|max:255', 'to_hub' => 'required|string|max:255', 'to_province' => 'required|string|max:255', 'reason' => ($decision === 'rejected' ? 'required' : 'nullable').'|string|max:1000']);
        $legs = $this->hubLegScope()->fromHub($data['from_hub'], $data['from_province'])->whereRaw('LOWER(to_hub) = ?', [mb_strtolower(trim($data['to_hub']))])
            ->whereRaw('LOWER(to_province) = ?', [mb_strtolower(trim($data['to_province']))])->where('status', 'pending')->whereNotNull('requested_at')->where(fn ($q) => $q->whereNull('approved_at')->orWhereHas('transferBatch', fn ($q) => $q->where('status', 'pending')))->get();
        abort_if($legs->isEmpty(), 422, 'No pending request matches this route.');
        $flow = app(HubTransferWorkflow::class);
        $batches = $legs->map(fn ($leg) => $flow->legacyBatch(auth()->user(), $leg->id))->unique('id');
        foreach ($batches as $batch) {
            $flow->decide(auth()->user(), $batch->id, $decision, $data['reason'] ?? null);
        }
        $request->attributes->set('logistics_record_ids', $legs->pluck('id')->all());

        return back()->with('success', $decision === 'approved' ? 'Transfer approved; dispatch is a separate Admin action.' : 'Transfer rejected and its reason recorded.');
    }

    public function approveHubTransferRequest(Request $request)
    {
        return $this->decideTransferRoute($request, 'approved');
    }

    public function rejectHubTransferRequest(Request $request)
    {
        return $this->decideTransferRoute($request, 'rejected');
    }

    /** e.g. "Leg 2 of 3" — null when there's only one leg, since numbering it adds nothing. */
    private function hubLegProgress(Shipment $shipment): ?string
    {
        $total = $shipment->hubLegs()->count();
        if ($total <= 1) {
            return null;
        }
        $done = $shipment->hubLegs()->where('status', 'completed')->count();

        return 'Leg '.min($done + 1, $total).' of '.$total;
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

        if (! $shipment) {
            $order = Order::where('order_number', $code)->first();
            if ($order) {
                $shipment = $this->companyScope()->with(['order.buyer', 'courier', 'hubTransferRider'])->where('order_id', $order->id)->first();
            }
        }

        if (! $shipment) {
            return response()->json(['ok' => false, 'message' => 'No shipment found for "'.$code.'".'], 404);
        }

        $buyer = $shipment->order?->buyer;
        $addr = $shipment->order?->shipping_address ?? [];
        $area = $addr['municipality'] ?? null; // delivery area = the order's destination municipality
        $allowedNext = self::STATUS_TRANSITIONS[$shipment->shipping_status] ?? [];
        $forwardNext = collect($allowedNext)->first(fn ($s) => ! in_array($s, ['delivery_failed', 'returned'], true));
        $canFail = in_array('delivery_failed', $allowedNext, true);

        // Sorting-center stage this parcel is at: receive+sort it in (one scan, since sorting
        // is just an automatic address lookup), assign it to an area rider, or (once assigned)
        // advance it through the normal delivery statuses.
        $stage = 'none';
        $areaRiders = [];
        $areaMatched = false;
        $areaServiceable = true;
        $areaNote = null;
        $hubTransferRiders = [];
        $currentHubLeg = null;
        $hubLegProgress = null;
        // Hub transfers ride on a four-wheeler — see eligibleRiders().
        $availableHubTransferRiders = fn () => $this->eligibleRiders('four_wheels')
            ->map(fn ($r) => ['id' => $r->id, 'name' => trim($r->given_names.' '.$r->last_name)])->values();

        if ($shipment->shipping_status === 'ready_for_pickup') {
            $stage = $shipment->pickup_rider_id ? 'awaiting_seller_confirm' : 'awaiting_pickup_rider';
        } elseif ($shipment->shipping_status === 'picked_up') {
            $stage = 'receive';
        } elseif ($shipment->shipping_status === 'at_sorting_center' && $shipment->needsHubTransfer()) {
            // Additive on top of the normal flow — only reached when this shipment's
            // origin and destination hub differ (see receiveAtSortingCenter()).
            $currentHubLeg = $shipment->nextPendingHubLeg();
            $hubLegProgress = $this->hubLegProgress($shipment);
            if ($shipment->nextAssignableHubLeg()) {
                $stage = 'assign_hub_transfer';
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
                $stage = 'in_hub_transfer';
                $currentHubLeg = $activeLeg;
            } else {
                // The previous leg was just completed and another one remains.
                $currentHubLeg = $shipment->nextPendingHubLeg();
                if ($shipment->nextAssignableHubLeg()) {
                    $stage = 'assign_hub_transfer';
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
                $areaNote = $unserviceable->note;
            } else {
                // Only this company's own riders whose vehicle actually fits a delivery leg (two-wheeler).
                $riders = $this->eligibleRiders('two_wheels')->filter(fn ($r) => $r->logisticsHub && app(LogisticsAccess::class)->matches($r->logisticsHub, $shipment->destination_hub, $shipment->destination_province));
                if ($area) {
                    $matched = $riders->filter(fn ($r) => mb_strtolower(trim((string) $r->municipality)) === mb_strtolower(trim($area)))->values();
                    if ($matched->isNotEmpty()) {
                        $riders = $matched;
                        $areaMatched = true;
                    }
                }
                $areaRiders = $riders->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => trim($r->given_names.' '.$r->last_name),
                    'municipality' => $r->municipality,
                ])->values();
            }
        } elseif ($forwardNext) {
            $stage = 'advance';
        }

        if (auth()->user()->isHubStaff()) {
            $access = app(LogisticsAccess::class);
            $hub = $access->hub(auth()->user());
            if ($currentHubLeg && ! $access->matches($hub, $currentHubLeg->from_hub, $currentHubLeg->from_province)
                && ! $access->matches($hub, $currentHubLeg->to_hub, $currentHubLeg->to_province)) {
                // Parcel-route visibility does not expose another hub's active transfer details.
                $currentHubLeg = null;
                $hubLegProgress = null;
                $stage = 'none';
                $hubTransferRiders = [];
            }
            $permitted = match ($stage) {
                'receive' => $access->matches($hub, $shipment->origin_hub, $shipment->origin_province),
                'assign','advance' => $access->matches($hub, $shipment->destination_hub, $shipment->destination_province),
                'assign_hub_transfer' => false,
                default => true,
            };
            if (! $permitted) {
                $stage = 'none';
                $areaRiders = [];
                $hubTransferRiders = [];
                $forwardNext = null;
                $canFail = false;
            }
        }

        return response()->json([
            'ok' => true,
            'shipment' => [
                'id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number ?? substr($shipment->id, 0, 8),
                'order_number' => $shipment->order?->order_number,
                'buyer_name' => trim(($buyer->given_names ?? '').' '.($buyer->last_name ?? '')) ?: null,
                'buyer_contact' => $buyer->contact_no ?? null,
                'address' => implode(', ', array_filter([
                    $addr['house_no'] ?? null, $addr['street'] ?? null, $addr['barangay'] ?? null,
                    $addr['municipality'] ?? null, $addr['province'] ?? null,
                ])) ?: null,
                'delivery_area' => $area,
                'pickup_rider_name' => $shipment->pickupRider
                    ? trim($shipment->pickupRider->given_names.' '.$shipment->pickupRider->last_name)
                    : null,
                'courier_name' => $shipment->courier
                    ? trim($shipment->courier->given_names.' '.$shipment->courier->last_name)
                    : null,
                'origin_hub' => $shipment->origin_hub,
                'destination_hub' => $shipment->destination_hub,
                'hub_transfer_rider_name' => $shipment->hubTransferRider && (! auth()->user()->isHubStaff() || $currentHubLeg)
                    ? trim($shipment->hubTransferRider->given_names.' '.$shipment->hubTransferRider->last_name)
                    : null,
                // The leg actually being worked right now — for a same-province transfer
                // this is the whole trip (from_hub/to_hub == origin_hub/destination_hub);
                // for a cross-province one it's just the current hop of up to three.
                'current_leg' => $currentHubLeg ? [
                    'leg_type' => $currentHubLeg->leg_type,
                    'from_hub' => $currentHubLeg->from_hub,
                    'to_hub' => $currentHubLeg->to_hub,
                    'requested' => (bool) $currentHubLeg->requested_at,
                    'rider_confirmed' => (bool) $currentHubLeg->rider_confirmed_pickup_at,
                    // Only the destination hub's own staff (or the admin) can mark it received
                    // there — the origin hub, or any other hub, scanning the same tracking
                    // number shouldn't see that action at all.
                    'can_complete' => ! auth()->user()->isHubStaff() || (auth()->user()->logisticsHub
                        && app(LogisticsAccess::class)->matches(auth()->user()->logisticsHub, $currentHubLeg->to_hub, $currentHubLeg->to_province)),
                ] : null,
                'hub_leg_progress' => $hubLegProgress,
                'status' => $shipment->shipping_status,
                'status_label' => ucfirst(str_replace('_', ' ', $shipment->shipping_status)),
                'updated_at' => optional($shipment->updated_at)->format('M d, Y H:i'),
            ],
            'stage' => $stage,
            'area_riders' => $areaRiders,
            'area_matched' => $areaMatched,
            'area_serviceable' => $areaServiceable,
            'area_note' => $areaNote,
            'hub_transfer_riders' => $hubTransferRiders,
            'next_status' => $forwardNext,
            'next_label' => $forwardNext ? (self::STAGE_LABELS[$forwardNext] ?? ucfirst(str_replace('_', ' ', $forwardNext))) : null,
            'can_fail' => $canFail,
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
        GcashLocks::shipment((string) $id);
        $shipment = $this->companyScope()->with('order')->lockForUpdate()->findOrFail($id);
        abort_unless($shipment->shipping_status === 'picked_up', 422, 'This parcel has not been picked up from the seller yet.');
        app(LogisticsAccess::class)->requireLocation(auth()->user(), $shipment->origin_hub, $shipment->origin_province);

        $area = $shipment->order?->shipping_address['municipality'] ?? null;
        $now = now();

        if ($shipment->needsHubTransfer()) {
            $shipment->buildHubLegs();
            $shipment->update(['shipping_status' => 'at_sorting_center', 'at_sorting_center_at' => $now]);
            $shipment->order?->update(['status' => 'at_sorting_center']);

            DB::table('order_status_history')->insert([
                'id' => (string) Str::uuid(), 'order_id' => $shipment->order_id, 'status' => 'at_sorting_center',
                'changed_by' => auth()->id(), 'created_at' => $now,
            ]);

            $firstLeg = $shipment->nextPendingHubLeg();

            return response()->json(['ok' => true, 'message' => "Received at {$shipment->origin_hub}. This hub's staff can now request a transfer to ".($firstLeg->to_hub ?? $shipment->destination_hub).' from their dashboard.']);
        }

        $shipment->update([
            'shipping_status' => 'sorted',
            'at_sorting_center_at' => $now,
            'sorted_at' => $now,
            'sorted_area' => $area,
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

        return response()->json(['ok' => true, 'message' => 'Parcel received and sorted'.($area ? " to {$area}" : '').'.']);
    }

    public function monitor()
    {
        $counts = $this->sidebarCounts();
        $shipments = $this->companyScope()->with(['order.buyer', 'courier', 'pickupRider', 'hubTransferRider', 'deliveryAssignment'])
            ->whereIn('shipping_status', ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'hub_transfer', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed'])
            ->latest('created_at')->orderByDesc('id')->paginate(20)->withQueryString();
        $transitions = self::STATUS_TRANSITIONS;

        return view('logistics.monitor', array_merge($counts, compact('shipments', 'transitions')));
    }

    /** Shipment stage → the timestamp column on `shipments` to stamp. */
    private const STAGE_TIMESTAMPS = [
        'at_sorting_center' => 'at_sorting_center_at',
        'sorted' => 'sorted_at',
        'assigned_to_rider' => 'assigned_at',
        'out_for_delivery' => 'out_for_delivery_at',
        'delivered' => 'delivered_at',
        'delivery_failed' => 'delivery_failed_at',
        'returned' => 'returned_at',
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
            'status' => 'required|in:'.implode(',', self::STATUSES),
            'reason' => 'nullable|string|max:500',
        ]);
        $status = $data['status'];

        // "Out for delivery" is the assigned rider's own call to make (they're the one
        // actually leaving with it) — from their own "My Deliveries" page (RiderController::advance()),
        // not something hub staff mark on the rider's behalf from the Scan page.
        abort_if($status === 'out_for_delivery', 422, 'The assigned rider marks this themselves from their own account when they head out.');

        // The whole move happens on the locked shipment/order rows: the transition is
        // re-validated against the CURRENT status (not a stale copy), and a parcel that
        // is returned to the seller is restocked exactly once.
        $result = DB::transaction(function () use ($id, $status, $data) {
            GcashLocks::shipment((string) $id);
            $shipment = $this->companyScope()->where('id', $id)->lockForUpdate()->firstOrFail();
            app(LogisticsAccess::class)->requireLocation(auth()->user(), $shipment->destination_hub, $shipment->destination_province);
            $order = $shipment->order_id ? Order::lockFresh($shipment->order_id) : null;

            // Guard the transition server-side too — the UI only ever offers valid next
            // statuses, but this stops a crafted request from skipping stages or moving
            // a shipment backward.
            $allowed = self::STATUS_TRANSITIONS[$shipment->shipping_status] ?? [];
            if (! in_array($status, $allowed, true)) {
                return 'Cannot move this shipment from '
                    .ucfirst(str_replace('_', ' ', $shipment->shipping_status)).' to '
                    .ucfirst(str_replace('_', ' ', $status)).'.';
            }

            if ($status === 'assigned_to_rider') {
                $rider = $shipment->courier;
                abort_unless($rider && ! $this->vehicleEligibilityError($rider, 'two_wheels') && app(LogisticsAccess::class)->matches($rider->logisticsHub, $shipment->destination_hub, $shipment->destination_province), 422, 'Assign an approved rider with an available vehicle before retrying delivery.');
                DeliveryAssignment::updateOrCreate(['shipment_id' => $shipment->id, 'leg' => 'delivery'], ['courier_id' => $rider->id, 'status' => 'assigned_to_rider', 'accepted_at' => now()]);
            }
            $updates = ['shipping_status' => $status];
            if (isset(self::STAGE_TIMESTAMPS[$status])) {
                $updates[self::STAGE_TIMESTAMPS[$status]] = now();
            }
            if ($status === 'delivery_failed') {
                $updates['delivery_failed_reason'] = $data['reason'] ?? 'No reason given.';
            }
            $shipment->update($updates);
            $shipment->setRelation('order', $order);

            if ($order) {
                $order->update(['status' => $status]);

                // A parcel coming back to the seller means the goods are physically back
                // in their hands, so the stock the order was holding is released again.
                if ($status === 'returned') {
                    $order->restoreStock();
                    Order::notifyUser($order->seller_id, 'Parcel Returned', 'Order #'.$order->order_number.' was returned to you ('.($shipment->delivery_failed_reason ?: 'delivery failed').'). Its stock has been restored.', 'order_status', $order->id);
                }
            }

            DB::table('order_status_history')->insert([
                'id' => (string) Str::uuid(), 'order_id' => $shipment->order_id, 'status' => $status,
                'changed_by' => auth()->id(), 'notes' => $data['reason'] ?? null, 'created_at' => now(),
            ]);

            return $shipment;
        });

        if (is_string($result)) {
            return back()->withErrors(['status' => $result]);
        }
        $shipment = $result;

        if ($shipment->order) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $shipment->order->buyer_id,
                'title' => 'Order Update',
                'message' => 'Your order #'.$shipment->order->order_number.' is now '.str_replace('_', ' ', $status).'.',
                'notification_type' => 'order_status',
                'reference_id' => $shipment->order_id,
                'is_read' => false,
                'created_at' => now(),
            ]);

            // Let the seller know the moment the parcel is marked delivered — the order
            // itself still waits on the buyer's own confirmation to close out.
            if ($status === 'delivered') {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $shipment->order->seller_id,
                    'title' => 'Courier Marked as Delivered',
                    'message' => 'Order #'.$shipment->order->order_number.' was marked delivered by the courier — awaiting the buyer\'s confirmation.',
                    'notification_type' => 'order_delivered',
                    'reference_id' => $shipment->order_id,
                    'is_read' => false,
                    'created_at' => now(),
                ]);
            }

            if ($status === 'delivery_failed') {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $shipment->order->seller_id,
                    'title' => 'Delivery Attempt Failed',
                    'message' => 'Order #'.$shipment->order->order_number.' delivery failed: '.($data['reason'] ?? 'No reason given.'),
                    'notification_type' => 'delivery_failed',
                    'reference_id' => $shipment->order_id,
                    'is_read' => false,
                    'created_at' => now(),
                ]);
            }
        }

        return back();
    }

    public function issues()
    {
        $counts = $this->sidebarCounts();
        $shipments = $this->companyScope()->with(['order.buyer', 'courier'])
            ->whereIn('shipping_status', ['delivery_failed', 'returned', 'cancelled'])
            ->latest('created_at')->get();

        return view('logistics.issues', array_merge($counts, compact('shipments')));
    }

    protected function filteredShipments(Request $request)
    {
        $data = $request->validate(['search' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date'.($request->filled('from') ? '|after_or_equal:from' : ''),
            'hub_id' => ['nullable', 'integer', Rule::exists('logistics_hubs', 'id')->where('company_name', auth()->user()->business_name)],
            'status' => 'nullable|in:delivered,completed,delivery_failed,returned,cancelled']);
        $query = $this->companyScope();
        if (! empty($data['hub_id'])) {
            $hub = LogisticsHub::where('company_name', auth()->user()->business_name)->findOrFail($data['hub_id']);
            $access = app(LogisticsAccess::class);
            $query->where(fn ($q) => $q->where(fn ($q) => $access->pair($q, 'origin_hub', 'origin_province', $hub))->orWhere(fn ($q) => $access->pair($q, 'destination_hub', 'destination_province', $hub))
                ->orWhereHas('hubLegs', fn ($q) => $q->where(fn ($q) => $access->pair($q, 'from_hub', 'from_province', $hub))->orWhere(fn ($q) => $access->pair($q, 'to_hub', 'to_province', $hub))));
        }

        return $query->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('tracking_number', 'like', '%'.$s.'%')->orWhereHas('order', fn ($q) => $q->where('order_number', 'like', '%'.$s.'%'))))
            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('shipments.created_at', '>=', $v))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('shipments.created_at', '<=', $v))
            ->when($data['status'] ?? null, fn ($q, $v) => $q->where('shipping_status', $v));
    }

    public function history(Request $request)
    {
        $shipments = $this->filteredShipments($request)->with(['order.buyer', 'courier'])
            ->whereIn('shipping_status', ['delivered', 'completed', 'delivery_failed', 'returned', 'cancelled'])->latest('updated_at')->paginate(20)->withQueryString();
        $hubs = LogisticsHub::where('company_name', auth()->user()->business_name)
            ->when(auth()->user()->isHubStaff(), fn ($q) => $q->where('id', auth()->user()->logistics_hub_id))->get();

        return view('logistics.history', array_merge($this->sidebarCounts(), compact('shipments', 'hubs')));
    }

    public function reports(Request $request)
    {
        $query = $this->filteredShipments($request);
        $total = (clone $query)->count();
        $completed = (clone $query)->whereIn('shipping_status', ['delivered', 'completed'])->count();
        $cancelled = (clone $query)->where('shipping_status', 'cancelled')->count();
        $failed = (clone $query)->whereIn('shipping_status', ['delivery_failed', 'returned'])->count();
        $deliveredByRider = (clone $query)->whereIn('shipping_status', ['delivered', 'completed'])
            ->selectRaw('courier_id, COUNT(*) as delivered_count')->groupBy('courier_id');
        $courierStats = User::where('account_type', 'rider')->where('business_name', auth()->user()->business_name)
            ->when($request->filled('hub_id'), fn ($q) => $q->where('logistics_hub_id', $request->integer('hub_id')))
            ->leftJoinSub($deliveredByRider, 'deliveries', fn ($join) => $join->on('users.id', '=', 'deliveries.courier_id'))
            ->select('users.*')->selectRaw('COALESCE(deliveries.delivered_count, 0) as delivered_count')
            ->orderByDesc('delivered_count')->orderBy('users.id')->limit(10)->get();
        $couriers = User::where('account_type', 'rider')->where('business_name', auth()->user()->business_name)->where('status', 'approved')
            ->when($request->filled('hub_id'), fn ($q) => $q->where('logistics_hub_id', $request->integer('hub_id')))->count();
        $hubs = LogisticsHub::where('company_name', auth()->user()->business_name)->get();
        $summary = (clone $query)->selectRaw('shipping_status, COUNT(*) as total')->groupBy('shipping_status')->get();
        $vehicleSummary = CompanyVehicle::where('company_name', auth()->user()->business_name)
            ->when($request->filled('hub_id'), fn ($q) => $q->where('logistics_hub_id', $request->integer('hub_id')))->selectRaw('platform_status, is_available, COUNT(*) as total')->groupBy('platform_status', 'is_available')->get();

        // UNION route endpoints, then count each parcel only once per municipality/province.
        // Hub names are trimmed like LogisticsAccess::pair; parcel endpoint values are not.
        $touches = (clone $query)->selectRaw('shipments.id as shipment_id, LOWER(origin_hub) as municipality, LOWER(origin_province) as province')->toBase();
        $touches->unionAll((clone $query)->selectRaw('shipments.id as shipment_id, LOWER(destination_hub) as municipality, LOWER(destination_province) as province')->toBase());
        foreach (['from', 'to'] as $endpoint) {
            $touches->unionAll(DB::table('shipment_hub_legs')->whereIn('shipment_id', (clone $query)->select('shipments.id'))
                ->selectRaw('shipment_id, LOWER('.$endpoint.'_hub) as municipality, LOWER('.$endpoint.'_province) as province'));
        }
        $touchCounts = DB::query()->fromSub($touches, 'touches')->selectRaw('municipality, province, COUNT(DISTINCT shipment_id) as parcel_count')->groupBy('municipality', 'province');
        $hubSummary = LogisticsHub::where('company_name', auth()->user()->business_name)
            ->when($request->filled('hub_id'), fn ($q) => $q->where('logistics_hubs.id', $request->integer('hub_id')))
            ->leftJoinSub($touchCounts, 'touch_counts', fn ($join) => $join->on(DB::raw('LOWER(TRIM(logistics_hubs.municipality))'), '=', 'touch_counts.municipality')
                ->on(DB::raw('LOWER(TRIM(logistics_hubs.province))'), '=', 'touch_counts.province'))
            ->select('logistics_hubs.*')->selectRaw('COALESCE(touch_counts.parcel_count, 0) as parcel_count')->get();

        return view('logistics.reports', array_merge($this->sidebarCounts(), compact('total', 'completed', 'cancelled', 'failed', 'couriers', 'courierStats', 'hubs', 'summary', 'vehicleSummary', 'hubSummary')));
    }

    public function exportReport(Request $request)
    {
        $query = $this->filteredShipments($request)->with(['courier', 'pickupRider', 'hubTransferRider']);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            $safe = fn ($value) => preg_match('/^[\s]*[=+@-]/', (string) $value) ? "'".$value : $value;
            fputcsv($out, ['Tracking', 'Status', 'Origin municipality', 'Origin province', 'Destination municipality', 'Destination province', 'Delivery rider', 'Pickup rider', 'Transfer rider', 'Created', 'Delivered'], ',', '"', '');
            foreach ($query->lazy(200) as $s) {
                fputcsv($out, array_map($safe, [$s->tracking_number, $s->shipping_status, $s->origin_hub, $s->origin_province, $s->destination_hub, $s->destination_province, $s->courier?->username, $s->pickupRider?->username, $s->hubTransferRider?->username, $s->created_at, $s->delivered_at]), ',', '"', '');
            }
            fclose($out);
        }, 'logistics-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
    // ── Rider management: "approve/disapprove rider/courier application; activate/deactivate" ──
    // Per the ERP spec, courier applications wait on the Logistics/Sorting Center's approval,
    // not the Admin's — unlike buyer/seller/logistics, which stay on AdminController.

    public function riders(Request $request)
    {
        $counts = $this->sidebarCounts();
        $company = auth()->user()->business_name;
        $status = in_array($request->query('status'), ['pending', 'pending_activation', 'approved', 'rejected', 'suspended'], true)
            ? $request->query('status') : 'pending';
        // A company's own roster only — riders who joined this exact company at registration.
        $riders = User::where('account_type', 'rider')->where('business_name', $company)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()->orderByDesc('id')->paginate(20)->withQueryString();
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
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->notifyRiderDecision($rider, $approved));

            return;
        }
        if (! $rider->email) {
            return;
        }
        try {
            Mail::raw(
                $approved
                    ? 'Good news! Your PocketFinds Courier account has been approved by the Logistics/Sorting Center team. You can now log in at '.url('/login').'.'
                    : 'Your PocketFinds Courier account application was not approved. If you believe this is a mistake, please contact support.',
                fn ($m) => $m->to($rider->email)->subject($approved ? 'PocketFinds — Courier Application Approved' : 'PocketFinds — Courier Application Update')
            );
        } catch (\Exception $e) {
            // Status is already saved either way — a mail hiccup shouldn't block the action.
        }
    }

    public function approveRider($id)
    {
        $rider = $this->rider($id);
        abort_unless($rider->status === 'pending', 422, 'Only pending rider applications can be approved.');
        $rider->update(['status' => 'approved']);
        $this->notifyRiderDecision($rider, true);

        return back()->with('success', 'Courier application approved.');
    }

    public function rejectRider($id)
    {
        $rider = $this->rider($id);
        abort_unless($rider->status === 'pending', 422, 'Only pending rider applications can be rejected.');
        $rider->update(['status' => 'rejected']);
        $this->notifyRiderDecision($rider, false);

        return back()->with('success', 'Courier application rejected.');
    }

    public function activateRider($id)
    {
        $rider = $this->rider($id);
        abort_unless($rider->status === 'suspended', 422, 'Only suspended personnel can be reactivated.');
        $rider->update(['status' => 'approved']);

        return back()->with('success', 'Courier activated.');
    }

    public function suspendRider($id)
    {
        $rider = $this->rider($id);
        abort_unless($rider->status === 'approved', 422, 'Only approved personnel can be suspended.');
        $rider->update(['status' => 'suspended']);

        return back()->with('success', 'Courier suspended.');
    }

    /**
     * Hub staff applications for this company — mirrors riders() exactly. Hub staff join an
     * existing company the same way a rider does, so it's this company's own admin who
     * reviews them, not the platform admin (see AdminController::registrations()).
     */
    public function staff(Request $request)
    {
        $counts = $this->sidebarCounts();
        $company = auth()->user()->business_name;
        $status = in_array($request->query('status'), ['pending', 'interview', 'approved', 'rejected', 'suspended'], true) ? $request->query('status') : 'all';
        $base = User::where('account_type', 'logistics')->where('logistics_role', 'hub_staff')->where('business_name', $company);
        $statusCounts = (clone $base)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $staff = $base->with('logisticsHub')->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()->orderByDesc('id')->paginate(20)->withQueryString();

        return view('logistics.staff', array_merge($counts, compact('staff', 'status', 'statusCounts')));
    }

    private function staffMember(int $id): User
    {
        return User::where('account_type', 'logistics')->where('logistics_role', 'hub_staff')
            ->where('business_name', auth()->user()->business_name)->findOrFail($id);
    }

    /** Mirrors notifyRiderDecision() — hub staff get the same email promise, one per stage of the review. */
    private function notifyStaffStatus(User $staff, string $status, ?string $reason = null): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->notifyStaffStatus($staff, $status, $reason));

            return;
        }
        if (! $staff->email) {
            return;
        }
        $reasonLine = $reason ? "\n\nReason: {$reason}" : '';
        [$subject, $body] = match ($status) {
            'interview' => [
                'PocketFinds — Face-to-Face Interview Invitation',
                "Good news! {$staff->business_name} would like to move forward with your Hub Staff application.\n\n"
                    ."You're invited to a face-to-face interview:\n"
                    .'  Date: '.$staff->interview_scheduled_at?->format('l, F j, Y')."\n"
                    .'  Time: '.$staff->interview_scheduled_at?->format('g:i A')."\n"
                    ."  Location: {$staff->interview_location}\n\n"
                    ."Once that's completed and confirmed, your account will be enabled and you'll be able to log in.\n\n"
                    ."If you have any questions or need to reschedule, please reach out to {$staff->business_name} directly.",
            ],
            'approved' => [
                'PocketFinds — Hub Staff Application Approved',
                "Good news! Your PocketFinds Hub Staff account for {$staff->business_name} has been approved following your interview. "
                    .'You can now log in at '.url('/login').'.',
            ],
            'rejected' => [
                'PocketFinds — Hub Staff Application Update',
                "Your PocketFinds Hub Staff application was not approved. If you believe this is a mistake, please contact support.{$reasonLine}",
            ],
            'suspended' => [
                'PocketFinds — Hub Staff Account Suspended',
                "Your PocketFinds Hub Staff account for {$staff->business_name} has been suspended and you will not be able to log in until it's reactivated. "
                    ."If you believe this is a mistake, please contact {$staff->business_name} or PocketFinds support.{$reasonLine}",
            ],
            'activated' => [
                'PocketFinds — Hub Staff Account Reactivated',
                "Good news! Your PocketFinds Hub Staff account for {$staff->business_name} has been reactivated. "
                    .'You can now log in again at '.url('/login').'.',
            ],
            default => [null, null],
        };
        if (! $subject) {
            return;
        }

        try {
            Mail::raw($body, fn ($m) => $m->to($staff->email)->subject($subject));
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
            'interview_date' => 'required|date|after_or_equal:today',
            'interview_time' => 'required|date_format:H:i',
            'interview_location' => 'required|string|max:255',
        ]);

        $staff->update([
            'status' => 'interview',
            'interview_scheduled_at' => "{$data['interview_date']} {$data['interview_time']}",
            'interview_location' => $data['interview_location'],
        ]);
        $this->notifyStaffStatus($staff, 'interview');

        return back()->with('success', 'Interview invitation sent — the applicant is on hold until you confirm after the interview.');
    }

    /** The actual, final approval — only meant to be clicked after the face-to-face interview has happened. */
    public function confirmStaffApproval($id)
    {
        $staff = $this->staffMember($id);
        abort_unless($staff->status === 'interview', 422, 'This application is not awaiting approval.');
        $staff->update(['status' => 'approved', 'status_reason' => null]);
        $this->notifyStaffStatus($staff, 'approved');

        return back()->with('success', 'Hub staff application approved — they can now log in.');
    }

    public function rejectStaff(Request $request, $id)
    {
        $staff = $this->staffMember($id);
        abort_unless(in_array($staff->status, ['pending', 'interview'], true), 422, 'Only an application awaiting review can be rejected.');
        $reason = $this->resolveStaffReason($request);
        $staff->update(['status' => 'rejected', 'status_reason' => $reason]);
        $this->notifyStaffStatus($staff, 'rejected', $reason);

        return back()->with('success', 'Hub staff application rejected.');
    }

    public function activateStaff($id)
    {
        $staff = $this->staffMember($id);
        abort_unless($staff->status === 'suspended', 422, 'Only suspended personnel can be reactivated.');
        $staff->update(['status' => 'approved', 'status_reason' => null]);
        $this->notifyStaffStatus($staff, 'activated');

        return back()->with('success', 'Hub staff account reactivated.');
    }

    public function suspendStaff(Request $request, $id)
    {
        $staff = $this->staffMember($id);
        abort_unless($staff->status === 'approved', 422, 'Only approved personnel can be suspended.');
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
            'reason_preset' => 'required|string|max:150',
            'reason_details' => $request->input('reason_preset') === 'other' ? 'required|string|max:1000' : 'nullable|string|max:1000',
        ]);
        $preset = $request->input('reason_preset');
        $details = trim((string) $request->input('reason_details'));
        if ($preset === 'other') {
            return $details;
        }

        return $details !== '' ? "{$preset} — {$details}" : $preset;
    }

    /** Hub management — view all company hubs, staff counts, and toggle hiring status. */
    public function hubs(Request $request)
    {
        $counts = $this->sidebarCounts();
        $company = auth()->user()->business_name;

        $search = trim($request->query('search', ''));
        $hiringFilter = $request->query('hiring', ''); // 'all', 'hiring', 'not_hiring'

        $hubsQuery = LogisticsHub::where('company_name', $company)->withCount([
            'staff', 'riders',
            'staff as approved_staff_count' => fn ($q) => $q->where('business_name', $company)->where('is_admin', false)->where('status', 'approved'),
            'riders as approved_rider_count' => fn ($q) => $q->where('business_name', $company)->where('is_admin', false)->where('is_logistics', false)->where('status', 'approved'),
        ]);

        if ($search) {
            $hubsQuery->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(municipality) LIKE ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereRaw('LOWER(province) LIKE ?', ['%'.mb_strtolower($search).'%']);
            });
        }

        $companyHubs = $hubsQuery->orderBy('province')->orderBy('municipality')->get();
        $companyHubs->each(function ($hub) {
            $flow = app(HubHiring::class);
            $hub->rider_hiring_state = $flow->availability($hub, 'rider', $hub->approved_rider_count);
            $hub->staff_hiring_state = $flow->availability($hub, 'hub_staff', $hub->approved_staff_count);
        });
        $allHubs = $companyHubs->filter(fn ($hub) => ! in_array($hiringFilter, ['hiring', 'not_hiring'], true)
            || ($hub->rider_hiring_state['eligible'] || $hub->staff_hiring_state['eligible']) === ($hiringFilter === 'hiring'));
        // Staff counts per hub
        $staffPerHub = User::where('account_type', 'logistics')
            ->where('business_name', $company)
            ->where('status', 'approved')
            ->whereNotNull('logistics_hub_id')
            ->selectRaw('logistics_hub_id, count(*) as count')
            ->groupBy('logistics_hub_id')
            ->pluck('count', 'logistics_hub_id');

        // Metrics for summary cards
        $totalHubs = LogisticsHub::where('company_name', $company)->count();
        $regionalHubsCount = LogisticsHub::where('company_name', $company)->where('is_regional_hub', true)->count();
        $metricHubs = LogisticsHub::where('company_name', $company)->withCount(['staff as approved_staff_count' => fn ($q) => $q->where('business_name', $company)->where('is_admin', false)->where('status', 'approved'), 'riders as approved_rider_count' => fn ($q) => $q->where('business_name', $company)->where('is_admin', false)->where('is_logistics', false)->where('status', 'approved')])->get();
        $flow = app(HubHiring::class);
        $riderHiringCount = $metricHubs->filter(fn ($h) => $flow->availability($h, 'rider', $h->approved_rider_count)['eligible'])->count();
        $staffHiringCount = $metricHubs->filter(fn ($h) => $flow->availability($h, 'hub_staff', $h->approved_staff_count)['eligible'])->count();
        $hiringHubsCount = $metricHubs->filter(fn ($h) => $flow->availability($h, 'rider', $h->approved_rider_count)['eligible'] || $flow->availability($h, 'hub_staff', $h->approved_staff_count)['eligible'])->count();
        $notHiringHubsCount = $totalHubs - $hiringHubsCount;

        $page = Paginator::resolveCurrentPage();
        $hubPages = new LengthAwarePaginator($allHubs->forPage($page, 20)->values(), $allHubs->count(), 20, $page,
            ['path' => $request->url(), 'query' => $request->query()]);
        $allHubs = $hubPages->getCollection();

        // Group hubs by province
        $groupedHubs = $allHubs->groupBy('province');

        return view('logistics.hubs', array_merge($counts, compact(
            'groupedHubs', 'totalHubs', 'regionalHubsCount', 'hiringHubsCount', 'notHiringHubsCount',
            'staffPerHub', 'search', 'hiringFilter', 'allHubs', 'riderHiringCount', 'staffHiringCount', 'hubPages'
        )));
    }

    public function toggleHubHiring(Request $request, $id)
    {
        LogisticsHub::where('company_name', $request->user()->business_name)->findOrFail($id);
        $data = $request->validate([
            'role' => 'required|in:rider,hub_staff', 'is_hiring' => 'required|boolean',
            'limit' => 'nullable|integer|min:0|max:100000',
            'closes_at' => 'nullable|date_format:Y-m-d\\TH:i', 'revision' => 'required|integer|min:0',
        ]);
        DB::transaction(function () use ($request, $id, $data) {
            $hub = LogisticsHub::where('company_name', $request->user()->business_name)->lockForUpdate()->findOrFail($id);
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isLogisticsAdmin() && $actor->account_type === 'logistics' && $actor->is_logistics && ! $actor->is_admin
                && $actor->status === 'approved' && $actor->business_name === $hub->company_name, 403);
            if ((int) $hub->hiring_revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'Hiring settings changed. Refresh before saving.']);
            }
            $prefix = app(HubHiring::class)->prefix($data['role']);
            $values = [$prefix.'_hiring_open' => (bool) $data['is_hiring'],
                $prefix.'_hire_limit' => array_key_exists('limit', $data) ? $data['limit'] : $hub->{$prefix.'_hire_limit'},
                $prefix.'_hiring_closes_at' => ! array_key_exists('closes_at', $data) ? $hub->{$prefix.'_hiring_closes_at'}
                    : (empty($data['closes_at']) ? null : Carbon::createFromFormat('Y-m-d\\TH:i', $data['closes_at'], config('app.timezone'))->startOfMinute())];
            $changes = ['role' => $data['role'], 'before' => $hub->only(array_keys($values)), 'after' => $values];
            foreach ($values as $key => $value) {
                $hub->{$key} = $value;
            }
            $hub->hiring_revision++;
            $hub->save();
            $request->attributes->set('hiring_changes', $changes);
        });

        return back()->with('success', 'Role hiring settings saved.');
    }

    /**
     * Company-wide vehicle fleet — logistics admin submits vehicles here (assigning them
     * to a specific hub), then the platform admin reviews each one. Vehicles only appear
     * to riders once the platform admin approves them.
     */
    public function fleet(Request $request)
    {
        $counts = $this->sidebarCounts();
        $company = auth()->user()->business_name;

        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true) ? $request->query('status') : 'all';
        $base = CompanyVehicle::where('company_name', $company);
        $platformCounts = (clone $base)->selectRaw('platform_status, COUNT(*) as c')->groupBy('platform_status')->pluck('c', 'platform_status');
        $vehicles = $base->with(['hub', 'submitter'])->when($status !== 'all', fn ($q) => $q->where('platform_status', $status))
            ->latest()->orderByDesc('id')->paginate(20)->withQueryString();
        $hubs = LogisticsHub::where('company_name', $company)
            ->orderBy('province')->orderBy('municipality')->get();
        $vehicleTypes = DB::table('vehicle_types')->orderBy('id')->get();

        return view('logistics.fleet', array_merge($counts, compact('vehicles', 'platformCounts', 'hubs', 'vehicleTypes', 'status')));
    }

    private function fleetVehicle(int $id): CompanyVehicle
    {
        return CompanyVehicle::where('company_name', auth()->user()->business_name)->findOrFail($id);
    }

    /** Logistics admin submits a vehicle — assigns it to one of their hubs, then waits for platform admin to approve. */
    public function storeVehicle(Request $request)
    {
        abort_unless(auth()->user()->isLogisticsAdmin(), 403);
        $company = auth()->user()->business_name;

        $data = $request->validate([
            'logistics_hub_id' => ['required', 'integer', Rule::exists('logistics_hubs', 'id')
                ->where('company_name', $company)],
            'vehicle_type' => 'required|string|exists:vehicle_types,slug',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'plate_number' => 'required|string|max:20|unique:company_vehicles,plate_number',
        ]);

        CompanyVehicle::create([
            'company_name' => $company,
            'logistics_hub_id' => $data['logistics_hub_id'],
            'vehicle_type' => $data['vehicle_type'],
            'brand' => $data['brand'],
            'model' => $data['model'],
            'plate_number' => strtoupper($data['plate_number']),
            'submitted_by' => auth()->id(),
            'platform_status' => 'pending',
            'status' => 'pending',
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
        $hub = auth()->user()->logisticsHub;
        abort_unless($hub, 422, 'Your account has no hub linked.');

        $vehicles = CompanyVehicle::where('logistics_hub_id', $hub->id)->latest()->orderByDesc('id')->paginate(20)->withQueryString();

        return view('logistics.hub-vehicles', array_merge($counts, compact('hub', 'vehicles')));
    }

    /** Hub staff toggles an already platform-approved vehicle in/out of maintenance. */
    public function toggleVehicleAvailability($id)
    {
        abort_unless(auth()->user()->isHubStaff(), 403);
        $hub = auth()->user()->logisticsHub;
        $vehicle = CompanyVehicle::where('logistics_hub_id', $hub?->id)
            ->where('platform_status', 'approved')->findOrFail($id);
        $vehicle->update(['is_available' => ! $vehicle->is_available]);
        $msg = $vehicle->is_available
            ? "{$vehicle->brand} {$vehicle->model} marked available again."
            : "{$vehicle->brand} {$vehicle->model} marked as in maintenance — it won't be offered to new riders until it's back.";

        return back()->with('success', $msg);
    }

    /** Enrich a set of contacts with their latest message + unread count against this staff member. */
    private function withThreadPreview($users)
    {
        $myId = auth()->id();
        $threads = Message::where('sender_id', $myId)->orWhere('receiver_id', $myId)
            ->latest('created_at')->get()
            ->groupBy(fn ($m) => $m->sender_id === $myId ? $m->receiver_id : $m->sender_id);

        return $users->map(function ($u) use ($threads, $myId) {
            $thread = $threads->get($u->id);
            $u->last_message = $thread?->first();
            $u->unread_count = $thread ? $thread->where('receiver_id', $myId)->where('read', false)->count() : 0;

            return $u;
        })->sortByDesc(fn ($u) => $u->last_message?->created_at ?? Carbon::createFromTimestamp(0))->values();
    }

    private function allowedContacts(): array
    {
        return [
            'admins' => $this->withThreadPreview(User::where('is_admin', true)->get()),
            'couriers' => $this->withThreadPreview(User::where('account_type', 'rider')->where('status', 'approved')->where('business_name', auth()->user()->business_name)->when(auth()->user()->isHubStaff(), fn ($q) => $q->where('logistics_hub_id', auth()->user()->logistics_hub_id))->get()),
            'sellers' => $this->withThreadPreview(User::where('account_type', 'seller')->where('status', 'approved')->whereIn('id', Order::whereIn('id', $this->companyScope()->select('order_id'))->select('seller_id'))->get()),
        ];
    }

    protected function isAllowedContact(User $user): bool
    {
        if ($user->is_admin) {
            return true;
        }
        if ($user->account_type === 'rider') {
            return $user->status === 'approved' && $user->business_name === auth()->user()->business_name
                && (! auth()->user()->isHubStaff() || (int) $user->logistics_hub_id === (int) auth()->user()->logistics_hub_id);
        }

        return $user->account_type === 'seller' && $user->status === 'approved' && $this->companyScope()->whereHas('order', fn ($q) => $q->where('seller_id', $user->id))->exists();
    }

    public function messages()
    {
        $counts = $this->sidebarCounts();
        $activeUser = null;
        $messages = collect();

        return view('logistics.messages', array_merge($counts, $this->allowedContacts(), compact('activeUser', 'messages')));
    }

    public function messagesThread($userId)
    {
        $counts = $this->sidebarCounts();
        $activeUser = User::findOrFail($userId);
        abort_if(! $this->isAllowedContact($activeUser), 403);
        Message::where('sender_id', $activeUser->id)->where('receiver_id', auth()->id())->where('read', false)->update(['read' => true]);
        $messages = Message::where(fn ($q) => $q->where('sender_id', auth()->id())->where('receiver_id', $activeUser->id))
            ->orWhere(fn ($q) => $q->where('sender_id', $activeUser->id)->where('receiver_id', auth()->id()))
            ->oldest()->get();

        return view('logistics.messages', array_merge($counts, $this->allowedContacts(), compact('activeUser', 'messages')));
    }

    public function account()
    {
        $counts = $this->sidebarCounts();
        $companyName = auth()->user()->business_name;
        $companyPolicy = $companyName ? LogisticsCompany::policyFor($companyName) : null;

        return view('logistics.account', array_merge($counts, [
            'company' => $companyName,
            'companyPolicy' => $companyPolicy,
            'pendingRequest' => $this->pendingAccountUpdateRequest(),
            'lastRequest' => $this->lastAccountUpdateRequest(),
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

        $policy = LogisticsCompany::policyFor($companyName)
            ?? Policy::create(['type' => 'logistics_company_terms', 'company_name' => $companyName, 'title' => $companyName.' — Terms & Conditions']);
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
            'given_names' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => ['nullable', 'regex:/^[A-Za-z]$/'],
            'contact_no' => ['nullable', 'regex:/^09[0-9]{9}$/'],
            'sex' => 'nullable|in:male,female,other',
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'middle_name.regex' => 'Middle name must be a single letter.',
            'contact_no.regex' => 'Contact number must start with 09 and be exactly 11 digits.',
        ]);

        if ($request->hasFile('profile_picture')) {
            $user = auth()->user();
            if ($user->profile_picture) {
                app(MediaLifecycle::class)->deleteAfterCommit($user->profile_picture, 'profile_images');
            }
            $user->update(['profile_picture' => app(MediaLifecycle::class)->store($request->file('profile_picture'), 'avatars', 'profile_images')]);
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
            'province' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'house_no' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
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
            'password' => 'required|min:8|confirmed',
        ]);
        if (! Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }
        auth()->user()->update(['password' => Hash::make($request->password)]);

        return back()->with('password_success', 'Password updated.');
    }
}
