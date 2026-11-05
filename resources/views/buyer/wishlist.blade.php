@extends('buyer.layout')
@section('title','My Wishlist')
@section('page-title','My Wishlist')
@push('head')
<style>
.wishlist-products{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:16px}
.wishlist-products .product-card{display:grid;grid-template-columns:120px minmax(0,1fr);height:100%;border-radius:10px;overflow:hidden}
.wishlist-products .product-card .product-img{width:120px;height:100%;min-height:150px;aspect-ratio:auto;border-radius:0}
.wishlist-products .product-card .product-img img{width:100%;height:100%;object-fit:cover}
.wishlist-products .product-info{min-width:0;padding:14px}
.wishlist-products .product-price-row{flex-wrap:wrap;gap:8px}
.wishlist-products .product-name{font-size:13px}
</style>
@endpush
@section('content')
@include('commerce.messages')
<div class="card"><div class="card-head"><h2>Saved products</h2><a class="btn btn-outline" href="{{ route('buyer.browse') }}">Browse products</a></div><div class="card-pad">
<div class="wishlist-products">@forelse($wishlist as $item)
<div>@if($p=$wishlistProducts->get($item->product_id))@include('buyer.partials.product-card',['p'=>$p])@else<div class="card-pad"><strong>{{ $item->name??'Unavailable product' }}</strong><p>This product is unavailable.</p></div>@endif
<form method="POST" action="{{ route('buyer.commerce.wishlist',$item->product_id) }}" style="margin-top:8px">@csrf<input type="hidden" name="saved" value="0"><button class="btn btn-outline">Remove from Wishlist</button></form></div>
@empty<div class="empty"><h3>Your wishlist is empty</h3><p>Save a product from its product page to find it here.</p></div>@endforelse
</div></div></div>
@endsection
