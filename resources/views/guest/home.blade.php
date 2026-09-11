<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>PocketFinds — Little finds. Big delight.</title>
<meta name="description" content="PocketFinds — discover real products from real local sellers.">
<link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
<link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
<link rel="stylesheet" href="{{ asset('css/marketplace.css') }}?v={{ filemtime(public_path('css/marketplace.css')) }}">
</head>
<body class="marketplace">

{{-- Real platform announcement when one is live for guests; otherwise the same generic
     welcome line this bar has always shown — never a fabricated promo. --}}
<div class="market-top"><div class="container"><div>{{ $announcement->title ?? 'Welcome to PocketFinds Marketplace' }}</div><div class="top-links"><span>Help Centre</span><span>Sell on PocketFinds</span></div></div></div>

<header class="market-header"><div class="container header-main">
<a class="logo" href="{{ url('/') }}"><span class="logo-mark"><img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img"></span><span><span class="logo-pocket">Pocket</span><span class="logo-finds">Finds</span></span></a>

<form class="search" action="{{ url('/') }}" method="GET">
  <input data-search name="q" type="search" placeholder="Search for products, brands and categories" value="{{ $search }}">
  <button type="submit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
</form>

<nav class="nav-links">
  <a class="nav-link" href="#products">Shop</a>
  <a class="nav-link" href="#categories">Categories</a>
  <a class="nav-link" href="#deals">Deals</a>
  <a class="nav-link" href="#about">About</a>
</nav>

<div class="header-actions">
<button class="icon-action" type="button" data-protected><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg><span>Cart</span></button>
<a class="login-btn" href="{{ url('/login') }}">Login</a>
</div>
</div></header>

<main class="container">
@php
  $heroProducts = collect($products)->filter(fn ($p) => !empty($p['img']))->take(6)->values();
@endphp
<section class="hero"><div class="hero-grid"><div class="hero-main"><div class="hero-copy">
  <div class="eyebrow">Guest shopping</div>
  <h1>Discover products <span class="accent">you'll love.</span></h1>
  <p>Browse products, explore categories, compare deals, and find something worth adding to your cart.</p>
  <div class="lp-hero-buttons" style="margin-top:16px">
    <a class="primary" href="#products">Explore products</a>
    <a class="lp-btn lp-btn-ghost" href="{{ url('/register/type') }}">Sell on PocketFinds</a>
  </div>
</div></div><div class="hero-side">
  <div class="hero-visual">
    <div class="hero-photo-frame" id="heroPhotoFrame">
      @forelse($heroProducts as $hp)
        <img class="hero-slide {{ $loop->first ? 'active' : '' }}" src="{{ $hp['img'] }}" alt="{{ $hp['name'] }}">
      @empty
        <svg class="ph-icon" xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      @endforelse
    </div>
    <span class="hero-script">Good Finds<br>Brighter Days</span>
  </div>
</div></div></section>

<div class="guest-notice"><div><strong>You're browsing as a guest.</strong><span>Sign in to save items, checkout, and track orders.</span></div><a class="notice-link" href="{{ url('/login') }}">Sign in now</a></div>

<section class="lp-trust">
  <div class="lp-trust-item"><span class="lp-trust-icon">🚚</span><div><strong>Fast delivery</strong><span>Track every order</span></div></div>
  <div class="lp-trust-item"><span class="lp-trust-icon">🛡</span><div><strong>Buyer protected</strong><span>Shop with confidence</span></div></div>
  <div class="lp-trust-item"><span class="lp-trust-icon">💗</span><div><strong>Real sellers</strong><span>No fake listings</span></div></div>
  <div class="lp-trust-item"><span class="lp-trust-icon">↩</span><div><strong>Easy returns</strong><span>Simple & stress-free</span></div></div>
</section>

<section class="section" id="categories"><div class="section-head"><h2 class="section-title">Shop by Category</h2></div><div class="category-grid" id="browseCategories"><p class="cat-loading">Loading…</p></div></section>

{{-- ── Deals — only real markdowns; hidden entirely when nothing is actually discounted ── --}}
@if(count($deals))
<section class="lp-section" id="deals">
  <div class="lp-section-head"><div><span class="lp-kicker">Don't blink</span><h2>Today's Deals</h2></div></div>
  <div class="lp-product-grid">
    @foreach($deals as $p)
      @include('guest.partials.product-card', ['p' => $p])
    @endforeach
  </div>
</section>
@endif

{{-- ── Featured shops — real sellers, real product counts, real ratings (or none yet) ── --}}
@if(count($shops))
<section class="lp-section" id="shops">
  <div class="lp-section-head"><div><span class="lp-kicker">Meet the pocket makers</span><h2>Featured shops</h2></div></div>
  <div class="lp-shop-grid">
    @foreach($shops as $shop)
    <a href="{{ route('guest.shop', $shop['slug']) }}" class="lp-shop-card">
      <div class="lp-shop-avatar">{{ $shop['initial'] }}</div>
      <strong>{{ $shop['name'] }}</strong>
      @if($shop['rating'])
      <span class="lp-shop-rating">★ {{ $shop['rating'] }}</span>
      @endif
      <span class="lp-shop-meta">{{ $shop['products_count'] }} product{{ $shop['products_count'] === 1 ? '' : 's' }} · Since {{ $shop['joined'] }}</span>
    </a>
    @endforeach
  </div>
</section>
@endif

<section class="section" id="products">
  <div class="section-head"><h2 class="section-title">
      @if($search) Results for "{{ $search }}"
      @elseif($activeCategory) {{ $activeCategory->name }}
      @else Featured Products
      @endif
    </h2><a class="see-all" href="#products">See all</a></div>
  {{-- Real category filter pills — reloads with ?category= (currently unused server-side data, now wired up) --}}
  <div class="lp-pills" style="margin:-6px 0 16px">
    <a href="{{ url('/') }}" class="lp-pill {{ !$categoryId ? 'active' : '' }}">All</a>
    @foreach($categories as $cat)
    <a href="{{ url('/') }}?category={{ $cat->id }}" class="lp-pill {{ $categoryId === $cat->id ? 'active' : '' }}">{{ $cat->name }}</a>
    @endforeach
  </div>
  <div class="deal-strip"><div class="deal-grid">
    @forelse($products as $p)
    <article class="product" data-product="{{ strtolower($p['name']) }}" onclick="window.location='{{ route('guest.product', $p['id']) }}'">
      <div class="product-img">
        @if($p['img'])
          <img src="{{ $p['img'] }}" alt="{{ $p['name'] }}">
        @else
          <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        @endif
      </div>
      <div class="product-body">
        <div class="product-name">{{ $p['name'] }}</div>
        <div class="product-price-row">
          <span class="price">₱{{ number_format($p['price']) }}</span>
          <span class="rating-pill">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            {{ $p['rating'] > 0 ? number_format($p['rating'],1) : 'New' }}
          </span>
        </div>
        @if(!empty($p['location']))
        <div class="product-location">
          <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          {{ $p['location'] }}
        </div>
        @endif
        <div class="product-actions">
          <button class="btn-cart" type="button" data-protected>
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg> Cart
          </button>
          <button class="btn-buy" type="button" data-protected>Buy Now</button>
        </div>
      </div>
    </article>
    @empty
    <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:#888">
      @if($search || $categoryId)
        <p style="font-size:14px">No products match this filter yet. <a href="{{ url('/') }}" style="color:var(--pink-dark);font-weight:700">Clear it</a> to see everything.</p>
      @else
        <p style="font-size:14px">No products available yet. Check back soon!</p>
      @endif
    </div>
    @endforelse
  </div></div>
</section>

<section class="section" id="promo">
  <div class="deals-grid">
    <div class="deal-card tint-a">
      <span class="deal-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 12 22l-9-9 8.59-8.59A2 2 0 0113 4h7v7a2 2 0 01-.41.59z"/><circle cx="16.5" cy="7.5" r="1.5"/></svg></span>
      <div class="deal-copy"><h3><span class="accent">Deals</span> for Every You</h3><p>Great finds. Greater days.</p></div>
    </div>
    <div class="deal-card tint-b">
      <span class="deal-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1-5h16l1 5M4 9v10a1 1 0 001 1h14a1 1 0 001-1V9M4 9a2 2 0 004 0 2 2 0 004 0 2 2 0 004 0 2 2 0 004 0"/></svg></span>
      <div class="deal-copy"><h3>Shop <span class="accent">Local</span></h3></div>
      <a class="deal-cta" href="#products">Explore more <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
    </div>
  </div>
</section>

{{-- ── Join CTA — a real link into registration, not a fake newsletter capture ── --}}
<section class="lp-section">
  <div class="lp-join">
    <div><h2>Small shops. Big personality.</h2><p>Bring your own products to PocketFinds and reach real local buyers.</p></div>
    <a href="{{ url('/register/type') }}">Sell on PocketFinds →</a>
  </div>
</section>
</main>

<footer class="footer" id="about"><div class="container"><div class="footer-grid"><div><h3>PocketFinds Marketplace</h3><p>A simple marketplace experience for discovering products from local sellers.</p></div><div><h3>Customer Service</h3><a href="#">Help Centre</a><a href="#">Contact Us</a><a href="#">Returns</a></div><div><h3>About</h3><a href="#">About Us</a><a href="#">Careers</a><a href="#">Privacy</a></div><div><h3>Account</h3><a href="{{ url('/login') }}">Sign In</a><a href="{{ url('/register/type') }}">Register</a><a href="{{ url('/register/type') }}">Seller Centre</a></div></div><div class="footer-bottom">© {{ date('Y') }} PocketFinds. All rights reserved.</div></div></footer>

<nav class="mobile-nav">
  <a href="{{ url('/') }}"><span>⌂</span>Home</a>
  <a href="#categories"><span>◫</span>Categories</a>
  <button type="button" data-protected style="all:unset;display:flex;flex-direction:column;align-items:center;gap:2px"><span>♡</span>Wishlist</button>
  <a href="{{ url('/login') }}"><span>◯</span>Account</a>
</nav>

@include('guest.auth-modal')
<script src="{{ asset('js/marketplace.js') }}"></script>
</body>
</html>
