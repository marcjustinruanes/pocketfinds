<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after LogisticsMiddleware (which already confirmed is_logistics), narrowing
 * a handful of company-wide actions — approving pickup/hub-transfer requests, rider
 * management, reports, company policy — to the company's admin account only. Hub
 * staff pass the outer 'logistics' gate but are blocked here from URLs their own
 * sidebar never shows them.
 */
class LogisticsAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->user()->isLogisticsAdmin()) {
            abort(403, "This is only available to your company's admin account.");
        }

        return $next($request);
    }
}
