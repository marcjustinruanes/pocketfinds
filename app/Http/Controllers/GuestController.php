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
    use \App\Http\Controllers\Concerns\LoadsStorefront;

    public function home(Request $request)
    {
        return view('guest.home', $this->storefrontData($request));
    }

    /** Admin announcements are for logged-in users only — guests always see the generic
     *  welcome line, regardless of what's active in the admin Announcements panel. */
    private function guestAnnouncement()
    {
        return null;
    }

    public function categoryImage(Request $request, int $id)
    {
        $category = Category::select(['id', 'image_data', 'image_mime', 'image_sha256'])->findOrFail($id);
        abort_unless($category->image_data && $category->image_mime === 'image/jpeg', 404);
        $response = response($category->image_data, 200, [
            'Content-Type' => $category->image_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ])->setEtag($category->image_sha256);
        $response->isNotModified($request);
        return $response;
    }

    public function product($id)
    {
        $p = Product::with(['seller', 'category'])->where('id', $id)->where('status', 'active')->sellerApproved()->firstOrFail();
        $product = $this->mapProduct($p);

        $shopProducts = Product::with(['seller', 'category'])
            ->where('seller_id', $p->seller_id)->where('status', 'active')->sellerApproved()->where('id', '!=', $id)
            ->limit(6)->get()->map(fn($r) => $this->mapProduct($r))->all();

        $sellerProductIds = Product::where('seller_id', $p->seller_id)->pluck('id');
        $sellerSold = $sellerProductIds->sum(fn ($productId) => $this->soldCount($productId));
        $sellerFollowers = $p->seller->followers()->count();
        $sellerRating = Review::whereIn('product_id', $sellerProductIds)->avg('rating');
        $sellerReviewCount = Review::whereIn('product_id', $sellerProductIds)->count();

        $titleTerms = collect(preg_split('/[^\\pL\\pN]+/u', $p->name))
            ->filter(fn ($term) => mb_strlen($term) > 2)
            ->unique()
            ->take(5);
        $related = Product::with(['seller', 'category'])
            ->where('status', 'active')
            ->sellerApproved()
            ->where('id', '!=', $id)
            ->where('seller_id', '!=', $p->seller_id)
            ->when($p->category_id !== null, fn ($query) => $query->where('category_id', $p->category_id), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn($r) => $this->mapProduct($r))
            ->all();

        $shop = null;
        $announcement = $this->guestAnnouncement();
        return view('guest.product', compact('product', 'shop', 'related', 'shopProducts', 'announcement', 'sellerSold', 'sellerFollowers', 'sellerRating', 'sellerReviewCount'));
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
            'photo'     => $seller->shopPhotoUrl(),
            'rating'    => 0,
            'products'  => count($items),
            'sales'     => '0',
            'joined'    => $seller->created_at->format('M Y'),
            'desc'      => '',
            'followers' => $seller->followers()->count(),
        ];

        $announcement = $this->guestAnnouncement();
        $items=app(\App\Services\ShopCatalog::class)->filter($items,$seller->id,request('shop_category'));
        return view('guest.shop', compact('shop', 'items', 'slug', 'announcement','seller'));
    }
}
