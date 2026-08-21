<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

trait FetchesProducts
{
    private function dbProducts(int $limit = 0, ?int $categoryId = null)
    {
        $q = Product::with(['seller', 'category'])
            ->where('status', 'active')
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->latest();

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

        return [
            'id'          => $p->id,
            'name'        => $p->name,
            'seller'      => $sellerName,
            'seller_slug' => $sellerSlug,
            'seller_id'   => $p->seller_id,
            'location'    => $location,
            'price'       => (float) $p->price,
            'old_price'   => null,
            'badge'       => null,
            'rating'      => 0,
            'sold'        => 0,
            'cat'         => $p->category->name ?? '—',
            'category_id' => $p->category_id,
            'img'         => $p->image ? Storage::url($p->image) : null,
            'desc'        => $p->description ?? '',
            'sku'         => $p->sku,
            'specs'       => !empty($p->details)
                ? collect($p->details)->map(fn($d) => [$d['label'], $d['value']])->all()
                : ($p->sku ? [['SKU', $p->sku]] : []),
            'variants'    => !empty($p->variations)
                ? collect($p->variations)->mapWithKeys(fn($v) => [$v['name'] => collect($v['options'])->pluck('value')->all()])->all()
                : [],
            'variations'  => $p->variations ?? [],
            'stock'       => $p->total_stock,
            'reviews'     => [],
        ];
    }
}
