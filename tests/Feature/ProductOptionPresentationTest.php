<?php

namespace Tests\Feature;

use App\Http\Controllers\SellerController;
use Tests\TestCase;

class ProductOptionPresentationTest extends TestCase
{
    public function test_sizes_are_abbreviated_without_changing_custom_sizes(): void
    {
        foreach (['Small' => 'S', 'Medium' => 'M', 'Extra Large' => 'XL', '42' => '42'] as $value => $label) {
            $this->view('partials.product-option-content', [
                'option' => ['value' => (string) $value], 'groupName' => 'Size',
            ])->assertSee('>'.$label.'</span>', false);
        }
    }

    public function test_color_swatches_take_priority_over_photos_and_unknown_colors_keep_their_name(): void
    {
        $this->view('partials.product-option-content', [
            'option' => ['value' => 'Pink', 'color' => '#ffaacc', 'image' => '/storage/pink.webp'],
            'groupName' => 'Color',
        ])->assertSee('pd-option-swatch')->assertSee('#ffaacc')->assertDontSee('pd-option-image');

        $this->view('partials.product-option-content', [
            'option' => ['value' => 'Pink'], 'groupName' => 'Color',
        ])->assertSee('pd-option-swatch')->assertSee('#ffc0cb');

        $this->view('partials.product-option-content', [
            'option' => ['value' => 'Patterned', 'color' => 'red; background:url(test)'], 'groupName' => 'Color',
        ])->assertSee('Patterned')->assertDontSee('pd-option-swatch');
    }

    public function test_seller_preserves_valid_palette_metadata_and_discards_invalid_colors(): void
    {
        $controller = new SellerController;
        $method = new \ReflectionMethod($controller, 'applyVariationImagesAndPrices');
        $variations = [['name' => 'Color', 'options' => [
            ['value' => 'Pink', 'stock' => 3, 'color' => '#ffaacc', 'price' => 12.345],
            ['value' => 'Invalid', 'stock' => 0, 'color' => 'bad'],
        ]]];
        $args = [&$variations, []];
        $method->invokeArgs($controller, $args);
        $this->assertSame('#ffaacc', $variations[0]['options'][0]['color']);
        $this->assertSame(12.35, $variations[0]['options'][0]['price']);
        $this->assertArrayNotHasKey('color', $variations[0]['options'][1]);
    }
}
