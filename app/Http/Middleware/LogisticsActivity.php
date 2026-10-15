<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogisticsActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();
        if ($request->isMethodSafe() || ! $name || str_contains($name, 'messages.') || str_ends_with($name, 'logout') || str_ends_with($name, 'scan.lookup')) {
            return $next($request);
        }
        // Even rendered errors and validation redirects roll back operational changes.
        // Store only record IDs, action, actor and timestamp; never request bodies or credentials.
        DB::beginTransaction();
        try {
            $response = $next($request);
            if ($response->getStatusCode() >= 400 || in_array('errors', $request->session()->get('_flash.new', []), true)) {
                DB::rollBack();

                return $response;
            }
            $actor = $request->user();
            if ($actor) {
                $records = $request->attributes->get('logistics_record_ids') ?? [(string) ($request->route('id') ?? $request->attributes->get('logistics_record_id', $actor->id))];
                foreach ($records as $record) {
                    DB::table('logistics_activity_logs')->insert([
                        'company_name' => $actor->business_name, 'actor_id' => $actor->id, 'hub_id' => $actor->logistics_hub_id,
                        'action' => $name, 'record_id' => (string) $record, 'created_at' => now(),
                        ...($request->attributes->has('hiring_changes') ? ['hiring_changes' => json_encode($request->attributes->get('hiring_changes'))] : []),
                    ]);
                }
            }
            DB::commit();

            return $response;
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }
    }
}
