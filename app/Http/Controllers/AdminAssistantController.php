<?php

namespace App\Http\Controllers;

use App\Services\AdminAssistant;
use App\Services\AdminAssistantContext;
use App\Services\AutomatedAssistant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminAssistantController extends Controller
{
    public function export(Request $request, \App\Services\CommerceInsights $insights)
    {
        abort_unless($request->user()?->is_admin, 403);
        $data = $request->validate(['days' => 'sometimes|integer|in:1,7,30,90']);
        $snapshot = $insights->snapshot((int) ($data['days'] ?? 30));
        return response()->streamDownload(function () use ($snapshot) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['PocketFinds business snapshot', now()->toIso8601String()]);
            fputcsv($stream, ['Delivered/completed order value; not profit. Forecasts are estimates.']);
            fputcsv($stream, ['Date', 'Order value PHP', 'Completed/delivered orders']);
            foreach ($snapshot['daily_sales'] as $row) fputcsv($stream, array_values($row));
            fputcsv($stream, []); fputcsv($stream, ['Product', 'Stock', 'Review flag', 'Estimated stock cover days', 'Estimated reorder units (30 days)']);
            foreach ($snapshot['inventory_recommendations'] as $row) {
                // CSV cells must not execute spreadsheet formulas from seller-entered names.
                $name = preg_match('/^[\s]*[=+@-]/u', $row['name']) ? "'".$row['name'] : $row['name'];
                fputcsv($stream, [$name, $row['stock'], $row['flag'], $row['estimated_days_remaining'] ?? 'Insufficient history', $row['estimated_reorder_units_for_30_days'] ?? 'Insufficient history']);
            }
            fputcsv($stream, []); fputcsv($stream, ['Next 7 days estimated order value PHP', $snapshot['forecast']['next_7_days_order_value'] ?? 'Insufficient history']);
            foreach ($snapshot['data_limits'] as $limit) fputcsv($stream, [$limit]);
            fclose($stream);
        }, 'pocketfinds-insights-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function photo(Request $request, \App\Models\Product $product, AdminAssistantContext $context)
    {
        abort_unless($request->user()?->is_admin, 403);
        $file = $context->photoFile($product);
        abort_unless($file, 404);
        return response()->file($file, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function analyze(Request $request, AdminAssistant $assistant, AdminAssistantContext $context, AutomatedAssistant $automated)
    {
        abort_unless($request->user()?->is_admin, 403);
        $input = $request->validate([
            'mode' => 'required|in:dashboard,reports,seller,product,draft,chat,analytics,inventory,anomalies,product_copy',
            'message' => 'required_if:mode,draft,chat|nullable|string|max:1500',
            'product_id' => 'required_if:mode,product,product_copy|nullable|string|max:64',
            'seller_id' => 'required_if:mode,seller|nullable|integer|min:1',
            'days' => 'sometimes|integer|in:1,7,30,90',
            'audience' => 'sometimes|in:all,buyer,seller,rider',
            'engine' => 'sometimes|in:ai,automated',
            'include_photo' => 'sometimes|boolean', 'consent' => 'exclude_if:engine,automated|required|accepted',
        ]);
        $engine = $input['engine'] ?? 'ai';
        abort_if($engine === 'automated' && $input['mode'] !== 'chat', 422, 'Automated replies are available in dashboard chat only.');
        if ($engine === 'ai' && !config('services.groq.key')) return response()->json(['message' => 'Groq is not configured. Use Automated dashboard chat or add the server-side API key.'], 503);
        $key = 'admin_assistant.'.$request->user()->id;
        $engineKey = 'admin_assistant_engine.'.$request->user()->id;
        $history = $input['mode'] === 'chat' && $request->session()->get($engineKey, 'ai') === $engine ? $request->session()->get($key, []) : [];
        try {
            $snapshot = $context->build($input);
            $answer = $engine === 'automated' ? $automated->adminReply($input['message'], $snapshot) : $assistant->review($snapshot, $input['message'] ?? '', $history);
            $answer['engine'] = $engine;
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Admin assistant request failed.', ['exception_type' => get_class($exception)]);
            if ($engine === 'automated') return response()->json(['message' => 'Automated replies could not load dashboard data. Please try again.'], 503);
            $limited = $exception instanceof \RuntimeException && $exception->getCode() === 429;
            return response()->json(['message' => $limited ? 'Groq usage limit reached. Please try later. Your review queues are unchanged.' : 'AI analysis is unavailable. Try again or continue with a manual review.'], $limited ? 429 : 503);
        }
        if ($input['mode'] === 'chat') {
            $history[] = ['role' => 'user', 'content' => $input['message']];
            $history[] = ['role' => 'assistant', 'content' => $answer['summary']];
            $request->session()->put($key, array_slice($history, -4));
            $request->session()->put($engineKey, $engine);
        }
        return response()->json($answer)->header('Cache-Control', 'private, no-store');
    }

    public function clear(Request $request)
    {
        abort_unless($request->user()?->is_admin, 403);
        $request->session()->forget('admin_assistant.'.$request->user()->id);
        $request->session()->forget('admin_assistant_engine.'.$request->user()->id);
        return response()->json(['cleared' => true]);
    }

    public function prepareDraft(Request $request)
    {
        abort_unless($request->user()?->is_admin, 403);
        $draft = $request->validate(['title' => 'required|string|max:255', 'body' => 'required|string|max:3000', 'audience' => 'required|in:all,buyer,seller,rider']);
        return redirect()->route('admin.announcements')->with('ai_announcement_draft', $draft);
    }
}
