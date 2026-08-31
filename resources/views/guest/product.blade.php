<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{{ $product['name'] }} — PocketFinds</title>
<link rel="stylesheet" href="{{ asset('css/marketplace.css') }}?v={{ filemtime(public_path('css/marketplace.css')) }}">
{{-- Reuses the buyer app's own product-page styles/icons so a guest sees
     exactly the same page a signed-in buyer would, right up until they try
     to actually do something that needs an account. --}}
<link rel="stylesheet" href="{{ asset('css/buyer.css') }}?v={{ filemtime(public_path('css/buyer.css')) }}">
</head>
<body class="marketplace">

<div class="market-top"><div class="container"><div>Welcome to PocketFinds Marketplace</div><div class="top-links"><span>Help Centre</span><span>Sell on PocketFinds</span><span>Download App</span></div></div></div>
<header class="market-header"><div class="container header-main">
<a class="logo" href="{{ url('/') }}"><span class="logo-mark"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span><span>PocketFinds</span></a>
<div class="search"><input type="search" placeholder="Search for products, brands and categories"><button type="button"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button></div>
<div class="header-actions">
<button class="icon-action" type="button" data-protected><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg><span>Wishlist</span></button>
<button class="icon-action" type="button" data-protected><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg><span>Cart</span></button>
<a class="signin" href="{{ url('/login') }}">Sign In</a><a class="register" href="{{ url('/register/type') }}">Register</a>
</div>
</div></header>

<main class="container" style="padding:20px 0 40px">

@php
  $avg     = $product['rating'];
  $reviews = $product['reviews'];
  $counts  = [1=>0,2=>0,3=>0,4=>0,5=>0];
  foreach($reviews as $r) $counts[$r['rating']]++;
  $total   = count($reviews) ?: 1;
@endphp

<div class="pd-page-grid {{ count($related) ? '' : 'pd-page-grid-solo' }}">
<div class="pd-page-main">

<section class="pd-shop-card">
  <a href="{{ url('/') }}" class="pd-shop-back" aria-label="Back"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></a>
  <div class="pd-shop-more-av">{{ strtoupper(substr($product['seller'], 0, 1)) }}</div>
  <div class="pd-shop-card-info">
    <div class="pd-shop-more-name">{{ $product['seller'] }}</div>
    <div class="pd-shop-card-location">
      <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
      {{ $product['location'] ?: 'Location not provided' }}
    </div>
  </div>
  <div class="pd-shop-stats"><span><strong>{{ $avg > 0 ? number_format($avg, 1) : '—' }}</strong> Rating</span><span><strong>{{ count($shopProducts) + 1 }}</strong> Products</span><span><strong>—</strong> Followers</span></div>
  <div class="pd-shop-card-actions">
    <button type="button" class="pd-shop-chat" data-protected>@include('buyer.partials.icon', ['name' => 'chat', 'size' => 13]) Chat with Seller</button>
    <a href="{{ route('guest.shop', $product['seller_slug']) }}" class="pd-shop-more-link pd-shop-view"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>View Shop</a>
  </div>
</section>

<div class="pd-main-grid">

  {{-- LEFT COL: thumb strip + main image --}}
  @php
    $hasRealImg  = !empty($product['img']);
    $hasGallery  = count($product['images'] ?? []) > 1 || !empty($product['video']);
  @endphp
  <div class="pd-img-col">
    @if($hasGallery)
    <div class="pd-thumb-col">
      <button type="button" class="pd-arr" id="pdThumbUp" aria-label="Scroll thumbnails up">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
      </button>
      <div class="pd-thumb-viewport">
        <div class="pd-thumb-track" id="pdThumbTrack">
          @if(!empty($product['video']))
          <button type="button" class="pd-thumb active" data-thumb-video onclick="showProductVideo(this)" style="background:#000;position:relative">
            <video src="{{ $product['video'] }}" style="width:100%;height:100%;object-fit:cover;opacity:.7;border-radius:6px"></video>
            <svg style="position:absolute;inset:0;margin:auto;color:#fff" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="6 3 20 12 6 21 6 3"/></svg>
          </button>
          @endif
          @foreach($product['images'] as $idx => $imgUrl)
          <button type="button" class="pd-thumb {{ $idx === 0 && empty($product['video']) ? 'active' : '' }}" onclick="showProductImage('{{ $imgUrl }}', this)">
            <img src="{{ $imgUrl }}" style="width:100%;height:100%;object-fit:cover;border-radius:6px">
          </button>
          @endforeach
        </div>
      </div>
      <button type="button" class="pd-arr" id="pdThumbDown" aria-label="Scroll thumbnails down">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
    @endif
    <div class="pd-main-img">
      @if($hasRealImg)
        <img src="{{ $product['img'] }}" id="pdMainImg">
      @else
        <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" opacity=".25"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      @endif
      @if(!empty($product['video']))
        <video id="pdMainVideo" src="{{ $product['video'] }}" controls playsinline style="display:{{ $hasRealImg ? 'none' : 'block' }};width:100%;height:100%;object-fit:contain"></video>
      @endif
    </div>
  </div>

  {{-- RIGHT COL: title card + tabs card stacked --}}
  <div class="pd-right-col">

    {{-- Title / info / actions card --}}
    <div class="pd-info-card">
      <div class="pd-crumb-row">
        <span class="pd-crumb">{{ $product['cat'] }}</span>
        @if($product['badge'])<span class="pd-pill">{{ $product['badge'] }}</span>@endif
      </div>
      <div class="pd-title-price-row">
        <h1 class="pd-title">{{ $product['name'] }}</h1>
        <span class="pd-price" id="pdPrice" data-base-price="{{ $product['price'] }}">₱{{ number_format($product['price']) }}</span>
      </div>
      <div class="pd-meta-row">
        <span class="pd-stars-row">
          @for($i=1;$i<=5;$i++)
          <svg width="12" height="12" viewBox="0 0 24 24" fill="{{ $i<=round($avg)?'#f59e0b':'none' }}" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          @endfor
        </span>
        <span class="pd-meta-val">{{ $avg }}</span>
        <span class="pd-meta-sep">·</span>
        <span class="pd-meta-muted">{{ number_format($product['sold']) }} sold</span>
        <span class="pd-meta-sep">·</span>
        <a class="pd-meta-link" href="#" onclick="event.preventDefault();switchTab(document.querySelector('[data-tab=reviews]'),'reviews')">{{ count($reviews) }} reviews</a>
        <button type="button" class="pd-report-btn" title="Report this listing" data-protected>
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4m0 1h10l-2 3 2 3H5"/></svg>
        </button>
        <div style="margin-left:auto;display:flex;align-items:center;gap:10px">
          @if($product['old_price'])<span class="pd-old">₱{{ number_format($product['old_price']) }}</span>@endif
        </div>
      </div>
      <div class="pd-actions">
        <button class="pd-btn-chat" type="button" data-protected>@include('buyer.partials.icon', ['name' => 'chat', 'size' => 15]) Chat</button>
        <button class="pd-btn-cart" id="pdAddCartBtn" type="button" data-protected {{ $product['stock'] <= 0 ? 'disabled' : '' }}>
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
          {{ $product['stock'] <= 0 ? 'Out of Stock' : 'Add to Cart' }}
        </button>
        <button class="pd-btn-buy" id="pdBuyNowBtn" type="button" data-protected {{ $product['stock'] <= 0 ? 'disabled' : '' }}>
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          {{ $product['stock'] <= 0 ? 'Unavailable' : 'Buy Now' }}
        </button>
      </div>
      @if($product['stock'] <= 0 && $product['restock_date'])
        <div class="pd-restock-note">Expected restock: {{ $product['restock_date'] }}</div>
      @endif
    </div>

    {{-- Description / tabs card --}}
    <div class="pd-tabs-wrap">
      <div class="pd-tab-bar">
        <button class="pd-tab active" data-tab="options" onclick="switchTab(this,'options')">Options</button>
        <button class="pd-tab"        data-tab="description" onclick="switchTab(this,'description')">Description</button>
        <button class="pd-tab"        data-tab="details" onclick="switchTab(this,'details')">Details</button>
        <button class="pd-tab"        data-tab="reviews" onclick="switchTab(this,'reviews')">Reviews</button>
      </div>

      {{-- Options --}}
      <div class="pd-tab-pane active" id="tab-options">
        @if(!empty($product['variations']))
          @php $firstOptionPicked = false; @endphp
          @foreach($product['variations'] as $variation)
          <div class="pd-opt-group">
            <div class="pd-opt-label">{{ $variation['name'] }}</div>
            <div class="pd-opt-row">
              @foreach($variation['options'] as $opt)
              @php
                $isActive = !$firstOptionPicked && $opt['stock'] > 0;
                if ($isActive) $firstOptionPicked = true;
              @endphp
              <button type="button" class="pd-opt-btn {{ $isActive ? 'active' : '' }}"
                onclick="selectVariant(this)"
                data-exclusive="true"
                data-group="{{ $variation['name'] }}"
                data-value="{{ $opt['value'] }}"
                data-stock="{{ $opt['stock'] }}"
                data-price="{{ $opt['price'] ?? $product['price'] }}"
                @if(!empty($opt['image'])) data-image="{{ $opt['image'] }}" @endif
                {{ $opt['stock'] == 0 ? 'disabled' : '' }}
                style="{{ $opt['stock'] == 0 ? 'opacity:.4;cursor:not-allowed' : '' }}">
                {{ $opt['value'] }}
                @if($opt['stock'] > 0)
                  <span style="font-size:10px;color:var(--muted);display:block">{{ $opt['stock'] }} left</span>
                @else
                  <span style="font-size:10px;color:var(--danger);display:block">Out of stock</span>
                @endif
              </button>
              @endforeach
            </div>
          </div>
          @endforeach
        @endif
        @if(empty($product['variations']))
          <div style="font-size:13px;color:var(--muted);padding:8px 0">
            Stock: <strong style="color:{{ $product['stock'] > 0 ? 'var(--success)' : 'var(--danger)' }}">{{ $product['stock'] > 0 ? $product['stock'] . ' available' : 'Out of stock' }}</strong>
          </div>
        @endif
        <div class="pd-opt-group">
          <div class="pd-opt-label">Quantity</div>
          <div style="display:flex;align-items:center;gap:12px">
            <div class="pd-qty-row">
              <button type="button" class="pd-qty-btn" onclick="changeQty(-1)">−</button>
              <span class="pd-qty-num" id="pdQty">1</span>
              <button type="button" class="pd-qty-btn" onclick="changeQty(1)">+</button>
            </div>
            <span class="pd-qty-total" id="pdQtyTotal" style="display:none"></span>
          </div>
        </div>
      </div>

      {{-- Description --}}
      <div class="pd-tab-pane" id="tab-description">
        <p class="pd-desc-text">{{ $product['desc'] ?: 'No description provided.' }}</p>
      </div>

      {{-- Details --}}
      <div class="pd-tab-pane" id="tab-details">
        @if(!empty($product['specs']))
        <div class="pd-section-label">Specifications</div>
        <div class="pd-specs-grid">
          @foreach($product['specs'] as $spec)
          <div class="pd-spec-row">
            <span class="pd-spec-key">{{ $spec[0] }}</span>
            <span class="pd-spec-val">{{ $spec[1] }}</span>
          </div>
          @endforeach
        </div>
        @else
        <p class="pd-desc-text">No specification provided.</p>
        @endif
      </div>

      {{-- Reviews --}}
      <div class="pd-tab-pane" id="tab-reviews">
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

    </div>{{-- end pd-tabs-wrap --}}

  </div>{{-- end pd-right-col --}}

</div>{{-- end pd-main-grid --}}

{{-- List of products from this shop — placed right after the main picture/title/options grid --}}
<div class="pd-shop-more">
  <div class="pd-shop-more-head">
    <div class="pd-shop-more-label">From the Same Shop</div>
    <a href="{{ route('guest.shop', $product['seller_slug']) }}" class="pd-shop-more-link">
      See All
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
  </div>
  <div class="product-grid product-grid-lg">
    @forelse($shopProducts as $sp)
      @include('buyer.partials.product-card', ['p' => $sp, 'route' => 'guest.product'])
    @empty
    <p style="color:var(--muted);font-size:13px;grid-column:1/-1;padding:8px 0">This shop doesn't have any other products yet.</p>
    @endforelse
  </div>
</div>

</div>{{-- end pd-page-main --}}

@if(count($related))
<div class="pd-page-side">
    <div class="pd-related pd-related-side">
      <div class="product-grid product-grid-lg">
        @foreach($related as $rp)
          @include('buyer.partials.product-card', ['p' => $rp, 'route' => 'guest.product'])
        @endforeach
      </div>
    </div>
</div>
@endif

</div>{{-- end pd-page-grid --}}

</main>

<footer class="footer"><div class="container"><div class="footer-grid"><div><h3>PocketFinds Marketplace</h3><p>A simple marketplace experience for discovering products from local sellers.</p></div><div><h3>Customer Service</h3><a href="#">Help Centre</a><a href="#">Contact Us</a><a href="#">Returns</a></div><div><h3>About</h3><a href="#">About Us</a><a href="#">Careers</a><a href="#">Privacy</a></div><div><h3>Account</h3><a href="{{ url('/login') }}">Sign In</a><a href="{{ url('/register/type') }}">Register</a><a href="#">Seller Centre</a></div></div><div class="footer-bottom">© {{ date('Y') }} PocketFinds. All rights reserved.</div></div></footer>

@include('guest.auth-modal')

<script>
function selectVariant(btn) {
  if (btn.disabled) return;
  const scope = btn.dataset.exclusive === 'true'
    ? document.getElementById('tab-options')
    : btn.closest('.pd-opt-row');
  scope.querySelectorAll('.pd-opt-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  if (btn.dataset.image) {
    showProductImage(btn.dataset.image);
    syncThumbForImage(btn.dataset.image);
  }
  updatePriceDisplay();
}

// Keeps the mini-picture rail and the Options tab pointed at the same
// photo — selecting a variation highlights its matching thumbnail, and
// clicking a thumbnail that belongs to a variation selects that variation.
function syncThumbForImage(src) {
  document.querySelectorAll('#pdThumbTrack .pd-thumb').forEach(t => {
    const img = t.querySelector('img');
    if (img && img.getAttribute('src') === src) setActiveThumb(t);
  });
}

function syncVariantForImage(src) {
  const match = [...document.querySelectorAll('#tab-options .pd-opt-btn[data-image]')]
    .find(b => b.dataset.image === src);
  if (match && !match.disabled) {
    document.getElementById('tab-options').querySelectorAll('.pd-opt-btn').forEach(b => b.classList.remove('active'));
    match.classList.add('active');
    updatePriceDisplay();
  }
}

function currentUnitPrice() {
  const priceEl = document.getElementById('pdPrice');
  const active  = document.querySelector('#tab-options .pd-opt-btn.active[data-exclusive="true"]');
  return active ? parseFloat(active.dataset.price) : parseFloat(priceEl.dataset.basePrice);
}

function updatePriceDisplay() {
  const priceEl = document.getElementById('pdPrice');
  if (!priceEl) return;
  priceEl.textContent = '₱' + currentUnitPrice().toLocaleString();
  updateQtyTotal();
}

function updateQtyTotal() {
  const totalEl = document.getElementById('pdQtyTotal');
  if (!totalEl) return;
  if (qty >= 2) {
    totalEl.textContent = 'Total: ₱' + (currentUnitPrice() * qty).toLocaleString();
    totalEl.style.display = '';
  } else {
    totalEl.style.display = 'none';
  }
}

function showProductImage(src, thumb) {
  const img = document.getElementById('pdMainImg');
  const video = document.getElementById('pdMainVideo');
  if (video) video.style.display = 'none';
  if (img) { img.src = src; img.style.display = 'block'; }
  if (thumb) {
    setActiveThumb(thumb);
    syncVariantForImage(src);
  }
}

function showProductVideo(thumb) {
  const img = document.getElementById('pdMainImg');
  const video = document.getElementById('pdMainVideo');
  if (!video) return;
  if (img) img.style.display = 'none';
  video.style.display = 'block';
  if (thumb) setActiveThumb(thumb);
}

function setActiveThumb(thumb) {
  const track = thumb.closest('.pd-thumb-track');
  if (!track) return;
  track.querySelectorAll('.pd-thumb').forEach(t => t.classList.remove('active'));
  thumb.classList.add('active');
}

(function () {
  const track = document.getElementById('pdThumbTrack');
  const up = document.getElementById('pdThumbUp');
  const down = document.getElementById('pdThumbDown');
  if (!track || !up || !down) return;
  const step = 72;
  let offset = 0;
  function maxOffset() {
    const viewport = track.parentElement;
    return Math.max(0, track.scrollHeight - viewport.clientHeight);
  }
  function updateArrowVisibility() {
    const needsScroll = maxOffset() > 0;
    up.style.display = needsScroll ? '' : 'none';
    down.style.display = needsScroll ? '' : 'none';
  }
  updateArrowVisibility();
  window.addEventListener('resize', updateArrowVisibility);
  function apply() {
    track.style.transform = `translateY(-${offset}px)`;
    updateArrowVisibility();
  }
  up.addEventListener('click', () => {
    offset = Math.max(0, offset - step);
    apply();
  });
  down.addEventListener('click', () => {
    offset = Math.min(maxOffset(), offset + step);
    apply();
  });
})();
let qty = 1;
function changeQty(d) {
  qty = Math.max(1, qty + d);
  document.getElementById('pdQty').textContent = qty;
  updateQtyTotal();
}
function switchTab(btn, id) {
  document.querySelectorAll('.pd-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.pd-tab-pane').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('tab-' + id).classList.add('active');
}
let expanded = false;
function toggleReviews() {
  expanded = !expanded;
  document.querySelectorAll('.rev-hidden').forEach(el => el.style.display = expanded ? '' : 'none');
  document.getElementById('pdSeeMore').innerHTML = expanded
    ? '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg> Show less'
    : '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg> See all {{ count($reviews) }} reviews';
}
const revCounts = @json($counts);
const revTotal  = {{ count($reviews) }};
let activeFilter = 0;
function filterReviews(star) {
  activeFilter = star;
  document.querySelectorAll('#pdRevList .pd-rev-item').forEach(el => {
    const match  = !activeFilter || parseInt(el.dataset.rating) === activeFilter;
    const hidden = el.classList.contains('rev-hidden') && !expanded;
    el.style.display = match && !hidden ? '' : 'none';
  });
  document.querySelectorAll('.pd-rev-chip').forEach(b => b.classList.toggle('active', parseInt(b.dataset.star) === activeFilter));
  const num = activeFilter ? (revCounts[activeFilter] || 0) : revTotal;
  const lbl = activeFilter ? 'rates' : 'reviews';
  document.getElementById('pdRevCountNum').textContent = num;
  document.getElementById('pdRevCountLbl').textContent = lbl;
}
document.querySelectorAll('.rev-hidden').forEach(el => el.style.display = 'none');
</script>
<script src="{{ asset('js/marketplace.js') }}"></script>
</body>
</html>
