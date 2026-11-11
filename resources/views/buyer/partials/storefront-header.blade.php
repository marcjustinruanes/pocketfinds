@php
  $notifications = $notifications ?? \Illuminate\Support\Facades\DB::table('notifications')->where('user_id', auth()->id())->orderByDesc('created_at')->limit(8)->get();
  $notificationUnreadCount = \Illuminate\Support\Facades\DB::table('notifications')->where('user_id', auth()->id())->where('is_read',false)->count();
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
      @include('account.partials.notification-menu', ['panelNotifications'=>$notifications, 'panelUnreadCount'=>$notificationUnreadCount, 'panelOpenRoute'=>'buyer.notifications.open', 'panelFooterUrl'=>route('buyer.announcements'), 'panelFooterLabel'=>'View announcements'])
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
<a href="{{ route('buyer.orders') }}" @if(request()->routeIs('buyer.orders')) aria-current="page" @endif>@include('buyer.partials.icon', ['name' => 'package', 'size' => 16]) My Orders</a>
@if(\App\Services\BuyerCommerce::installed())
@endif
<a href="{{ route('buyer.account') }}" @if(request()->routeIs('buyer.account*')) aria-current="page" @endif>@include('buyer.partials.icon', ['name' => 'user', 'size' => 16]) My Account</a>
          <form method="POST" action="{{ route('buyer.logout') }}">@csrf<button type="submit">@include('buyer.partials.icon', ['name' => 'logout', 'size' => 16]) Sign Out</button></form>
        </div>
      </details>
    </div>
  </div>
</header>
