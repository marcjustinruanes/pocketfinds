<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAssignment;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LogisticsController extends Controller
{
    private function sidebarCounts(): array
    {
        return [
            'pendingDeliveries' => Shipment::where('shipping_status', 'pending')->count(),
            'activeDeliveries'      => Shipment::whereIn('shipping_status', ['assigned', 'accepted', 'picked_up', 'out_for_delivery'])->count(),
            'unassigned'            => Shipment::whereNull('courier_id')->where('shipping_status', 'pending')->count(),
            'unreadNotifications'   => 0,
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

        $recent = Shipment::with(['order.buyer', 'courier'])
            ->latest('created_at')->take(8)->get();

        return view('logistics.dashboard', array_merge($counts, compact(
            'total', 'pending', 'active', 'completed', 'cancelled', 'recent'
        )));
    }

    public function requests()
    {
        $counts   = $this->sidebarCounts();
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

        $courierStats = User::where('account_type', 'rider')
            ->where('status', 'approved')
            ->selectRaw('users.*, (SELECT COUNT(*) FROM shipments WHERE shipments.courier_id::text = users.id::text AND shipping_status = ?) AS delivered_count', ['delivered'])
            ->orderByDesc('delivered_count')
            ->take(10)->get();

        return view('logistics.reports', array_merge($counts, compact(
            'total', 'completed', 'cancelled', 'failed', 'couriers', 'courierStats'
        )));
    }

    public function notifications()
    {
        $counts        = $this->sidebarCounts();
        $notifications = collect();
        return view('logistics.notifications', array_merge($counts, compact('notifications')));
    }

    public function messages()
    {
        $counts   = $this->sidebarCounts();
        $couriers = User::where('account_type', 'rider')->where('status', 'approved')->get();
        $sellers  = User::where('account_type', 'seller')->where('status', 'approved')->get();
        $buyers   = User::where('account_type', 'buyer')->where('status', 'approved')->get();
        $activeUser = $couriers->first() ?? $sellers->first() ?? $buyers->first();
        $messages = $activeUser ? DB::table('messages')
            ->where(fn($q) => $q->where('sender_id', auth()->id())->where('receiver_id', $activeUser->id))
            ->orWhere(fn($q) => $q->where('sender_id', $activeUser->id)->where('receiver_id', auth()->id()))
            ->orderBy('created_at')->get() : collect();
        return view('logistics.messages', array_merge($counts, compact('couriers', 'sellers', 'buyers', 'activeUser', 'messages')));
    }

    public function messagesThread($userId)
    {
        $counts     = $this->sidebarCounts();
        $couriers   = User::where('account_type', 'rider')->where('status', 'approved')->get();
        $sellers    = User::where('account_type', 'seller')->where('status', 'approved')->get();
        $buyers     = User::where('account_type', 'buyer')->where('status', 'approved')->get();
        $activeUser = User::findOrFail($userId);
        $messages   = DB::table('messages')
            ->where(fn($q) => $q->where('sender_id', auth()->id())->where('receiver_id', $activeUser->id))
            ->orWhere(fn($q) => $q->where('sender_id', $activeUser->id)->where('receiver_id', auth()->id()))
            ->orderBy('created_at')->get();
        DB::table('messages')->where('sender_id', $activeUser->id)->where('receiver_id', auth()->id())->update(['read' => true]);
        return view('logistics.messages', array_merge($counts, compact('couriers', 'sellers', 'buyers', 'activeUser', 'messages')));
    }

    public function messagesSend(Request $request, $userId)
    {
        $request->validate(['body' => 'required|string|max:2000']);
        DB::table('messages')->insert([
            'sender_id'   => auth()->id(),
            'receiver_id' => $userId,
            'body'        => $request->body,
            'read'        => false,
            'created_at'  => now(),
            'updated_at'  => now(),
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
        $user = auth()->user();
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
        ]);
        $user->update($request->only('first_name', 'last_name'));
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
