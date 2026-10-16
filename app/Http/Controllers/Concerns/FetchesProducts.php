<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\Review;
use App\Models\StoreCategory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait FetchesProducts
{
    private function storeCategoryData(User $seller): array
    {
        $categories = StoreCategory::where('seller_id', $seller->id)->visible()->orderBy('position')->orderBy('id')->get();
        $selected = request()->query('store_category');
        $query = Product::with(['seller', 'category'])->where('seller_id', $seller->id)->where('status', 'active')->sellerApproved();
        if ($selected === 'uncategorized') {
            $query->whereNotIn('id', DB::table('store_category_products')->select('product_id'));
        } elseif ($selected !== null) {
            $category = $categories->firstWhere('id', $selected);
            abort_unless($category, 404);
            $query->whereIn('id', DB::table('store_category_products')->where('store_category_id', $category->id)->select('product_id'));
        }

        return [$categories, $query->get()->map(fn ($product) => $this->mapProduct($product))->all()];
    }

    private function productImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/storage/')) {
            return $path;
        }

        return rtrim(config('filesystems.disks.public.url'), '/').'/'.ltrim($path, '/');
    }

    /** Real units sold — summed straight from completed orders' line items
     *  (there's no order_items table; orders store their items as a json
     *  column), never a placeholder. */
    private function soldCount(string $productId): int
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return (int) DB::table('orders')->where('status', 'completed')->pluck('items')
                ->flatMap(fn ($items) => json_decode($items ?: '[]', true) ?: [])
                ->where('product_id', $productId)->sum('qty');
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

    private function dbProducts(int $limit = 0, ?int $categoryId = null, ?string $search = null, ?string $sort = null, ?float $minPrice = null, ?float $maxPrice = null, ?float $minRating = null, ?string $location = null)
    {
        $q = Product::with(['seller', 'category'])
            ->where('status', 'active')
            ->sellerApproved()
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($search !== null && trim($search) !== '', function ($q) use ($search) {
                foreach (preg_split('/\s+/u', trim($search)) as $term) {
                    $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$this->literalSearchPattern($term)]);
                }
            });
        $displayPrice = 'CASE WHEN discount_price IS NOT NULL AND discount_price < price THEN discount_price ELSE price END';
        if ($minPrice !== null) {
            $q->whereRaw($displayPrice.' >= ?', [$minPrice]);
        }
        if ($maxPrice !== null) {
            $q->whereRaw($displayPrice.' <= ?', [$maxPrice]);
        }
        if ($minRating !== null) {
            $q->whereRaw('(SELECT AVG(rating) FROM reviews WHERE reviews.product_id = products.id) >= ?', [$minRating]);
        }
        if ($location !== null && trim($location) !== '') {
            $pattern = $this->literalSearchPattern($location);
            $q->whereHas('seller', fn ($seller) => $seller->where(fn ($area) => $area
                ->whereRaw("LOWER(province) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(municipality) LIKE ? ESCAPE '!'", [$pattern])));
        }

        $q = match ($sort) {
            'price_asc' => $q->orderByRaw($displayPrice.' asc')->orderBy('id'),
            'price_desc' => $q->orderByRaw($displayPrice.' desc')->orderBy('id'),
            default => $q->latest()->orderBy('id'),
        };

        $rows = $limit ? $q->limit($limit)->get() : $q->get();

        $mapped = $rows->map(fn ($p) => $this->mapProduct($p));
        if ($sort === 'best_selling') {
            $mapped = $mapped->sortByDesc('sold');
        }

        return $mapped->values()->all();
    }

    private function literalSearchPattern(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($value))).'%';
    }

    private function productSuggestion(string $query, ?int $categoryId): ?string
    {
        $tokens = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: []);
        if (! $tokens || count($tokens) > 6) {
            return null;
        }
        $best = null;
        $bestScore = PHP_INT_MAX;
        $bestExtraWords = PHP_INT_MAX;
        $names = Product::where('status', 'active')->sellerApproved()->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))->orderBy('name')->limit(2000)->pluck('name');
        foreach ($names as $name) {
            $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($name)) ?: []);
            if (! $words) {
                continue;
            }
            $score = 0;
            foreach ($tokens as $token) {
                $distance = min(array_map(fn ($word) => levenshtein($token, $word), $words));
                if ($distance > (mb_strlen($token) < 4 ? 0 : 2)) {
                    $score = PHP_INT_MAX;
                    break;
                }
                $score += $distance;
            }
            $extraWords = max(0, count($words) - count($tokens));
            if ($score < $bestScore || ($score !== PHP_INT_MAX && $score === $bestScore && $extraWords < $bestExtraWords)) {
                $best = $name;
                $bestScore = $score;
                $bestExtraWords = $extraWords;
            }
        }

        return $best;
    }

    /** Real markdowns only — products whose seller actually set a discount_price below price.
     *  No fabricated "flash sale" countdown: the schema has no sale-window field, so this is
     *  just "currently discounted", sorted by the biggest real percentage off. */
    private function dbDeals(int $limit = 8, ?int $categoryId = null)
    {
        return Product::with(['seller', 'category'])
            ->where('status', 'active')
            ->sellerApproved()
            ->whereNotNull('discount_price')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->whereColumn('discount_price', '<', 'price')
            ->get()
            ->sortByDesc(fn ($p) => 1 - ((float) $p->discount_price / max((float) $p->price, 0.01)))
            ->take($limit)
            ->map(fn ($p) => $this->mapProduct($p))
            ->values()
            ->all();
    }

    private function mapProduct(Product $p): array
    {
        $sellerName = $p->seller->business_name
            ?? ($p->seller->given_names.' '.$p->seller->last_name);
        $sellerSlug = $p->seller->username ?? 'shop-'.$p->seller_id;

        $mun = trim($p->seller->municipality ?? '');
        $prov = trim($p->seller->province ?? '');
        $location = trim($mun.($mun && $prov ? ', ' : '').$prov);

        $reviews = Review::with('buyer')->where('product_id', $p->id)->latest('created_at')->get();

        $variations = collect($p->variations ?? [])->map(function ($variation) {
            $variation['options'] = collect($variation['options'] ?? [])->map(function ($option) {
                if (! empty($option['image'])) {
                    $option['image'] = $this->productImageUrl($option['image']);
                }

                return $option;
            })->all();

            return $variation;
        })->all();

        $variationImageUrls = collect($variations)
            ->flatMap(fn ($v) => collect($v['options'])->pluck('image')->filter())
            ->values();

        $coverImageUrls = ! empty($p->images)
            ? collect($p->images)->map(fn ($path) => $this->productImageUrl($path))
            : ($p->image ? collect([$this->productImageUrl($p->image)]) : collect());

        $axes = collect($p->variant_axes ?? [])->map(function ($axis) {
            $axis['options'] = collect($axis['options'])->map(function ($option) { if ($option['image'] ?? null) $option['image'] = $this->productImageUrl($option['image']); return $option; })->all();
            return $axis;
        })->all();
        $rows = collect($p->variant_rows ?? [])->map(function ($row) use ($p) { $option = $p->combinationOption('variant:'.$row['id']); $row['image'] = $this->productImageUrl($option['image'] ?? null); return $row; })->all();
        $axisImages = collect($axes)->flatMap(fn ($axis) => collect($axis['options'])->pluck('image'))->filter();
        $imageUrls = $coverImageUrls->merge($variationImageUrls)->merge($axisImages)->unique()->values()->all();

        $hasDiscount = empty($axes) && $p->discount_price !== null && (float) $p->discount_price < (float) $p->price;
        $displayPrice = $hasDiscount ? (float) $p->discount_price : (float) $p->price;
        $percentOff = $hasDiscount ? (int) round((1 - ($p->discount_price / $p->price)) * 100) : 0;

        return [
            'id' => $p->id,
            'name' => $p->name,
            'seller' => $sellerName,
            'seller_slug' => $sellerSlug,
            'seller_id' => $p->seller_id,
            'on_vacation' => (bool) $p->seller->is_on_vacation,
            'is_new' => $p->created_at?->gt(now()->subDays(14)) ?? false,
            'location' => $location,
            'price' => $rows ? (float)collect($rows)->min('price') : $displayPrice,
            'price_max' => $rows ? (float)collect($rows)->max('price') : $displayPrice,
            'variant_axes' => $axes, 'variant_rows' => $rows,
            'old_price' => $hasDiscount ? (float) $p->price : null,
            'badge' => $hasDiscount ? '-'.$percentOff.'%' : null,
            'rating' => $reviews->isNotEmpty() ? round($reviews->avg('rating'), 1) : 0,
            'sold' => $this->soldCount($p->id),
            'cat' => $p->category->name ?? 'Uncategorized',
            'category_id' => $p->category_id,
            'img' => $imageUrls[0] ?? null,
            'images' => $imageUrls,
            'video' => $this->productImageUrl($p->video),
            'desc' => $p->description ?? '',
            'sku' => $p->sku,
            'specs' => ! empty($p->details)
                ? collect($p->details)->map(fn ($d) => [$d['label'], $d['value']])->all()
                : ($p->sku ? [['SKU', $p->sku]] : []),
            'variants' => ! empty($p->variations)
                ? collect($p->variations)->mapWithKeys(fn ($v) => [$v['name'] => collect($v['options'])->pluck('value')->all()])->all()
                : [],
            'variations' => $variations,
            'stock' => $p->total_stock,
            'restock_date' => $p->restock_date?->format('M d, Y'),
            'reviews' => $reviews->map(fn ($r) => [
                'name' => $r->buyer?->given_names ?: 'Buyer',
                'rating' => $r->rating,
                'date' => $r->created_at ? Carbon::parse($r->created_at)->format('M d, Y') : '',
                'text' => $r->comment ?: '',
            ])->all(),
        ];
    }
}
