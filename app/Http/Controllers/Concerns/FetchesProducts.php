<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

trait FetchesProducts
{
    private function productImageUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/storage/')) return $path;
        return rtrim(config('filesystems.disks.public.url'), '/') . '/' . ltrim($path, '/');
    }

    /** Real units sold — summed straight from completed orders' line items
     *  (there's no order_items table; orders store their items as a json
     *  column), never a placeholder. */
    private function soldCount(string $productId): int
    {
        if(DB::connection()->getDriverName()==='sqlite') {
            $row=DB::selectOne("SELECT COALESCE(SUM(json_extract(item.value, '$.qty')),0) AS total FROM orders JOIN json_each(COALESCE(orders.items,'[]')) AS item WHERE orders.status='completed' AND json_extract(item.value,'$.product_id')=?",[$productId]);
            return (int)($row->total??0);
        }
        $row = DB::selectOne("
                SELECT COALESCE(SUM(item.qty), 0) AS total
                FROM orders
                JOIN JSON_TABLE(COALESCE(orders.items, JSON_ARRAY()), '$[*]' COLUMNS (
                    product_id VARCHAR(36) PATH '$.product_id',
                    qty INT PATH '$.qty'
                )) AS item ON TRUE
                WHERE orders.status = 'completed' AND item.product_id = ?
            ", [$productId]);

        return (int) ($row->total ?? 0);
    }

    private function dbProducts(int $limit = 0, ?int $categoryId = null, ?string $search = null, ?string $sort = null, ?float $minPrice = null, ?float $maxPrice = null)
    {
        $q = Product::with(['seller', 'category'])
            ->where('status', 'active')
            ->sellerApproved()
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->when($search, fn($q) => $q->whereLike('name', '%' . $search . '%'));
        $displayPrice = 'CASE WHEN discount_price IS NOT NULL AND discount_price < price THEN discount_price ELSE price END';
        if ($minPrice !== null) $q->whereRaw($displayPrice.' >= ?', [$minPrice]);
        if ($maxPrice !== null) $q->whereRaw($displayPrice.' <= ?', [$maxPrice]);

        $q = match ($sort) {
            'price_asc'  => $q->orderByRaw($displayPrice.' asc')->orderBy('id'),
            'price_desc' => $q->orderByRaw($displayPrice.' desc')->orderBy('id'),
            default      => $q->latest(),
        };

        $rows = $limit ? $q->limit($limit)->get() : $q->get();

        return $rows->map(fn($p) => $this->mapProduct($p))->values()->all();
    }

    /** Deals share the existing gallery: regular markdowns and scheduled flash sales. */
    private function dbDeals(int $limit = 8, ?int $categoryId = null)
    {
        $flashProducts=\App\Services\BuyerCommerce::installed()?\Illuminate\Support\Facades\DB::table('commerce_flash_sales')->where('is_active',true)->where('ends_at','>',now())->pluck('product_id'):collect();
        return Product::with(['seller', 'category'])
            ->where('status', 'active')
            ->sellerApproved()
            ->whereHas('seller',fn($query)=>$query->when(\App\Services\BuyerCommerce::installed(),fn($q)=>$q->where('is_on_vacation',false)))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->where(fn($q)=>$q->whereColumn('discount_price','<','price')->orWhereIn('id',$flashProducts))
            ->get()
            ->sortByDesc(fn ($p) => $flashProducts->contains($p->id)?2:1 - ((float) $p->discount_price / max((float) $p->price, 0.01)))
            ->take($limit)
            ->map(fn ($p) => $this->mapProduct($p))
            ->values()
            ->all();
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
                if (!empty($option['image'])) $option['image'] = $this->productImageUrl($option['image']);
                return $option;
            })->all();
            return $variation;
        })->all();

        $variationImageUrls = collect($variations)
            ->flatMap(fn ($v) => collect($v['options'])->pluck('image')->filter())
            ->values();

        $coverImageUrls = !empty($p->images)
            ? collect($p->images)->map(fn ($path) => $this->productImageUrl($path))
            : ($p->image ? collect([$this->productImageUrl($p->image)]) : collect());

        $imageUrls = $coverImageUrls->merge($variationImageUrls)->unique()->values()->all();

        $hasDiscount = $p->discount_price !== null && (float) $p->discount_price < (float) $p->price;
        $displayPrice = $hasDiscount ? (float) $p->discount_price : (float) $p->price;
        $flash = app(\App\Services\BuyerCommerce::class)->flash($p);
        $scheduledFlash=(!$flash&&\App\Services\BuyerCommerce::installed())?\Illuminate\Support\Facades\DB::table('commerce_flash_sales')->where('product_id',$p->id)->where('is_active',true)->where('starts_at','>',now())->orderBy('starts_at')->first():null;
        if($flash) $displayPrice=(float)$flash->price;
        $percentOff = $hasDiscount ? (int) round((1 - ($p->discount_price / $p->price)) * 100) : 0;

        return [
            'id'          => $p->id,
            'on_vacation' => (bool)$p->seller->is_on_vacation,
            'flash_sale' => $flash ? ['id'=>$flash->id,'ends_at'=>\Illuminate\Support\Carbon::parse($flash->ends_at)->toIso8601String(),'remaining'=>$flash->remaining] : null,
            'scheduled_flash' => $scheduledFlash?['id'=>$scheduledFlash->id,'starts_at'=>\Illuminate\Support\Carbon::parse($scheduledFlash->starts_at)->toIso8601String(),'price'=>(float)$scheduledFlash->price]:null,
            'name'        => $p->name,
            'seller'      => $sellerName,
            'seller_slug' => $sellerSlug,
            'seller_id'   => $p->seller_id,
            'is_new'      => $p->created_at?->gt(now()->subDays(14)) ?? false,
            'location'    => $location,
            'price'       => $displayPrice,
            'old_price'   => $hasDiscount ? (float) $p->price : null,
            'badge'       => $hasDiscount ? '-' . $percentOff . '%' : null,
            'rating'      => $reviews->isNotEmpty() ? round($reviews->avg('rating'), 1) : 0,
            'sold'        => $this->soldCount($p->id),
            'cat'         => $p->category->name ?? 'Uncategorized',
            'category_id' => $p->category_id,
            'img'         => $imageUrls[0] ?? null,
            'images'      => $imageUrls,
            'video'       => $this->productImageUrl($p->video),
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
                'name'   => $r->buyer?->given_names ? mb_substr($r->buyer->given_names,0,1).'***' : 'Buyer',
                'photos' => array_map(fn($path)=>\Illuminate\Support\Facades\Storage::disk('public')->url($path),$r->photos??[]),
                'rating' => $r->rating,
                'date'   => $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at)->format('M d, Y') : '',
                'text'   => $r->comment ?: '',
            ])->all(),
        ];
    }
}
