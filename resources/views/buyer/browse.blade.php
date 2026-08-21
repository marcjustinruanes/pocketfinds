@extends('buyer.layout')
@section('title', 'Browse Products')
@section('page-title', 'Browse Products')
@section('page-sub', 'Discover items from local sellers')

@section('content')
<div class="filter-bar">
  <div class="search-mini">
    <span class="ic">@include('buyer.partials.icon', ['name' => 'search', 'size' => 13])</span>
    <input type="text" id="browseSearch" placeholder="Search products…" oninput="filterBrowse()">
  </div>
  <select class="select" id="browseCategory" onchange="filterBrowse()">
    <option value="">All Categories</option>
    <option>Food & Drinks</option>
    <option>Clothing</option>
    <option>Beauty</option>
    <option>Electronics</option>
    <option>Home & Living</option>
    <option>Hobbies</option>
    <option>Sports</option>
  </select>
  <select class="select">
    <option>Sort: Newest</option>
    <option>Price: Low to High</option>
    <option>Price: High to Low</option>
    <option>Most Popular</option>
  </select>
</div>

<div class="card">
  <div class="card-pad">
    <div class="product-grid product-grid-lg" id="browseGrid">
      @foreach($products as $p)
      <div class="product-card" onclick="window.location='{{ route('buyer.product', $p['id']) }}'" data-name="{{ strtolower($p['name']) }}" data-cat="{{ $p['cat'] }}">
        <div class="product-img">
          @include('buyer.partials.icon', ['name' => $p['img'], 'size' => 36])
          @if($p['badge'])<span class="product-badge">{{ $p['badge'] }}</span>@endif
        </div>
        <div class="product-info">
          <div class="product-name">{{ $p['name'] }}</div>
          <a class="product-seller" href="{{ route('buyer.shop', $p['seller_slug']) }}" onclick="event.stopPropagation()">by {{ $p['seller'] }}</a>
          <div class="product-price">₱{{ number_format($p['price']) }}</div>
          <div class="product-rating"><span class="star-ic">@include('buyer.partials.icon',['name'=>'star','size'=>11])</span> {{ $p['rating'] }} · {{ number_format($p['sold']) }} sold</div>
          <div class="pc-actions">
            <button class="pc-act pc-act-cart" onclick="event.stopPropagation();openCart('{{ addslashes($p['name']) }}',{{ $p['price'] }},{{ json_encode($p['variants']['color']) }},{{ json_encode($p['variants']['size']) }},false,this,'{{ $p['id'] }}','{{ $p['img'] }}')" title="Add to Cart">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
            </button>
            <button class="pc-act pc-act-buy" onclick="event.stopPropagation();openCart('{{ addslashes($p['name']) }}',{{ $p['price'] }},{{ json_encode($p['variants']['color']) }},{{ json_encode($p['variants']['size']) }},true,this,'{{ $p['id'] }}','{{ $p['img'] }}')" title="Buy Now">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            </button>
          </div>
        </div>
      </div>
      @endforeach
    </div>
    <div class="empty" id="browseEmpty" style="display:none">
      <div class="ic">@include('buyer.partials.icon', ['name' => 'bag', 'size' => 28])</div>
      <h3>No products found</h3>
      <p>Try adjusting your search or filters.</p>
    </div>
  </div>
</div>
@endsection
