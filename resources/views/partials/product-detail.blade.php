@php
 $buyerMode = $buyerMode ?? false;
 $productRoute = $buyerMode ? 'buyer.product' : 'guest.product';
@endphp
@php
  $combinationGroup = \App\Support\ProductVariationSet::combination($product['variations'] ?? []);
  $displayVariations = \App\Support\ProductVariationSet::displayGroups($product['variations'] ?? []);
  $avg     = $product['rating'];
  $reviews = $product['reviews'];
  $counts  = [1=>0,2=>0,3=>0,4=>0,5=>0];
  foreach($reviews as $r) $counts[$r['rating']]++;
  $total   = count($reviews) ?: 1;
@endphp

<div class="pd-page-grid {{ count($related) ? '' : 'pd-page-grid-solo' }}">
<div class="pd-page-main">

<div class="pd-main-grid">

  {{-- LEFT COL: thumb strip + main image --}}
  @php
    $hasRealImg  = !empty($product['img']);
    $hasGallery  = count($product['images'] ?? []) > 1 || !empty($product['video']);
  @endphp
  <div class="pd-img-col">
    <div class="pd-main-img" onclick="openImgViewer()">
      @if($hasRealImg)
        <img src="{{ $product['img'] }}" id="pdMainImg" alt="{{ $product['name'] }}">
      @else
        <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" opacity=".25"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      @endif
      @if(!empty($product['video']))
        <video id="pdMainVideo" src="{{ $product['video'] }}" controls playsinline style="display:{{ $hasRealImg ? 'none' : 'block' }};max-width:100%;max-height:100%;object-fit:contain" onclick="event.stopPropagation()"></video>
      @endif
    </div>
    @if($hasGallery)
    <div class="pd-thumb-col">
      <button type="button" class="pd-arr" id="pdThumbUp" aria-label="Scroll thumbnails up">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <div class="pd-thumb-viewport">
        <div class="pd-thumb-track" id="pdThumbTrack">
          @if(!empty($product['video']))
          <button type="button" class="pd-thumb active" aria-label="View product video" data-thumb-video onclick="showProductVideo(this)" style="background:#000;position:relative">
            <video src="{{ $product['video'] }}" style="width:100%;height:100%;object-fit:cover;opacity:.7;border-radius:6px"></video>
            <svg style="position:absolute;inset:0;margin:auto;color:#fff" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="6 3 20 12 6 21 6 3"/></svg>
          </button>
          @endif
          @foreach($product['images'] as $idx => $imgUrl)
          <button type="button" class="pd-thumb {{ $idx === 0 && empty($product['video']) ? 'active' : '' }}" aria-label="View product photo {{ $idx + 1 }}" onclick="showProductImage('{{ $imgUrl }}', this)">
            <img src="{{ $imgUrl }}" alt="{{ $product['name'] }} photo {{ $idx + 1 }}" style="width:100%;height:100%;object-fit:cover;border-radius:6px">
          </button>
          @endforeach
        </div>
      </div>
      <button type="button" class="pd-arr" id="pdThumbDown" aria-label="Scroll thumbnails down">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
    </div>
    @endif
  </div>

  {{-- RIGHT COL: title card + tabs card stacked --}}
  <div class="pd-right-col">

    {{-- Title / info / actions card --}}
    <div class="pd-info-card">
      <div class="pd-crumb-row">
        <span class="pd-crumb">{{ $product['cat'] }}</span>
        @include('partials.product-promotions')
        @if($product['badge'])<span class="pd-pill">{{ $product['badge'] }}</span>@endif

      </div>
      <div class="pd-title-price-row">
        <h1 class="pd-title" title="{{ $product['name'] }}">{{ $product['name'] }}</h1>
      </div>
      <div class="pd-meta-row">
        <span class="pd-stars-row">
          @for($i=1;$i<=5;$i++)
          <svg width="12" height="12" viewBox="0 0 24 24" fill="{{ $i<=round($avg)?'#f59e0b':'none' }}" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          @endfor
        </span>
        <span class="pd-meta-val">{{ $avg }}</span>
        <span class="pd-meta-sep">·</span>
        <a class="pd-meta-link" href="#tab-reviews" onclick="event.preventDefault();switchTab(document.querySelector('[data-tab=reviews]'),'reviews')"><strong>{{ count($reviews) }}</strong> ratings</a>
        <span class="pd-meta-sep" aria-hidden="true">·</span>
        <span class="pd-meta-muted"><strong>{{ number_format($product['sold']) }}</strong> sold</span>
        <button type="button" class="pd-report-btn" title="Report this listing" @if($buyerMode) data-modal-open="productReportModal" @else data-protected @endif>
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4m0 1h10l-2 3 2 3H5"/></svg>
        </button>
      </div>
      @include('partials.product-price')
    </div>

    <section class="pd-tabs-wrap pd-product-information" aria-label="Product information">
      <div class="pd-tab-bar" role="tablist" aria-label="Product information">
        <button type="button" class="pd-tab active" id="pdTabOptions" role="tab" aria-selected="true" aria-controls="tab-options" data-tab="options" onclick="switchTab(this,'options')">Options</button>
        <button type="button" class="pd-tab" id="pdTabDescription" role="tab" aria-selected="false" aria-controls="tab-description" data-tab="description" onclick="switchTab(this,'description')">Description</button>
        <button type="button" class="pd-tab" id="pdTabDetails" role="tab" aria-selected="false" aria-controls="tab-details" data-tab="details" onclick="switchTab(this,'details')">Specifications</button>
        <button type="button" class="pd-tab" id="pdTabReviews" role="tab" aria-selected="false" aria-controls="tab-reviews" data-tab="reviews" onclick="switchTab(this,'reviews')">Reviews</button>
      </div>
      {{-- Options --}}
      <div class="pd-tab-pane active" id="tab-options" role="tabpanel" aria-labelledby="pdTabOptions">
        @if(!empty($product['variations']))
          @foreach($displayVariations as $variation)
          <div class="pd-opt-group">
            <div class="pd-opt-label">{{ $variation['name'] }}</div>
            <div class="pd-opt-row">
              @foreach($variation['options'] as $opt)
              @php
                $isActive = false;
              @endphp
              <button type="button" class="pd-opt-btn {{ $isActive ? 'active' : '' }} {{ preg_match('/^colou?rs?$/i', $variation['name']) ? 'pd-opt-color' : '' }} {{ preg_match('/^sizes?$/i', $variation['name']) ? 'pd-opt-size' : '' }}" title="{{ $opt['value'] }}" aria-label="{{ $variation['name'] }}: {{ $opt['value'] }}"
                onclick="selectVariant(this)"
                data-exclusive="{{ $combinationGroup ? 'false' : 'true' }}"
                data-group="{{ $variation['name'] }}"
                data-value="{{ $opt['value'] }}"
                data-stock="{{ $opt['stock'] }}"
                data-price="{{ $opt['price'] ?? $product['price'] }}"
                @if(!empty($opt['image'])) data-image="{{ $opt['image'] }}" @endif
                {{ $opt['stock'] == 0 && !($combinationGroup['requires_stock_setup'] ?? false) ? 'disabled' : '' }}
                style="{{ $opt['stock'] == 0 && !($combinationGroup['requires_stock_setup'] ?? false) ? 'opacity:.4;cursor:not-allowed' : '' }}">
                @include('partials.product-option-content', ['option' => $opt, 'groupName' => $variation['name']])
              </button>
              @endforeach
            </div>
          </div>
          @endforeach
        @endif
        @if(empty($product['variations']))
          <div class="pd-stock-status">
            Stock: <strong style="color:{{ $product['stock'] > 0 ? 'var(--success)' : 'var(--danger)' }}">{{ $product['stock'] > 0 ? $product['stock'] . ' available' : 'Out of stock' }}</strong>
          </div>
        @endif
        @if(!empty($product['variations']))
          @if($combinationGroup['requires_stock_setup'] ?? false)<p class="pd-stock-status">The seller is updating stock for variation combinations.</p>@endif
          <div id="pdSelectedStock" class="pd-stock-status" role="status" aria-live="polite" hidden></div>
        @endif
        <div class="pd-opt-group">
          <div class="pd-opt-label">Quantity</div>
          <div class="pd-quantity-controls">
            <div class="pd-qty-row">
              <button type="button" class="pd-qty-btn" aria-label="Decrease quantity" id="pdQtyDecrease" onclick="changeQty(-1)" disabled>−</button>
              <span class="pd-qty-num" id="pdQty">1</span>
              <button type="button" class="pd-qty-btn" aria-label="Increase quantity" id="pdQtyIncrease" onclick="changeQty(1)" disabled>+</button>
            </div>
            <span class="pd-qty-total" id="pdQtyTotal" style="display:none"></span>
          </div>
        </div>
      </div>
      {{-- Description --}}
      <div class="pd-tab-pane" id="tab-description" role="tabpanel" aria-labelledby="pdTabDescription">
        <p class="pd-desc-text">{{ $product['desc'] ?: 'No description provided.' }}</p>
      </div>

      {{-- Details --}}
      <div class="pd-tab-pane" id="tab-details" role="tabpanel" aria-labelledby="pdTabDetails">
        @if(!empty($product['specs']))
        <table class="pd-specs-grid" aria-label="Product specifications">
          <tbody>
          @foreach($product['specs'] as $spec)
          <tr class="pd-spec-row">
            <th scope="row" class="pd-spec-key">{{ $spec[0] }}</th>
            <td class="pd-spec-val">{{ $spec[1] }}</td>
          </tr>
          @endforeach
          </tbody>
        </table>
        @else
        <p class="pd-desc-text">No specification provided.</p>
        @endif
      </div>

      {{-- Reviews --}}
      <div class="pd-tab-pane" id="tab-reviews" role="tabpanel" aria-labelledby="pdTabReviews">
        <div class="pd-rev-filter">
          <div class="pd-rev-chips">
            <button class="pd-rev-chip active" data-star="0" onclick="filterReviews(0)">All</button>
            @foreach([5,4,3,2,1] as $star)
            <button class="pd-rev-chip" onclick="filterReviews({{ $star }})" data-star="{{ $star }}">
              {{ $star }}<svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="margin-left:1px"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </button>
            @endforeach
          </div>
          <div class="pd-rev-count" id="pdRevCount">
            <span class="pd-rev-count-num" id="pdRevCountNum">{{ count($reviews) }}</span>
            <span class="pd-rev-count-lbl" id="pdRevCountLbl">reviews</span>
          </div>
        </div>
        <div class="pd-rev-list" id="pdRevList">
          @forelse($reviews as $idx => $r)
          <div class="pd-rev-item {{ $idx>=3?'rev-hidden':'' }}" data-rating="{{ $r['rating'] }}">
            <div class="pd-rev-av">{{ strtoupper(substr($r['name'],0,1)) }}</div>
            <div class="pd-rev-body">
              <div class="pd-rev-header">
                <span class="pd-rev-name">{{ $r['name'] }}</span>
                <div class="pd-rev-stars-sm">
                  @for($i=1;$i<=5;$i++)
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="{{ $i<=$r['rating']?'#f59e0b':'none' }}" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                  @endfor
                </div>
                <span class="pd-rev-date">{{ $r['date'] }}</span>
              </div>
              <p class="pd-rev-text">{{ $r['text'] }}</p>
              @if(!empty($r['variation']))<p style="font-size:11px;color:var(--muted)">{{ $r['variation'] }}</p>@endif
            </div>
          </div>
          @empty
          <div class="empty"><h3>No reviews yet</h3></div>
          @endforelse
          @if(count($reviews) > 3)
          <button class="pd-see-more" id="pdSeeMore" onclick="toggleReviews()">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" id="pdSeeIcon"><polyline points="6 9 12 15 18 9"/></svg>
            See all {{ count($reviews) }} reviews
          </button>
          @endif
        </div>
      </div>

    </section>{{-- end pd-tabs-wrap --}}
    @if(isset($product['seller_id']))
      @include('partials.claimable-vouchers',['claimableVouchers'=>app(\App\Services\ShopCatalog::class)->vouchers((int)$product['seller_id'])])
    @endif
    <div class="pd-purchase">

    <div class="pd-actions">
      <button class="pd-btn-cart" id="pdAddCartBtn" type="button" @if($buyerMode) onclick="directAddToCart(this)" data-product-id="{{ $product['id'] }}" data-product-name="{{ $product['name'] }}" data-product-img="{{ $product['img'] }}" @else data-protected @endif aria-label="{{ $product['stock'] <= 0 ? 'Out of Stock' : 'Add to Cart' }}" title="{{ $product['stock'] <= 0 ? 'Out of Stock' : 'Add to Cart' }}" {{ ($product['stock'] <= 0 || ($buyerMode && ($product['on_vacation'] ?? false))) ? 'disabled' : '' }}>
        {{ $product['stock'] <= 0 ? 'Out of Stock' : 'Add to Cart' }}
      </button>
      <button class="pd-btn-wishlist" id="pdWishlistBtn" @if($buyerMode) type="submit" form="pdWishlistForm" @else type="button" data-protected @endif aria-label="{{ ($buyerMode && ($isSaved ?? false)) ? 'Remove from Wishlist' : 'Add to Wishlist' }}" title="{{ ($buyerMode && ($isSaved ?? false)) ? 'Remove from Wishlist' : 'Add to Wishlist' }}" @if($buyerMode) aria-pressed="{{ ($isSaved ?? false) ? 'true' : 'false' }}" @endif>
        @include('buyer.partials.icon', ['name' => 'wishlist', 'size' => 19])
      </button>
      <button class="pd-btn-product-message" type="button" @if($buyerMode) onclick="openChatWithSelectedVariant(this)" href="{{ route('buyer.messages', ['seller' => $product['seller_slug'], 'product' => $product['id']]) }}" @else data-protected @endif aria-label="Message about this product" title="Message about this product">
        @include('buyer.partials.icon', ['name' => 'chat', 'size' => 19])
      </button>
    </div>
      @include('partials.product-shipping')
    </div>
    @if($product['stock'] <= 0 && $product['restock_date'])
      <div class="pd-restock-note">Expected restock: {{ $product['restock_date'] }}</div>
    @endif


  </div>{{-- end pd-right-col --}}
</div>{{-- end pd-main-grid --}}

{{-- Other products from this seller --}}
<div class="pd-shop-more">
  <div class="pd-shop-more-head">
    <h2 class="pd-shop-more-label">From the Same Shop</h2>
    <div class="pd-seller-inline" role="group" aria-label="Seller">
      <div class="pd-seller-details">
        <a class="pd-seller-link" href="{{ route($buyerMode ? 'buyer.shop' : 'guest.shop', $product['seller_slug']) }}" title="View shop: {{ $product['seller'] }}">
          <span>{{ $product['seller'] }}</span>
        </a>
        @if($product['location'])
        <span class="pd-seller-location">{{ $product['location'] }}</span>
        @endif
      </div>
      <div class="pd-seller-name-actions">
        <button type="button" class="pd-seller-icon" @if($buyerMode) id="pdFollowShop" aria-pressed="{{ ($isFollowing ?? false) ? 'true' : 'false' }}" data-slug="{{ $product['seller_slug'] }}" @else data-protected @endif aria-label="{{ ($buyerMode && ($isFollowing ?? false)) ? 'Unfollow shop' : 'Follow shop' }}" title="{{ ($buyerMode && ($isFollowing ?? false)) ? 'Unfollow shop' : 'Follow shop' }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10v11h11M2 10l2-7h14l2 7M2 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M7 21v-7h5v7m7-7v8m-4-4h8"/></svg></button>
        <button type="button" class="pd-seller-icon" @if($buyerMode) onclick="openChatWithSelectedVariant(this)" href="{{ route('buyer.messages', ['seller' => $product['seller_slug']]) }}" @else data-protected @endif aria-label="Chat with seller" title="Chat with seller">@include('buyer.partials.icon', ['name' => 'chat', 'size' => 18])</button>
      </div>
    </div>

  </div>
  <div class="product-grid product-grid-lg">
    @forelse($shopProducts as $sp)
      @include('guest.partials.pf-product-card', ['p' => $sp, 'catalogCard' => true])
    @empty
    <p style="color:var(--muted);font-size:13px;grid-column:1/-1;padding:8px 0">This shop doesn't have any other products yet.</p>
    @endforelse
  </div>
</div>


</div>{{-- end pd-page-main --}}

@if(count($related))
<div class="pd-page-side">
    <div class="pd-related pd-related-side">
      <div class="pd-related-head"><h2>You Might Also Like</h2></div>
      <div class="product-grid product-grid-lg">
        @foreach($related as $rp)
          @include('guest.partials.pf-product-card', ['p' => $rp, 'catalogCard' => true])
        @endforeach
      </div>
    </div>
</div>
@endif

</div>{{-- end pd-page-grid --}}

