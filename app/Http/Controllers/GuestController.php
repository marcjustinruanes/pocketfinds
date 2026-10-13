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

        $sellerSold = $product['sold'] + collect($shopProducts)->sum('sold');

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
        return view('guest.product', compact('product', 'shop', 'related', 'shopProducts', 'announcement', 'sellerSold'));
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
        return view('guest.shop', compact('shop', 'items', 'slug', 'announcement'));
    }
}
