<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopFollow;
use App\Models\User;
use App\Models\WishlistEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuyerSavedController extends Controller
{
    public function index(Request $request)
    {
        $entries = WishlistEntry::with('product.seller')->where('buyer_id', $request->user()->id)->latest()->get();

        return view('buyer.wishlist', compact('entries'));
    }

    public function save(Request $request, string $product)
    {
        $data = $request->validate(['saved' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $product, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $query = WishlistEntry::where('buyer_id', $request->user()->id)->where('product_id', $product);
            if (! $data['saved']) {
                $query->delete();

                return;
            }
            $listing = Product::with('seller')->whereKey($product)->where('status', 'active')->sellerApproved()->firstOrFail();
            WishlistEntry::updateOrCreate(['buyer_id' => $request->user()->id, 'product_id' => $product], [
                'product_name' => $listing->name, 'image' => $listing->image,
                'price' => $listing->discount_price !== null && $listing->discount_price < $listing->price ? $listing->discount_price : $listing->price,
            ]);
        }, 3);

        return response()->json(['saved' => (bool) $data['saved']]);
    }

    public function following(Request $request)
    {
        $follows = ShopFollow::with('seller')->where('buyer_id', $request->user()->id)->get();

        return view('buyer.following', compact('follows'));
    }
}
