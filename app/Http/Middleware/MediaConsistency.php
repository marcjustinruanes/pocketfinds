<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MediaConsistency
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || (count($request->allFiles()) === 0 && ! $request->boolean('hero_image_clear') && ! $request->boolean('hero_image_2_clear') && ! $request->routeIs('admin.update-requests.*'))) {
            return $next($request);
        }
        $level = DB::transactionLevel();
        DB::beginTransaction();
        try {
            $response = $next($request);
            if ($response->getStatusCode() >= 400 || in_array('errors', $request->session()->get('_flash.new', []), true)) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            return $response;
        } catch (Throwable $error) {
            if (DB::transactionLevel() > $level) {
                DB::rollBack($level);
            }
            throw $error;
        }
    }
}
