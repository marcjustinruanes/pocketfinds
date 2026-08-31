@extends('buyer.layout')
@section('title', 'Browse Products')
@section('page-title', 'Browse Products')
@section('page-sub', 'Discover items from local sellers')

@section('content')
<form class="filter-bar" method="GET" action="{{ route('buyer.browse') }}">
  <div class="search-mini">
    <span class="ic">@include('buyer.partials.icon', ['name' => 'search', 'size' => 13])</span>
    <input type="text" name="q" placeholder="Search products…" value="{{ request('q') }}">
  </div>
  <select class="select" name="category" onchange="this.form.submit()">
    <option value="">All Categories</option>
    @foreach($categories as $cat)
      <option value="{{ $cat->id }}" {{ (string) request('category') === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
    @endforeach
  </select>
  <select class="select" name="sort" onchange="this.form.submit()">
    <option value="" {{ !request('sort') ? 'selected' : '' }}>Sort: Newest</option>
    <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
    <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
  </select>
  <button type="submit" class="btn btn-primary" style="display:none">Filter</button>
</form>

<div class="card">
  <div class="card-pad">
    <div class="product-grid product-grid-lg" id="browseGrid">
      @forelse($products as $p)
      @include('buyer.partials.product-card', ['p' => $p])
      @empty
      <div class="empty" style="grid-column:1/-1">
        @include('buyer.partials.icon', ['name' => 'bag', 'size' => 28])
        <h3>No products found</h3>
        <p>Try adjusting your search or filters.</p>
      </div>
      @endforelse
    </div>
  </div>
</div>
@endsection
