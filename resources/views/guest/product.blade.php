<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{{ $product['name'] }} — PocketFinds</title>
<link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
<link rel="stylesheet" href="{{ asset('css/guest-product.css') }}">
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

<a href="{{ url()->previous() === url()->current() ? url('/') : url()->previous() }}" class="gp-back">
  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg> Back
</a>

@php
  $avg     = $product['rating'];
  $reviews = $product['reviews'];
  $counts  = [1=>0,2=>0,3=>0,4=>0,5=>0];
  foreach($reviews as $r) $counts[$r['rating']]++;
  $total   = count($reviews) ?: 1;
  $icons = [
    'headphones'=>'<path d="M3 18v-6a9 9 0 0118 0v6"/><path d="M21 19a2 2 0 01-2 2h-1a2 2 0 01-2-2v-3a2 2 0 012-2h3zM3 19a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2H3z"/>',
    'bag'       =>'<path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>',
    'phone'     =>'<path d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
    'sparkle'   =>'<path d="M5 3l1.5 4.5L11 9l-4.5 1.5L5 15l-1.5-4.5L-1 9l4.5-1.5L5 3zm12 9l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z"/>',
    'shirt'     =>'<path d="M3 7l3-4h12l3 4-4 2v10H7V9L3 7z"/>',
    'puzzle'    =>'<path d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/>',
  ];
  $iconPath = $icons[$product['img']] ?? $icons['bag'];
@endphp

<div class="gp-grid">
  {{-- Image col --}}
  <div class="gp-img-col">
    <div class="gp-thumb-col">
      <button class="gp-arr" id="gpArrUp" onclick="gpScrollThumbs(-1)">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
      </button>
      <div class="gp-thumb-viewport" id="gpThumbViewport">
        <div class="gp-thumb-track" id="gpThumbTrack">
          @foreach(range(1,6) as $t)
          <button class="gp-thumb {{ $t===1?'active':'' }}" onclick="gpThumb(this)">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">{!! $iconPath !!}</svg>
          </button>
          @endforeach
        </div>
      </div>
      <button class="gp-arr" id="gpArrDown" onclick="gpScrollThumbs(1)">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
    <div class="gp-main-img">
      <svg xmlns="http://www.w3.org/2000/svg" width="110" height="110" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">{!! $iconPath !!}</svg>
    </div>
  </div>

  {{-- Info col --}}
  <div class="gp-right-col">
    <div class="gp-info-card">
      <div class="gp-crumb-row">
        <span class="gp-crumb">{{ $product['cat'] }}</span>
        @if($product['badge'])<span class="gp-pill">{{ $product['badge'] }}</span>@endif
      </div>
      <div class="gp-title-price-row">
        <h1 class="gp-title">{{ $product['name'] }}</h1>
        <div class="gp-price-block">
          <span class="gp-price">₱{{ number_format($product['price']) }}</span>
          @if($product['old_price'])<span class="gp-old">₱{{ number_format($product['old_price']) }}</span>@endif
        </div>
      </div>
      <div class="gp-meta-row">
        <span class="gp-stars">
          @for($i=1;$i<=5;$i++)
          <svg width="12" height="12" viewBox="0 0 24 24" fill="{{ $i<=round($avg)?'#f59e0b':'none' }}" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          @endfor
        </span>
        <span class="gp-meta-val">{{ $avg }}</span>
        <span class="gp-meta-sep">·</span>
        <span class="gp-meta-muted">{{ number_format($product['sold']) }} sold</span>
        <span class="gp-meta-sep">·</span>
        <a class="gp-meta-link" href="#" onclick="event.preventDefault();gpTab('reviews')">{{ count($reviews) }} reviews</a>
      </div>
      <div class="gp-actions">
        <button class="gp-btn-cart" type="button" data-protected>
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
          Add to Cart
        </button>
        <button class="gp-btn-buy" type="button" data-protected>
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          Buy Now
        </button>
      </div>
    </div>

    <div class="gp-tabs-wrap">
      <div class="gp-tab-bar">
        <button class="gp-tab active" onclick="gpTab('options')">Options</button>
        <button class="gp-tab" onclick="gpTab('details')">Details</button>
        <button class="gp-tab" onclick="gpTab('reviews')">Reviews <span class="gp-tab-count">{{ count($reviews) }}</span></button>
      </div>

      <div class="gp-pane active" id="gp-options">
        @if(!empty($product['variants']['color']))
        <div class="gp-opt-group">
          <div class="gp-opt-label">Color</div>
          <div class="gp-opt-row">
            @foreach($product['variants']['color'] as $c)
            <button class="gp-opt-btn {{ $loop->first?'active':'' }}" onclick="gpVariant(this)">{{ $c }}</button>
            @endforeach
          </div>
        </div>
        @endif
        @if(!empty($product['variants']['size']))
        <div class="gp-opt-group">
          <div class="gp-opt-label">{{ str_contains(implode(',',$product['variants']['size']),'Switch') ? 'Switch Type' : 'Size' }}</div>
          <div class="gp-opt-row">
            @foreach($product['variants']['size'] as $s)
            <button class="gp-opt-btn {{ $loop->first?'active':'' }}" onclick="gpVariant(this)">{{ $s }}</button>
            @endforeach
          </div>
        </div>
        @endif
        <div class="gp-opt-group">
          <div class="gp-opt-label">Quantity</div>
          <div class="gp-qty-row">
            <button class="gp-qty-btn" onclick="gpQty(-1)">−</button>
            <span class="gp-qty-num" id="gpQty">1</span>
            <button class="gp-qty-btn" onclick="gpQty(1)">+</button>
          </div>
        </div>
      </div>

      <div class="gp-pane" id="gp-details">
        <p class="gp-desc">{{ $product['desc'] }}</p>
        <div class="gp-divider"></div>
        <div class="gp-specs">
          @foreach($product['specs'] as [$label,$val])
          <div class="gp-spec-row"><span class="gp-spec-key">{{ $label }}</span><span class="gp-spec-val">{{ $val }}</span></div>
          @endforeach
        </div>
      </div>

      <div class="gp-pane" id="gp-reviews">
        <div class="gp-rev-filter">
          <div class="gp-rev-chips">
            <button class="gp-rev-chip active" data-star="0" onclick="gpFilterRev(0)">All</button>
            @foreach([5,4,3,2,1] as $star)
            <button class="gp-rev-chip" data-star="{{ $star }}" onclick="gpFilterRev({{ $star }})">{{ $star }}<svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="margin-left:1px"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>
            @endforeach
          </div>
          <div class="gp-rev-count">
            <span class="gp-rev-count-num" id="gpRevCountNum">{{ count($reviews) }}</span>
            <span class="gp-rev-count-lbl" id="gpRevCountLbl">reviews</span>
          </div>
        </div>
        <div id="gpRevList">
          @forelse($reviews as $idx => $r)
          <div class="gp-rev-item {{ $idx>=3?'gp-rev-hidden':'' }}" data-rating="{{ $r['rating'] }}">
            <div class="gp-rev-av">{{ strtoupper(substr($r['name'],0,1)) }}</div>
            <div class="gp-rev-body">
              <div class="gp-rev-header">
                <span class="gp-rev-name">{{ $r['name'] }}</span>
                <div class="gp-rev-stars">
                  @for($i=1;$i<=5;$i++)
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="{{ $i<=$r['rating']?'#f59e0b':'none' }}" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                  @endfor
                </div>
                <span class="gp-rev-date">{{ $r['date'] }}</span>
              </div>
              <p class="gp-rev-text">{{ $r['text'] }}</p>
            </div>
          </div>
          @empty
          <p style="color:var(--muted);font-size:13px">No reviews yet.</p>
          @endforelse
          @if(count($reviews) > 3)
          <button class="gp-see-more" id="gpSeeMore" onclick="gpToggleRev()">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            See all {{ count($reviews) }} reviews
          </button>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>

{{-- More from shop --}}
@if(count($shopProducts))
<div class="gp-shop-more">
  <div class="gp-shop-more-head">
    <div style="display:flex;align-items:center;gap:12px">
      <div class="gp-shop-av">{{ strtoupper(substr($product['seller'],0,1)) }}</div>
      <div>
        <div class="gp-shop-name">{{ $product['seller'] }}</div>
        <div class="gp-shop-sub">More from this shop</div>
      </div>
    </div>
    <a href="{{ route('guest.shop', $product['seller_slug']) }}" class="gp-shop-link">View Shop →</a>
  </div>
  <div class="gp-mini-grid">
    @foreach($shopProducts as $sp)
    <a class="gp-mini-card" href="{{ route('guest.product', $sp['id']) }}">
      <div class="gp-mini-img">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">{!! $icons[$sp['img']] ?? $icons['bag'] !!}</svg>
        @if($sp['badge'])<span class="gp-mini-badge">{{ $sp['badge'] }}</span>@endif
      </div>
      <div class="gp-mini-info">
        <div class="gp-mini-name">{{ $sp['name'] }}</div>
        <div class="gp-mini-price">₱{{ number_format($sp['price']) }}</div>
        <div class="gp-mini-rating"><svg width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> {{ $sp['rating'] }}</div>
      </div>
    </a>
    @endforeach
  </div>
</div>
@endif

{{-- Related --}}
@if(count($related))
<div class="gp-related">
  <div class="gp-related-head"><span>Similar Products</span></div>
  <div class="deal-grid">
    @foreach($related as $rp)
    <a class="product" href="{{ route('guest.product', $rp['id']) }}" style="text-decoration:none;color:inherit">
      <div class="product-img">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">{!! $icons[$rp['img']] ?? $icons['bag'] !!}</svg>
      </div>
      <div class="product-body">
        @if($rp['badge'])<span class="badge">{{ $rp['badge'] }}</span>@endif
        <div class="product-name">{{ $rp['name'] }}</div>
        <div class="price">₱{{ number_format($rp['price']) }}</div>
        <div class="meta">
          <span class="meta-rating"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> {{ $rp['rating'] }}</span>
          <span>{{ number_format($rp['sold']) }} sold</span>
        </div>
        <div class="product-actions">
          <button class="btn-cart" type="button" data-protected onclick="event.preventDefault();event.stopPropagation()"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg> Cart</button>
          <button class="btn-buy" type="button" data-protected onclick="event.preventDefault();event.stopPropagation()">Buy Now</button>
        </div>
      </div>
    </a>
    @endforeach
  </div>
</div>
@endif

</main>

<footer class="footer"><div class="container"><div class="footer-grid"><div><h3>PocketFinds Marketplace</h3><p>A simple marketplace experience for discovering products from local sellers.</p></div><div><h3>Customer Service</h3><a href="#">Help Centre</a><a href="#">Contact Us</a><a href="#">Returns</a></div><div><h3>About</h3><a href="#">About Us</a><a href="#">Careers</a><a href="#">Privacy</a></div><div><h3>Account</h3><a href="{{ url('/login') }}">Sign In</a><a href="{{ url('/register/type') }}">Register</a><a href="#">Seller Centre</a></div></div><div class="footer-bottom">© {{ date('Y') }} PocketFinds. All rights reserved.</div></div></footer>

@include('guest.auth-modal')

<script>
// Tabs
function gpTab(name) {
  document.querySelectorAll('.gp-tab').forEach((t,i) => t.classList.toggle('active', ['options','details','reviews'][i]===name));
  document.querySelectorAll('.gp-pane').forEach(p => p.classList.toggle('active', p.id==='gp-'+name));
}
// Thumbnails with scroll arrows (matches buyer pd-* logic)
const THUMB_STEP = 60;
let thumbPos = 0;
function gpScrollThumbs(dir) {
  const track = document.getElementById('gpThumbTrack');
  const vp    = document.getElementById('gpThumbViewport');
  const max   = Math.max(0, track.scrollHeight - vp.clientHeight);
  thumbPos = Math.max(0, Math.min(max, thumbPos + dir * THUMB_STEP));
  track.style.transform = `translateY(-${thumbPos}px)`;
  document.getElementById('gpArrUp').style.opacity   = thumbPos <= 0   ? '.3' : '1';
  document.getElementById('gpArrDown').style.opacity = thumbPos >= max ? '.3' : '1';
}
function gpThumb(btn) {
  btn.closest('.gp-thumb-track').querySelectorAll('.gp-thumb').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
document.getElementById('gpArrUp').style.opacity = '.3';
// Variants
function gpVariant(btn) {
  btn.closest('.gp-opt-row').querySelectorAll('.gp-opt-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
// Qty
let gpQtyVal = 1;
function gpQty(d) { gpQtyVal = Math.max(1, gpQtyVal+d); document.getElementById('gpQty').textContent = gpQtyVal; }
// Reviews
let gpRevExpanded = false;
function gpToggleRev() {
  gpRevExpanded = !gpRevExpanded;
  document.querySelectorAll('.gp-rev-hidden').forEach(el => el.style.display = gpRevExpanded ? '' : 'none');
  document.getElementById('gpSeeMore').innerHTML = gpRevExpanded
    ? '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg> Show less'
    : '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg> See all {{ count($reviews) }} reviews';
}
const gpRevCounts = @json($counts);
const gpRevTotal  = {{ count($reviews) }};
function gpFilterRev(star) {
  document.querySelectorAll('#gpRevList .gp-rev-item').forEach(el => {
    const match = !star || parseInt(el.dataset.rating)===star;
    el.style.display = match ? '' : 'none';
  });
  document.querySelectorAll('.gp-rev-chip').forEach(b => b.classList.toggle('active', parseInt(b.dataset.star)===star));
  const num = star ? (gpRevCounts[star] || 0) : gpRevTotal;
  const lbl = star ? 'rates' : 'reviews';
  document.getElementById('gpRevCountNum').textContent = num;
  document.getElementById('gpRevCountLbl').textContent = lbl;
}
document.querySelectorAll('.gp-rev-hidden').forEach(el => el.style.display = 'none');
// Match tabs wrap height to image col (same as buyer page)
function gpFitTabs() {
  const imgCol   = document.querySelector('.gp-img-col');
  const infoCard = document.querySelector('.gp-info-card');
  const tabsWrap = document.querySelector('.gp-tabs-wrap');
  const rightCol = document.querySelector('.gp-right-col');
  if (!imgCol || !infoCard || !tabsWrap || !rightCol) return;
  const gap = parseInt(getComputedStyle(rightCol).gap) || 12;
  tabsWrap.style.height = (imgCol.offsetHeight - infoCard.offsetHeight - gap) + 'px';
}
gpFitTabs();
window.addEventListener('resize', gpFitTabs);
</script>
<script src="{{ asset('js/marketplace.js') }}"></script>
</body>
</html>
