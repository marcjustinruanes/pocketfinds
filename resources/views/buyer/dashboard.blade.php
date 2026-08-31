@extends('buyer.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->given_names . '!')

@section('content')
<section class="home-hero">
  <div class="home-hero-copy">
    <div class="eyebrow">PocketFinds</div>
    <h1>Discover products you'll love.</h1>
    <p>Browse products, explore categories, and pick up right where you left off.</p>
  </div>
</section>

<section class="home-section">
  <div class="home-section-head">
    <h2>Browse Categories</h2>
    <a href="{{ route('buyer.browse') }}" class="see-all">See All</a>
  </div>
  <div class="category-grid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr))">
    @forelse($categories as $cat)
    <a href="{{ route('buyer.browse', ['category' => $cat->id]) }}" class="category-chip">
      <span class="category-icon">@include('buyer.partials.category-icon', ['name' => $cat->name])</span>
      <span>{{ $cat->name }}</span>
    </a>
    @empty
    <p style="color:var(--muted);font-size:13px;grid-column:1/-1">No categories yet.</p>
    @endforelse
  </div>
</section>

<section class="home-section home-products">
  <div class="home-section-head">
    <h2>All Products</h2>
    <a href="{{ route('buyer.browse') }}" class="see-all">See All</a>
  </div>
  <div class="product-grid product-grid-lg">
    @forelse($products as $p)
      @include('buyer.partials.product-card', ['p' => $p])
    @empty
      <div class="empty" style="grid-column:1/-1">
        @include('buyer.partials.icon', ['name' => 'bag', 'size' => 28])
        <h3>No products yet</h3>
        <p>Check back soon!</p>
      </div>
    @endforelse
  </div>
</section>

<footer class="site-footer">
  <div class="site-footer-grid">
    <div>
      <h3>PocketFinds Marketplace</h3>
      <p>A simple marketplace experience for discovering products from local sellers.</p>
    </div>
    <div>
      <h3>Customer Service</h3>
      <a href="#">Help Centre</a>
      <a href="#">Contact Us</a>
      <a href="#">Returns</a>
    </div>
    <div>
      <h3>About</h3>
      <a href="#">About Us</a>
      <a href="#">Careers</a>
      <a href="#">Privacy</a>
    </div>
    <div>
      <h3>Account</h3>
      <a href="{{ route('buyer.account') }}">My Account</a>
      <a href="{{ route('buyer.orders') }}">My Orders</a>
      <a href="{{ route('buyer.cart') }}">My Cart</a>
    </div>
  </div>
  <div class="site-footer-bottom">© {{ date('Y') }} PocketFinds. All rights reserved.</div>
</footer>
@endsection
