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
      <div data-name="{{ strtolower($p['name']) }}" data-cat="{{ $p['cat'] }}">
        @include('buyer.partials.product-card', ['p' => $p])
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
