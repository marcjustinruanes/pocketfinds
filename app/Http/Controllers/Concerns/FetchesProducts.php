<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Support\Facades\Storage;

trait FetchesProducts
{
    private function dbProducts(int $limit = 0, ?int $categoryId = null, ?string $search = null, ?string $sort = null)
    {
        $q = Product::with(['seller', 'category'])
            ->where('status', 'active')
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->when($search, fn($q) => $q->where('name', 'ilike', '%' . $search . '%'));

        $q = match ($sort) {
            'price_asc'  => $q->orderBy('price', 'asc'),
            'price_desc' => $q->orderBy('price', 'desc'),
            default      => $q->latest(),
        };

        $rows = $limit ? $q->limit($limit)->get() : $q->get();

        return $rows->map(fn($p) => $this->mapProduct($p))->values()->all();
    }

    private function mapProduct(Product $p): array
    {
        $sellerName = $p->seller->business_name
            ?? ($p->seller->given_names . ' ' . $p->seller->last_name);
        $sellerSlug = $p->seller->username ?? 'shop-' . $p->seller_id;

        $mun  = trim($p->seller->municipality ?? '');
        $prov = trim($p->seller->province ?? '');
        $location = trim($mun . ($mun && $prov ? ', ' : '') . $prov);

        $reviews = Review::with('buyer')->where('product_id', $p->id)->latest('created_at')->get();

        // Variation options may carry their own photo (e.g. one per color) —
        // resolve those to full URLs so both the swatch buttons and the cover
        // fallback below can use them.
        $variations = collect($p->variations ?? [])->map(function ($variation) {
            $variation['options'] = collect($variation['options'] ?? [])->map(function ($option) {
                if (!empty($option['image'])) $option['image'] = Storage::url($option['image']);
                return $option;
            })->all();
            return $variation;
        })->all();
        $variationImageUrls = collect($variations)
            ->flatMap(fn ($v) => collect($v['options'])->pluck('image')->filter())
            ->values();

        // The mini-picture gallery should include every photo the seller
        // uploaded for this product — the general product photos AND any
        // per-variation-option photos — not just whichever one happens to
        // be set, so the thumbnail rail always reflects everything shown.
        $coverImageUrls = !empty($p->images)
            ? collect($p->images)->map(fn ($path) => Storage::url($path))
            : ($p->image ? collect([Storage::url($p->image)]) : collect());

        $imageUrls = $coverImageUrls->merge($variationImageUrls)->unique()->values()->all();

        // A seller-set discount price is what the buyer actually pays; the
        // regular price is then shown struck through alongside a % off badge.
        $hasDiscount = $p->discount_price !== null && (float) $p->discount_price < (float) $p->price;
        $displayPrice = $hasDiscount ? (float) $p->discount_price : (float) $p->price;
        $percentOff = $hasDiscount ? (int) round((1 - ($p->discount_price / $p->price)) * 100) : 0;

        return [
            'id'          => $p->id,
            'name'        => $p->name,
            'seller'      => $sellerName,
            'seller_slug' => $sellerSlug,
            'seller_id'   => $p->seller_id,
            'location'    => $location,
            'price'       => $displayPrice,
            'old_price'   => $hasDiscount ? (float) $p->price : null,
            'badge'       => $hasDiscount ? '-' . $percentOff . '%' : null,
            'rating'      => $reviews->isNotEmpty() ? round($reviews->avg('rating'), 1) : 0,
            'sold'        => 0,
            'cat'         => $p->category->name ?? '—',
            'category_id' => $p->category_id,
            'img'         => $imageUrls[0] ?? null,
            'images'      => $imageUrls,
            'video'       => $p->video ? Storage::url($p->video) : null,
            'desc'        => $p->description ?? '',
            'sku'         => $p->sku,
            'specs'       => !empty($p->details)
                ? collect($p->details)->map(fn($d) => [$d['label'], $d['value']])->all()
                : ($p->sku ? [['SKU', $p->sku]] : []),
            'variants'    => !empty($p->variations)
                ? collect($p->variations)->mapWithKeys(fn($v) => [$v['name'] => collect($v['options'])->pluck('value')->all()])->all()
                : [],
            'variations'  => $variations,
            'stock'       => $p->total_stock,
            'restock_date'=> $p->restock_date?->format('M d, Y'),
            'reviews'     => $reviews->map(fn ($r) => [
                'name'   => $r->buyer?->given_names ?: 'Buyer',
                'rating' => $r->rating,
                'date'   => $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at)->format('M d, Y') : '',
                'text'   => $r->comment ?: '',
            ])->all(),
        ];
    }
}
