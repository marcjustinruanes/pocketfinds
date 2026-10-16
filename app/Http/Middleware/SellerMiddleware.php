<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SellerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }
        if (auth()->user()->account_type !== 'seller' && ! auth()->user()->is_admin) {
            abort(403);
        }
        $draftAccess = auth()->user()->status === 'pending' && (
            $request->routeIs('seller.inventory', 'seller.logout') ||
            ($request->routeIs('seller.inventory.store', 'seller.inventory.update') && $request->input('intent') === 'draft')
        );
        abort_unless(auth()->user()->status === 'approved' || $draftAccess, 403, 'Your store must be approved before publishing or managing live products.');
        Cache::put('seller-online-'.auth()->id(), true, now()->addMinutes(3));

        return $next($request);
    }
}
