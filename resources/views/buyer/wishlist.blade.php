@extends('buyer.layout')
@section('title', 'Wishlist')
@section('page-title', 'Wishlist')
@section('page-sub', 'Your saved products')
@section('content')
<p><a href="{{ route('buyer.following') }}">Following stores</a></p>
<p id="wishlistStatus" role="status" aria-live="polite"></p>
<div class="product-grid product-grid-lg">
@forelse($entries as $entry)
  @php
    $listing = $entry->product;
    $unavailable = !$listing || $listing->status !== 'active' || $listing->seller?->status !== 'approved';
    $soldOut = !$unavailable && $listing->total_stock <= 0;
    $vacation = !$unavailable && $listing->seller->is_on_vacation;
  @endphp
  <article class="card" data-wishlist-entry="{{ $entry->product_id }}"><div class="card-pad">
    <h2>{{ $listing?->name ?? $entry->product_name }}</h2>
    @if($entry->image)<img src="{{ Storage::disk('public')->url($entry->image) }}" alt="" style="max-width:100%;height:130px;object-fit:contain">@endif
    <p>₱{{ number_format($listing?->discount_price ?? $listing?->price ?? $entry->price, 2) }}</p>
    @if($unavailable)<p role="status">Unavailable</p>
    @elseif($soldOut)<p role="status">Sold out</p>
    @elseif($vacation)<p role="status">On vacation</p>@endif
    @if(!$unavailable)<a href="{{ route('buyer.product', $entry->product_id) }}">View product</a>@endif
    @if(!$unavailable && ($soldOut || $vacation))<button type="button" disabled>Add to Cart</button>
    @elseif(!$unavailable)<a class="btn btn-outline" href="{{ route('buyer.product', $entry->product_id) }}#tab-options">Choose options / Add to Cart</a>@endif
    <button type="button" data-wishlist-product="{{ $entry->product_id }}" data-wishlist-url="{{ route('buyer.wishlist.save', $entry->product_id) }}" data-remove-entry="true" aria-pressed="true">♥ Saved to wishlist</button>
  </div></article>
@empty<p>Your wishlist is empty.</p>@endforelse
</div>
<script src="{{ asset('js/buyer-wishlist.js') }}" defer></script>
@endsection
