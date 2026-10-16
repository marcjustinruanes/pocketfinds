@extends('buyer.layout')
@section('title', 'Browse Products')
@section('page-title', 'Browse Products')
@section('page-sub', 'Discover items from local sellers')

@section('content')
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
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
    <option value="best_selling" @selected(request('sort') === 'best_selling')>Best selling</option>
  </select>
  <button type="submit" class="btn btn-primary" style="display:none">Filter</button>
  <label>Min price<input type="number" name="min_price" min="0" step="0.01" value="{{ request('min_price') }}"></label>
  <label>Max price<input type="number" name="max_price" min="0" step="0.01" value="{{ request('max_price') }}"></label>
  <label>Rating<select name="min_rating"><option value="">All ratings</option><option value="4" @selected(request('min_rating') == 4)>4 stars and up</option></select></label>
  <label>Location<input type="text" name="location" maxlength="120" value="{{ request('location') }}" placeholder="Province or city"></label>
  <a href="{{ route('buyer.browse') }}">Clear filters</a>
  <button type="submit" class="btn btn-primary">Apply filters</button>
</form>
@if(!empty($shops))
<section class="card" aria-label="Matching stores"><div class="card-pad"><h2>Matching stores</h2>
  @foreach($shops as $shop)<a class="pf-shop-name" href="{{ route('buyer.shop', $shop['slug']) }}">{{ $shop['name'] }}</a>@endforeach
</div></section>
@endif
@if($suggestion)<p>Did you mean <a href="{{ route('buyer.browse', ['q' => $suggestion]) }}">{{ $suggestion }}</a>?</p>@endif

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
@if($products->hasPages())
<nav aria-label="Product pages" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px">
  @if(!$products->onFirstPage())<a href="{{ $products->previousPageUrl() }}">Previous</a>@endif
  <span>Page {{ $products->currentPage() }} of {{ $products->lastPage() }}</span>
  @if($products->hasMorePages())<a href="{{ $products->nextPageUrl() }}">Next</a>@endif
</nav>
@endif
@endsection
