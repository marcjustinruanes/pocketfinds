<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class AdminAiFeature
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('features.admin_ai') && $request->is('admin/ai', 'admin/ai/*')) abort(404);
        return $next($request);
    }
}
