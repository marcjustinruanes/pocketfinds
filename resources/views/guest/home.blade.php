<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Marketplace — PocketFinds</title>
<link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
</head>
<body class="marketplace">
<div class="market-top"><div class="container"><div>Welcome to PocketFinds Marketplace</div><div class="top-links"><span>Help Centre</span><span>Sell on PocketFinds</span><span>Download App</span></div></div></div>
<header class="market-header"><div class="container header-main">
<a class="logo" href="{{ url('/') }}"><span class="logo-mark"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span><span>PocketFinds</span></a>
<div class="search"><input data-search type="search" placeholder="Search for products, brands and categories"><button type="button"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button></div>
<div class="header-actions">
<button class="icon-action" type="button" data-protected><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg><span>Wishlist</span></button>
<button class="icon-action" type="button" data-protected><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg><span>Cart</span></button>
<a class="signin" href="{{ url('/login') }}">Sign In</a><a class="register" href="{{ url('/register/type') }}">Register</a>
</div>
</div></header>

<main class="container">
<section class="hero"><div class="hero-grid"><div class="hero-main"><div class="hero-copy"><div class="eyebrow">Guest shopping</div><h1>Discover products you'll love.</h1><p>Browse products, explore categories, compare deals, and find something worth adding to your cart.</p><a class="primary" href="#products">Explore products</a></div></div><div class="hero-side">
  <div class="promo">
    <span class="promo-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg></span>
    <strong>New Arrivals</strong><span>Fresh products from local sellers.</span>
  </div>
  <div class="promo dark">
    <span class="promo-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-5"/></svg></span>
    <strong>Real Products</strong><span>Discover authentic items from verified sellers.</span>
  </div>
</div></div></section>

<div class="guest-notice"><div><strong>You're browsing as a guest.</strong><span>Sign in to save items, checkout, and track orders.</span></div><a class="notice-link" href="{{ url('/login') }}">Sign in now</a></div>

<section class="section"><div class="section-head"><h2 class="section-title">Browse Categories</h2><a class="see-all" href="#">See All</a></div><div class="category-grid" id="browseCategories"><p class="cat-loading">Loading…</p></div></section>

<section class="section" id="products">
  <div class="section-head"><h2 class="section-title">All Products</h2></div>
  <div class="deal-strip"><div class="deal-grid">
    @forelse($products as $p)
    <article class="product" data-product="{{ strtolower($p['name']) }}" onclick="window.location='{{ route('guest.product', $p['id']) }}'">
      <div class="product-img">
        @if($p['img'])
          <img src="{{ $p['img'] }}" alt="{{ $p['name'] }}" style="width:100%;height:100%;object-fit:cover;border-radius:8px">
        @else
          <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        @endif
      </div>
      <div class="product-body">
        <div class="product-name">{{ $p['name'] }}</div>
        @if(!empty($p['location']))
        <div style="display:inline-flex;align-items:center;gap:3px;font-size:11px;color:#888;margin:3px 0">
          <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          {{ $p['location'] }}
        </div>
        @endif
        <div style="display:inline-flex;align-items:center;gap:3px;font-size:11px;color:#888;margin-bottom:4px">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="#f59e0b" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          {{ $p['rating'] > 0 ? number_format($p['rating'],1) : 'New' }}
        </div>
        <div class="price">₱{{ number_format($p['price']) }}</div>
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
      <p style="font-size:14px">No products available yet. Check back soon!</p>
    </div>
    @endforelse
  </div></div>
</section>
</main>

<footer class="footer"><div class="container"><div class="footer-grid"><div><h3>PocketFinds Marketplace</h3><p>A simple marketplace experience for discovering products from local sellers.</p></div><div><h3>Customer Service</h3><a href="#">Help Centre</a><a href="#">Contact Us</a><a href="#">Returns</a></div><div><h3>About</h3><a href="#">About Us</a><a href="#">Careers</a><a href="#">Privacy</a></div><div><h3>Account</h3><a href="{{ url('/login') }}">Sign In</a><a href="{{ url('/register/type') }}">Register</a><a href="#">Seller Centre</a></div></div><div class="footer-bottom">© {{ date('Y') }} PocketFinds. All rights reserved.</div></div></footer>
@include('guest.auth-modal')
<script src="{{ asset('js/marketplace.js') }}"></script>
</body></html>
