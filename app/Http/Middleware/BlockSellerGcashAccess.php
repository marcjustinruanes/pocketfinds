<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BlockSellerGcashAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user()?->fresh();
        if ($user?->account_type === 'seller' && !$user->is_admin && $request->isMethod('GET') && !$request->expectsJson()) {
            if ($request->routeIs('gcash.index')) {
                return redirect()->route('seller.orders', ['payment'=>'gcash']);
            }
            if ($request->routeIs('gcash.show')) {
                $order = \App\Models\Order::visibleToSeller()->where('gcash_purchase_id', $request->route('id'))->where('seller_id', $user->id)->first();
                if (!$order) return redirect()->route('seller.orders', ['payment'=>'gcash']);
                return redirect()->route('seller.orders', ['payment'=>'gcash', 'q'=>$order->order_number]);
            }
        }
        abort_if($user?->account_type === 'seller' && ! $user->is_admin, 403, 'Seller accounts cannot access platform GCash payment review.');

        return $next($request);
    }
}
