@php
  $route   = $route ?? 'buyer.product';
  $pLink   = route($route, $p['id']);
  $pImg    = $p['img'] ?? '';
  $pName   = $p['name'];
  $pPrice  = $p['price'];
  $pOldPrice = $p['old_price'] ?? null;
  $pBadge  = $p['badge'] ?? '';
  $pLoc    = $p['location'] ?? '—';
  $pRating = $p['rating'];
  $pSold   = $p['sold'];
  $filterName = $filterName ?? null;
@endphp
<div class="product-card"@if($filterName !== null) data-product="{{ strtolower($filterName) }}"@endif onclick="window.location='{{ $pLink }}'">
  <div class="product-img">
    @if($pImg)
      <img src="{{ $pImg }}" alt="{{ $pName }}" style="width:100%;height:100%;object-fit:cover">
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
    <div class="pc-price-row">
      <span class="product-price">₱{{ number_format($pPrice) }}</span>
      @if($pOldPrice)
        <span style="font-size:11px;color:var(--muted);text-decoration:line-through">₱{{ number_format($pOldPrice) }}</span>
      @endif
      @if($pRating > 0 || $pSold > 0)
      <span class="pc-meta pc-stat-capsule">
        @if($pRating > 0)
          <span class="star-ic"><svg width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
          {{ number_format($pRating, 1) }}
        @endif
        @if($pRating > 0 && $pSold > 0)
          <span class="pc-sep">|</span>
        @endif
        @if($pSold > 0)
          {{ number_format($pSold) }} sold
        @endif
      </span>
      @endif
    </div>
    <div class="product-location">
      <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
        <circle cx="12" cy="10" r="3"/>
      </svg>
      {{ $pLoc }}
    </div>
  </div>
</div>
