@php
  $route   = $route ?? 'buyer.product';
  $pLink   = route($route, $p['id']);
  $pImg    = $p['img'] ?? '';
  $pName   = $p['name'];
  $pPrice  = $p['price'];
  $pBadge  = $p['badge'] ?? '';
  $pLoc    = $p['location'] ?? '';
  $pRating = $p['rating'];
  $filterName = $filterName ?? null;
@endphp
<div class="product-card"@if($filterName !== null) data-product="{{ strtolower($filterName) }}"@endif onclick="window.location='{{ $pLink }}'">
  <div class="product-img">
    @if($pImg)
      <img src="{{ $pImg }}" alt="{{ $pName }}">
    @else
      <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".35">
        <rect x="3" y="3" width="18" height="18" rx="2"/>
        <circle cx="8.5" cy="8.5" r="1.5"/>
        <polyline points="21 15 16 10 5 21"/>
      </svg>
    @endif
    @if($pBadge)
      <span class="product-badge">{{ $pBadge }}</span>
    @endif
  </div>
  <div class="product-info">
    <div class="product-name">{{ $pName }}</div>
    <div class="product-price-row">
      <span class="product-price">₱{{ number_format($pPrice) }}</span>
      <span class="rating-pill">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        {{ $pRating > 0 ? number_format($pRating, 1) : 'New' }}
      </span>
    </div>
    @if($pLoc)
    <div class="product-location">
      <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
        <circle cx="12" cy="10" r="3"/>
      </svg>
      {{ $pLoc }}
    </div>
    @endif
    <div class="pc-actions" data-id="{{ $p['id'] }}" data-name="{{ $pName }}" data-price="{{ $pPrice }}" data-img="{{ $pImg }}">
      <button type="button" class="btn-cart" onclick="event.stopPropagation(); pcCart(this)" title="Add to Cart">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
        Cart
      </button>
      <button type="button" class="btn-buy" onclick="event.stopPropagation(); pcBuy(this)">Buy Now</button>
    </div>
  </div>
</div>
