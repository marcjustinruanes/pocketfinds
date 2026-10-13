<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

class BuyerStorefrontTest extends TestCase
{
    private function storefrontData(): array
    {
        return [
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'heroSettings' => new Setting(), 'categories' => collect(),
            'products' => [[
                'id' => 'sample-product', 'name' => 'Sample Product', 'img' => null,
                'price' => 100, 'old_price' => null, 'rating' => 0, 'sold' => 0,
            ]],
            'deals' => [], 'shops' => [], 'dbError' => false,
            'browseCategoryId' => null, 'productCategoryId' => null,
            'activeCategory' => null, 'search' => null, 'minPrice' => null,
            'maxPrice' => null, 'sort' => 'newest',
        ];
    }

    public function test_buyer_storefront_uses_signed_in_links_without_guest_prompts(): void
    {
        $this->actingAs(new User(['id' => 'test-buyer', 'given_names' => 'Alex', 'last_name' => 'Buyer', 'account_type' => 'buyer']));
        $view = $this->view('buyer.dashboard', array_merge($this->storefrontData(), [
            'buyerMode' => true, 'notifications' => collect(), 'cartCount' => 3,
            'unreadMessages' => 1,
            'statusCounts' => ['to_ship' => 2, 'in_transit' => 0, 'out_for_delivery' => 0, 'completed' => 1],
        ]));
        $view->assertSee('Hi, Alex!')
            ->assertSee(route('buyer.product', 'sample-product'))
            ->assertSee(route('buyer.cart'))
            ->assertSee(route('buyer.logout'))
            ->assertSee('Cart, 3 items')
            ->assertSee('id="pfAssistantPanel"', false)
            ->assertDontSee('data-protected', false)
            ->assertDontSee('id="authModal"', false)
            ->assertDontSee(route('guest.product', 'sample-product'));
    }

    public function test_guest_storefront_keeps_guest_links_and_sign_in_dialog(): void
    {
        $this->view('guest.home', $this->storefrontData())
            ->assertSee(route('guest.product', 'sample-product'))
            ->assertSee('id="authModal"', false)
            ->assertSee('data-protected', false)
            ->assertSee('id="pfAssistantPanel"', false)
            ->assertSee(route('guest.assistant.send'))
            ->assertDontSee('data-share-orders', false)
            ->assertDontSee('pf-buyer-orders', false);
    }

    public function test_dashboard_still_requires_authentication(): void
    {
        $this->get(route('buyer.dashboard'))->assertRedirect(route('login'));
    }

    public function test_buyer_layout_uses_the_shared_storefront_navigation_and_theme(): void
    {
        $this->actingAs(new User(['id' => 'test-buyer', 'given_names' => 'Alex', 'account_type' => 'buyer']));
        $this->view('buyer.layout', [
            'notifications' => collect(), 'cartCount' => 0, 'unreadMessages' => 0,
        ])->assertSee('css/buyer-pages.css', false)
            ->assertSee('class="market-header"', false)
            ->assertSee('aria-label="Buyer navigation"', false)
            ->assertSee(route('buyer.dashboard').'#shops')
            ->assertSee('id="cartBadge"', false)
            ->assertSee('id="pfAssistantPanel"', false)
            ->assertDontSee('class="topbar"', false);
    }

    public function test_seller_cannot_open_buyer_dashboard(): void
    {
        $this->actingAs(new User(['id' => 'test-seller', 'account_type' => 'seller', 'is_admin' => false]));
        $this->get(route('buyer.dashboard'))->assertForbidden();
    }
}
