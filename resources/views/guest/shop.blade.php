<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{{ $shop['name'] }} — PocketFinds</title>
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

{{-- Shop hero --}}
<div class="gs-hero">
  <div class="gs-avatar">{{ $shop['initial'] }}</div>
  <div class="gs-info">
    <h1 class="gs-name">{{ $shop['name'] }}</h1>
    <div class="gs-rating-row">
      @for($i=1;$i<=5;$i++)
      <svg width="13" height="13" viewBox="0 0 24 24" fill="{{ $i<=round($shop['rating'])?'#f59e0b':'none' }}" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      @endfor
      <span class="gs-rating-val">{{ $shop['rating'] }}</span>
    </div>
    <p class="gs-desc">{{ $shop['desc'] }}</p>
  </div>
  <div class="gs-stats">
    <div class="gs-stat"><div class="gs-stat-val">{{ $shop['products'] }}</div><div class="gs-stat-label">Products</div></div>
    <div class="gs-stat"><div class="gs-stat-val">{{ $shop['sales'] }}</div><div class="gs-stat-label">Sales</div></div>
    <div class="gs-stat"><div class="gs-stat-val">{{ $shop['rating'] }}</div><div class="gs-stat-label">Rating</div></div>
    <div class="gs-stat"><div class="gs-stat-val">{{ $shop['joined'] }}</div><div class="gs-stat-label">Joined</div></div>
  </div>
</div>

<div class="gs-products-head">
  <span>All Products</span>
  <span class="gs-products-count">{{ count($items) }} item{{ count($items)!==1?'s':'' }}</span>
</div>

@php
$icons = [
  'headphones'=>'<path d="M3 18v-6a9 9 0 0118 0v6"/><path d="M21 19a2 2 0 01-2 2h-1a2 2 0 01-2-2v-3a2 2 0 012-2h3zM3 19a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2H3z"/>',
  'bag'       =>'<path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>',
  'phone'     =>'<path d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
  'sparkle'   =>'<path d="M5 3l1.5 4.5L11 9l-4.5 1.5L5 15l-1.5-4.5L-1 9l4.5-1.5L5 3zm12 9l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z"/>',
  'shirt'     =>'<path d="M3 7l3-4h12l3 4-4 2v10H7V9L3 7z"/>',
  'puzzle'    =>'<path d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/>',
];
@endphp

<div class="deal-grid" style="margin-top:0">
  @forelse($items as $p)
  <a class="product" href="{{ route('guest.product', $p['id']) }}" style="text-decoration:none;color:inherit">
    <div class="product-img">
      <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">{!! $icons[$p['img']] ?? $icons['bag'] !!}</svg>
      @if($p['badge'])<span class="badge">{{ $p['badge'] }}</span>@endif
    </div>
    <div class="product-body">
      <div class="product-name">{{ $p['name'] }}</div>
      <div class="price">₱{{ number_format($p['price']) }}</div>
      <div class="meta">
        <span class="meta-rating"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> {{ $p['rating'] }}</span>
        <span>{{ number_format($p['sold']) }} sold</span>
      </div>
      <div class="product-actions">
        <button class="btn-cart" type="button" data-protected onclick="event.preventDefault();event.stopPropagation()"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg> Cart</button>
        <button class="btn-buy" type="button" data-protected onclick="event.preventDefault();event.stopPropagation()">Buy Now</button>
      </div>
    </div>
  </a>
  @empty
  <p style="color:var(--muted);font-size:13px;grid-column:1/-1">No products yet.</p>
  @endforelse
</div>

</main>

<footer class="footer"><div class="container"><div class="footer-grid"><div><h3>PocketFinds Marketplace</h3><p>A simple marketplace experience for discovering products from local sellers.</p></div><div><h3>Customer Service</h3><a href="#">Help Centre</a><a href="#">Contact Us</a><a href="#">Returns</a></div><div><h3>About</h3><a href="#">About Us</a><a href="#">Careers</a><a href="#">Privacy</a></div><div><h3>Account</h3><a href="{{ url('/login') }}">Sign In</a><a href="{{ url('/register/type') }}">Register</a><a href="#">Seller Centre</a></div></div><div class="footer-bottom">© {{ date('Y') }} PocketFinds. All rights reserved.</div></div></footer>

@include('guest.auth-modal')
<script src="{{ asset('js/marketplace.js') }}"></script>
</body>
</html>
