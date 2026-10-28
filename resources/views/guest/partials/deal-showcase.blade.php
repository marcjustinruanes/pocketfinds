@if(($buyerMode??false)&&session('success'))<p role="status">{{ session('success') }}</p>@endif
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
      <h3 class="pf-deal-product-name">{{ $p['name'] }}</h3>
      @if($p['badge'] ?? null)<span class="pf-deal-fold-badge">{{ $p['badge'] }}</span>@endif
      @if($p['flash_sale']??null)<p class="pf-deal-fold-badge">Flash sale · Ends in <span data-countdown="{{ $p['flash_sale']['ends_at'] }}"></span></p>
      @elseif($p['scheduled_flash']??null)<p class="pf-deal-fold-badge">Flash sale ₱{{ number_format($p['scheduled_flash']['price'],2) }} · Starts in <span data-countdown="{{ $p['scheduled_flash']['starts_at'] }}"></span></p>@endif
      @if(($buyerMode??false)&&(($p['flash_sale']??null)||($p['scheduled_flash']??null)))
      <form method="POST" action="{{ route('buyer.commerce.reminder',($p['flash_sale']??$p['scheduled_flash'])['id']) }}">@csrf<button class="pf-deal-fold-link" type="submit">Remind me</button></form>
      @endif
      <div class="pf-deal-fold-prices"><strong>₱{{ number_format($p['price'], 2) }}</strong>
        @if($p['old_price'] ?? null)<del>₱{{ number_format($p['old_price'], 2) }}</del>@endif
      </div>
      <a class="pf-deal-fold-link" href="{{ route($productRoute ?? 'guest.product', $p['id']) }}">View product <span aria-hidden="true">↗</span></a>
    </div>
  </article>
  @endforeach
</div>
</div>
<p class="pf-deal-empty" data-deal-filter-empty hidden role="status">No deals match these filters right now.</p>
@else
<p class="pf-deal-empty">No deals right now — check back soon.</p>
@endif
@once<script src="{{ asset('js/commerce.js') }}?v={{ filemtime(public_path('js/commerce.js')) }}"></script>@endonce
