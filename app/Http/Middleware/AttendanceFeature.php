<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class AttendanceFeature
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('features.attendance') && $request->is('logistics/attendance', 'logistics/attendance/*', 'logistics/my-attendance', 'logistics/my-attendance/*')) abort(404);
        return $next($request);
    }
}
