{{-- Exact copy of the guest-homepage product card (guest.home) so product listings
     look identical wherever they appear on the guest side. --}}
<a class="pf-product-card" href="{{ route('guest.product', $p['id']) }}">
  <div class="pf-product-media">
    @if($p['img'])
      <img src="{{ $p['img'] }}" alt="{{ $p['name'] }}" loading="lazy">
    @else
      <svg class="placeholder-icon" xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    @endif
    @if($p['is_new'] ?? false)<span class="pf-badge pf-badge-new">New</span>@endif
    @if(($p['badge'] ?? null) && !($p['is_new'] ?? false))<span class="pf-badge pf-badge-deal">{{ $p['badge'] }}</span>@endif
  </div>
  <div class="pf-product-body">
    <div class="pf-product-name">{{ $p['name'] }}</div>
    <div>
      <span class="pf-product-price">₱{{ number_format($p['price']) }}</span>
      @if($p['old_price'] ?? null)<span class="pf-product-old-price">₱{{ number_format($p['old_price']) }}</span>@endif
    </div>
    {{-- Sold always shown (0 if none); rating only once a buyer has actually left one --}}
    <div class="pf-product-pill">
      @if(($p['rating'] ?? 0) > 0)
      <span class="pf-pill-rating">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        {{ number_format($p['rating'], 1) }}
      </span>
      <span class="pf-pill-sep"></span>
      @endif
      <span class="pf-pill-sold">{{ ($p['sold'] ?? 0) >= 1000 ? round($p['sold']/1000,1).'k' : ($p['sold'] ?? 0) }} sold</span>
    </div>
    @if($p['location'] ?? null)
    <div class="pf-product-loc">
      <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
      {{ $p['location'] }}
    </div>
    @endif
  </div>
</a>
