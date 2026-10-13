<?php

namespace Tests\Feature;

use App\Models\SupportFaq;
use App\Models\User;
use App\Services\BuyerAssistant;
use App\Services\FaqKnowledge;
use App\Services\GeminiAssistantClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiFaqTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_10_000003_create_support_faqs.php'))->up();
        config(['services.gemini.key' => 'gemini-test-secret', 'services.gemini.rpm' => 5, 'services.gemini.daily_requests' => 100]);
        Http::preventStrayRequests();
    }

    private function user(bool $admin = false): User
    {
        return (new User(['account_type' => 'buyer', 'is_admin' => $admin]))->forceFill(['id' => 1]);
    }

    private function faq(string $audience = 'buyer', bool $published = true): SupportFaq
    {
        return SupportFaq::create(['audience' => $audience, 'question' => 'Can I collect an order?',
            'answer' => 'Ask the seller to confirm collection availability.', 'keywords' => ['collection'],
            'is_published' => $published, 'updated_by' => '1', 'sort_order' => 0]);
    }

    private function response(string $text = ''): array
    {
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $text ?: json_encode([
            'reply' => 'Please check the seller details.', 'product_ids' => [], 'pages' => [],
        ])]]]]]];
    }

    public function test_real_gemini_request_uses_header_key_structured_output_and_redacts_orders(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->response())]);
        $answer = app(BuyerAssistant::class)->reply('Suggest a gift in Taglish.', [], [
            'products' => [], 'pages' => ['orders' => ['label' => 'Orders', 'url' => '/buyer/orders']],
            'recent_orders' => [['number' => 'PRIVATE-ORDER', 'total' => 100]], 'orders_shared' => true,
            'faqs' => [['question' => 'Collection', 'answer' => 'Ask seller']],
        ], true);
        $this->assertNotEmpty($answer['reply']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-3.5-flash-lite:generateContent')
            && !str_contains($request->url(), 'gemini-test-secret') && $request->hasHeader('x-goog-api-key', 'gemini-test-secret')
            && $request['generationConfig']['responseFormat']['text']['mimeType'] === 'application/json'
            && !str_contains(json_encode($request['contents']), 'PRIVATE-ORDER')
            && str_contains(json_encode($request['contents']), 'Ask seller')
            && str_contains(json_encode($request['systemInstruction']), 'neutral'));
    }

    public function test_quota_cooldown_prevents_repeat_api_calls(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 429)]);
        for ($i = 0; $i < 2; $i++) {
            try { app(GeminiAssistantClient::class)->generate('Test', [['role' => 'user', 'content' => 'Test']], []); $this->fail('Expected quota rejection'); }
            catch (\RuntimeException $error) { $this->assertSame(429, $error->getCode()); }
        }
        Http::assertSentCount(1);
    }

    public function test_local_budget_caps_requests_and_rejects_invalid_or_blocked_content(): void
    {
        config(['services.gemini.rpm' => 1]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'SAFETY']]])]);
        try { app(GeminiAssistantClient::class)->generate('Test', [], []); $this->fail('Blocked content must fail'); }
        catch (\RuntimeException $error) { $this->assertSame(0, $error->getCode()); }
        try { app(GeminiAssistantClient::class)->generate('Test', [], []); $this->fail('Local cap must fail'); }
        catch (\RuntimeException $error) { $this->assertSame(429, $error->getCode()); }
        Http::assertSentCount(1);
    }

    public function test_role_specific_faqs_exclude_drafts_and_update_immediately(): void
    {
        $buyer = $this->faq(); $this->faq('admin'); $this->faq('buyer', false);
        $knowledge = app(FaqKnowledge::class);
        $this->assertCount(1, $knowledge->retrieve('buyer', 'collection'));
        $this->assertSame($buyer->id, $knowledge->match('buyer', 'Can I collect an order?')->id);
        $this->assertNull($knowledge->match('buyer', 'compare collection options'));
        $buyer->update(['answer' => 'Updated published answer']);
        $this->assertSame('Updated published answer', $knowledge->retrieve('buyer', 'collection')[0]['answer']);
        $buyer->update(['is_published' => false]);
        $this->assertNull($knowledge->match('buyer', 'collection'));
    }

    public function test_only_admins_manage_faqs_and_html_is_not_published_as_markup(): void
    {
        $data = ['audience' => 'buyer', 'question' => 'Collection?', 'answer' => '<b>Confirm with seller.</b>', 'keywords_text' => 'collection, pick up', 'sort_order' => 0, 'is_published' => true];
        $this->actingAs($this->user())->post(route('admin.faqs.store'), $data)->assertRedirect(route('login'));
        $this->actingAs($this->user(true))->post(route('admin.faqs.store'), $data)->assertRedirect();
        $faq = SupportFaq::firstOrFail(); $this->assertSame('Confirm with seller.', $faq->answer);
        $this->put(route('admin.faqs.update', $faq), array_merge($data, ['answer' => 'New terms']))->assertRedirect();
        $this->assertSame('New terms', $faq->fresh()->answer);
        $this->delete(route('admin.faqs.delete', $faq))->assertRedirect();
        $this->assertDatabaseCount('support_faqs', 0);
    }

    public function test_published_buyer_faq_answers_are_api_free_and_old_provider_context_is_excluded(): void
    {
        (require database_path('migrations/2026_10_10_000002_create_buyer_conversations.php'))->up();
        (require database_path('migrations/2026_10_10_000004_add_buyer_chat_provider.php'))->up();
        $this->faq();
        $this->mock(\App\Services\BuyerAssistantContext::class)->shouldReceive('build')->andReturn(['products' => [], 'pages' => [], 'recent_orders' => []]);
        $this->actingAs($this->user());
        $base = route('buyer.assistant.conversations');
        $id = $this->postJson($base, ['engine' => 'smart'])->assertCreated()->json('conversation.id');
        $this->postJson($base."/$id/messages", ['message' => 'Can I collect an order?'])->assertOk()
            ->assertJsonPath('engine', 'automated')->assertJsonPath('reply', 'Ask the seller to confirm collection availability.');
        Http::assertNothingSent();
        $this->patchJson($base."/$id", ['engine' => 'ai'])->assertOk();
        $chat = \App\Models\BuyerConversation::findOrFail($id);
        $chat->messages()->create(['role' => 'assistant', 'content' => 'SECRET OLD GROQ ORDER DATA',
            'engine' => 'ai', 'context_segment' => $chat->context_segment, 'payload' => []]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->response())]);
        $this->postJson($base."/$id/messages", ['message' => 'Suggest a gift'])->assertOk()->assertJsonPath('engine', 'ai');
        Http::assertSent(fn ($request) => !str_contains(json_encode($request['contents']), 'SECRET OLD GROQ ORDER DATA'));
    }
}
