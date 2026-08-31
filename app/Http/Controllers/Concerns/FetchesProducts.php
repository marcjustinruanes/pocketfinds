<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\Review;

trait FetchesProducts
{
    private function supabaseUrl(?string $path): ?string
    {
        if (!$path) return null;
        return rtrim(config('filesystems.disks.supabase.url'), '/') . '/' . ltrim($path, '/');
    }

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

        $variations = collect($p->variations ?? [])->map(function ($variation) {
            $variation['options'] = collect($variation['options'] ?? [])->map(function ($option) {
                if (!empty($option['image'])) $option['image'] = $this->supabaseUrl($option['image']);
                return $option;
            })->all();
            return $variation;
        })->all();

        $variationImageUrls = collect($variations)
            ->flatMap(fn ($v) => collect($v['options'])->pluck('image')->filter())
            ->values();

        $coverImageUrls = !empty($p->images)
            ? collect($p->images)->map(fn ($path) => $this->supabaseUrl($path))
            : ($p->image ? collect([$this->supabaseUrl($p->image)]) : collect());

        $imageUrls = $coverImageUrls->merge($variationImageUrls)->unique()->values()->all();

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
            'video'       => $this->supabaseUrl($p->video),
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
