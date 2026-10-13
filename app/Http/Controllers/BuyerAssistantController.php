<?php

namespace App\Http\Controllers;

use App\Services\BuyerAssistant;
use App\Services\BuyerAssistantContext;
use App\Services\AutomatedAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class BuyerAssistantController extends Controller
{
    private function historyKey(Request $request): string
    {
        return 'shopping_assistant.'.$request->user()->id;
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($request->user()->account_type === 'buyer', 403);
        $state = $request->session()->get($this->historyKey($request), []);
        return response()->json([
            'history' => array_slice($state['history'] ?? [], -8),
            'orders_shared' => (bool) ($state['orders_shared'] ?? false),
            'available' => (bool) config('services.groq.key'),
            'engine' => $state['engine'] ?? (empty($state['history']) ? 'automated' : 'ai'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function send(Request $request, BuyerAssistant $assistant, BuyerAssistantContext $context, AutomatedAssistant $automated): JsonResponse
    {
        abort_unless($request->user()->account_type === 'buyer', 403);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
            'include_orders' => ['sometimes', 'boolean'],
            'product_id' => ['nullable', 'string', 'max:64'],
            'engine' => ['sometimes', 'in:ai,automated'],
        ]);

        $engine = $data['engine'] ?? 'ai';
        if ($engine === 'ai' && !config('services.groq.key')) {
            return response()->json(['message' => 'The AI assistant is not connected yet. You can still browse products or message a seller below.'], 503);
        }

        $key = $this->historyKey($request);
        $includeOrders = (bool) ($data['include_orders'] ?? false);
        // Changing consent discards any earlier replies that may contain order information.
        $history = $request->session()->get($key.'.orders_shared') === $includeOrders && $request->session()->get($key.'.engine', 'ai') === $engine
            ? $request->session()->get($key.'.history', []) : [];
        try {
            $snapshot = $context->build($request->user(), $includeOrders, $data['message'], $data['product_id'] ?? null);
            $answer = $engine === 'automated' ? $automated->buyerReply($data['message'], $snapshot)
                : $assistant->reply(trim($data['message']), array_slice($history, -8), $snapshot);
            $answer['engine'] = $engine;
        } catch (Throwable $exception) {
            // Do not log messages, order details, provider bodies or API credentials.
            Log::warning('Buyer assistant request failed.', ['exception_type' => get_class($exception)]);
            if ($exception instanceof \RuntimeException && $exception->getCode() === 429) {
                return response()->json(['message' => 'The AI usage limit has been reached. Please try later; products and seller messages are still available.'], 429);
            }
            return response()->json(['message' => 'The assistant is temporarily unavailable. Please try again, or message your seller.'], 503);
        }

        $history[] = ['role' => 'user', 'content' => trim($data['message'])];
        $history[] = ['role' => 'assistant', 'content' => $answer['reply']];
        $request->session()->put($key, ['engine' => $engine, 'orders_shared' => $includeOrders, 'history' => array_slice($history, -8)]);
        return response()->json($answer)->header('Cache-Control', 'private, no-store');
    }

    public function clear(Request $request): JsonResponse
    {
        abort_unless($request->user()->account_type === 'buyer', 403);
        $request->session()->forget($this->historyKey($request));
        return response()->json(['cleared' => true]);
    }
}
