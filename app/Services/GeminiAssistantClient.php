<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class GeminiAssistantClient
{
    public function generate(string $instructions, array $messages, array $schema): array
    {
        $key = config('services.gemini.key');
        $model = config('services.gemini.model');
        if (!$key || !preg_match('/^gemini-[a-z0-9.-]+$/', $model)) throw new RuntimeException('Gemini is not configured.');
        $budget = 'gemini:'.hash('sha256', $key.':'.$model);
        Cache::lock($budget.':lock', 5)->block(2, function () use ($budget) {
            if (Cache::has($budget.':cooldown')
                || RateLimiter::tooManyAttempts($budget.':minute', max(1, (int) config('services.gemini.rpm')))
                || RateLimiter::tooManyAttempts($budget.':day', max(1, (int) config('services.gemini.daily_requests')))) {
                throw new RuntimeException('Gemini request budget reached.', 429);
            }
            RateLimiter::hit($budget.':minute', 60);
            // Rolling 24-hour local budget, deliberately independent of Google's quota reset.
            RateLimiter::hit($budget.':day', 86400);
        });
        $contents = array_map(fn ($turn) => ['role' => $turn['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $turn['content']]]], $messages);
        $response = Http::withHeaders(['x-goog-api-key' => $key])->acceptJson()->connectTimeout(5)->timeout(25)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent', [
                'systemInstruction' => ['parts' => [['text' => $instructions]]], 'contents' => $contents,
                'generationConfig' => ['maxOutputTokens' => 1024,
                    'responseFormat' => ['text' => ['mimeType' => 'application/json', 'schema' => $schema]]],
            ]);
        if ($response->status() === 429) {
            Cache::put($budget.':cooldown', true, 60);
            throw new RuntimeException('Gemini quota exhausted.', 429);
        }
        if (!$response->successful() || $response->json('candidates.0.finishReason') !== 'STOP') {
            throw new RuntimeException('Gemini unavailable or response blocked.');
        }
        $text = collect($response->json('candidates.0.content.parts', []))->reject(fn ($part) => $part['thought'] ?? false)->pluck('text')->implode('');
        $answer = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($answer)) throw new RuntimeException('Invalid Gemini response.');
        return $answer;
    }
}
