<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GroqAssistantClient
{
    public function generate(string $instructions, array $messages, array $schema, bool $vision = false): array
    {
        $payload = [
            'model' => config($vision ? 'services.groq.vision_model' : 'services.groq.model'),
            'max_completion_tokens' => 2048,
            'messages' => array_merge([['role' => 'system', 'content' => $instructions]], $messages),
            'response_format' => ['type' => 'json_schema', 'json_schema' => [
                'name' => 'assistant_reply', 'strict' => true, 'schema' => $schema,
            ]],
        ];
        if (!$vision) {
            $payload['reasoning_effort'] = 'low';
        }
        $response = Http::withToken(config('services.groq.key'))->acceptJson()
            ->connectTimeout(5)->timeout(30)->post('https://api.groq.com/openai/v1/chat/completions', $payload);
        if ($response->status() === 429) {
            throw new RuntimeException('Assistant usage limit reached.', 429);
        }
        if (!$response->successful() || $response->json('choices.0.finish_reason') !== 'stop') {
            throw new RuntimeException('Assistant provider unavailable.');
        }
        $answer = json_decode($response->json('choices.0.message.content', ''), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($answer)) {
            throw new RuntimeException('Assistant returned an invalid response.');
        }
        return $answer;
    }
}
