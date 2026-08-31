<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesMessaging;
use App\Models\DeliveryAssignment;
use App\Models\Message;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RiderController extends Controller
{
    use HandlesMessaging;

    /**
     * Forward moves a courier is allowed to make themselves. This is a strict subset of
     * LogisticsController::STATUS_TRANSITIONS — a rider can take a shipment from "available"
     * (by accepting it) through to "delivered", but never past that: closing a shipment out
     * to "completed" stays a back-office (logistics) step, same as failing a delivery.
     */
    private const STAGE_ORDER = ['accepted', 'picked_up', 'out_for_delivery', 'delivered'];

    private const STAGE_TIMESTAMPS = [
        'picked_up'        => 'picked_up_at',
        'out_for_delivery' => 'out_for_delivery_at',
        'delivered'        => 'delivered_at',
    ];

    private const STAGE_ACTION_LABELS = [
        'picked_up'        => 'Confirm Item Pickup',
        'out_for_delivery' => 'Mark Out for Delivery',
        'delivered'        => 'Complete Delivery',
    ];

    /**
     * Shipment stage → the buyer-facing Order.status it should roll up into.
     * 'delivered' deliberately does NOT map to 'completed' — the rider marking a delivery
     * done only means the physical handoff happened; the order isn't closed out until the
     * buyer confirms receipt themselves (BuyerController::confirmReceipt(), gated on
     * status === 'delivered'). Skipping straight to 'completed' here would let a buyer's
     * "Confirm Receipt" button light up while the rider was still just en route.
     */
    private const ORDER_STATUS_MAP = [
        'picked_up'        => 'in_transit',
        'out_for_delivery' => 'out_for_delivery',
        'delivered'        => 'delivered',
    ];

    private function sidebarCounts(): array
    {
        $riderId = auth()->id();
        return [
            'availableRequests' => Shipment::where('shipping_status', 'available')->whereNull('courier_id')->count(),
            'activeDeliveries'  => Shipment::where('courier_id', $riderId)
                ->whereIn('shipping_status', ['accepted', 'picked_up', 'out_for_delivery'])->count(),
            'unreadMessages'    => Message::where('receiver_id', $riderId)->where('read', false)->count(),
        ];
    }

    /** Shipments this rider currently holds, ordered by their stage in the delivery flow. */
    private function myActiveShipments()
    {
        return Shipment::with(['order.buyer', 'order.seller'])
            ->where('courier_id', auth()->id())
            ->whereIn('shipping_status', ['accepted', 'picked_up', 'out_for_delivery'])
            ->latest('updated_at')->get();
    }

    public function dashboard()
    {
        $counts    = $this->sidebarCounts();
        $available = Shipment::with(['order.buyer', 'order.seller'])
            ->where('shipping_status', 'available')->whereNull('courier_id')
            ->latest('created_at')->take(5)->get();
        $active    = $this->myActiveShipments();
        $completedToday = Shipment::where('courier_id', auth()->id())
            ->whereIn('shipping_status', ['delivered', 'completed'])
            ->whereDate('delivered_at', now()->toDateString())->count();
        $totalCompleted  = Shipment::where('courier_id', auth()->id())
            ->whereIn('shipping_status', ['delivered', 'completed'])->count();

        return view('rider.dashboard', array_merge($counts, compact('available', 'active', 'completedToday', 'totalCompleted')));
    }

    /** Available pickup requests — first come, first served. Any approved rider can browse and accept. */
    public function requests()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer', 'order.seller'])
            ->where('shipping_status', 'available')->whereNull('courier_id')
            ->latest('created_at')->get();
        return view('rider.requests', array_merge($counts, compact('shipments')));
    }

    /**
     * Accept a delivery request. Guarded with an atomic conditional UPDATE — only the first
     * request to hit this while the shipment is still "available" and unassigned wins; every
     * later request (even milliseconds later) finds 0 rows affected and is told it's taken.
     * This is what makes "System Assigns Delivery to the First Courier Who Accepts" actually
     * safe under concurrent requests, not just "usually true".
     */
    public function acceptRequest(Request $request, $id)
    {
        $riderId = auth()->id();

        $claimed = Shipment::where('id', $id)
            ->where('shipping_status', 'available')
            ->whereNull('courier_id')
            ->update(['courier_id' => $riderId, 'shipping_status' => 'accepted']);

        if (!$claimed) {
            return back()->withErrors(['status' => 'This delivery request was already accepted by another courier.']);
        }

        DeliveryAssignment::updateOrCreate(
            ['shipment_id' => $id],
            ['courier_id' => $riderId, 'status' => 'accepted', 'accepted_at' => now()]
        );

        return redirect()->route('rider.deliveries')->with('success', 'Delivery request accepted. Proceed to the seller\'s location to pick up the item.');
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
     * Advance one of this rider's own shipments to the next stage — Confirm Item Pickup,
     * Mark Out for Delivery, or Complete Delivery, depending on where it currently sits.
     * Ownership (courier_id === this rider) and the fixed stage order are both enforced
     * server-side so a crafted request can't skip a stage or touch another rider's parcel.
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

        DeliveryAssignment::where('shipment_id', $shipment->id)->update(array_filter([
            'status'       => $next,
            'picked_up_at' => $next === 'picked_up' ? now() : null,
            'delivered_at' => $next === 'delivered' ? now() : null,
        ]));

        if ($shipment->order && isset(self::ORDER_STATUS_MAP[$next])) {
            $shipment->order->update(['status' => self::ORDER_STATUS_MAP[$next]]);

            $orderStatus = self::ORDER_STATUS_MAP[$next];
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $shipment->order->buyer_id,
                'title' => 'Order Update',
                'message' => 'Your order #' . $shipment->order->order_number . ' is now ' . str_replace('_', ' ', $orderStatus) . '.',
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

        $labels = ['picked_up' => 'Item pickup confirmed.', 'out_for_delivery' => 'Order marked out for delivery.', 'delivered' => 'Delivery completed. Great job!'];
        return back()->with('success', $labels[$next] ?? 'Updated.');
    }

    public function history()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer', 'order.seller'])
            ->where('courier_id', auth()->id())
            ->whereIn('shipping_status', ['delivered', 'completed'])
            ->latest('delivered_at')->get();
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
        $orderIds = Shipment::where('courier_id', auth()->id())->pluck('order_id')->filter();
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
        return view('rider.account', $counts);
    }

    public function accountUpdate(Request $request)
    {
        $data = $request->validate([
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

        $user = auth()->user();

        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture) {
                Storage::disk('profile_images')->delete($user->profile_picture);
            }
            $data['profile_picture'] = $request->file('profile_picture')->store('avatars', 'profile_images');
        } else {
            unset($data['profile_picture']);
        }

        $user->update($data);
        return back()->with('profile_success', 'Profile updated.');
    }

    public function accountAddressUpdate(Request $request)
    {
        $data = $request->validate([
            'province'     => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay'     => 'required|string|max:255',
            'house_no'     => 'nullable|string|max:255',
            'street'       => 'nullable|string|max:255',
        ]);
        auth()->user()->update($data);
        return back()->with('address_success', 'Address updated.');
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
