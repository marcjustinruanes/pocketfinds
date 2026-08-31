<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RiderMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || auth()->user()->account_type !== 'rider') {
            return redirect()->route('login');
        }
        if (auth()->user()->status !== 'approved') {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors([
                'email' => 'Your courier account is not yet approved.',
            ]);
        }

        return $next($request);
    }
}
