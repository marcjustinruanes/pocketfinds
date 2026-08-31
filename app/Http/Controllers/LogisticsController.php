<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesMessaging;
use App\Models\DeliveryAssignment;
use App\Models\Message;
use App\Models\Order;
use App\Models\Shipment;
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

    const STATUSES = [
        'pending', 'for_verification', 'verified', 'available',
        'accepted', 'picked_up', 'out_for_delivery',
        'delivered', 'completed', 'cancelled', 'failed',
    ];

    /**
     * Valid forward moves for a shipment's status, keyed by its current status.
     * A shipment can only ever move to one of these — never backward, never skipped
     * ahead. This is the single source of truth for both the Monitor page's status
     * dropdown and the Scan page's action buttons, and is enforced server-side in
     * updateStatus() so a crafted request can't bypass it either.
     *
     * 'available' → 'accepted' is deliberately absent: that move only happens through
     * assignCourier(), which also sets courier_id — flipping the status alone here
     * would leave a shipment marked "accepted" with no courier attached.
     */
    private const STATUS_TRANSITIONS = [
        'accepted'         => ['picked_up', 'failed'],
        'picked_up'        => ['out_for_delivery', 'failed'],
        'out_for_delivery' => ['delivered', 'failed'],
        'delivered'        => ['completed'],
    ];

    /** Human label for the primary (non-failure) target of each transition. */
    private const STAGE_LABELS = [
        'picked_up'        => 'Mark Picked Up',
        'out_for_delivery' => 'Mark Out for Delivery',
        'delivered'        => 'Mark Delivered',
        'completed'        => 'Mark Completed',
    ];

    private function sidebarCounts(): array
    {
        return [
            'pendingDeliveries'   => Shipment::where('shipping_status', 'pending')->count(),
            'activeDeliveries'    => Shipment::whereIn('shipping_status', ['accepted', 'picked_up', 'out_for_delivery'])->count(),
            'unassigned'          => Shipment::where('shipping_status', 'available')->whereNull('courier_id')->count(),
            'unreadNotifications' => 0,
        ];
    }

    public function dashboard()
    {
        $counts      = $this->sidebarCounts();
        $total       = Shipment::count();
        $pending     = Shipment::where('shipping_status', 'pending')->count();
        $forVerify   = Shipment::where('shipping_status', 'for_verification')->count();
        $available   = Shipment::where('shipping_status', 'available')->count();
        $active      = Shipment::whereIn('shipping_status', ['accepted', 'picked_up', 'out_for_delivery'])->count();
        $completed   = Shipment::whereIn('shipping_status', ['delivered', 'completed'])->count();
        $cancelled   = Shipment::whereIn('shipping_status', ['cancelled', 'failed'])->count();
        $recent      = Shipment::with(['order.buyer', 'courier'])->latest('created_at')->take(8)->get();

        return view('logistics.dashboard', array_merge($counts, compact(
            'total', 'pending', 'forVerify', 'available', 'active', 'completed', 'cancelled', 'recent'
        )));
    }

    public function requests()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer'])
            ->where('shipping_status', 'pending')
            ->latest('created_at')->get();
        return view('logistics.requests', array_merge($counts, compact('shipments')));
    }

    public function approveRequest(Request $request, $id)
    {
        Shipment::where('id', $id)->update(['shipping_status' => 'available']);
        return back();
    }

    public function rejectRequest(Request $request, $id)
    {
        Shipment::where('id', $id)->update(['shipping_status' => 'cancelled']);
        return back();
    }

    public function assignments()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer', 'assignment.courier'])
            ->whereIn('shipping_status', ['available', 'accepted', 'picked_up', 'out_for_delivery'])
            ->latest('created_at')->get();
        $couriers  = User::where('account_type', 'rider')->where('status', 'approved')->orderBy('given_names')->get();
        return view('logistics.assign', array_merge($counts, compact('shipments', 'couriers')));
    }

    public function assignCourier(Request $request, $id)
    {
        $data     = $request->validate(['courier_id' => 'required|integer|exists:users,id']);
        $shipment = Shipment::findOrFail($id);
        $courier  = User::where('id', $data['courier_id'])->where('account_type', 'rider')->where('status', 'approved')->firstOrFail();

        $shipment->update(['courier_id' => $courier->id, 'shipping_status' => 'accepted']);
        DeliveryAssignment::updateOrCreate(
            ['shipment_id' => $shipment->id],
            ['courier_id' => $courier->id, 'status' => 'accepted', 'accepted_at' => now()]
        );

        return back()->with('success', 'Courier assigned.');
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

        $shipment = Shipment::with(['order.buyer', 'courier'])
            ->where('tracking_number', $code)
            ->first();

        if (!$shipment) {
            $order = Order::where('order_number', $code)->first();
            if ($order) {
                $shipment = Shipment::with(['order.buyer', 'courier'])->where('order_id', $order->id)->first();
            }
        }

        if (!$shipment) {
            return response()->json(['ok' => false, 'message' => 'No shipment found for "' . $code . '".'], 404);
        }

        $buyer       = $shipment->order?->buyer;
        $addr        = $shipment->order?->shipping_address ?? [];
        $area        = $addr['municipality'] ?? null; // delivery area = the order's destination municipality
        $allowedNext = self::STATUS_TRANSITIONS[$shipment->shipping_status] ?? [];
        $forwardNext = collect($allowedNext)->first(fn ($s) => $s !== 'failed');
        $canFail     = in_array('failed', $allowedNext, true);

        // Sorting-center stage this parcel is at: receive it in, sort/assign it to an area rider,
        // or (once assigned) advance it through the normal delivery statuses.
        $stage            = 'none';
        $areaRiders       = [];
        $areaMatched      = false;
        $areaServiceable  = true;
        $areaNote         = null;
        if ($shipment->shipping_status === 'pending') {
            $stage = 'receive';
        } elseif ($shipment->shipping_status === 'available') {
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
                $riders = User::where('account_type', 'rider')->where('status', 'approved');
                if ($area) {
                    $matched = (clone $riders)->whereRaw('LOWER(municipality) = ?', [strtolower($area)])
                        ->orderBy('given_names')->get();
                    if ($matched->isNotEmpty()) {
                        $riders      = $matched;
                        $areaMatched = true;
                    } else {
                        $riders = $riders->orderBy('given_names')->get();
                    }
                } else {
                    $riders = $riders->orderBy('given_names')->get();
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
                'courier_name'    => $shipment->courier
                    ? trim($shipment->courier->given_names . ' ' . $shipment->courier->last_name)
                    : null,
                'status'          => $shipment->shipping_status,
                'status_label'    => ucfirst(str_replace('_', ' ', $shipment->shipping_status)),
                'updated_at'      => optional($shipment->updated_at)->format('M d, Y H:i'),
            ],
            'stage'             => $stage,
            'area_riders'       => $areaRiders,
            'area_matched'      => $areaMatched,
            'area_serviceable'  => $areaServiceable,
            'area_note'         => $areaNote,
            'next_status'  => $forwardNext,
            'next_label'   => $forwardNext ? (self::STAGE_LABELS[$forwardNext] ?? ucfirst(str_replace('_', ' ', $forwardNext))) : null,
            'can_fail'     => $canFail,
        ]);
    }

    public function monitor()
    {
        $counts      = $this->sidebarCounts();
        $shipments   = Shipment::with(['order.buyer', 'courier', 'assignment'])
            ->whereIn('shipping_status', ['for_verification', 'verified', 'available', 'accepted', 'picked_up', 'out_for_delivery'])
            ->latest('created_at')->get();
        $transitions = self::STATUS_TRANSITIONS;
        return view('logistics.monitor', array_merge($counts, compact('shipments', 'transitions')));
    }

    /**
     * Shipment stage → the buyer-facing Order.status it should roll up into.
     * 'delivered' deliberately does NOT map to 'completed' — that only means the physical
     * handoff happened; the order isn't closed out until the buyer confirms receipt
     * (BuyerController::confirmReceipt(), gated on status === 'delivered'). Only the
     * shipment reaching 'completed' itself — a logistics-only closeout step — rolls the
     * order all the way to 'completed'.
     */
    private const ORDER_STATUS_MAP = [
        'picked_up'         => 'in_transit',
        'in_transit'        => 'in_transit',
        'out_for_delivery'  => 'out_for_delivery',
        'delivered'         => 'delivered',
        'completed'         => 'completed',
    ];

    /** Shipment stage → the timestamp column on `shipments` to stamp. */
    private const STAGE_TIMESTAMPS = [
        'picked_up'        => 'picked_up_at',
        'in_transit'       => 'in_transit_at',
        'out_for_delivery' => 'out_for_delivery_at',
        'delivered'        => 'delivered_at',
    ];

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:' . implode(',', self::STATUSES)]);
        $shipment = Shipment::with('order')->findOrFail($id);
        $status   = $request->status;

        // Guard the transition server-side too — the UI only ever offers valid next
        // statuses, but this stops a crafted request from skipping stages or moving
        // a shipment backward.
        $allowed = self::STATUS_TRANSITIONS[$shipment->shipping_status] ?? [];
        if (!in_array($status, $allowed, true)) {
            return back()->withErrors(['status' => 'Cannot move this shipment from '
                . ucfirst(str_replace('_', ' ', $shipment->shipping_status)) . ' to '
                . ucfirst(str_replace('_', ' ', $status)) . '.']);
        }

        $updates = ['shipping_status' => $status];
        if (isset(self::STAGE_TIMESTAMPS[$status])) {
            $updates[self::STAGE_TIMESTAMPS[$status]] = now();
        }
        $shipment->update($updates);

        if ($shipment->order && isset(self::ORDER_STATUS_MAP[$status])) {
            $shipment->order->update(['status' => self::ORDER_STATUS_MAP[$status]]);

            $orderStatus = self::ORDER_STATUS_MAP[$status];
            DB::table('notifications')->insert([
                'id'                => (string) Str::uuid(),
                'user_id'           => $shipment->order->buyer_id,
                'title'             => 'Order Update',
                'message'           => 'Your order #' . $shipment->order->order_number . ' is now ' . str_replace('_', ' ', $orderStatus) . '.',
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
        }

        DB::table('order_status_history')->insert([
            'id' => (string) Str::uuid(), 'order_id' => $shipment->order_id, 'status' => $status,
            'changed_by' => auth()->id(), 'created_at' => now(),
        ]);

        return back();
    }

    public function issues()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer', 'courier'])
            ->whereIn('shipping_status', ['failed', 'cancelled'])
            ->latest('created_at')->get();
        return view('logistics.issues', array_merge($counts, compact('shipments')));
    }

    public function history()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer', 'courier'])
            ->whereIn('shipping_status', ['delivered', 'completed'])
            ->latest('delivered_at')->get();
        return view('logistics.history', array_merge($counts, compact('shipments')));
    }

    public function reports()
    {
        $counts    = $this->sidebarCounts();
        $total     = Shipment::count();
        $completed = Shipment::whereIn('shipping_status', ['delivered', 'completed'])->count();
        $cancelled = Shipment::where('shipping_status', 'cancelled')->count();
        $failed    = Shipment::where('shipping_status', 'failed')->count();
        $couriers  = User::where('account_type', 'rider')->where('status', 'approved')->count();

        $courierStats = User::where('account_type', 'rider')
            ->where('status', 'approved')
            ->selectRaw("users.*, (SELECT COUNT(*) FROM shipments WHERE shipments.courier_id = users.id AND shipping_status IN ('delivered','completed')) AS delivered_count")
            ->orderByDesc('delivered_count')
            ->take(10)->get();

        return view('logistics.reports', array_merge($counts, compact(
            'total', 'completed', 'cancelled', 'failed', 'couriers', 'courierStats'
        )));
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
        return view('logistics.account', $counts);
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
