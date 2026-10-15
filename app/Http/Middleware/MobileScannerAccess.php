<?php

namespace App\Http\Middleware;

use App\Services\LogisticsAccess;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class MobileScannerAccess
{
    public static function check($user): void
    {
        abort_unless($user && $user->account_type === 'logistics' && $user->is_logistics && ! $user->is_admin
            && $user->status === 'approved' && filled($user->business_name)
            && ($user->isLogisticsAdmin() || $user->isHubStaff()), 403, 'An approved Logistics Admin or Hub Staff account is required.');
        if ($user->isHubStaff()) {
            app(LogisticsAccess::class)->hub($user);
        }
    }

    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->currentAccessToken() instanceof PersonalAccessToken
            && $request->user()->tokenCan('scanner'), 401, 'A scanner API token is required.');
        self::check($request->user());

        return $next($request)->header('Cache-Control', 'no-store, private');
    }
}
