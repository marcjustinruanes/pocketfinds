<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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

    public function test_each_banner_renders_its_saved_crop(): void
    {
        Storage::fake('public');
        $data = $this->storefrontData();
        $data['heroSettings'] = new Setting([
            'hero_image' => 'hero_banners/primary.jpg',
            'hero_crop_x' => 20, 'hero_crop_y' => 75, 'hero_crop_zoom' => 130,
            'hero_2_crop_x' => 80, 'hero_2_crop_y' => 10, 'hero_2_crop_zoom' => 160,
        ]);
        $this->view('guest.home', $data)
            ->assertSee('object-position: 20% 75%; transform-origin: 20% 75%; transform: scale(1.3);', false)
            ->assertSee('object-position: 80% 10%; transform-origin: 80% 10%; transform: scale(1.6);', false);
    }

    public function test_crop_editor_loads_saved_values_for_the_correct_banner(): void
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $setting = new Setting(['hero_2_crop_x' => 80, 'hero_2_crop_y' => 10, 'hero_2_crop_zoom' => 160]);
        $this->blade('<x-hero-crop :setting="$setting" :slide="2" src="/image.jpg" />', compact('setting'))
            ->assertSee('name="hero_2_crop_x" data-crop-axis="x" type="range" min="0" max="100" step="1" value="80"', false)
            ->assertSee('value="160"', false)
            ->assertSee('Mobile')->assertSee('Reset crop');
    }

    public function test_hero_uses_beauty_image_as_second_slide_by_default(): void
    {
        Storage::fake('public');
        $data = $this->storefrontData();
        $data['heroSettings'] = new Setting(['hero_image' => 'hero_banners/primary.jpg']);

        $this->view('guest.home', $data)->assertSeeInOrder([
            'class="hero-slide active" src="'.Storage::url('hero_banners/primary.jpg').'"',
            'class="hero-slide " src="'.asset('images/hero-beauty.jpg').'"',
        ], false);
    }

    public function test_admin_second_image_replaces_default_and_excludes_stale_uploads(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('hero_banners/stale.jpg', 'unused');
        $data = $this->storefrontData();
        $data['heroSettings'] = new Setting([
            'hero_image' => 'hero_banners/primary.jpg',
            'hero_image_2' => 'hero_banners/second.jpg',
        ]);

        $this->view('guest.home', $data)->assertSeeInOrder([
            'class="hero-slide active" src="'.Storage::url('hero_banners/primary.jpg').'"',
            'class="hero-slide " src="'.Storage::url('hero_banners/second.jpg').'"',
        ], false)->assertDontSee('images/hero-beauty.jpg', false)
            ->assertDontSee('hero_banners/stale.jpg', false);
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
