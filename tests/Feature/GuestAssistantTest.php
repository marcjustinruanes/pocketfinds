<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BuyerAssistantContext;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GuestAssistantTest extends TestCase
{
    public function test_guest_chat_never_uses_authenticated_order_data(): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode([
            'reply' => 'Sign in for order status.', 'product_ids' => ['public', 'invented'], 'pages' => ['orders'],
        ])]]]])]);
        $this->mock(BuyerAssistantContext::class)->shouldReceive('build')->once()->with(null, false, 'Find a gift', null)->andReturn([
            'products' => [['id' => 'public', 'name' => 'Public product', 'price' => 100, 'stock' => 2, 'url' => route('guest.product', 'public')]],
            'orders_shared' => false, 'recent_orders' => [], 'pages' => ['orders' => ['label' => 'Sign in', 'url' => route('login')]],
        ]);
        $buyer = (new User(['account_type' => 'buyer']))->forceFill(['id' => 1]);
        $this->actingAs($buyer)->withSession(['shopping_assistant' => [1 => ['history' => [['role' => 'user', 'content' => 'PRIVATE ORDER']]]]])
            ->postJson(route('guest.assistant.send'), ['message' => 'Find a gift', 'include_orders' => false])->assertOk()
            ->assertJsonCount(1, 'products')->assertJsonPath('products.0.url', route('guest.product', 'public'));
        Http::assertSent(fn ($request) => !str_contains(json_encode($request['messages']), 'PRIVATE ORDER'));
        $this->getJson(route('guest.assistant.history'))->assertJsonPath('orders_shared', false)->assertJsonCount(2, 'history');
        $this->deleteJson(route('guest.assistant.clear'))->assertOk();
        $this->assertNotNull(session('shopping_assistant.1'));
        $this->assertNull(session('guest_assistant'));
    }

    public function test_guests_cannot_request_orders_and_missing_key_is_honest(): void
    {
        Http::fake(); config(['services.groq.key' => null]);
        $this->postJson(route('guest.assistant.send'), ['message' => 'My orders', 'include_orders' => true])->assertUnprocessable();
        $this->postJson(route('guest.assistant.send'), ['message' => 'Hello'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_public_history_does_not_expose_secrets(): void
    {
        config(['services.groq.key' => 'SECRET-API-KEY']);
        $this->getJson(route('guest.assistant.history'))->assertOk()->assertJsonPath('available', true)->assertDontSee('SECRET-API-KEY');
    }
}
