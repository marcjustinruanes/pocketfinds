@extends('buyer.layout')
@section('title', $shop['name'])
@section('page-title', 'Shop')
@section('page-sub', $shop['name'])

@section('content')

<a href="{{ url()->previous() }}" class="pd-back">
  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  Back
</a>

{{-- Shop hero --}}
<div class="sp-hero">
  <div class="sp-avatar">{{ $shop['initial'] }}</div>
  <div class="sp-info">
    <h1 class="sp-name">{{ $shop['name'] }}</h1>
    <div class="sp-rating-row">
      @for($i=1;$i<=5;$i++)
      <svg width="13" height="13" viewBox="0 0 24 24" fill="{{ $i<=round($shop['rating'])?'#f59e0b':'none' }}" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      @endfor
      <span class="sp-rating-val">{{ $shop['rating'] }}</span>
    </div>
    <p class="sp-desc">{{ $shop['desc'] }}</p>
  </div>
  <div class="sp-stats">
    <div class="sp-stat">
      <div class="sp-stat-val">{{ $shop['products'] }}</div>
      <div class="sp-stat-label">Products</div>
    </div>
    <div class="sp-stat">
      <div class="sp-stat-val">{{ $shop['sales'] }}</div>
      <div class="sp-stat-label">Sales</div>
    </div>
    <div class="sp-stat">
      <div class="sp-stat-val">{{ $shop['rating'] }}</div>
      <div class="sp-stat-label">Rating</div>
    </div>
    <div class="sp-stat">
      <div class="sp-stat-val">{{ $shop['joined'] }}</div>
      <div class="sp-stat-label">Joined</div>
    </div>
  </div>
</div>

{{-- Products --}}
<div class="sp-products-head">
  <span>All Products</span>
  <span class="sp-products-count">{{ count($items) }} item{{ count($items)!==1?'s':'' }}</span>
</div>

<div class="product-grid product-grid-lg">
  @forelse($items as $p)
  <div class="product-card" onclick="window.location='{{ route('buyer.product', $p['id']) }}'">
    <div class="product-img">
      @include('buyer.partials.icon', ['name' => $p['img'], 'size' => 36])
      @if($p['badge'])<span class="product-badge">{{ $p['badge'] }}</span>@endif
    </div>
    <div class="product-info">
      <div class="product-name">{{ $p['name'] }}</div>
      <div class="product-price">₱{{ number_format($p['price']) }}</div>
      <div class="product-rating">
        <span class="star-ic">@include('buyer.partials.icon',['name'=>'star','size'=>11])</span>
        {{ $p['rating'] }} · {{ number_format($p['sold']) }} sold
      </div>
      <div class="pc-actions">
        <button class="pc-act pc-act-cart" onclick="event.stopPropagation()" title="Add to Cart">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
        </button>
        <button class="pc-act pc-act-buy" onclick="event.stopPropagation()" title="Buy Now">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
      </div>
    </div>
  </div>
  @empty
  <div class="empty"><h3>No products yet</h3></div>
  @endforelse
</div>

@endsection
