<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAssignment;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LogisticsController extends Controller
{
    private function sidebarCounts(): array
    {
        return [
            'pendingDeliveries'  => Shipment::where('shipping_status', 'pending')->count(),
            'activeDeliveries'   => Shipment::whereIn('shipping_status', ['assigned', 'accepted', 'picked_up', 'out_for_delivery'])->count(),
            'unassigned'         => Shipment::whereNull('courier_id')->where('shipping_status', 'pending')->count(),
            'unreadNotifications'=> 0,
        ];
    }

    public function dashboard()
    {
        $counts    = $this->sidebarCounts();
        $total     = Shipment::count();
        $pending   = Shipment::where('shipping_status', 'pending')->count();
        $active    = Shipment::whereIn('shipping_status', ['assigned', 'accepted', 'picked_up', 'out_for_delivery'])->count();
        $completed = Shipment::where('shipping_status', 'delivered')->count();
        $cancelled = Shipment::whereIn('shipping_status', ['cancelled', 'failed'])->count();
        $recent    = Shipment::with(['order.buyer', 'courier'])->latest('created_at')->take(8)->get();

        return view('logistics.dashboard', array_merge($counts, compact(
            'total', 'pending', 'active', 'completed', 'cancelled', 'recent'
        )));
    }

    public function requests()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer', 'order.items'])
            ->where('shipping_status', 'pending')
            ->latest('created_at')->get();
        return view('logistics.requests', array_merge($counts, compact('shipments')));
    }

    public function approveRequest(Request $request, $id)
    {
        Shipment::where('id', $id)->update(['shipping_status' => 'assigned']);
        return back();
    }

    public function rejectRequest(Request $request, $id)
    {
        Shipment::where('id', $id)->update(['shipping_status' => 'cancelled']);
        return back();
    }

    public function assign()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer', 'assignment.courier'])
            ->whereIn('shipping_status', ['pending', 'assigned'])
            ->latest('created_at')->get();
        $couriers  = User::where('account_type', 'rider')->where('status', 'approved')->get();
        return view('logistics.assign', array_merge($counts, compact('shipments', 'couriers')));
    }

    public function assignCourier(Request $request, $id)
    {
        $request->validate(['courier_id' => 'required|exists:users,id']);
        Shipment::where('id', $id)->update([
            'courier_id'      => $request->courier_id,
            'shipping_status' => 'assigned',
        ]);
        DeliveryAssignment::updateOrCreate(
            ['shipment_id' => $id],
            ['courier_id' => $request->courier_id, 'status' => 'assigned', 'requested_at' => now()]
        );
        return back();
    }

    public function monitor()
    {
        $counts    = $this->sidebarCounts();
        $shipments = Shipment::with(['order.buyer', 'courier', 'assignment'])
            ->whereIn('shipping_status', ['assigned', 'accepted', 'picked_up', 'out_for_delivery'])
            ->latest('created_at')->get();
        return view('logistics.monitor', array_merge($counts, compact('shipments')));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:pending,assigned,accepted,picked_up,out_for_delivery,delivered,completed,cancelled,failed']);
        Shipment::where('id', $id)->update(['shipping_status' => $request->status]);
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
            ->where('shipping_status', 'delivered')
            ->latest('delivered_at')->get();
        return view('logistics.history', array_merge($counts, compact('shipments')));
    }

    public function reports()
    {
        $counts    = $this->sidebarCounts();
        $total     = Shipment::count();
        $completed = Shipment::where('shipping_status', 'delivered')->count();
        $cancelled = Shipment::where('shipping_status', 'cancelled')->count();
        $failed    = Shipment::where('shipping_status', 'failed')->count();
        $couriers  = User::where('account_type', 'rider')->where('status', 'approved')->count();

        return view('logistics.reports', array_merge($counts, compact(
            'total', 'completed', 'cancelled', 'failed', 'couriers'
        )));
    }

    public function notifications()
    {
        $counts        = $this->sidebarCounts();
        $notifications = collect();
        return view('logistics.notifications', array_merge($counts, compact('notifications')));
    }

    public function settings()
    {
        $counts   = $this->sidebarCounts();
        $settings = DB::table('logistics_settings')->pluck('value', 'key');
        return view('logistics.settings', array_merge($counts, compact('settings')));
    }

    public function settingsUpdate(Request $request)
    {
        $request->validate([
            'max_deliveries_per_courier' => 'required|integer|min:1|max:50',
            'delivery_timeout_hours'     => 'required|integer|min:1|max:72',
        ]);

        $data = [
            'email_notifications'        => $request->boolean('email_notifications') ? '1' : '0',
            'auto_assign'                => $request->boolean('auto_assign') ? '1' : '0',
            'max_deliveries_per_courier' => $request->max_deliveries_per_courier,
            'delivery_timeout_hours'     => $request->delivery_timeout_hours,
        ];

        foreach ($data as $key => $value) {
            DB::table('logistics_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        // Run auto-assign immediately if enabled
        if ($data['auto_assign'] === '1') {
            $this->runAutoAssign((int) $data['max_deliveries_per_courier']);
        }

        return back()->with('success', 'Settings saved.');
    }

    private function runAutoAssign(int $maxPerCourier)
    {
        $unassigned = Shipment::whereNull('courier_id')
            ->where('shipping_status', 'pending')
            ->with('order.shippingAddress.municipality')
            ->get();

        if ($unassigned->isEmpty()) return;

        $couriers = User::where('account_type', 'rider')
            ->where('status', 'approved')
            ->get();

        if ($couriers->isEmpty()) return;

        // Pre-compute active delivery count per courier
        $activeCounts = Shipment::whereIn('courier_id', $couriers->pluck('id'))
            ->whereIn('shipping_status', ['assigned', 'accepted', 'picked_up', 'out_for_delivery'])
            ->selectRaw('courier_id, COUNT(*) as cnt')
            ->groupBy('courier_id')
            ->pluck('cnt', 'courier_id');

        foreach ($unassigned as $shipment) {
            $deliveryMunicipality = optional(optional(optional($shipment->order)->shippingAddress)->municipality)->name;

            $available = $couriers->filter(fn($c) => ($activeCounts[$c->id] ?? 0) < $maxPerCourier);

            if ($available->isEmpty()) break;

            // Prefer couriers in the same municipality, fall back to fewest deliveries
            $courier = $available->filter(fn($c) => $deliveryMunicipality && strtolower($c->municipality) === strtolower($deliveryMunicipality))
                ->sortBy(fn($c) => $activeCounts[$c->id] ?? 0)
                ->first()
                ?? $available->sortBy(fn($c) => $activeCounts[$c->id] ?? 0)->first();

            if (!$courier) break;

            $shipment->update([
                'courier_id'      => $courier->id,
                'shipping_status' => 'assigned',
            ]);

            DeliveryAssignment::updateOrCreate(
                ['shipment_id' => $shipment->id],
                ['courier_id' => $courier->id, 'status' => 'assigned', 'requested_at' => now()]
            );

            // Update local count so next iteration reflects this assignment
            $activeCounts[$courier->id] = ($activeCounts[$courier->id] ?? 0) + 1;
        }
    }

    public function messages()
    {
        $counts     = $this->sidebarCounts();
        $admins     = User::where('is_admin', true)->get();
        $couriers   = User::where('account_type', 'rider')->where('status', 'approved')->get();
        $sellers    = User::where('account_type', 'seller')->where('status', 'approved')->get();
        $buyers     = User::where('account_type', 'buyer')->where('status', 'approved')->get();
        $activeUser = null;
        $messages   = collect();
        return view('logistics.messages', array_merge($counts, compact('admins', 'couriers', 'sellers', 'buyers', 'activeUser', 'messages')));
    }

    public function messagesThread($userId)
    {
        $counts     = $this->sidebarCounts();
        $admins     = User::where('is_admin', true)->get();
        $couriers   = User::where('account_type', 'rider')->where('status', 'approved')->get();
        $sellers    = User::where('account_type', 'seller')->where('status', 'approved')->get();
        $buyers     = User::where('account_type', 'buyer')->where('status', 'approved')->get();
        $activeUser = User::findOrFail($userId);
        Message::where('sender_id', $activeUser->id)->where('receiver_id', auth()->id())->update(['read' => true]);
        $messages   = Message::where(fn($q) => $q->where('sender_id', auth()->id())->where('receiver_id', $activeUser->id))
            ->orWhere(fn($q) => $q->where('sender_id', $activeUser->id)->where('receiver_id', auth()->id()))
            ->oldest()->get();
        return view('logistics.messages', array_merge($counts, compact('admins', 'couriers', 'sellers', 'buyers', 'activeUser', 'messages')));
    }

    public function messagesSend(Request $request, $userId)
    {
        $request->validate(['body' => 'required|string|max:2000']);
        Message::create([
            'sender_id'   => auth()->id(),
            'receiver_id' => $userId,
            'body'        => $request->body,
            'read'        => false,
        ]);
        return back();
    }

    public function account()
    {
        $counts = $this->sidebarCounts();
        return view('logistics.account', $counts);
    }

    public function accountUpdate(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
        ]);
        auth()->user()->update($request->only('first_name', 'last_name'));
        return back()->with('success', 'Profile updated.');
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
        return back()->with('success', 'Password updated.');
    }
}
