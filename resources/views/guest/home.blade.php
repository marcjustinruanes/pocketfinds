<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>PocketFinds — Find It. Love It. Pocket It.</title>
<meta name="description" content="PocketFinds — discover real products from verified local sellers. Simple, convenient, and enjoyable online shopping.">
<link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/marketplace.css') }}?v={{ filemtime(public_path('css/marketplace.css')) }}">
<link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
</head>
<body class="marketplace">

<div class="market-top"></div>

{{-- ── Header ── --}}
<header class="market-header">
  <div class="container header-main">

    <a class="logo" href="{{ url('/') }}">
      <span class="logo-mark">
        <img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img">
      </span>
      <span><span class="logo-pocket">Pocket</span><span class="logo-finds">Finds</span></span>
    </a>

    <div class="pf-search-wrap">
      <form class="search" action="{{ url('/') }}" method="GET">
        <span class="pf-search-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </span>
        <input data-search name="q" type="search" placeholder="Search products, brands, categories…" value="{{ $search ?? '' }}">
        <button type="submit">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
      </form>
    </div>

    <nav class="nav-links">
      <a class="nav-link" href="#categories">Categories</a>
      <a class="nav-link" href="#shops">Shops</a>
      <a class="nav-link" href="#deals">Deals</a>
      <a class="nav-link" href="#about">About</a>
    </nav>

    <div class="header-actions">
      <button class="pf-acct-icon" type="button" data-protected title="Cart">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
      </button>

      {{-- Account icon → dropdown with Sign In + Register --}}
      <div class="pf-account-btn" id="pf-acct-btn">
        <button class="pf-acct-icon" type="button" id="pf-acct-toggle" aria-label="Account">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </button>
        <div class="pf-acct-dropdown" id="pf-acct-dropdown">
          <a href="{{ url('/login') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Sign In
          </a>
          <div class="pf-acct-sep"></div>
          <a class="register-link" href="{{ url('/register/type') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            Create Account
          </a>
        </div>
      </div>
    </div>

  </div>
</header>

<main>

{{-- ── Hero — full-width editorial banner ── --}}
@php
  $heroImg      = ($heroSettings->hero_image ?? null) ? \Illuminate\Support\Facades\Storage::url($heroSettings->hero_image) : null;
  $heroLabel    = $heroSettings->hero_label    ?: 'Local Marketplace · Philippines';
  $heroTagline  = $heroSettings->hero_tagline  ?: 'Find It. Love It. Pocket It.';
  $heroSubtitle = $heroSettings->hero_subtitle ?: 'Browse products from verified local sellers — pet supplies, electronics, fashion, home essentials, and more.';
  $heroCtaText  = $heroSettings->hero_cta_text ?: 'Browse Products';
  $heroOverlay  = $heroSettings->hero_overlay  ?: 'dark';
@endphp

<section class="pf-hero-banner pf-overlay-{{ $heroOverlay }}"
  @if($heroImg)style="background-image:url('{{ $heroImg }}')"@endif>
  <div class="pf-hero-overlay"></div>
  <div class="pf-hero-content container">
    <div class="pf-hero-eyebrow">
      {{ $heroLabel }}
    </div>
    <h1 class="pf-hero-h1">{!! nl2br(e($heroTagline)) !!}</h1>
    <p class="pf-hero-p">{{ $heroSubtitle }}</p>
    <a class="pf-hero-cta" href="#products">
      {{ $heroCtaText }}
    </a>
  </div>
</section>

<div class="container" style="padding-top:14px">

  @if(!empty($dbError))
  <div style="margin-bottom:20px;padding:12px 18px;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;display:flex;align-items:center;gap:10px;font-size:13px;color:#92400e">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span>Trouble connecting to the database. Products will appear once the connection is restored.</span>
    <a href="{{ url('/') }}" style="margin-left:auto;white-space:nowrap;font-weight:700;color:#92400e;text-decoration:underline">Retry</a>
  </div>
  @endif

  {{-- ── Categories ── --}}
  <section class="pf-section" id="categories">
    <div class="pf-section-head">
      <div>
        <div class="pf-kicker">Explore</div>
        <h2 class="pf-section-title">Shop by Category</h2>
      </div>
    </div>
    <div class="category-grid" id="browseCategories">
      <p class="cat-loading">Loading…</p>
    </div>
  </section>

  {{-- ── Featured shops + Deals ── --}}
  @if(count($shops))
  <section class="pf-section" id="shops">
    <div class="pf-split-row">
      <div class="pf-split-col">
        <div class="pf-section-head">
          <div>
            <div class="pf-kicker">Meet the sellers</div>
            <h2 class="pf-section-title">Featured Shops</h2>
          </div>
        </div>
        <div class="pf-shop-row">
          @foreach($shops as $shop)
          <div class="pf-shop-card">
            <div class="pf-shop-av">{{ $shop['initial'] }}</div>
            <div class="pf-shop-name-row">
              <div class="pf-shop-name-line">
                <span class="pf-shop-name">{{ $shop['name'] }}</span>
                @if($shop['rating'])
                <span class="pf-shop-rating-pill">
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                  {{ $shop['rating'] }}
                </span>
                @endif
              </div>
              @if(($shop['sold'] ?? 0) > 0 || $shop['products_count'])
              <div class="pf-shop-stats-row">
                @if(($shop['sold'] ?? 0) > 0)
                <span class="pf-shop-count">{{ $shop['sold'] >= 1000 ? round($shop['sold']/1000,1).'k' : $shop['sold'] }} sold</span>
                @endif
                @if(($shop['sold'] ?? 0) > 0 && $shop['products_count'])
                <span class="pf-shop-dot">·</span>
                @endif
                @if($shop['products_count'])
                <span class="pf-shop-count">{{ $shop['products_count'] }} product{{ $shop['products_count'] === 1 ? '' : 's' }}</span>
                @endif
              </div>
              @endif
            </div>
            <a href="{{ route('guest.shop', $shop['slug']) }}" class="pf-shop-view-btn">View Shop</a>
          </div>
          @endforeach
        </div>
      </div>

      <div class="pf-split-col" id="deals">
        <div class="pf-section-head">
          <div>
            <div class="pf-kicker">Save more</div>
            <h2 class="pf-section-title">Deals</h2>
          </div>
        </div>
        <div class="pf-deal-row">
          @forelse($deals as $p)
          <a class="pf-product-card" href="{{ route('guest.product', $p['id']) }}">
            <div class="pf-product-media">
              @if($p['img'])
                <img src="{{ $p['img'] }}" alt="{{ $p['name'] }}" loading="lazy">
              @else
                <svg class="placeholder-icon" xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              @endif
              @if($p['badge'] ?? null)<span class="pf-badge pf-badge-deal">{{ $p['badge'] }}</span>@endif
            </div>
            <div class="pf-product-body">
              <div class="pf-product-name">{{ $p['name'] }}</div>
              <div>
                <span class="pf-product-price">₱{{ number_format($p['price']) }}</span>
                @if($p['old_price'] ?? null)<span class="pf-product-old-price">₱{{ number_format($p['old_price']) }}</span>@endif
              </div>
            </div>
          </a>
          @empty
          <p class="pf-deal-empty">No deals right now — check back soon.</p>
          @endforelse
        </div>
      </div>
    </div>
  </section>
  @endif

  {{-- ── All Products ── --}}
  <section class="pf-section" id="products">
    <div class="pf-section-head">
      <div>
        <div class="pf-kicker">
          @if($search) Results @elseif($activeCategory) {{ $activeCategory->name }} @else All products @endif
        </div>
        <h2 class="pf-section-title">
          @if($search) &ldquo;{{ $search }}&rdquo;
          @elseif($activeCategory) {{ $activeCategory->name }}
          @else Latest Listings
          @endif
        </h2>
      </div>
    </div>

    <div class="pf-product-grid">
      @forelse($products as $p)
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
      @empty
      <div style="grid-column:1/-1;text-align:center;padding:56px 20px">
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="color:var(--border);display:block;margin:0 auto 12px"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        @if($search || $categoryId)
          <p style="font-size:13.5px;color:var(--muted)">No products match this filter. <a href="{{ url('/') }}" style="color:var(--pink-dark);font-weight:700">Clear filter</a></p>
        @else
          <p style="font-size:13.5px;color:var(--muted)">No products yet — check back soon!</p>
        @endif
      </div>
      @endforelse
    </div>
  </section>

</div>{{-- end .container --}}
</main>

{{-- ── Footer ── --}}
<footer class="pf-footer" id="about">
  <div class="container">
    <div class="pf-footer-wrap">
      <div class="pf-footer-brand">
        <a class="logo" href="{{ url('/') }}">
          <span class="logo-mark" style="width:30px;height:30px">
            <img src="{{ asset('images/logo.png') }}" alt="PocketFinds" class="brand-logo-img">
          </span>
          <span><span class="logo-pocket">Pocket</span><span class="logo-finds">Finds</span></span>
        </a>
        <p>A convenient marketplace where customers discover and purchase products that fit their needs and budget.</p>
        <div class="pf-footer-tagline">"Find It. Love It. Pocket It."</div>
      </div>
      <div class="pf-footer-col">
        <h4>Shop</h4>
        <a href="#products">All Products</a>
        <a href="#categories">Categories</a>
        <a href="#shops">Shops</a>
        <a href="#deals">Deals</a>
      </div>
      <div class="pf-footer-col">
        <h4>Account</h4>
        <a href="{{ url('/login') }}">Sign In</a>
        <a href="{{ url('/register/type') }}">Register</a>
        <a href="{{ url('/register/type') }}">Sell on PocketFinds</a>
      </div>
      <div class="pf-footer-col">
        <h4>Help</h4>
        <a href="#">Help Centre</a>
        <a href="#">Contact Us</a>
        <a href="#">Returns</a>
      </div>
    </div>
    <div class="pf-footer-bottom">
      <span>© {{ date('Y') }} PocketFinds. All rights reserved.</span>
      <div class="pf-footer-legal">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
      </div>
    </div>
  </div>
</footer>

{{-- ── Mobile bottom nav ── --}}
<nav class="mobile-nav">
  <a href="{{ url('/') }}">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    Home
  </a>
  <a href="#categories">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Categories
  </a>
  <button type="button" data-protected style="all:unset;display:flex;flex-direction:column;align-items:center;gap:3px;cursor:pointer;font-size:10px;color:var(--muted);font-weight:600">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
    Wishlist
  </button>
  <a href="{{ url('/login') }}">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    Account
  </a>
</nav>

@include('guest.auth-modal')
<script src="{{ asset('js/marketplace.js') }}?v={{ @filemtime(public_path('js/marketplace.js')) ?: 1 }}"></script>
<script>
// ── Account dropdown toggle ──────────────────────────────────────────────────
(function () {
  const btn  = document.getElementById('pf-acct-btn');
  const toggle = document.getElementById('pf-acct-toggle');
  if (!btn || !toggle) return;
  toggle.addEventListener('click', function (e) {
    e.stopPropagation();
    btn.classList.toggle('open');
  });
  document.addEventListener('click', function (e) {
    if (!btn.contains(e.target)) btn.classList.remove('open');
  });
})();

// ── Horizontal slideshow with dot indicators ─────────────────────────────────
// ── Account dropdown and search handled by marketplace.js ──
</script>
</body>
</html>
