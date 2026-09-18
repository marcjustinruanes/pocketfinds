{{-- One product card used inside the "Shop by Category" showcase — reused for both
     the hero slot and the list rows (JS moves the same markup between slots, CSS
     styles it differently depending on which slot it currently sits in). --}}
@php
  $stock = $item['stock'] ?? null;
  $isOut = $stock !== null && $stock <= 0;
@endphp
<div class="pfx-showcase-item pf-stagger-item" data-showcase-item
     data-category-id="{{ $item['category_id'] }}"
     data-price="{{ $item['price'] }}"
     data-rating="{{ $item['rating'] }}"
     data-stock="{{ $stock ?? 0 }}">
  {{-- Full-card click target for mouse/touch, hidden from assistive tech; the
       real accessible link (with the product name as its label) lives below
       so keyboard/screen-reader users still get a sanely-labeled tab stop. --}}
  <a href="{{ route('guest.product', $item['id']) }}" class="pfx-showcase-cover-link" tabindex="-1" aria-hidden="true"></a>
  <span class="pfx-showcase-media">
    @if($item['img'])
      <img src="{{ $item['img'] }}" alt="{{ $item['name'] }}" loading="lazy">
    @else
      <svg class="pfx-placeholder-icon" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    @endif
    <span class="pfx-showcase-badges">
      @if($item['is_new'] ?? false)<span class="pfx-badge pfx-badge-new">New</span>@endif
      @if(($item['badge'] ?? null) && !($item['is_new'] ?? false))<span class="pfx-badge pfx-badge-deal">{{ $item['badge'] }}</span>@endif
    </span>
    <button type="button" class="pfx-showcase-wish" data-protected title="Add to wishlist" aria-label="Add {{ $item['name'] }} to wishlist">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
    </button>
  </span>
  <span class="pfx-showcase-body">
    <span class="pfx-showcase-seller">{{ $item['seller'] }}</span>
    <a href="{{ route('guest.product', $item['id']) }}" class="pfx-showcase-name-link">
      <span class="pfx-showcase-name">{{ $item['name'] }}</span>
    </a>
    @if(!empty($item['desc']))
      <span class="pfx-showcase-desc">{{ \Illuminate\Support\Str::limit($item['desc'], 120) }}</span>
    @endif
  </span>
  <span class="pfx-showcase-foot">
    <span class="pfx-showcase-price-row">
      <span class="pfx-showcase-price">₱{{ number_format($item['price']) }}</span>
      @if($item['old_price'] ?? null)<span class="pfx-product-old-price">₱{{ number_format($item['old_price']) }}</span>@endif
    </span>
    @if($isOut)
      <span class="pfx-showcase-stock is-out">Out of stock</span>
    @elseif($stock !== null && $stock <= 10)
      <span class="pfx-showcase-stock">Only {{ $stock }} left</span>
    @endif
    <button type="button" class="pfx-showcase-bag" data-protected>
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      Bag
    </button>
  </span>
</div>
