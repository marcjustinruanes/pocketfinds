<?php

namespace App\Http\Controllers;

use App\Services\BuyerAssistant;
use App\Services\BuyerAssistantContext;
use App\Services\AutomatedAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class GuestAssistantController extends Controller
{
    public function history(Request $request)
    {
        return response()->json(['history' => $request->session()->get('guest_assistant', []), 'orders_shared' => false,
            'engine' => $request->session()->get('guest_assistant_engine', $request->session()->has('guest_assistant') ? 'ai' : 'automated'),
            'available' => (bool) config('services.groq.key')])->header('Cache-Control', 'private, no-store');
    }

    public function send(Request $request, BuyerAssistant $assistant, BuyerAssistantContext $context, AutomatedAssistant $automated)
    {
        $data = $request->validate(['message' => 'required|string|max:1500', 'include_orders' => 'sometimes|declined', 'product_id' => 'nullable|string|max:64', 'engine' => 'sometimes|in:ai,automated']);
        $engine = $data['engine'] ?? 'ai';
        if ($engine === 'ai' && !config('services.groq.key')) return response()->json(['message' => 'AI is not connected. Choose Automated for quick help without AI.'], 503);
        $history = $request->session()->get('guest_assistant_engine', 'ai') === $engine ? $request->session()->get('guest_assistant', []) : [];
        try {
            // Even an authenticated visitor to this endpoint receives public context only.
            $snapshot = $context->build(null, false, $data['message'], $data['product_id'] ?? null);
            $answer = $engine === 'automated' ? $automated->buyerReply($data['message'], $snapshot) : $assistant->reply($data['message'], $history, $snapshot);
            $answer['engine'] = $engine;
        } catch (Throwable $exception) {
            Log::warning('Guest assistant unavailable.', ['exception_type' => get_class($exception)]);
            if ($engine === 'automated') return response()->json(['message' => 'Automated replies could not load store information. Please try again or contact support.'], 503);
            $limited = $exception instanceof \RuntimeException && $exception->getCode() === 429;
            return response()->json(['message' => $limited ? 'AI usage limit reached. Please try later or contact support.' : 'AI is temporarily unavailable. Please contact support.'], $limited ? 429 : 503);
        }
        $history[] = ['role' => 'user', 'content' => $data['message']];
        $history[] = ['role' => 'assistant', 'content' => $answer['reply']];
        $request->session()->put('guest_assistant', array_slice($history, -8));
        $request->session()->put('guest_assistant_engine', $engine);
        return response()->json($answer)->header('Cache-Control', 'private, no-store');
    }

    public function clear(Request $request)
    {
        $request->session()->forget('guest_assistant');
        $request->session()->forget('guest_assistant_engine');
        return response()->json(['cleared' => true]);
    }
}
