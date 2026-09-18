<!DOCTYPE html>
<html lang="en">
<head>
<script>document.documentElement.classList.add('js');</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>PocketFinds: Find It. Love It. Pocket It.</title>
<meta name="description" content="PocketFinds: discover real products from verified local sellers. Simple, convenient, and enjoyable online shopping.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="{{ url('/') }}">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url('/') }}">
<meta property="og:title" content="PocketFinds: Find It. Love It. Pocket It.">
<meta property="og:description" content="Discover real products from verified local sellers. Simple, convenient, and enjoyable online shopping.">
<meta property="og:image" content="{{ asset('images/logo.png') }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="PocketFinds: Find It. Love It. Pocket It.">
<meta name="twitter:description" content="Discover real products from verified local sellers. Simple, convenient, and enjoyable online shopping.">
<meta name="twitter:image" content="{{ asset('images/logo.png') }}">
<link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/marketplace.css') }}?v={{ @filemtime(public_path('css/marketplace.css')) ?: 1 }}">
<link rel="stylesheet" href="{{ asset('css/landing-editorial.css') }}?v={{ @filemtime(public_path('css/landing-editorial.css')) ?: 1 }}">
</head>
<body class="pfx-page">

{{-- ── Top ticker — real facts only ── --}}
<div class="pfx-ticker">
  <div class="container pfx-ticker-row">
    <span class="pfx-ticker-item"><span class="pfx-dot"></span>{{ $stats['categories'] }} Categories</span>
    <span class="pfx-ticker-item">{{ $stats['shops'] }} Verified Sellers</span>
    <span class="pfx-ticker-item">Cash on Delivery</span>
    <span class="pfx-ticker-item">Philippines</span>
  </div>
</div>

{{-- ── Header ── --}}
<header class="pfx-header">
  <div class="container pfx-header-row">

    <a class="pfx-logo" href="{{ url('/') }}">
      <span class="pfx-logo-mark">
        <img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds">
      </span>
      <span class="pfx-logo-word">Pocket<span>Finds</span></span>
    </a>

    <nav class="pfx-nav">
      <a href="#categories">Categories</a>
      <a href="#shops">Shops</a>
      @if(count($deals))<a href="#deals">Deals</a>@endif
      <a href="#about">About</a>
    </nav>

    <div class="pfx-header-actions">
      <form class="pfx-search" action="{{ url('/') }}" method="GET" role="search">
        <button type="submit" class="pfx-search-submit" aria-label="Search">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
        <input name="q" type="search" placeholder="Search products, brands, categories…" value="{{ $search ?? '' }}" aria-label="Search products, brands, and categories">
      </form>

      <button class="pfx-icon-btn" type="button" data-protected title="Wishlist" aria-label="Wishlist">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
      </button>
      <button class="pfx-icon-btn" type="button" data-protected title="Cart" aria-label="Cart">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
      </button>

      {{-- Account icon → dropdown with Sign In + Register (same ids as before, restyled) --}}
      <div class="pfx-account-btn" id="pf-acct-btn">
        <button class="pfx-icon-btn" type="button" id="pf-acct-toggle" aria-label="Account">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </button>
        <div class="pfx-acct-dropdown" id="pf-acct-dropdown">
          <a href="{{ url('/login') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Sign In
          </a>
          <div class="pfx-acct-sep"></div>
          <a class="pfx-register-link" href="{{ url('/register/type') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            Create Account
          </a>
        </div>
      </div>
    </div>

  </div>
</header>

<main>

{{-- ── Hero — split editorial layout: real admin-configured copy + real image ── --}}
@php
  $heroImg      = ($heroSettings->hero_image ?? null) ? \Illuminate\Support\Facades\Storage::url($heroSettings->hero_image) : null;
  $heroLabel    = $heroSettings->hero_label    ?: 'Local Marketplace · Philippines';
  $heroTagline  = $heroSettings->hero_tagline  ?: 'Find It. Love It. Pocket It.';
  $heroSubtitle = $heroSettings->hero_subtitle ?: 'Browse products from verified local sellers: pet supplies, electronics, fashion, home essentials, and more.';
  $heroCtaText  = $heroSettings->hero_cta_text ?: 'Browse Products';
@endphp

<section class="pfx-hero">
  <div class="container pfx-hero-grid">
    <div>
      <div class="pfx-eyebrow"><span class="pfx-dot"></span>{{ $heroLabel }}</div>
      <h1 class="pfx-hero-h1">{!! nl2br(e($heroTagline)) !!}</h1>
      <p class="pfx-hero-p">{{ $heroSubtitle }}</p>
      <div class="pfx-hero-actions">
        <a class="pfx-btn pfx-btn-primary" href="#products">{{ $heroCtaText }}</a>
        <a class="pfx-btn pfx-btn-text" href="#categories">
          <span>Browse Categories</span>
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>
    </div>

    <div>
      @if($heroImg)
      <div class="pfx-hero-frame" style="--hero-bg-img:url('{{ $heroImg }}')">
        <div class="pfx-hero-frame-img"></div>
        <div class="pfx-hero-caption">
          <span class="pfx-dot-sm"></span>
          <span>{{ number_format($stats['products']) }} products from {{ number_format($stats['shops']) }} verified sellers</span>
        </div>
      </div>
      @else
      <div class="pfx-hero-frame pfx-hero-frame-empty">
        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        <div class="pfx-hero-caption">
          <span class="pfx-dot-sm"></span>
          <span>{{ number_format($stats['products']) }} products from {{ number_format($stats['shops']) }} verified sellers</span>
        </div>
      </div>
      @endif
    </div>
  </div>
</section>

<div class="container">

  @if(!empty($dbError))
  <div style="margin-bottom:20px;padding:12px 18px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;display:flex;align-items:center;gap:10px;font-size:13px;color:#92400e">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span>Trouble connecting to the database. Products will appear once the connection is restored.</span>
    <a href="{{ url('/') }}" style="margin-left:auto;white-space:nowrap;font-weight:700;color:#92400e;text-decoration:underline">Retry</a>
  </div>
  @endif

</div>

{{-- ── Shop by Category — real categories, real per-category counts, real products.
     Pills are genuine links to the same ?category= filter the "Latest Listings"
     section already understands, so picking one is a real, working category
     browse, not a client-only illusion. The in-stock toggle and sort control
     only rearrange the curated preview below; they don't claim to touch the
     full catalog. ── --}}
@php $showcase = array_slice($products, 0, 7); @endphp
<section class="pfx-section pfx-section-tint" id="categories">
  <div class="container">
    <div class="pfx-section-head pfx-cat-head pf-reveal">
      <span class="pfx-kicker">Taxonomy</span>
      <h2 class="pfx-h2">Shop by Category</h2>
      <p class="pfx-section-desc">Real listings from verified local sellers, organized the way you actually shop.</p>
    </div>

    <div class="pfx-cat-toolbar pf-reveal">
      <nav class="pfx-cat-pills" aria-label="Filter by category">
        <a href="{{ url('/') }}" class="pfx-cat-pill" @if(!$activeCategory) aria-current="page" @endif>All <span class="pfx-cat-pill-count">{{ $stats['products'] }}</span></a>
        @foreach($categories as $c)
        <a href="{{ url('/') }}?category={{ $c->id }}" class="pfx-cat-pill" @if($activeCategory && $activeCategory->id === $c->id) aria-current="page" @endif>{{ $c->name }} <span class="pfx-cat-pill-count">{{ $c->products_count }}</span></a>
        @endforeach
      </nav>
      <div class="pfx-cat-controls">
        <label class="pfx-cat-check">
          <input type="checkbox" data-instock-toggle>
          <span>In stock only</span>
        </label>
        <label class="pfx-cat-sort">
          <span>Sort</span>
          <select data-sort-select>
            <option value="newest">Newest first</option>
            <option value="price_asc">Price: low to high</option>
            <option value="price_desc">Price: high to low</option>
            <option value="rating">Top rated</option>
          </select>
        </label>
        <span class="pfx-cat-preview-note">Filters preview only</span>
      </div>
    </div>

    @if(count($showcase))
    <p class="pfx-cat-status"><span data-visible-count>{{ count($showcase) }}</span> of <span data-total-count>{{ count($showcase) }}</span> shown</p>
    <div class="pfx-showcase pf-stagger-group" data-showcase>
      <div class="pfx-showcase-hero-slot" data-hero-slot>
        @include('guest.partials.showcase-item', ['item' => $showcase[0]])
      </div>
      <div class="pfx-showcase-list" data-list-slot>
        @foreach(array_slice($showcase, 1) as $item)
          @include('guest.partials.showcase-item', ['item' => $item])
        @endforeach
      </div>
      <p class="pfx-showcase-empty" data-showcase-empty hidden>Nothing here with that filter. <button type="button" data-clear-instock>Clear it</button></p>
    </div>
    @else
    <div class="pfx-showcase-empty-state pf-reveal">
      <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      @if($activeCategory)
        <p>No products in {{ $activeCategory->name }} yet. <a href="{{ url('/') }}">Browse everything</a></p>
      @else
        <p>No products yet. Check back soon.</p>
      @endif
    </div>
    @endif
  </div>
</section>

{{-- ── Today's Deals — real deals only; the section doesn't exist when there's
     nothing to feature, rather than showing an empty "no deals" banner ── --}}
@if(count($deals))
<section class="pfx-section" id="deals">
  <div class="container">
    <div class="pfx-deals-panel pf-reveal">
      <div class="pfx-section-head">
        <div>
          <span class="pfx-kicker">Limited stock</span>
          <h2 class="pfx-h2">Today's Deals</h2>
        </div>
        @if(count($deals) > 1)
        <div class="pfx-carousel-nav">
          <button type="button" class="pfx-carousel-btn" data-carousel-prev aria-label="Scroll to previous deal">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <button type="button" class="pfx-carousel-btn" data-carousel-next aria-label="Scroll to next deal">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
        @endif
      </div>
      <div class="pfx-product-grid pfx-carousel pf-stagger-group" data-carousel>
        @foreach($deals as $p)
          @include('guest.partials.product-tile', ['p' => $p])
        @endforeach
      </div>
    </div>
  </div>
</section>
@endif

{{-- ── Top Selling Shops — real sellers, ranked by real units sold ── --}}
@if(count($shops))
<section class="pfx-section" id="shops">
  <div class="container">
    <div class="pfx-section-head pfx-section-head-stack pf-reveal">
      <div>
        <span class="pfx-kicker">Ranked by real sales</span>
        <h2 class="pfx-h2">Top Selling Shops</h2>
      </div>
      <p class="pfx-section-desc">The sellers moving the most product on PocketFinds right now.</p>
    </div>
    <div class="pfx-shop-row">
      @foreach($shops as $shop)
      <div class="pfx-shop-card">
        <div class="pfx-shop-av">{{ $shop['initial'] }}</div>
        <div class="pfx-shop-info">
          <div class="pfx-shop-name-row">
            <span class="pfx-shop-name">{{ $shop['name'] }}</span>
            @if($shop['rating'])
            <span class="pfx-shop-rating">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="#f59e0b" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              {{ $shop['rating'] }}
            </span>
            @endif
          </div>
          @if(($shop['sold'] ?? 0) > 0 || $shop['products_count'])
          <div class="pfx-shop-meta">
            @if(($shop['sold'] ?? 0) > 0){{ $shop['sold'] >= 1000 ? round($shop['sold']/1000,1).'k' : $shop['sold'] }} sold @endif
            @if(($shop['sold'] ?? 0) > 0 && $shop['products_count']) · @endif
            @if($shop['products_count']){{ $shop['products_count'] }} product{{ $shop['products_count'] === 1 ? '' : 's' }}@endif
          </div>
          @endif
        </div>
        <a href="{{ route('guest.shop', $shop['slug']) }}" class="pfx-shop-btn">View Shop</a>
      </div>
      @endforeach
    </div>
  </div>
</section>
@endif

{{-- ── Latest Listings — every real, active, seller-approved product ── --}}
<section class="pfx-section pfx-section-tint" id="products">
  <div class="container">
    <div class="pfx-section-head pf-reveal">
      <div>
        @if($search || $activeCategory)
        <span class="pfx-kicker">{{ $search ? 'Search results' : 'Category' }}</span>
        @else
        <span class="pfx-kicker">Small batch, real inventory</span>
        @endif
        <h2 class="pfx-h2">
          @if($search) &ldquo;{{ $search }}&rdquo;
          @elseif($activeCategory) {{ $activeCategory->name }}
          @else Latest Listings
          @endif
        </h2>
      </div>
    </div>

    <div class="pfx-product-grid pf-stagger-group">
      @forelse($products as $p)
        @include('guest.partials.product-tile', ['p' => $p])
      @empty
      <div style="grid-column:1/-1;text-align:center;padding:56px 20px">
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="color:var(--pfx-border);display:block;margin:0 auto 12px"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        @if($search || $categoryId)
          <p style="font-size:13.5px;color:var(--pfx-muted)">No products match this filter. <a href="{{ url('/') }}" style="color:var(--pfx-accent-dark);font-weight:700">Clear filter</a></p>
        @else
          <p style="font-size:13.5px;color:var(--pfx-muted)">No products yet. Check back soon!</p>
        @endif
      </div>
      @endforelse
    </div>
  </div>
</section>

{{-- ── Trust — real stats and real features, honestly framed ── --}}
<section class="pfx-section" id="about">
  <div class="container pfx-trust-grid">
    <div class="pfx-trust-col pf-reveal">
      <span class="pfx-kicker">Why PocketFinds</span>
      <h2 class="pfx-trust-quote">Local sellers, real listings, no filler.</h2>
      <p class="pfx-trust-body">Every shop on PocketFinds is a real, verified local seller. Every count on this page is a live number from our own database, not a marketing estimate.</p>
      <div class="pfx-trust-byline">
        <div class="pfx-trust-byline-mark">PF</div>
        <div>
          <strong>PocketFinds Team</strong>
          <span>Philippines</span>
        </div>
      </div>
    </div>
    <div class="pfx-pillars">
      <div class="pfx-pillar pf-reveal">
        <span class="pfx-pillar-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9M3 9v11a1 1 0 001 1h16a1 1 0 001-1V9M3 9h18M9 21v-6h6v6"/></svg>
        </span>
        <div>
          <h3>{{ number_format($stats['shops']) }} verified sellers</h3>
          <p>Every seller on PocketFinds goes through an approval step before their shop goes live.</p>
        </div>
      </div>
      <div class="pfx-pillar pf-reveal">
        <span class="pfx-pillar-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9v.01M18 15v.01"/></svg>
        </span>
        <div>
          <h3>Cash on delivery</h3>
          <p>Pay in cash when your order arrives, no online payment setup needed.</p>
        </div>
      </div>
      <div class="pfx-pillar pf-reveal">
        <span class="pfx-pillar-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 7.28L12 12l-8.5-4.72M12 22V12"/><path d="M20.5 7.28l-8.16-4.54a1 1 0 00-.98 0L3.5 7.28a1 1 0 00-.5.87v7.7a1 1 0 00.5.87l8.16 4.54a1 1 0 00.98 0l8.16-4.54a1 1 0 00.5-.87v-7.7a1 1 0 00-.5-.87z"/></svg>
        </span>
        <div>
          <h3>{{ number_format($stats['products']) }} products listed</h3>
          <p>Browse real, current inventory across {{ number_format($stats['categories']) }} categories.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="container">
  {{-- ── Sell CTA — real, working seller-recruitment path ── --}}
  <div class="pfx-cta-banner pf-reveal">
    <div>
      <div class="pfx-cta-kicker">For sellers</div>
      <h2>Start Selling on PocketFinds</h2>
      <p>List your products, reach local buyers, and manage orders from one seller dashboard.</p>
    </div>
    <div class="pfx-cta-actions">
      <a class="pfx-btn pfx-cta-white" href="{{ route('register.seller') }}">Become a Seller</a>
      <a class="pfx-btn pfx-cta-ghost" href="{{ url('/login') }}">Already selling? Sign in</a>
    </div>
  </div>
</div>

</main>

{{-- ── Footer ── --}}
<footer class="pfx-footer">
  <div class="container">
    <div class="pfx-footer-grid">
      <div class="pfx-footer-brand">
        <a class="pfx-logo" href="{{ url('/') }}">
          <span class="pfx-logo-mark">
            <img src="{{ asset('images/logo.png') }}" alt="PocketFinds">
          </span>
          <span class="pfx-logo-word">Pocket<span>Finds</span></span>
        </a>
        <p>A convenient marketplace where customers discover and purchase products that fit their needs and budget.</p>
      </div>
      <div class="pfx-footer-col">
        <span>Shop</span>
        <a href="#products">All Products</a>
        <a href="#categories">Categories</a>
        <a href="#shops">Shops</a>
        @if(count($deals))<a href="#deals">Deals</a>@endif
      </div>
      <div class="pfx-footer-col">
        <span>Account</span>
        <a href="{{ url('/login') }}">Sign In</a>
        <a href="{{ url('/register/type') }}">Register</a>
        <a href="{{ route('register.seller') }}">Sell on PocketFinds</a>
      </div>
      <div class="pfx-footer-col">
        <span>Help</span>
        <a href="#">Help Centre</a>
        <a href="#">Contact Us</a>
        <a href="#">Returns</a>
      </div>
    </div>
    <div class="pfx-footer-bottom">
      <span>© {{ date('Y') }} PocketFinds. All rights reserved.</span>
      <div class="pfx-footer-legal">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
      </div>
    </div>
  </div>
</footer>

{{-- ── Mobile bottom nav ── --}}
<nav class="pfx-mobile-nav">
  <a href="{{ url('/') }}">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    Home
  </a>
  <a href="#categories">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Categories
  </a>
  <button type="button" data-protected class="pfx-mobile-nav-btn">
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
</script>
</body>
</html>
