<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminAssistantContext;
use App\Services\AutomatedAssistant;
use App\Services\BuyerAssistantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AutomatedAssistantTest extends TestCase
{
    private function buyer(): User
    {
        return (new User(['account_type' => 'buyer', 'is_admin' => false, 'status' => 'approved']))->forceFill(['id' => 1]);
    }

    private function shopping(): array
    {
        return ['signed_in_buyer' => true, 'orders_shared' => true,
            'recent_orders' => [['number' => 'OWN-ORDER', 'status' => 'placed', 'placed_on' => '2026-10-10']],
            'products' => [['id' => 'p1', 'name' => 'Pet bowl', 'stock' => 3, 'price' => 400, 'url' => route('buyer.product', 'p1')],
                ['id' => 'p2', 'name' => 'Pet bed', 'stock' => 0, 'price' => 300, 'url' => route('buyer.product', 'p2')],
                ['id' => 'p3', 'name' => 'Carrier', 'stock' => 2, 'price' => 900, 'url' => route('buyer.product', 'p3')]],
            'active_payment_methods' => ['Cash on delivery'], 'shipping_note' => 'Seller fees are shown at checkout.',
            'policies' => [['title' => 'Buyer terms', 'excerpt' => 'Published buyer terms only.']],
            'pages' => ['products' => ['label' => 'Products', 'url' => route('buyer.dashboard')], 'orders' => ['label' => 'Orders', 'url' => route('buyer.orders')], 'messages' => ['label' => 'Seller messages', 'url' => route('buyer.messages')], 'cart' => ['label' => 'Cart', 'url' => route('buyer.cart')]]];
    }

    public function test_buyer_automated_orders_work_without_a_key_or_provider_call(): void
    {
        Http::fake(); config(['services.groq.key' => null]);
        $this->mock(BuyerAssistantContext::class)->shouldReceive('build')->once()->withArgs(fn ($user, $include) => $user->id === 1 && $include)->andReturn($this->shopping());
        $this->actingAs($this->buyer())->postJson(route('buyer.assistant.send'), ['engine' => 'automated', 'message' => 'My order status', 'include_orders' => true])
            ->assertOk()->assertJsonPath('engine', 'automated')->assertSee('OWN-ORDER')->assertJsonCount(5, 'suggestions');
        $this->getJson(route('buyer.assistant.history'))->assertJsonPath('engine', 'automated')->assertJsonPath('available', false);
        Http::assertNothingSent();
    }

    public function test_automated_shopping_topics_use_data_and_never_send_messages(): void
    {
        Http::fake(); Mail::fake();
        $bot = app(AutomatedAssistant::class); $context = $this->shopping();
        $products = $bot->buyerReply('Help me find products under 500 pesos', $context);
        $this->assertCount(1, $products['products']); $this->assertSame('Pet bowl', $products['products'][0]['name']);
        $this->assertStringContainsString('Cash on delivery', $bot->buyerReply('Payment methods', $context)['reply']);
        $this->assertStringContainsString('Seller fees', $bot->buyerReply('Shipping fees', $context)['reply']);
        $this->assertStringContainsString('Published buyer terms', $bot->buyerReply('Returns policy', $context)['reply']);
        $this->assertStringContainsString('cannot cancel', $bot->buyerReply('Cancel my order', $context)['reply']);
        $this->assertStringContainsString('Choose a topic', $bot->buyerReply('Tell me about astronomy', $context)['reply']);
        $this->assertStringContainsString('Nothing is sent automatically', $bot->buyerReply('Human support', $context)['reply']);
        Http::assertNothingSent(); Mail::assertNothingSent();
    }

    public function test_order_consent_and_guest_boundaries_apply_to_automated_replies(): void
    {
        $context = $this->shopping(); $bot = app(AutomatedAssistant::class);
        $context['orders_shared'] = false;
        $this->assertStringNotContainsString('OWN-ORDER', $bot->buyerReply('My orders', $context)['reply']);
        $context['signed_in_buyer'] = false; $context['orders_shared'] = true;
        $this->assertStringNotContainsString('OWN-ORDER', $bot->buyerReply('My orders', $context)['reply']);
        $this->assertStringContainsString('sign in', $bot->buyerReply('My orders', $context)['reply']);
        Http::fake(); config(['services.groq.key' => null]);
        $this->mock(BuyerAssistantContext::class)->shouldReceive('build')->once()->with(null, false, 'My orders', null)->andReturn($context);
        $this->postJson(route('guest.assistant.send'), ['engine' => 'automated', 'message' => 'My orders'])->assertOk()->assertDontSee('OWN-ORDER');
        $this->postJson(route('guest.assistant.send'), ['engine' => 'automated', 'message' => 'My orders', 'include_orders' => true])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_automated_history_is_never_forwarded_when_switching_to_ai(): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(['reply' => 'AI reply', 'product_ids' => [], 'pages' => []])]]]])]);
        $this->mock(BuyerAssistantContext::class)->shouldReceive('build')->once()->andReturn($this->shopping());
        $this->actingAs($this->buyer())->withSession(['shopping_assistant' => [1 => ['engine' => 'automated', 'orders_shared' => false,
            'history' => [['role' => 'user', 'content' => 'AUTOMATED-HISTORY-DO-NOT-SHARE']]]]])
            ->postJson(route('buyer.assistant.send'), ['engine' => 'ai', 'message' => 'Gift ideas', 'include_orders' => false])->assertOk()->assertJsonPath('engine', 'ai');
        Http::assertSent(fn ($request) => !str_contains(json_encode($request['messages']), 'AUTOMATED-HISTORY-DO-NOT-SHARE'));
    }

    private function adminData(): array
    {
        return ['image' => null, 'data' => ['mode' => 'chat', 'generated_at' => now()->toIso8601String(), 'currency' => 'PHP', 'sources' => [],
            'period' => ['days' => 7], 'low_stock_count' => 2, 'low_stock_threshold' => 5, 'low_stock_examples' => [['name' => 'Recorded product', 'stock' => 2]],
            'period_order_count' => 12, 'period_delivered_completed_order_count' => 5, 'period_cancelled_order_count' => 1, 'period_delivered_completed_order_value' => 1500,
            'pending_seller_applications' => 2, 'pending_products' => 3, 'open_complaints' => 4]];
    }

    public function test_admin_automated_chat_uses_database_counts_without_ai_or_consent(): void
    {
        Http::fake(); config(['services.groq.key' => null]);
        $this->mock(AdminAssistantContext::class)->shouldReceive('build')->twice()->andReturn($this->adminData());
        $admin = (new User(['account_type' => 'buyer', 'is_admin' => true]))->forceFill(['id' => 10]);
        $this->actingAs($admin)->postJson(route('admin.ai.analyze'), ['engine' => 'automated', 'mode' => 'chat', 'message' => 'Sales summary'])
            ->assertOk()->assertJsonPath('engine', 'automated')->assertSee('1,500.00')->assertSee('not net profit');
        $this->postJson(route('admin.ai.analyze'), ['engine' => 'automated', 'mode' => 'chat', 'message' => 'Low stock', 'consent' => false])
            ->assertOk()->assertJsonPath('findings.0.evidence', 'Recorded stock: 2 units.')->assertJsonCount(6, 'suggestions');
        $this->assertSame('automated', session('admin_assistant_engine.10'));
        Http::assertNothingSent();
    }

    public function test_automated_mode_cannot_bypass_admin_permissions_or_ai_consent(): void
    {
        Http::fake(); config(['services.groq.key' => null]);
        $this->actingAs($this->buyer())->postJson(route('admin.ai.analyze'), ['engine' => 'automated', 'mode' => 'chat', 'message' => 'Sales'])->assertRedirect(route('login'));
        $admin = (new User(['account_type' => 'buyer', 'is_admin' => true]))->forceFill(['id' => 10]);
        $this->actingAs($admin)->postJson(route('admin.ai.analyze'), ['engine' => 'automated', 'mode' => 'product', 'product_id' => 'p1'])->assertUnprocessable();
        $this->postJson(route('admin.ai.analyze'), ['engine' => 'ai', 'mode' => 'chat', 'message' => 'Sales'])->assertUnprocessable();
        $this->postJson(route('admin.ai.analyze'), ['engine' => 'invalid', 'mode' => 'chat', 'message' => 'Sales', 'consent' => true])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_admin_automated_history_is_not_sent_to_the_ai_provider(): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode([
            'summary' => 'Verified database summary.', 'risk' => 'not_applicable', 'findings' => [], 'limitations' => [],
            'draft' => ['title' => '', 'body' => ''], 'source_ids' => [],
        ])]]]])]);
        $this->mock(AdminAssistantContext::class)->shouldReceive('build')->once()->andReturn($this->adminData());
        $admin = (new User(['account_type' => 'buyer', 'is_admin' => true]))->forceFill(['id' => 10]);
        $this->actingAs($admin)->withSession(['admin_assistant_engine' => [10 => 'automated'], 'admin_assistant' => [10 => [['role' => 'user', 'content' => 'PRIVATE-AUTOMATED-ADMIN-HISTORY']]]])
            ->postJson(route('admin.ai.analyze'), ['engine' => 'ai', 'mode' => 'chat', 'message' => 'Sales summary', 'consent' => true])->assertOk()->assertJsonPath('engine', 'ai');
        Http::assertSent(fn ($request) => !str_contains(json_encode($request['messages']), 'PRIVATE-AUTOMATED-ADMIN-HISTORY'));
    }
}
