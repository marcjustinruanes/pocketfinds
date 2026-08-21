<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SellerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }
        if (auth()->user()->account_type !== 'seller' && !auth()->user()->is_admin) {
            abort(403);
        }
        Cache::put('seller-online-' . auth()->id(), true, now()->addMinutes(3));
        return $next($request);
    }
}
