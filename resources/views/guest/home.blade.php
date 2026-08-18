<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Marketplace — Guest</title>
<link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
</head>
<body class="marketplace">

<div class="market-top"><div class="container"><div>Welcome to PocketFinds Marketplace</div><div class="top-links"><span>Help Centre</span><span>Sell on PocketFinds</span><span>Download App</span></div></div></div>

<header class="market-header"><div class="container header-main">
  <a class="logo" href="{{ url('/') }}">
    <span class="logo-mark">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
    </span>
    <span>PocketFinds</span>
  </a>
  <div class="search">
    <input data-search type="search" placeholder="Search for products, brands and categories">
    <button type="button">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </button>
  </div>
  <div class="header-actions">
    <button class="icon-action" type="button" data-protected>
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
      <span>Wishlist</span>
    </button>
    <button class="icon-action" type="button" data-protected>
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      <span>Cart</span>
    </button>
    <a class="signin" href="{{ url('/login') }}">Sign In</a>
    <a class="register" href="{{ url('/register/type') }}">Register</a>
  </div>
</div></header>

<nav class="category-bar"><div class="container categories">
  <a class="category active" href="#">All Categories</a>
  <a class="category" href="#">Electronics</a>
  <a class="category" href="#">Fashion</a>
  <a class="category" href="#">Beauty</a>
  <a class="category" href="#">Home & Living</a>
  <a class="category" href="#">Gaming</a>
  <a class="category" href="#">Food</a>
  <a class="category" href="#">Sports</a>
  <a class="category" href="#">Health</a>
</div></nav>

<main class="container">

  <section class="hero">
    <div class="hero-grid">
      <div class="hero-main">
        <div class="hero-copy">
          <div class="eyebrow">Guest shopping</div>
          <h1>Discover products you'll love.</h1>
          <p>Browse products, explore categories, compare deals, and find something worth adding to your cart.</p>
          <a class="primary" href="#products">Explore products →</a>
        </div>
      </div>
      <div class="hero-side">
        <div class="promo"><strong>Flash Deals</strong><span>Fresh discounts updated throughout the day.</span></div>
        <div class="promo dark"><strong>New arrivals</strong><span>Discover the latest products from our sellers.</span></div>
      </div>
    </div>
  </section>

  <div class="guest-notice">
    <div><strong>You're browsing as a guest.</strong><span>Sign in to save items, checkout, and track orders.</span></div>
    <a class="notice-link" href="{{ url('/login') }}">Sign in now →</a>
  </div>

  <section class="section" id="products">
    <div class="section-head"><h2 class="section-title">Flash Deals</h2><a class="see-all" href="#">See All →</a></div>
    <div class="deal-strip"><div class="deal-grid">

      <article class="product" data-product="wireless earbuds">
        <div class="product-img">
          <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0118 0v6"/><path d="M21 19a2 2 0 01-2 2h-1a2 2 0 01-2-2v-3a2 2 0 012-2h3zM3 19a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2H3z"/></svg>
        </div>
        <div class="product-body"><span class="badge">20% OFF</span><div class="product-name">Wireless Earbuds Pro</div><div class="price">₱799 <span class="old-price">₱999</span></div><div class="meta"><span class="meta-rating">4.8</span><span>1.2k sold</span></div></div>
      </article>

      <article class="product" data-product="minimal backpack">
        <div class="product-img">
          <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        </div>
        <div class="product-body"><span class="badge">HOT</span><div class="product-name">Minimal Everyday Backpack</div><div class="price">₱549 <span class="old-price">₱699</span></div><div class="meta"><span class="meta-rating">4.7</span><span>856 sold</span></div></div>
      </article>

      <article class="product" data-product="smart watch">
        <div class="product-img">
          <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/><path d="M9 2h6M9 22h6"/></svg>
        </div>
        <div class="product-body"><span class="badge">15% OFF</span><div class="product-name">Smart Watch Series 5</div><div class="price">₱1,299 <span class="old-price">₱1,499</span></div><div class="meta"><span class="meta-rating">4.9</span><span>2.4k sold</span></div></div>
      </article>

      <article class="product" data-product="running shoes">
        <div class="product-img">
          <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l4-8 4 4 4-6 4 10H3z"/><path d="M3 17h18"/></svg>
        </div>
        <div class="product-body"><span class="badge">SALE</span><div class="product-name">Lightweight Running Shoes</div><div class="price">₱899 <span class="old-price">₱1,199</span></div><div class="meta"><span class="meta-rating">4.6</span><span>648 sold</span></div></div>
      </article>

      <article class="product" data-product="desk lamp">
        <div class="product-img">
          <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="9" y1="18" x2="15" y2="18"/><line x1="12" y1="2" x2="12" y2="9"/><path d="M4.22 10.22a8 8 0 1115.56 0"/></svg>
        </div>
        <div class="product-body"><span class="badge">NEW</span><div class="product-name">LED Desk Lamp</div><div class="price">₱399 <span class="old-price">₱499</span></div><div class="meta"><span class="meta-rating">4.8</span><span>731 sold</span></div></div>
      </article>

    </div></div>
  </section>

  <section class="section">
    <div class="section-head"><h2 class="section-title">Browse Categories</h2><a class="see-all" href="#">View All →</a></div>
    <div class="category-grid">

      <a class="cat-card" href="#">
        <span class="cat-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
        </span>
        <span class="cat-name">Electronics</span>
      </a>

      <a class="cat-card" href="#">
        <span class="cat-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l3-4h12l3 4-4 2v10H7V9L3 7z"/></svg>
        </span>
        <span class="cat-name">Fashion</span>
      </a>

      <a class="cat-card" href="#">
        <span class="cat-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a5 5 0 015 5c0 3-5 11-5 11S7 10 7 7a5 5 0 015-5z"/><circle cx="12" cy="7" r="2"/></svg>
        </span>
        <span class="cat-name">Beauty</span>
      </a>

      <a class="cat-card" href="#">
        <span class="cat-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9M5 10v9a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1v-9"/></svg>
        </span>
        <span class="cat-name">Home</span>
      </a>

      <a class="cat-card" href="#">
        <span class="cat-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12h.01M17 12h.01M7 12h.01"/></svg>
        </span>
        <span class="cat-name">Gaming</span>
      </a>

      <a class="cat-card" href="#">
        <span class="cat-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
        </span>
        <span class="cat-name">Food</span>
      </a>

    </div>
  </section>

  <section class="section">
    <div class="section-head"><h2 class="section-title">Recommended For You</h2><a class="see-all" href="#">See All →</a></div>
    <div class="deal-grid">

      <article class="product" data-product="phone case">
        <div class="product-img">
          <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
        </div>
        <div class="product-body"><div class="product-name">Premium Phone Case</div><div class="price">₱249</div><div class="meta"><span class="meta-rating">4.8</span><span>2k sold</span></div></div>
      </article>

      <article class="product" data-product="coffee tumbler">
        <div class="product-img">
          <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
        </div>
        <div class="product-body"><div class="product-name">Insulated Coffee Tumbler</div><div class="price">₱329</div><div class="meta"><span class="meta-rating">4.7</span><span>945 sold</span></div></div>
      </article>

      <article class="product" data-product="keyboard">
        <div class="product-img">
          <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M8 14h8"/></svg>
        </div>
        <div class="product-body"><div class="product-name">Mechanical Keyboard</div><div class="price">₱1,899</div><div class="meta"><span class="meta-rating">4.9</span><span>512 sold</span></div></div>
      </article>

      <article class="product" data-product="hoodie">
        <div class="product-img">
          <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l3-4h12l3 4-4 2v10H7V9L3 7z"/></svg>
        </div>
        <div class="product-body"><div class="product-name">Everyday Oversized Hoodie</div><div class="price">₱699</div><div class="meta"><span class="meta-rating">4.8</span><span>1.1k sold</span></div></div>
      </article>

      <article class="product" data-product="skincare set">
        <div class="product-img">
          <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2v-4M9 21H5a2 2 0 01-2-2v-4m0 0h18"/></svg>
        </div>
        <div class="product-body"><div class="product-name">Daily Skincare Set</div><div class="price">₱599</div><div class="meta"><span class="meta-rating">4.6</span><span>782 sold</span></div></div>
      </article>

    </div>
  </section>

</main>

<footer class="footer"><div class="container">
  <div class="footer-grid">
    <div><h3>PocketFinds Marketplace</h3><p>A simple marketplace experience for discovering products from local sellers.</p></div>
    <div><h3>Customer Service</h3><a href="#">Help Centre</a><a href="#">Contact Us</a><a href="#">Returns</a></div>
    <div><h3>About</h3><a href="#">About Us</a><a href="#">Careers</a><a href="#">Privacy</a></div>
    <div><h3>Account</h3><a href="{{ url('/login') }}">Sign In</a><a href="{{ url('/register/type') }}">Register</a><a href="#">Seller Centre</a></div>
  </div>
  <div class="footer-bottom">© {{ date('Y') }} PocketFinds. All rights reserved.</div>
</div></footer>

<script src="{{ asset('js/marketplace.js') }}"></script>
</body>
</html>
