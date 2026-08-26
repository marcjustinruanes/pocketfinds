<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FetchesProducts;
use App\Models\Product;

class GuestController extends Controller
{
    use FetchesProducts;

    public function home()
    {
        $products = $this->dbProducts(12);
        return view('guest.home', compact('products'));
    }

    public function product($id)
    {
        $p = Product::with(['seller', 'category'])->where('id', $id)->where('status', 'active')->firstOrFail();
        $product = $this->mapProduct($p);

        $shopProducts = Product::with(['seller', 'category'])
            ->where('seller_id', $p->seller_id)->where('status', 'active')->where('id', '!=', $id)
            ->limit(6)->get()->map(fn($r) => $this->mapProduct($r))->all();

        $titleTerms = collect(preg_split('/[^\\pL\\pN]+/u', $p->name))
            ->filter(fn ($term) => mb_strlen($term) > 2)
            ->unique()
            ->take(5);
        $related = Product::with(['seller', 'category'])
            ->where('status', 'active')
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
        return view('guest.product', compact('product', 'shop', 'related', 'shopProducts'));
    }

    public function shop($slug)
    {
        $seller = \App\Models\User::where('username', $slug)->where('account_type', 'seller')->firstOrFail();
        $items  = Product::with(['seller', 'category'])
            ->where('seller_id', $seller->id)->where('status', 'active')
            ->get()->map(fn($p) => $this->mapProduct($p))->all();

        $shop = [
            'name'     => $seller->business_name ?? ($seller->given_names . ' ' . $seller->last_name),
            'initial'  => strtoupper(substr($seller->given_names, 0, 1)),
            'rating'   => 0,
            'products' => count($items),
            'sales'    => '0',
            'joined'   => $seller->created_at->format('M Y'),
            'desc'     => '',
        ];

        return view('guest.shop', compact('shop', 'items', 'slug'));
    }
}
