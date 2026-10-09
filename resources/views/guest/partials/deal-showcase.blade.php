@if(count($deals))
<div class="pf-deal-gallery-frame">
<div class="pf-deal-showcase pf-deal-accordion" data-deal-showcase aria-label="Explore deals">
  @foreach($deals as $p)
  <article class="pf-deal-fold {{ $loop->first ? 'is-active' : '' }}" data-deal-panel="{{ $loop->index }}">
    @if($p['img'])<img class="pf-deal-fold-photo" src="{{ $p['img'] }}" alt="{{ $p['name'] }}" loading="lazy" decoding="async">@endif
    <button type="button" class="pf-deal-fold-toggle" data-deal-select="{{ $loop->index }}" data-deal-category-id="{{ $p['category_id'] }}" data-deal-price="{{ $p['price'] }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="deal-details-{{ $loop->index }}" aria-label="Preview {{ $p['name'] }}">
      <span class="pf-deal-fold-title">{{ $p['name'] }}</span>
      <span class="pf-deal-fold-number" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
    </button>
    <div class="pf-deal-fold-details" id="deal-details-{{ $loop->index }}" @if(!$loop->first) hidden @endif>
      @if($p['badge'] ?? null)<span class="pf-deal-fold-badge">{{ $p['badge'] }}</span>@endif
      <div class="pf-deal-fold-prices"><strong>₱{{ number_format($p['price'], 2) }}</strong>
        @if($p['old_price'] ?? null)<del>₱{{ number_format($p['old_price'], 2) }}</del>@endif
      </div>
      <a class="pf-deal-fold-link" href="{{ route('guest.product', $p['id']) }}">View product <span aria-hidden="true">↗</span></a>
    </div>
  </article>
  @endforeach
</div>
<div class="pf-deal-gallery-controls">
  <span data-deal-position aria-live="polite">1 / {{ count($deals) }}</span>
</div>
</div>
<p class="pf-deal-empty" data-deal-filter-empty hidden role="status">No deals match these filters right now.</p>
@else
<p class="pf-deal-empty">No deals right now — check back soon.</p>
@endif
