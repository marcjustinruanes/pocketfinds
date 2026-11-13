<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\OrderDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderReceiptController extends Controller
{
    public function show(Request $request, Order $order, OrderDetails $details)
    {
        $actor = $request->user()->fresh();
        abort_unless($actor && $actor->status === 'approved' && !$actor->deleted_at, 403);
        $order->load(['seller', 'buyer', 'paymentMethod', 'shipment.courier', 'shipment.pickupRider']);
        $role = match (true) {
            (bool)$actor->is_admin => 'admin',
            $actor->account_type === 'buyer' && !$actor->is_logistics && (int)$actor->id === (int)$order->buyer_id => 'buyer',
            $actor->account_type === 'seller' && !$actor->is_logistics && (int)$actor->id === (int)$order->seller_id && !$order->awaitingAdminPayment() => 'seller',
            $actor->account_type === 'logistics' && $actor->is_logistics && filled($actor->business_name)
                && $actor->business_name === $order->shipment?->logistics_company => 'logistics',
            default => null,
        };
        abort_unless($role, 403);
        $detail = $details->forOrders([$order])->get($order->id);
        $history = DB::table('order_status_history')->where('order_id', $order->id)->orderBy('created_at')->get();
        $timeline = \App\Support\OrderTimeline::forOrder($order, $history, $detail['received_at']);
        return response()->view('orders.receipt', compact('order', 'detail', 'role', 'timeline'))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
