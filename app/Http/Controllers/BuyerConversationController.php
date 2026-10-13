<?php

namespace App\Http\Controllers;

use App\Models\BuyerConversation;
use App\Services\AutomatedAssistant;
use App\Services\BuyerAssistant;
use App\Services\BuyerAssistantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BuyerConversationController extends Controller
{
    private function owned(Request $request)
    {
        abort_unless($request->user()->account_type === 'buyer' && !$request->user()->is_admin, 403);
        return BuyerConversation::where('buyer_id', (string) $request->user()->id);
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'private, no-store');
    }

    private function summary(BuyerConversation $chat): array
    {
        return ['id' => $chat->id, 'title' => $chat->title, 'engine' => $chat->engine,
            'orders_shared' => $chat->orders_shared, 'updated_at' => $chat->updated_at->toIso8601String()];
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $this->owned($request);
        $legacyKey = 'shopping_assistant.'.$request->user()->id;
        $state = $request->session()->get($legacyKey, []);
        $legacy = collect($state['history'] ?? [])->filter(fn ($turn) => is_array($turn)
            && in_array($turn['role'] ?? null, ['user', 'assistant'], true) && is_string($turn['content'] ?? null))->take(-8);
        if ($legacy->isNotEmpty()) {
            DB::transaction(function () use ($request, $state, $legacy) {
                $engine = in_array($state['engine'] ?? null, ['ai', 'automated'], true) ? $state['engine'] : 'ai';
                $chat = BuyerConversation::create(['buyer_id' => (string) $request->user()->id, 'title' => 'Previous conversation',
                    'engine' => $engine, 'orders_shared' => (bool) ($state['orders_shared'] ?? false), 'context_segment' => 1]);
                foreach ($legacy as $turn) $chat->messages()->create(['role' => $turn['role'], 'engine' => $engine,
                    'context_segment' => 0, 'content' => mb_substr($turn['content'], 0, 6000), 'payload' => []]);
            });
            $request->session()->forget($legacyKey);
        }
        $chats = $this->owned($request)->orderByDesc('updated_at')->orderByDesc('id')->paginate(30);
        return $this->json(['conversations' => $chats->map(fn ($chat) => $this->summary($chat))->all(),
            'next_page' => $chats->hasMorePages() ? $chats->currentPage() + 1 : null,
            'available' => (bool) config('services.gemini.key')]);
    }

    public function create(Request $request): JsonResponse
    {
        $this->owned($request);
        $data = $request->validate(['engine' => ['sometimes', 'in:smart,automated,ai']]);
        $chat = BuyerConversation::create(['buyer_id' => (string) $request->user()->id,
            'title' => 'New conversation', 'engine' => $data['engine'] ?? 'smart', 'orders_shared' => false, 'context_segment' => 0]);
        return $this->json(['conversation' => $this->summary($chat)], 201);
    }

    public function show(Request $request, string $conversation): JsonResponse
    {
        $chat = $this->owned($request)->findOrFail($conversation);
        $data = $request->validate(['before' => ['sometimes', 'integer', 'min:1']]);
        $messages = $chat->messages()->when(isset($data['before']), fn ($query) => $query->where('id', '<', $data['before']))
            ->orderByDesc('id')->limit(51)->get();
        $more = $messages->count() > 50;
        $messages = $messages->take(50)->reverse()->values();
        return $this->json(['conversation' => $this->summary($chat), 'history' => $messages->map(fn ($message) => [
            'id' => $message->id, 'role' => $message->role, 'content' => $message->content,
            'engine' => $message->engine, 'result' => $message->payload ?? [],
            'created_at' => $message->created_at->toIso8601String(),
        ])->all(), 'before' => $more ? $messages->first()->id : null]);
    }

    public function update(Request $request, string $conversation): JsonResponse
    {
        $data = $request->validate(['title' => ['sometimes', 'required', 'string', 'max:100', 'regex:/\S/u'],
            'engine' => ['sometimes', 'in:smart,automated,ai'], 'orders_shared' => ['sometimes', 'boolean']]);
        return DB::transaction(function () use ($request, $conversation, $data) {
            $chat = $this->owned($request)->lockForUpdate()->findOrFail($conversation);
            if ((isset($data['engine']) && $data['engine'] !== $chat->engine)
                || (isset($data['orders_shared']) && (bool) $data['orders_shared'] !== $chat->orders_shared)) {
                // Keep the transcript, but never forward an earlier consent/mode segment to AI.
                $chat->context_segment++;
            }
            if (isset($data['title'])) $data['title'] = trim($data['title']);
            $chat->fill($data)->save();
            return $this->json(['conversation' => $this->summary($chat)]);
        });
    }

    public function delete(Request $request, string $conversation): JsonResponse
    {
        DB::transaction(function () use ($request, $conversation) {
            $this->owned($request)->lockForUpdate()->findOrFail($conversation)->delete();
        });
        return $this->json(['deleted' => true]);
    }

    public function send(Request $request, string $conversation, BuyerAssistant $ai, BuyerAssistantContext $context, AutomatedAssistant $rules): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1500', 'regex:/\S/u'],
            'product_id' => ['nullable', 'string', 'max:64']]);
        return DB::transaction(function () use ($request, $conversation, $data, $ai, $context, $rules) {
            $chat = $this->owned($request)->lockForUpdate()->findOrFail($conversation);
            $message = trim($data['message']);
            $snapshot = $context->build($request->user(), $chat->orders_shared, $message, $data['product_id'] ?? null);
            $answer = $rules->buyerReply($message, $snapshot);
            $faq = app(\App\Services\FaqKnowledge::class)->match('buyer', $message);
            if ($faq) $answer = ['engine' => 'automated', 'reply' => $faq->answer, 'products' => [],
                'links' => [['label' => $faq->question, 'url' => route('buyer.faqs', ['faq' => $faq->id]).'#faq-'.$faq->id]]];
            $useAi = $chat->engine === 'ai' || ($chat->engine === 'smart' && !$faq && !$rules->recognizesBuyerQuestion($message));
            if ($useAi) {
                try {
                    if (!config('services.gemini.key')) throw new \RuntimeException('AI not configured');
                    $history = $chat->messages()->where('context_segment', $chat->context_segment)->where('engine', 'ai')->where('provider', 'gemini')
                        ->orderByDesc('id')->limit(4)->get()->reverse()->map(fn ($turn) => [
                            'role' => $turn->role, 'content' => $turn->content,
                        ])->values()->all();
                    $answer = $ai->reply($message, $history, $snapshot, true);
                    $answer['engine'] = 'ai';
                } catch (Throwable $exception) {
                    Log::warning('Buyer conversation AI unavailable.', ['exception_type' => get_class($exception)]);
                    $answer = ['engine' => 'fallback', 'reply' => 'AI is unavailable right now. Try Automated for common shopping questions, or use Contact support to send your issue to a person. Nothing has been sent to support automatically.',
                        'products' => [], 'links' => [], 'support_available' => true];
                    if ($exception->getCode() === 429) $answer['reply'] = 'The AI usage limit has been reached. Please try later, choose Automated for common questions, or use Contact support. No support message has been sent automatically.';
                }
            }
            $common = ['engine' => $answer['engine'], 'context_segment' => $chat->context_segment,
                'provider' => $answer['engine'] === 'ai' ? 'gemini' : null];
            $firstMessage = !$chat->messages()->exists();
            $chat->messages()->create($common + ['role' => 'user', 'content' => $message, 'payload' => []]);
            $chat->messages()->create($common + ['role' => 'assistant', 'content' => $answer['reply'], 'payload' => $answer]);
            if ($firstMessage && $chat->title === 'New conversation') $chat->title = Str::limit($message, 65, '...');
            $chat->touch();
            return $this->json($answer + ['conversation' => $this->summary($chat)]);
        });
    }
}
