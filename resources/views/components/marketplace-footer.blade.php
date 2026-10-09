@php
  $landing = $landing ?? false;
  $buyerFooter = $buyerFooter ?? false;
  $signedInBuyer = auth()->check() && auth()->user()->account_type === 'buyer';
  try { $supportEmail = \App\Models\Setting::current()->support_email; }
  catch (\Throwable $e) { $supportEmail = null; }
  $heading = $landing ? 'h4' : 'h3';
@endphp
<footer class="{{ $landing ? 'pf-footer' : 'footer' }}" id="about" style="padding-bottom:36px">
  <div class="{{ $buyerFooter ? 'footer-inner' : 'container' }}">
    <div class="{{ $landing ? 'pf-footer-wrap' : 'footer-grid' }}">
      <div class="{{ $landing ? 'pf-footer-brand' : '' }}">
        <a class="logo" href="{{ url('/') }}" style="display:flex;flex-direction:row;align-items:center;gap:10px">
          <span class="logo-mark"><img src="{{ asset('images/logo.png') }}" alt="" class="brand-logo-img" width="30" height="30"></span>
          <span><span class="logo-pocket" style="color:#593346">Pocket</span><span class="logo-finds" style="color:#bf3c7d">Finds</span></span>
        </a>
        <p>Discover everyday essentials and new favourites from local shops.</p>
        <p class="{{ $landing ? 'pf-footer-tagline' : '' }}">Find It. Love It. Pocket It.</p>
      </div>
      <div class="{{ $landing ? 'pf-footer-col' : '' }}">
        <{{ $heading }}>Explore</{{ $heading }}>
        <a href="{{ url('/') }}#products">All Products</a>
        <a href="{{ url('/') }}#categories">Shop by Category</a>
        <a href="{{ url('/') }}#shops">Featured Shops</a>
        <a href="{{ url('/') }}#deals">Deals</a>
      </div>
      <div class="{{ $landing ? 'pf-footer-col' : '' }}">
        <{{ $heading }}>Your Account</{{ $heading }}>
        @if($signedInBuyer)
        <a href="{{ route('buyer.account') }}">My Account</a>
        <a href="{{ route('buyer.orders') }}">My Orders</a>
        <a href="{{ route('buyer.cart') }}">My Cart</a>
        <a href="{{ route('buyer.messages') }}">Messages</a>
        @else
        <a href="{{ url('/login') }}">Sign In</a>
        <a href="{{ url('/register/buyer') }}">Create a Buyer Account</a>
        <a href="{{ url('/register/seller') }}">Become a Seller</a>
        @endif
      </div>
      <div class="{{ $landing ? 'pf-footer-col' : '' }}">
        <{{ $heading }}>Contact Us</{{ $heading }}>
        @if($supportEmail)
        <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
        @else
        <a href="{{ url('/login') }}">Sign in to contact us</a>
        @endif
      </div>
    </div>
  </div>
</footer>
