<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;

trait LoadsStorefront
{
    protected function storefrontData(Request $request)
    {
        $categoryId = $request->integer('category') ?: null;
        $browseCategoryId = $request->integer('browse_category') ?: null;
        $productCategoryId = $request->has('category') ? $categoryId : $browseCategoryId;
        $search     = $request->string('q')->trim()->value() ?: null;
        $priceFilters = $request->validate([
            'min_price' => 'nullable|numeric|min:0|max:1000000000',
            'max_price' => 'nullable|numeric|min:0|max:1000000000'.($request->filled('min_price') ? '|gte:min_price' : ''),
        ]);
        $minPrice = isset($priceFilters['min_price']) ? (float) $priceFilters['min_price'] : null;
        $maxPrice = isset($priceFilters['max_price']) ? (float) $priceFilters['max_price'] : null;
        $sort = in_array($request->input('sort'), ['newest', 'price_asc', 'price_desc'], true) ? $request->input('sort') : 'newest';

        // Fallback object so the hero banner always has safe defaults even when DB is down
        $heroSettings = new \App\Models\Setting([
            'hero_image'    => null,
            'hero_tagline'  => 'Find It. Love It. Pocket It.',
            'hero_subtitle' => 'Discover everyday finds that fit your needs and budget. Simple, convenient shopping—all in one place.',
            'hero_cta_text' => 'Browse Products',
            'hero_overlay'  => 'dark',
        ]);

        try {
            $heroSettings   = \App\Models\Setting::current();
            $products       = $this->dbProducts(24, $productCategoryId, $search, $sort, $minPrice, $maxPrice);
            $deals          = $this->dbDeals(8, $browseCategoryId);
            $categories     = Category::withCount(['products' => fn ($q) => $q->where('status', 'active')->sellerApproved()])
                ->orderBy('name')->get(['id', 'name', 'image_sha256']);
            $activeCategory = $productCategoryId ? $categories->firstWhere('id', $productCategoryId) : null;
            $announcement   = null;
            $shops          = $this->topSellingShops(null, $browseCategoryId);
            $stats          = [
                'products'   => Product::where('status', 'active')->sellerApproved()->count(),
                'shops'      => User::where('account_type', 'seller')->where('status', 'approved')->whereNotNull('business_name')->count(),
                'categories' => $categories->count(),
            ];
            $dbError = false;
        } catch (\Exception $e) {
            report($e);
            $products       = [];
            $deals          = [];
            $categories     = collect();
            $activeCategory = null;
            $announcement   = null;
            $shops          = [];
            $stats          = ['products' => 0, 'shops' => 0, 'categories' => 0];
            $dbError        = true;
        }

        return compact(
            'products', 'deals', 'categories', 'activeCategory',
            'announcement', 'shops', 'stats', 'categoryId', 'search',
            'heroSettings', 'dbError', 'minPrice', 'maxPrice', 'browseCategoryId', 'productCategoryId', 'sort'
        );
    }

    /** Approved sellers ranked by real units sold (completed orders only) — not by
     *  listing count. Ties (commonly 0 sold, for a young marketplace) keep a stable,
     *  deterministic order rather than looking shuffled. Real average ratings across
     *  their catalog too (null, not a fabricated number, when nobody's reviewed them yet). */
    private function topSellingShops(?int $limit = null, ?int $categoryId = null)
    {
        return User::where('account_type', 'seller')->where('status', 'approved')->whereNotNull('business_name')
            ->when($categoryId, fn ($q) => $q->whereHas('products', fn ($products) => $products->where('status', 'active')->where('category_id', $categoryId)))
            ->withCount(['products' => fn ($q) => $q->where('status', 'active')])
            ->get()
            ->filter(fn (User $seller) => $seller->products_count > 0)
            ->map(function (User $seller) {
                $productIds = $seller->products()->where('status', 'active')->pluck('id');
                $avgRating  = $productIds->isNotEmpty() ? Review::whereIn('product_id', $productIds)->avg('rating') : null;
                $soldTotal  = $productIds->reduce(fn ($carry, $id) => $carry + $this->soldCount($id), 0);

                return [
                    'name'          => $seller->business_name,
                    'slug'          => $seller->username ?: ('shop-' . $seller->id),
                    'initial'       => strtoupper(substr($seller->business_name, 0, 1)),
                    'photo'         => $seller->shopPhotoUrl(),
                    'rating'        => $avgRating ? round($avgRating, 1) : null,
                    'sold'          => $soldTotal,
                    'products_count'=> $seller->products_count,
                    'joined'        => $seller->created_at->format('M Y'),
                ];
            })
            ->sortByDesc('sold')
            ->when($limit !== null, fn ($shops) => $shops->take($limit))
            ->values()
            ->all();
    }

}
