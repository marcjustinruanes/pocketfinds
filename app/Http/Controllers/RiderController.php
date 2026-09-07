<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesMessaging;
use App\Http\Controllers\Concerns\HandlesAccountUpdateRequests;
use App\Models\DeliveryAssignment;
use App\Models\Message;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentHubLeg;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RiderController extends Controller
{
    use HandlesMessaging;
    use HandlesAccountUpdateRequests;

    /**
     * Forward moves a rider is allowed to make themselves on the DELIVERY leg (sorting
     * center -> buyer). A strict subset of LogisticsController::STATUS_TRANSITIONS — closing
     * a shipment out to "completed" or failing it stays a back-office (logistics) capability;
     * the rider can only ever push it as far as "delivered".
     */
    private const STAGE_ORDER = ['assigned_to_rider', 'out_for_delivery', 'delivered'];

    private const STAGE_TIMESTAMPS = [
        'out_for_delivery' => 'out_for_delivery_at',
        'delivered'        => 'delivered_at',
    ];

    private function sidebarCounts(): array
    {
        $riderId = auth()->id();
        return [
            'pickupRequests'    => Shipment::where('shipping_status', 'ready_for_pickup')->whereNotNull('pickup_approved_at')->whereNull('pickup_rider_id')->count(),
            'myPickups'         => Shipment::where('pickup_rider_id', $riderId)->whereIn('shipping_status', ['ready_for_pickup', 'picked_up'])->count(),
            'myHubTransfers'    => ShipmentHubLeg::where('rider_id', $riderId)->where('status', 'in_transit')->count(),
            'deliveryRequests'  => Shipment::where('shipping_status', 'sorted')->whereNull('courier_id')->count(),
            'activeDeliveries'  => Shipment::where('courier_id', $riderId)
                ->whereIn('shipping_status', ['assigned_to_rider', 'out_for_delivery'])->count(),
            'unreadMessages'    => Message::where('receiver_id', $riderId)->where('read', false)->count(),
        ];
    }

    /** Delivery-leg shipments this rider currently holds, ordered by stage. */
    private function myActiveShipments()
    {
        return Shipment::with(['order.buyer', 'order.seller'])
            ->where('courier_id', auth()->id())
            ->whereIn('shipping_status', ['assigned_to_rider', 'out_for_delivery'])
            ->latest('updated_at')->get();
    }

    public function dashboard()
    {
        $counts        = $this->sidebarCounts();
        $pickupsOpen   = Shipment::with(['order.buyer', 'order.seller'])
            ->where('shipping_status', 'ready_for_pickup')->whereNotNull('pickup_approved_at')->whereNull('pickup_rider_id')
            ->latest('created_at')->take(5)->get();
        // Named distinctly from sidebarCounts()'s 'myPickups' (an int badge count) — array_merge
        // below would otherwise silently clobber that count with this Collection.
        $myPickupsList = Shipment::with(['order.buyer', 'order.seller'])
            ->where('pickup_rider_id', auth()->id())->where('shipping_status', 'ready_for_pickup')
            ->latest('updated_at')->get();
        $deliveriesOpen = Shipment::with(['order.buyer', 'order.seller'])
            ->where('shipping_status', 'sorted')->whereNull('courier_id')
            ->latest('created_at')->take(5)->get();
        $active         = $this->myActiveShipments();
        $completedToday = Shipment::where('courier_id', auth()->id())
            ->whereIn('shipping_status', ['delivered', 'completed'])
            ->whereDate('delivered_at', now()->toDateString())->count();
        $totalCompleted = Shipment::where('courier_id', auth()->id())
            ->whereIn('shipping_status', ['delivered', 'completed'])->count();

        return view('rider.dashboard', array_merge($counts, compact(
            'pickupsOpen', 'myPickupsList', 'deliveriesOpen', 'active', 'completedToday', 'totalCompleted'
        )));
    }

    // ── Pickup leg: seller -> sorting center ──────────────────────────────────────────────

    /** Open pickup requests — a seller's parcel, approved by Logistics, awaiting a rider to claim it. */
    public function pickupRequests()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer', 'order.seller'])
            ->where('shipping_status', 'ready_for_pickup')->whereNotNull('pickup_approved_at')->whereNull('pickup_rider_id')
            ->latest('created_at')->get();
        return view('rider.pickup-requests', array_merge($counts, compact('shipments')));
    }

    /**
     * Claim a pickup request, FCFS. Atomic conditional UPDATE — only the first request to hit
     * this while the shipment is still unclaimed wins; a later request finds 0 rows affected.
     * This does NOT change shipping_status (stays ready_for_pickup) — the actual "picked_up"
     * transition only happens once the SELLER confirms the handoff (SellerController::confirmPickup()),
     * mirroring how the buyer (not the rider) attests actual delivery at the other end.
     */
    public function acceptPickupRequest(Request $request, $id)
    {
        $riderId = auth()->id();
        $claimed = Shipment::where('id', $id)
            ->where('shipping_status', 'ready_for_pickup')->whereNotNull('pickup_approved_at')->whereNull('pickup_rider_id')
            ->update(['pickup_rider_id' => $riderId]);

        if (!$claimed) {
            return back()->withErrors(['status' => 'This pickup request was already accepted by another rider.']);
        }

        DeliveryAssignment::updateOrCreate(
            ['shipment_id' => $id, 'leg' => 'pickup'],
            ['courier_id' => $riderId, 'status' => 'accepted', 'accepted_at' => now()]
        );

        $shipment = Shipment::with('order')->find($id);
        if ($shipment->order) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $shipment->order->seller_id,
                'title' => 'Pickup Rider Assigned', 'message' => 'A rider accepted the pickup for order #' . $shipment->order->order_number . '. They\'re on their way.',
                'notification_type' => 'order_status', 'reference_id' => $shipment->order_id,
                'is_read' => false, 'created_at' => now(),
            ]);
        }

        return redirect()->route('rider.my-pickups')->with('success', 'Pickup accepted. Proceed to the seller\'s location.');
    }

    /**
     * "My Pickups" — everything this rider has claimed, from acceptance through to actually
     * carrying it to the hub: still awaiting mutual confirmation (ready_for_pickup), or
     * already confirmed by both sides and en route (picked_up). It drops off this list on
     * its own once the origin hub scans it in (shipping_status becomes at_sorting_center).
     */
    public function myPickups()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer', 'order.seller'])
            ->where('pickup_rider_id', auth()->id())->whereIn('shipping_status', ['ready_for_pickup', 'picked_up'])
            ->latest('updated_at')->get();
        return view('rider.my-pickups', array_merge($counts, compact('shipments')));
    }

    /**
     * Rider confirms actually receiving the parcel from the seller — the FIRST half of the
     * pickup leg's mutual confirmation (see Shipment::maybeAdvancePastPickupConfirmation()).
     * The seller's own Confirm Pickup button stays hidden until this fires
     * (SellerController::confirmPickup() enforces it server-side too), so this step alone
     * never advances the shipment on its own — it just unlocks the seller's side.
     */
    public function confirmPickupReceipt($id)
    {
        $shipment = Shipment::with('order')->where('pickup_rider_id', auth()->id())->findOrFail($id);
        abort_unless($shipment->shipping_status === 'ready_for_pickup', 422, 'This pickup has already moved on.');
        abort_if($shipment->rider_confirmed_pickup_at, 422, 'You already confirmed receiving this parcel.');

        $shipment->update(['rider_confirmed_pickup_at' => now()]);

        if ($shipment->order?->seller_id) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $shipment->order->seller_id,
                'title' => 'Rider Confirmed Pickup', 'message' => 'The rider confirmed receiving order #' . $shipment->order->order_number . '. Please confirm the handover in your Seller account.',
                'notification_type' => 'order_status', 'reference_id' => $shipment->order_id,
                'is_read' => false, 'created_at' => now(),
            ]);
        }

        return back()->with('success', 'Receipt confirmed — waiting for the seller to confirm the handover.');
    }

    // ── Hub transfer leg: hub -> hub ───────────────────────────────────────────────────────

    /**
     * "My Hub Transfers" — every leg this rider is currently carrying. Confirming pickup here
     * is what actually unlocks the destination hub's "Mark Received" action on the Scan page
     * (LogisticsController::completeHubTransfer()) — before this, a rider could be assigned to
     * a leg and the destination hub could mark it received before the rider had even left with
     * it, with no rider-facing page or action at all.
     */
    public function myHubTransfers()
    {
        $counts = $this->sidebarCounts();
        $legs   = ShipmentHubLeg::with(['shipment.order'])
            ->where('rider_id', auth()->id())->where('status', 'in_transit')
            ->latest('started_at')->get();
        return view('rider.hub-transfers', array_merge($counts, compact('legs')));
    }

    /** Rider confirms they've physically picked up the parcel from the origin hub and are carrying it onward. */
    public function confirmHubTransferPickup($id)
    {
        $leg = ShipmentHubLeg::with('shipment.order')->where('rider_id', auth()->id())->where('status', 'in_transit')->findOrFail($id);
        abort_if($leg->rider_confirmed_pickup_at, 422, 'You already confirmed picking this up.');

        $leg->update(['rider_confirmed_pickup_at' => now()]);

        // Notify the destination hub's own staff — they're the ones who'll mark it received once it arrives.
        $destinationStaff = User::where('account_type', 'logistics')->where('logistics_role', 'hub_staff')
            ->where('business_name', $leg->shipment->logistics_company)
            ->whereHas('logisticsHub', fn ($q) => $q->whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim($leg->to_hub))]))
            ->pluck('id');
        foreach ($destinationStaff as $staffId) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $staffId,
                'title' => 'Hub Transfer On The Way', 'message' => "A rider picked up a parcel from {$leg->from_hub}, heading to {$leg->to_hub}.",
                'notification_type' => 'hub_transfer_pickup_confirmed', 'reference_id' => $leg->shipment_id,
                'is_read' => false, 'created_at' => now(),
            ]);
        }

        return back()->with('success', "Pickup confirmed — heading to {$leg->to_hub}.");
    }

    // ── Delivery leg: sorting center -> buyer ─────────────────────────────────────────────

    /** Open delivery requests — a parcel that's been sorted and is awaiting a rider to claim it. */
    public function requests()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer', 'order.seller'])
            ->where('shipping_status', 'sorted')->whereNull('courier_id')
            ->latest('created_at')->get();
        return view('rider.requests', array_merge($counts, compact('shipments')));
    }

    /**
     * Accept a delivery request. Guarded with an atomic conditional UPDATE — only the first
     * request to hit this while the shipment is still "sorted" and unassigned wins; every
     * later request (even milliseconds later) finds 0 rows affected and is told it's taken.
     * This is what makes "System Assigns Delivery to the First Courier Who Accepts" actually
     * safe under concurrent requests, not just "usually true".
     */
    public function acceptRequest(Request $request, $id)
    {
        $riderId = auth()->id();

        $claimed = Shipment::where('id', $id)
            ->where('shipping_status', 'sorted')
            ->whereNull('courier_id')
            ->update(['courier_id' => $riderId, 'shipping_status' => 'assigned_to_rider', 'assigned_at' => now()]);

        if (!$claimed) {
            return back()->withErrors(['status' => 'This delivery request was already accepted by another courier.']);
        }

        DeliveryAssignment::updateOrCreate(
            ['shipment_id' => $id, 'leg' => 'delivery'],
            ['courier_id' => $riderId, 'status' => 'assigned_to_rider', 'accepted_at' => now()]
        );

        $shipment = Shipment::find($id);
        $shipment->order?->update(['status' => 'assigned_to_rider']);

        if ($shipment->order_id) {
            DB::table('order_status_history')->insert([
                'id' => (string) Str::uuid(), 'order_id' => $shipment->order_id, 'status' => 'assigned_to_rider',
                'changed_by' => $riderId, 'created_at' => now(),
            ]);
        }

        return redirect()->route('rider.deliveries')->with('success', 'Delivery request accepted. Pick it up from the sorting center.');
    }

    /** "My Deliveries" — everything this rider has accepted but not yet finished, in flow order. */
    public function deliveries()
    {
        $counts    = $this->sidebarCounts();
        $shipments = $this->myActiveShipments();
        return view('rider.deliveries', array_merge($counts, compact('shipments')));
    }

    /** Detail view for one shipment: seller/buyer info, order contents, and the next action button. */
    public function show($id)
    {
        $counts   = $this->sidebarCounts();
        $shipment = Shipment::with(['order.buyer', 'order.seller'])
            ->where('courier_id', auth()->id())->findOrFail($id);
        $stageIndex = array_search($shipment->shipping_status, self::STAGE_ORDER, true);
        $nextStage  = $stageIndex !== false ? (self::STAGE_ORDER[$stageIndex + 1] ?? null) : null;
        return view('rider.show', array_merge($counts, compact('shipment', 'nextStage')));
    }

    /**
     * Advance one of this rider's own shipments to the next delivery-leg stage — Mark Out for
     * Delivery or Complete Delivery, depending on where it currently sits. Ownership
     * (courier_id === this rider) and the fixed stage order are both enforced server-side so a
     * crafted request can't skip a stage or touch another rider's parcel.
     */
    public function advance(Request $request, $id)
    {
        $shipment = Shipment::with('order')->where('courier_id', auth()->id())->findOrFail($id);

        $stageIndex = array_search($shipment->shipping_status, self::STAGE_ORDER, true);
        $next       = $stageIndex !== false ? (self::STAGE_ORDER[$stageIndex + 1] ?? null) : null;

        if (!$next) {
            return back()->withErrors(['status' => 'This delivery has no further action to take.']);
        }

        $updates = ['shipping_status' => $next];
        if (isset(self::STAGE_TIMESTAMPS[$next])) {
            $updates[self::STAGE_TIMESTAMPS[$next]] = now();
        }
        $shipment->update($updates);

        DeliveryAssignment::where('shipment_id', $shipment->id)->where('leg', 'delivery')->update(array_filter([
            'status'       => $next,
            'delivered_at' => $next === 'delivered' ? now() : null,
        ]));

        // Order.status shares the exact same vocabulary as shipping_status now (see
        // Shipment::STATUSES) — no remapping table needed, it's a direct passthrough. The one
        // deliberate exception ('delivered' stopping short of 'completed') is baked into
        // STAGE_ORDER itself: this method can never advance a shipment past 'delivered'.
        if ($shipment->order) {
            $shipment->order->update(['status' => $next]);

            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $shipment->order->buyer_id,
                'title' => 'Order Update',
                'message' => 'Your order #' . $shipment->order->order_number . ' is now ' . str_replace('_', ' ', $next) . '.',
                'notification_type' => 'order_status', 'reference_id' => $shipment->order_id,
                'is_read' => false, 'created_at' => now(),
            ]);

            if ($next === 'delivered') {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(), 'user_id' => $shipment->order->seller_id,
                    'title' => 'Courier Marked as Delivered',
                    'message' => 'Order #' . $shipment->order->order_number . ' was marked delivered by the courier — awaiting the buyer\'s confirmation.',
                    'notification_type' => 'order_delivered', 'reference_id' => $shipment->order_id,
                    'is_read' => false, 'created_at' => now(),
                ]);
            }
        }

        if ($shipment->order_id) {
            DB::table('order_status_history')->insert([
                'id' => (string) Str::uuid(), 'order_id' => $shipment->order_id, 'status' => $next,
                'changed_by' => auth()->id(), 'created_at' => now(),
            ]);
        }

        $labels = ['out_for_delivery' => 'Order marked out for delivery.', 'delivered' => 'Delivery completed. Great job!'];
        return back()->with('success', $labels[$next] ?? 'Updated.');
    }

    /** Rider reports a failed delivery attempt — "Reason Recorded" branch of the doc's flow. */
    public function markFailed(Request $request, $id)
    {
        $data     = $request->validate(['reason' => 'required|string|max:500']);
        $shipment = Shipment::with('order')->where('courier_id', auth()->id())
            ->where('shipping_status', 'out_for_delivery')->findOrFail($id);

        $shipment->update([
            'shipping_status'        => 'delivery_failed',
            'delivery_failed_at'     => now(),
            'delivery_failed_reason' => $data['reason'],
        ]);
        $shipment->order?->update(['status' => 'delivery_failed']);

        DB::table('order_status_history')->insert([
            'id' => (string) Str::uuid(), 'order_id' => $shipment->order_id, 'status' => 'delivery_failed',
            'changed_by' => auth()->id(), 'notes' => $data['reason'], 'created_at' => now(),
        ]);

        if ($shipment->order) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $shipment->order->buyer_id,
                'title' => 'Delivery Attempt Failed',
                'message' => 'Your order #' . $shipment->order->order_number . ' delivery attempt failed: ' . $data['reason'],
                'notification_type' => 'delivery_failed', 'reference_id' => $shipment->order_id,
                'is_read' => false, 'created_at' => now(),
            ]);
        }

        return redirect()->route('rider.deliveries')->with('success', 'Delivery attempt marked as failed. Logistics will follow up on redelivery or return.');
    }

    public function history()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer', 'order.seller'])
            ->where('courier_id', auth()->id())
            ->whereIn('shipping_status', ['delivered', 'completed', 'delivery_failed', 'returned'])
            ->latest('updated_at')->get();
        return view('rider.history', array_merge($counts, compact('shipments')));
    }

    /** Earnings: the shipping fee collected on every order this rider has completed. */
    public function profit()
    {
        $counts = $this->sidebarCounts();
        $riderId = auth()->id();

        $completed = Shipment::with('order')
            ->where('courier_id', $riderId)
            ->whereIn('shipping_status', ['delivered', 'completed'])
            ->latest('delivered_at')->get();

        $total       = $completed->sum(fn ($s) => (float) ($s->order?->shipping_amount ?? 0));
        $thisMonth   = $completed->filter(fn ($s) => $s->delivered_at && $s->delivered_at->isCurrentMonth())
            ->sum(fn ($s) => (float) ($s->order?->shipping_amount ?? 0));
        $deliveryCount = $completed->count();

        return view('rider.profit', array_merge($counts, compact('completed', 'total', 'thisMonth', 'deliveryCount')));
    }

    /** Everyone this rider is allowed to chat with: admins, logistics staff, and the buyer/seller on any of their shipments. */
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

    private function shipmentContactIds(): array
    {
        $orderIds = Shipment::where('courier_id', auth()->id())->orWhere('pickup_rider_id', auth()->id())
            ->pluck('order_id')->filter();
        $orders   = Order::whereIn('id', $orderIds)->get(['buyer_id', 'seller_id']);
        return $orders->flatMap(fn ($o) => [$o->buyer_id, $o->seller_id])->filter()->unique()->values()->all();
    }

    private function allowedContacts(): array
    {
        return [
            'admins'     => $this->withThreadPreview(User::where('is_admin', true)->get()),
            'logistics'  => $this->withThreadPreview(User::where('is_logistics', true)->get()),
            'contacts'   => $this->withThreadPreview(User::whereIn('id', $this->shipmentContactIds())->get()),
        ];
    }

    protected function isAllowedContact(User $user): bool
    {
        return $user->is_admin
            || $user->is_logistics
            || in_array($user->id, $this->shipmentContactIds(), true);
    }

    public function messages()
    {
        $counts     = $this->sidebarCounts();
        $activeUser = null;
        $messages   = collect();
        return view('rider.messages', array_merge($counts, $this->allowedContacts(), compact('activeUser', 'messages')));
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
        return view('rider.messages', array_merge($counts, $this->allowedContacts(), compact('activeUser', 'messages')));
    }

    public function account()
    {
        $counts = $this->sidebarCounts();
        return view('rider.account', array_merge($counts, [
            'pendingRequest' => $this->pendingAccountUpdateRequest(),
            'lastRequest'    => $this->lastAccountUpdateRequest(),
        ]));
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
