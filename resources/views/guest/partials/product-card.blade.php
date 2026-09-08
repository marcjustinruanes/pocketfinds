{{-- Fed by FetchesProducts::mapProduct() — every field here is real: price/discount from
     the product row, rating from actual Review records, "NEW" from real created_at recency.
     No sold-count badge: that field isn't tracked yet (mapProduct() returns 0 for it). --}}
<a href="{{ route('guest.product', $p['id']) }}" class="lp-product-card" data-product="{{ strtolower($p['name']) }}">
  <div class="lp-product-media">
    @if($p['img'])
      <img src="{{ $p['img'] }}" alt="{{ $p['name'] }}" loading="lazy">
    @endif
    @if($p['badge'])
      <span class="lp-product-tag lp-tag-off">{{ $p['badge'] }}</span>
    @elseif($p['is_new'])
      <span class="lp-product-tag lp-tag-new">NEW</span>
    @endif
    <button type="button" class="lp-heart" data-protected aria-label="Save to wishlist">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
    </button>
  </div>
  <div class="lp-product-info">
    @if($p['rating'] > 0)
    <div class="lp-product-rating">★ {{ $p['rating'] }} <span>({{ count($p['reviews']) }})</span></div>
    @endif
    <h3>{{ $p['name'] }}</h3>
    <div class="lp-price-row">
      <strong>₱{{ number_format($p['price'], 2) }}</strong>
      @if($p['old_price'])<del>₱{{ number_format($p['old_price'], 2) }}</del>@endif
    </div>
    @if($p['location'])
    <small class="lp-product-loc">📍 {{ $p['location'] }}</small>
    @endif
  </div>
</a>
