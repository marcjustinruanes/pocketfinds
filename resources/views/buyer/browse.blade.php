@extends('buyer.layout')
@section('title', 'Browse Products')
@section('page-title', 'Browse Products')
@section('page-sub', 'Discover items from local sellers')

@section('content')
<div class="filter-bar">
  <div class="search-mini">
    <span class="ic">🔍</span>
    <input type="text" placeholder="Search products…">
  </div>
  <select class="select">
    <option>All Categories</option>
    <option>Food & Drinks</option>
    <option>Clothing</option>
    <option>Beauty</option>
    <option>Electronics</option>
    <option>Home & Living</option>
    <option>Hobbies</option>
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
    <div class="product-grid product-grid-lg">
      @foreach(range(1,12) as $i)
      <div class="product-card">
        <div class="product-img">🛍</div>
        <div class="product-info">
          <div class="product-name">Product {{ $i }}</div>
          <div class="product-seller">by Sample Seller</div>
          <div class="product-price">₱ —</div>
          <div style="display:flex;gap:6px;margin-top:8px">
            <button class="btn btn-sm btn-outline" style="flex:1">Details</button>
            <button class="btn btn-sm btn-primary" style="flex:1">Add to Cart</button>
          </div>
        </div>
      </div>
      @endforeach
    </div>
    <div class="empty" style="display:none">
      <div class="ic">🛍</div>
      <h3>No products found</h3>
      <p>Try adjusting your search or filters.</p>
    </div>
  </div>
</div>
@endsection
