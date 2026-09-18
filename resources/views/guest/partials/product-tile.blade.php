{{-- Product card shared by Today's Deals and Latest Listings. Whole card is
     clickable via a hidden cover-link (mouse/touch); the visible name stays a
     normal accessible link so keyboard/screen-reader users get a sanely-labeled
     tab stop. Wishlist and bag buttons reuse the same guest-auth flow as the
     header icons — clicking either opens sign-in, it doesn't fake an add. --}}
@php
  $stock = $p['stock'] ?? null;
@endphp
<div class="pfx-product-card pf-stagger-item">
  <a href="{{ route('guest.product', $p['id']) }}" class="pfx-product-cover-link" tabindex="-1" aria-hidden="true"></a>
  <div class="pfx-product-media">
    @if($p['img'])
      <img src="{{ $p['img'] }}" alt="{{ $p['name'] }}" loading="lazy">
    @else
      <svg class="pfx-placeholder-icon" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    @endif
    @if($p['is_new'] ?? false)<span class="pfx-badge pfx-badge-new">New</span>@endif
    @if(($p['badge'] ?? null) && !($p['is_new'] ?? false))<span class="pfx-badge pfx-badge-deal">{{ $p['badge'] }}</span>@endif
    <button type="button" class="pfx-product-wish" data-protected title="Add to wishlist" aria-label="Add {{ $p['name'] }} to wishlist">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
    </button>
  </div>
  <div class="pfx-product-body">
    <a href="{{ route('guest.product', $p['id']) }}" class="pfx-product-name-link">
      <div class="pfx-product-name">{{ $p['name'] }}</div>
    </a>
    <div class="pfx-product-price-row">
      <span class="pfx-product-price">₱{{ number_format($p['price']) }}</span>
      @if($p['old_price'] ?? null)<span class="pfx-product-old-price">₱{{ number_format($p['old_price']) }}</span>@endif
    </div>
    <div class="pfx-product-meta">
      @if(($p['rating'] ?? 0) > 0)
      <span>★ {{ number_format($p['rating'], 1) }}</span>
      <span class="sep">·</span>
      @endif
      <span>{{ ($p['sold'] ?? 0) >= 1000 ? round($p['sold']/1000,1).'k' : ($p['sold'] ?? 0) }} sold</span>
      @if($p['location'] ?? null)
      <span class="sep">·</span>
      <span>{{ $p['location'] }}</span>
      @endif
    </div>
    @if($stock !== null && $stock > 0 && $stock <= 10)
    <div class="pfx-stock-low">Only {{ $stock }} left</div>
    @endif
    <button type="button" class="pfx-product-bag" data-protected>
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      Add to Bag
    </button>
  </div>
</div>
