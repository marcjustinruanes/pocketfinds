<?php

namespace Tests\Feature;

use App\Models\BuyerConversation;
use App\Models\User;
use App\Services\BuyerAssistantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BuyerConversationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_10_000002_create_buyer_conversations.php'))->up();
        (require database_path('migrations/2026_10_10_000004_add_buyer_chat_provider.php'))->up();
        config(['services.gemini.key' => null, 'services.gemini.rpm' => 50]);
        Http::preventStrayRequests();
        $this->actingAs($this->buyer(1));
        $this->mock(BuyerAssistantContext::class)->shouldReceive('build')->andReturn([
            'products' => [['id' => 'p1', 'name' => 'Carrier', 'price' => 899.0, 'stock' => 5, 'url' => '/buyer/product/p1']],
            'pages' => ['orders' => ['label' => 'Orders', 'url' => '/buyer/orders']],
            'active_payment_methods' => ['Cash on delivery'], 'shipping_note' => 'Shipping fee shown at checkout.',
            'signed_in_buyer' => true, 'orders_shared' => false,
        ]);
    }

    private function buyer(int $id): User
    {
        return (new User(['account_type' => 'buyer', 'is_admin' => false, 'status' => 'approved']))->forceFill(['id' => $id]);
    }

    private function base(): string { return route('buyer.assistant.conversations'); }

    private function create(string $engine = 'smart'): string
    {
        return $this->postJson($this->base(), ['engine' => $engine])->assertCreated()->json('conversation.id');
    }

    private function provider(): void
    {
        config(['services.gemini.key' => 'test-only-secret']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => [
            'parts' => [['text' => json_encode(['reply' => 'Compare the recorded product details.', 'product_ids' => ['p1'], 'pages' => []])]],
        ]]]])]);
    }

    public function test_smart_faq_is_api_free_and_transcript_survives_session_replacement(): void
    {
        $id = $this->create();
        $this->postJson($this->base()."/$id/messages", ['message' => 'Payment methods'])->assertOk()->assertJsonPath('engine', 'automated');
        Http::assertNothingSent();
        $this->app['session']->flush();
        $this->actingAs($this->buyer(1));
        $this->getJson($this->base()."/$id")->assertOk()->assertJsonCount(2, 'history')->assertJsonPath('history.0.content', 'Payment methods');
        $this->getJson($this->base())->assertJsonCount(1, 'conversations');
        $raw = DB::table('buyer_chat_messages')->where('conversation_id', $id)->first();
        $this->assertStringNotContainsString('Payment methods', $raw->content);
        $this->assertStringNotContainsString('Cash on delivery', $raw->payload);
    }

    public function test_smart_unmatched_uses_ai_and_unavailable_ai_saves_support_fallback(): void
    {
        $id = $this->create();
        $this->postJson($this->base()."/$id/messages", ['message' => 'Compare these products'])->assertOk()
            ->assertJsonPath('engine', 'fallback')->assertJsonPath('support_available', true);
        Http::assertNothingSent();
        $this->provider();
        $this->postJson($this->base()."/$id/messages", ['message' => 'Compare these products'])->assertOk()->assertJsonPath('engine', 'ai');
        Http::assertSentCount(1);
        $this->getJson($this->base()."/$id")->assertJsonCount(4, 'history');
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 429)]);
        $this->postJson($this->base()."/$id/messages", ['message' => 'Which is better?'])->assertOk()->assertJsonPath('engine', 'fallback');
        $this->getJson($this->base()."/$id")->assertJsonCount(6, 'history');
    }

    public function test_automated_mode_never_sends_unmatched_questions_to_ai(): void
    {
        $this->provider();
        $id = $this->create('automated');
        $this->postJson($this->base()."/$id/messages", ['message' => 'Explain quantum physics'])->assertOk()->assertJsonPath('engine', 'automated');
        Http::assertNothingSent();
    }

    public function test_other_buyers_cannot_read_rename_send_delete_or_list_a_conversation(): void
    {
        $id = $this->create();
        $this->actingAs($this->buyer(2));
        $this->getJson($this->base())->assertJsonCount(0, 'conversations');
        $this->getJson($this->base()."/$id")->assertNotFound();
        $this->patchJson($this->base()."/$id", ['title' => 'Stolen'])->assertNotFound();
        $this->postJson($this->base()."/$id/messages", ['message' => 'Hello'])->assertNotFound();
        $this->deleteJson($this->base()."/$id")->assertNotFound();
        Http::assertNothingSent();
        $this->assertDatabaseCount('buyer_conversations', 1);
    }

    public function test_rename_delete_and_new_chat_only_affect_selected_conversation(): void
    {
        $id = $this->create();
        $this->postJson($this->base()."/$id/messages", ['message' => 'Hello'])->assertOk();
        $this->patchJson($this->base()."/$id", ['title' => 'My shopping'])->assertOk()->assertJsonPath('conversation.title', 'My shopping');
        $other = $this->create();
        $this->getJson($this->base())->assertJsonCount(2, 'conversations');
        $this->deleteJson($this->base()."/$id")->assertOk();
        $this->assertDatabaseCount('buyer_chat_messages', 0);
        $this->getJson($this->base()."/$other")->assertOk();
    }

    public function test_mode_and_consent_changes_retain_transcript_but_isolate_ai_context(): void
    {
        $this->provider();
        $id = $this->create('ai');
        $this->postJson($this->base()."/$id/messages", ['message' => 'Private earlier question'])->assertOk();
        $this->patchJson($this->base()."/$id", ['orders_shared' => true])->assertOk();
        $this->postJson($this->base()."/$id/messages", ['message' => 'Fresh question'])->assertOk();
        Http::assertSent(fn ($request) => str_contains(json_encode($request['contents']), 'Fresh question')
            && !str_contains(json_encode($request['contents']), 'Private earlier question'));
        $this->patchJson($this->base()."/$id", ['engine' => 'automated', 'orders_shared' => false])->assertOk();
        $this->postJson($this->base()."/$id/messages", ['message' => 'Payment methods'])->assertOk();
        $this->patchJson($this->base()."/$id", ['engine' => 'ai'])->assertOk();
        $this->postJson($this->base()."/$id/messages", ['message' => 'Another comparison'])->assertOk();
        Http::assertSent(fn ($request) => str_contains(json_encode($request['contents']), 'Another comparison')
            && !str_contains(json_encode($request['contents']), 'Fresh question')
            && !str_contains(json_encode($request['contents']), 'Payment methods'));
        $this->getJson($this->base()."/$id")->assertJsonCount(8, 'history');
    }

    public function test_old_messages_are_paginated_not_pruned(): void
    {
        $id = $this->create(); $chat = BuyerConversation::findOrFail($id);
        for ($i = 0; $i < 60; $i++) $chat->messages()->create(['role' => 'user', 'content' => 'Message '.$i, 'engine' => 'automated', 'context_segment' => 0, 'payload' => []]);
        $state = $this->getJson($this->base()."/$id")->assertJsonCount(50, 'history');
        $this->getJson($this->base()."/$id?before=".$state->json('before'))->assertJsonCount(10, 'history')->assertJsonPath('history.0.content', 'Message 0');
        $this->assertDatabaseCount('buyer_chat_messages', 60);
        $this->postJson($this->base()."/$id/messages", ['message' => '   '])->assertUnprocessable();
        $this->patchJson($this->base()."/$id", ['title' => str_repeat('x', 101)])->assertUnprocessable();
        $this->getJson($this->base()."/$id?before=invalid")->assertUnprocessable();
    }

    public function test_guests_sellers_and_admins_are_denied(): void
    {
        $this->actingAs((new User(['account_type' => 'seller']))->forceFill(['id' => 3]));
        $this->getJson($this->base())->assertForbidden();
        $this->actingAs((new User(['account_type' => 'buyer', 'is_admin' => true]))->forceFill(['id' => 4]));
        $this->getJson($this->base())->assertForbidden();
        auth()->forgetUser();
        $this->getJson($this->base())->assertRedirect(route('login'));
    }

    public function test_conversation_list_paginates_and_never_exposes_api_key(): void
    {
        config(['services.gemini.key' => 'private-test-secret']);
        for ($i = 0; $i < 31; $i++) BuyerConversation::create(['buyer_id' => '1', 'title' => 'Chat '.$i]);
        $this->getJson($this->base())->assertOk()->assertJsonCount(30, 'conversations')->assertJsonPath('next_page', 2)
            ->assertDontSee('private-test-secret');
        $this->getJson($this->base().'?page=2')->assertJsonCount(1, 'conversations')->assertJsonPath('next_page', null);
    }

    public function test_available_legacy_session_history_is_imported_once_for_its_owner(): void
    {
        $this->withSession(['shopping_assistant' => [1 => ['history' => [
            ['role' => 'user', 'content' => 'Old question'], ['role' => 'assistant', 'content' => 'Old answer'],
        ]], 2 => ['history' => [['role' => 'user', 'content' => 'Other buyer secret']]]]])
            ->getJson($this->base())->assertOk()->assertJsonCount(1, 'conversations');
        $this->getJson($this->base())->assertJsonCount(1, 'conversations');
        $chat = BuyerConversation::firstOrFail();
        $this->getJson($this->base().'/'.$chat->id)->assertJsonCount(2, 'history')->assertDontSee('Other buyer secret');
        $this->assertSame(1, (int) $chat->context_segment);
        $this->assertSame(0, (int) $chat->messages()->first()->context_segment);
    }
}
