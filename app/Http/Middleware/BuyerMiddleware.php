<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BuyerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }
        if (auth()->user()->account_type !== 'buyer' && !auth()->user()->is_admin) {
            abort(403);
        }
        abort_unless(auth()->user()->status === 'approved', 403, 'This account is not active.');
        return $next($request);
    }
}
