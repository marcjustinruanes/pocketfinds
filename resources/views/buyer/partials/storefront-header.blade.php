@php
  $notifications = $notifications ?? \Illuminate\Support\Facades\DB::table('notifications')->where('user_id', auth()->id())->orderByDesc('created_at')->limit(8)->get();
  $cartCount = $cartCount ?? (int) \App\Models\CartItem::where('buyer_id', auth()->id())->sum('qty');
  $unreadMessages = $unreadMessages ?? \App\Models\Message::where('receiver_id', auth()->id())->where('read', false)->count();
  $storefrontAnchor = request()->routeIs('buyer.dashboard') ? '' : route('buyer.dashboard');
@endphp
<header class="market-header">
  <div class="container header-main">
    <a class="logo" href="{{ route('buyer.dashboard') }}">
      <span class="logo-mark"><img src="{{ asset('images/logo.png') }}" alt="" class="brand-logo-img"></span>
      <span><span class="logo-pocket">Pocket</span><span class="logo-finds">Finds</span></span>
    </a>
    <div class="pf-search-wrap">
      <form class="search" action="{{ route('buyer.dashboard') }}#products" method="GET">
        <input data-search name="q" type="search" placeholder="Search" aria-label="Search products" value="{{ $search ?? '' }}">
        <button type="submit" aria-label="Search">@include('buyer.partials.icon', ['name' => 'search', 'size' => 15])</button>
      </form>
    </div>
    <nav class="nav-links" aria-label="Storefront">
      <a class="nav-link" href="{{ $storefrontAnchor }}#deals">Deals</a>
      <a class="nav-link" href="{{ $storefrontAnchor }}#products">Products</a>
      <a class="nav-link" href="{{ $storefrontAnchor }}#about">About</a>
    </nav>
    <div class="header-actions">
      <details class="pf-buyer-menu">
        <summary class="pf-acct-icon" aria-label="Notifications" title="Notifications">
          @include('buyer.partials.icon', ['name' => 'bell', 'size' => 18])
          @if($notifications->where('is_read', false)->count())<span class="pf-buyer-dot"></span>@endif
        </summary>
        <div class="pf-buyer-dropdown pf-buyer-notifications">
          <strong>Notifications</strong>
          @if(\App\Services\BuyerCommerce::installed())
          <form method="POST" action="{{ route('buyer.commerce.read-all') }}">@csrf<button type="submit">Mark all as read</button></form>
          @endif
          @forelse($notifications as $notification)
          <a href="{{ route('buyer.notifications.open', $notification->id) }}" class="{{ $notification->is_read ? '' : 'is-unread' }}">
            <span>{{ $notification->message }}</span>
            <small>{{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}</small>
          </a>
          @empty
          <p>No notifications yet.</p>
          @endforelse
          <a href="{{ route('buyer.announcements') }}">Announcements</a>
        </div>
      </details>
      @if(\App\Services\BuyerCommerce::installed())
      <a class="pf-acct-icon" href="{{ route('buyer.wishlist') }}" title="Wishlist" aria-label="Wishlist">
        @include('buyer.partials.icon', ['name' => 'wishlist', 'size' => 18])
      </a>
      @endif
      <a class="pf-acct-icon" id="cartIconBtn" href="{{ route('buyer.cart') }}" title="Cart" aria-label="Cart, {{ $cartCount }} items">
        @include('buyer.partials.icon', ['name' => 'cart', 'size' => 18])
        <span id="cartBadge" class="pf-buyer-count" style="{{ $cartCount ? '' : 'display:none' }}">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
      </a>
      <details class="pf-buyer-menu">
        <summary class="pf-acct-icon pf-buyer-avatar" aria-label="Account menu" title="{{ auth()->user()->given_names }}">
          <x-user-avatar :user="auth()->user()" size="32" />
        </summary>
        <div class="pf-buyer-dropdown pf-buyer-account-navigation">
          <div class="pf-buyer-identity"><strong>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</strong><small>Buyer account</small></div>
<a href="{{ route('buyer.dashboard') }}" @if(request()->routeIs('buyer.dashboard')) aria-current="page" @endif>@include('buyer.partials.icon', ['name' => 'home', 'size' => 16]) Home</a>
<a href="{{ route('buyer.orders') }}" @if(request()->routeIs('buyer.orders')) aria-current="page" @endif>@include('buyer.partials.icon', ['name' => 'package', 'size' => 16]) My Orders</a>
@if(\App\Services\BuyerCommerce::installed())
@endif
@if(\App\Services\GcashInventory::installed())
<a href="{{ route('gcash.index') }}" @if(request()->routeIs('gcash.*')) aria-current="page" @endif>@include('buyer.partials.icon', ['name' => 'wallet', 'size' => 16]) GCash payments</a>
@endif
<a href="{{ route('buyer.account') }}" @if(request()->routeIs('buyer.account*')) aria-current="page" @endif>@include('buyer.partials.icon', ['name' => 'user', 'size' => 16]) My Account</a>
          <form method="POST" action="{{ route('buyer.logout') }}">@csrf<button type="submit">@include('buyer.partials.icon', ['name' => 'logout', 'size' => 16]) Sign Out</button></form>
        </div>
      </details>
    </div>
  </div>
</header>
