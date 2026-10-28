@extends('buyer.layout')
@section('title','My Wishlist')
@section('page-title','My Wishlist')
@section('content')
@include('commerce.messages')
<div class="card"><div class="card-head"><h2>Saved products</h2><a class="btn btn-outline" href="{{ route('buyer.browse') }}">Browse products</a></div><div class="card-pad">
<div class="product-grid-lg">@forelse($wishlist as $item)
<div>@if($p=$wishlistProducts->get($item->product_id))@include('buyer.partials.product-card',['p'=>$p])@else<div class="card-pad"><strong>{{ $item->name??'Unavailable product' }}</strong><p>This product is unavailable.</p></div>@endif
<form method="POST" action="{{ route('buyer.commerce.wishlist',$item->product_id) }}" style="margin-top:8px">@csrf<input type="hidden" name="saved" value="0"><button class="btn btn-outline">Remove from Wishlist</button></form></div>
@empty<div class="empty"><h3>Your wishlist is empty</h3><p>Save a product from its product page to find it here.</p></div>@endforelse
</div></div></div>
@endsection
