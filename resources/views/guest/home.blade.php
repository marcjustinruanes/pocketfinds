<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>PocketFinds — Little finds. Big delight.</title>
<meta name="description" content="PocketFinds — discover real products from real local sellers.">
<link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
<link rel="stylesheet" href="{{ asset('css/marketplace.css') }}?v={{ filemtime(public_path('css/marketplace.css')) }}">
<link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
</head>
<body class="marketplace">

{{-- Real platform announcement when one is live for guests; otherwise the same generic
     welcome line this bar has always shown — never a fabricated promo. --}}
<div class="market-top"><div class="container"><div>{{ $announcement->title ?? 'Welcome to PocketFinds Marketplace' }}</div><div class="top-links"><span>Help Centre</span><span>Sell on PocketFinds</span></div></div></div>

<header class="market-header"><div class="container header-main">
<a class="logo" href="{{ url('/') }}"><span class="logo-mark"><x-brand-logo :size="16" /></span><span>PocketFinds</span></a>

<form class="search" action="{{ url('/') }}" method="GET">
  <input data-search name="q" type="search" placeholder="Search for products, brands and categories" value="{{ $search }}">
  <button type="submit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
</form>

<div class="header-actions">
<button class="icon-action" type="button" data-protected><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg><span>Wishlist</span></button>
<button class="icon-action" type="button" data-protected><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg><span>Cart</span></button>
<a class="signin" href="{{ url('/login') }}">Sign In</a><a class="register" href="{{ url('/register/type') }}">Register</a>
</div>
</div></header>

<main class="container">

  {{-- ── Hero ── real, computed stats — never a placeholder "12k+ shoppers" figure --}}
  <section class="lp-hero">
    <div>
      <div class="lp-eyebrow">Curated just for your pocket</div>
      <h1>Find little things<br><em>you'll love.</em></h1>
      <p>Fresh finds, honest prices, and small-business favorites — sourced straight from PocketFinds' own real sellers.</p>
      <div class="lp-hero-buttons">
        <a class="lp-btn lp-btn-primary" href="#products">Explore finds ↗</a>
        <a class="lp-btn lp-btn-ghost" href="{{ url('/register/type') }}">Sell on PocketFinds</a>
      </div>
      <div class="lp-stats">
        <div class="lp-stat"><strong>{{ $stats['products'] }}</strong><span>Products listed</span></div>
        <div class="lp-stat"><strong>{{ $stats['shops'] }}</strong><span>Local shops</span></div>
        <div class="lp-stat"><strong>{{ $stats['categories'] }}</strong><span>Categories</span></div>
      </div>
    </div>
    <div class="lp-art">
      <div class="lp-blob lp-blob-a"></div>
      <div class="lp-blob lp-blob-b"></div>
      <div class="lp-float lp-float-top"><strong>✦ New drop!</strong><small>Just landed</small></div>
      <div class="lp-stage"><div class="lp-mock"><x-brand-logo :size="72" /></div></div>
      <div class="lp-float lp-float-bottom"><strong>People are finding</strong><small>their new fave ♡</small></div>
    </div>
  </section>

  <div class="guest-notice"><div><strong>You're browsing as a guest.</strong><span>Sign in to save items, checkout, and track orders.</span></div><a class="notice-link" href="{{ url('/login') }}">Sign in now</a></div>

  <section class="lp-trust">
    <div class="lp-trust-item"><span class="lp-trust-icon">🚚</span><div><strong>Fast delivery</strong><span>Track every order</span></div></div>
    <div class="lp-trust-item"><span class="lp-trust-icon">🛡</span><div><strong>Buyer protected</strong><span>Shop with confidence</span></div></div>
    <div class="lp-trust-item"><span class="lp-trust-icon">💗</span><div><strong>Real sellers</strong><span>No fake listings</span></div></div>
    <div class="lp-trust-item"><span class="lp-trust-icon">↩</span><div><strong>Easy returns</strong><span>Simple & stress-free</span></div></div>
  </section>

  {{-- ── Categories — real records, real icon match, real links (see marketplace.js) ── --}}
  <section class="lp-section" id="categories">
    <div class="lp-section-head"><div><span class="lp-kicker">Browse the good stuff</span><h2>What's in your pocket?</h2></div></div>
    <div class="category-grid" id="browseCategories"><p class="cat-loading">Loading…</p></div>
  </section>

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

  {{-- ── Main product grid — real, filterable by real category / real search ── --}}
  <section class="lp-section" id="products">
    <div class="lp-section-head">
      <div>
        <span class="lp-kicker">Fresh on the shelf</span>
        <h2>
          @if($search) Results for "{{ $search }}"
          @elseif($activeCategory) {{ $activeCategory->name }}
          @else New Arrivals
          @endif
        </h2>
      </div>
      <div class="lp-pills">
        <a href="{{ url('/') }}" class="lp-pill {{ !$categoryId ? 'active' : '' }}">All</a>
        @foreach($categories as $cat)
        <a href="{{ url('/') }}?category={{ $cat->id }}" class="lp-pill {{ $categoryId === $cat->id ? 'active' : '' }}">{{ $cat->name }}</a>
        @endforeach
      </div>
    </div>
    <div class="lp-product-grid">
      @forelse($products as $p)
        @include('guest.partials.product-card', ['p' => $p])
      @empty
        <div class="lp-empty">
          @if($search || $categoryId)
            No products match this filter yet. <a href="{{ url('/') }}" style="color:var(--pink-dark);font-weight:700">Clear it</a> to see everything.
          @else
            No products available yet. Check back soon!
          @endif
        </div>
      @endforelse
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

<footer class="footer"><div class="container"><div class="footer-grid"><div><h3>PocketFinds Marketplace</h3><p>A simple marketplace experience for discovering products from local sellers.</p></div><div><h3>Customer Service</h3><a href="#">Help Centre</a><a href="#">Contact Us</a><a href="#">Returns</a></div><div><h3>About</h3><a href="#">About Us</a><a href="#">Careers</a><a href="#">Privacy</a></div><div><h3>Account</h3><a href="{{ url('/login') }}">Sign In</a><a href="{{ url('/register/type') }}">Register</a><a href="{{ url('/register/type') }}">Seller Centre</a></div></div><div class="footer-bottom">© {{ date('Y') }} PocketFinds. All rights reserved.</div></div></footer>

<nav class="mobile-nav">
  <a href="{{ url('/') }}"><span>⌂</span>Home</a>
  <a href="#categories"><span>◫</span>Categories</a>
  <button type="button" data-protected style="all:unset;display:flex;flex-direction:column;align-items:center;gap:2px"><span>♡</span>Wishlist</button>
  <a href="{{ url('/login') }}"><span>◯</span>Account</a>
</nav>

@include('guest.auth-modal')
<script src="{{ asset('js/marketplace.js') }}"></script>
</body></html>
