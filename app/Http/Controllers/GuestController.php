<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FetchesProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    use FetchesProducts;

    public function home(Request $request)
    {
        $categoryId = $request->integer('category') ?: null;
        $search     = $request->string('q')->trim()->value() ?: null;

        $products   = $this->dbProducts(24, $categoryId, $search);
        $deals      = $categoryId || $search ? [] : $this->dbDeals(8);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $activeCategory = $categoryId ? $categories->firstWhere('id', $categoryId) : null;

        $announcement = $this->guestAnnouncement();
        $shops = $this->featuredShops(3);

        $stats = [
            'products' => Product::where('status', 'active')->sellerApproved()->count(),
            'shops'    => User::where('account_type', 'seller')->where('status', 'approved')->whereNotNull('business_name')->count(),
            'categories' => $categories->count(),
        ];

        return view('guest.home', compact('products', 'deals', 'categories', 'activeCategory', 'announcement', 'shops', 'stats', 'categoryId', 'search'));
    }

    /** Admin announcements are for logged-in users only — guests always see the generic
     *  welcome line, regardless of what's active in the admin Announcements panel. */
    private function guestAnnouncement()
    {
        return null;
    }

    /** Approved sellers with the most live listings — real product counts and real average
     *  ratings across their catalog (null, not a fabricated number, when nobody's reviewed them yet). */
    private function featuredShops(int $limit = 3)
    {
        return User::where('account_type', 'seller')->where('status', 'approved')->whereNotNull('business_name')
            ->withCount(['products' => fn ($q) => $q->where('status', 'active')])
            ->get()
            ->filter(fn (User $seller) => $seller->products_count > 0)
            ->sortByDesc('products_count')
            ->take($limit)
            ->map(function (User $seller) {
                $productIds = $seller->products()->where('status', 'active')->pluck('id');
                $avgRating  = $productIds->isNotEmpty() ? Review::whereIn('product_id', $productIds)->avg('rating') : null;

                return [
                    'name'          => $seller->business_name,
                    'slug'          => $seller->username ?: ('shop-' . $seller->id),
                    'initial'       => strtoupper(substr($seller->business_name, 0, 1)),
                    'rating'        => $avgRating ? round($avgRating, 1) : null,
                    'products_count'=> $seller->products_count,
                    'joined'        => $seller->created_at->format('M Y'),
                ];
            })
            ->values()
            ->all();
    }

    public function product($id)
    {
        $p = Product::with(['seller', 'category'])->where('id', $id)->where('status', 'active')->sellerApproved()->firstOrFail();
        $product = $this->mapProduct($p);

        $shopProducts = Product::with(['seller', 'category'])
            ->where('seller_id', $p->seller_id)->where('status', 'active')->sellerApproved()->where('id', '!=', $id)
            ->limit(6)->get()->map(fn($r) => $this->mapProduct($r))->all();

        $titleTerms = collect(preg_split('/[^\\pL\\pN]+/u', $p->name))
            ->filter(fn ($term) => mb_strlen($term) > 2)
            ->unique()
            ->take(5);
        $related = Product::with(['seller', 'category'])
            ->where('status', 'active')
            ->sellerApproved()
            ->where('id', '!=', $id)
            ->when($titleTerms->isNotEmpty(), function ($query) use ($titleTerms) {
                $query->where(function ($matches) use ($titleTerms) {
                    foreach ($titleTerms as $term) {
                        $matches->orWhere('name', 'like', '%' . $term . '%');
                    }
                });
            }, fn ($query) => $query->whereRaw('1 = 0'))
            ->limit(8)
            ->get()
            ->map(fn($r) => $this->mapProduct($r))
            ->all();

        $shop = null;
        $announcement = $this->guestAnnouncement();
        return view('guest.product', compact('product', 'shop', 'related', 'shopProducts', 'announcement'));
    }

    public function shop($slug)
    {
        $seller = \App\Models\User::where('username', $slug)->where('account_type', 'seller')->firstOrFail();
        $items  = Product::with(['seller', 'category'])
            ->where('seller_id', $seller->id)->where('status', 'active')->sellerApproved()
            ->get()->map(fn($p) => $this->mapProduct($p))->all();

        $shop = [
            'name'      => $seller->business_name ?? ($seller->given_names . ' ' . $seller->last_name),
            'initial'   => strtoupper(substr($seller->given_names, 0, 1)),
            'rating'    => 0,
            'products'  => count($items),
            'sales'     => '0',
            'joined'    => $seller->created_at->format('M Y'),
            'desc'      => '',
            'followers' => $seller->followers()->count(),
        ];

        $announcement = $this->guestAnnouncement();
        return view('guest.shop', compact('shop', 'items', 'slug', 'announcement'));
    }
}
