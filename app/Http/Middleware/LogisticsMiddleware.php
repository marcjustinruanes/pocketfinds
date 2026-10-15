<?php

namespace App\Http\Middleware;

use App\Services\LogisticsAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogisticsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check() || ! auth()->user()->is_logistics) {
            return redirect()->route('login');
        }

        if ($request->routeIs('logistics.logout')) {
            return $next($request);
        }

        abort_unless(auth()->user()->account_type === 'logistics' && ! auth()->user()->is_admin && auth()->user()->status === 'approved', 403, 'An approved logistics account is required.');
        abort_unless(filled(auth()->user()->business_name), 403, 'No logistics company is assigned.');
        abort_unless(auth()->user()->isLogisticsAdmin() || auth()->user()->isHubStaff(), 403, 'A valid logistics role is required.');
        if (auth()->user()->isHubStaff()) {
            app(LogisticsAccess::class)->hub(auth()->user());
        }

        return $next($request);
    }
}
