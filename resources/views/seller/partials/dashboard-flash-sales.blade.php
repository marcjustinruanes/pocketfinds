<section class="card dashboard-flash-sales" aria-labelledby="dashboardFlashTitle">
  <div class="card-head"><h2 id="dashboardFlashTitle">Flash-sale products</h2><a class="dashboard-report-arrow" href="{{ route('seller.promotions.flash') }}" aria-label="Manage flash sales" title="Manage flash sales">↗</a></div>
  <div class="card-pad">
    @if($dashboardFlashSales->isNotEmpty())
      <div class="dashboard-deals" data-dashboard-deals>
        <div class="dashboard-deal-gallery">
          @foreach($dashboardFlashSales as $sale)
            <article class="dashboard-deal" data-dashboard-deal @if(!$loop->first) hidden @endif aria-label="{{ $sale->product_name }}">
              @if($sale->product_image)<img src="{{ \Illuminate\Support\Facades\Storage::url($sale->product_image) }}" alt="{{ $sale->product_name }}" loading="lazy">@endif
              <span class="dashboard-deal-badge">Live flash sale</span>
              <div class="dashboard-deal-copy">
                <h3>{{ $sale->product_name }}</h3>
                <p class="dashboard-deal-price">₱{{ number_format($sale->price,2) }} @if($sale->original_price > $sale->price)<del>₱{{ number_format($sale->original_price,2) }}</del>@endif</p>
                <p>{{ $sale->remaining }} promo units left</p>
                <p>Until {{ \Illuminate\Support\Carbon::parse($sale->ends_at)->format('M j, Y · g:i A') }}</p>
                <a href="{{ route('seller.promotions.flash') }}">View sale <span aria-hidden="true">↗</span></a>
              </div>
            </article>
          @endforeach
        </div>
        @if($dashboardFlashSales->count()>1)
        <div class="dashboard-deal-controls">
          <button type="button" data-dashboard-deal-prev aria-label="Previous flash-sale product">←</button>
          <span data-dashboard-deal-position aria-live="polite">1 / {{ $dashboardFlashSales->count() }}</span>
          <button type="button" data-dashboard-deal-next aria-label="Next flash-sale product">→</button>
        </div>
        @endif
      </div>
    @else
      <p class="dashboard-offer-empty">No live flash sales. <a href="{{ route('seller.promotions.flash') }}">Manage flash sales</a></p>
    @endif
  </div>
</section>
@once
@push('scripts')
<script src="{{ asset('js/seller-dashboard-deals.js') }}?v={{ filemtime(public_path('js/seller-dashboard-deals.js')) }}" defer></script>
@endpush
@endonce
