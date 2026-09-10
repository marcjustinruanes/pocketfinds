<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">
      <img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img">
    </div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Buyer Portal</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Shop</div>
    <a href="{{ route('buyer.dashboard') }}" class="nav-item {{ request()->routeIs('buyer.dashboard') ? 'active' : '' }}">
      <span class="ic">@include('buyer.partials.icon', ['name' => 'dashboard', 'size' => 16])</span> Dashboard
    </a>
    <a href="{{ route('buyer.browse') }}" class="nav-item {{ request()->routeIs('buyer.browse') ? 'active' : '' }}">
      <span class="ic">@include('buyer.partials.icon', ['name' => 'bag', 'size' => 16])</span> Browse Products
    </a>
    <a href="{{ route('buyer.cart') }}" class="nav-item {{ request()->routeIs('buyer.cart') ? 'active' : '' }}">
      <span class="ic">@include('buyer.partials.icon', ['name' => 'cart', 'size' => 16])</span> My Cart
    </a>

    <div class="nav-label">Orders</div>
    <a href="{{ route('buyer.orders') }}?tab=to_ship" class="nav-item {{ request()->routeIs('buyer.orders') ? 'active' : '' }}">
      <span class="ic">@include('buyer.partials.icon', ['name' => 'package', 'size' => 16])</span> My Orders
    </a>

    <div class="nav-label">Account</div>
    <a href="{{ route('buyer.messages') }}" class="nav-item {{ request()->routeIs('buyer.messages') ? 'active' : '' }}">
      <span class="ic">@include('buyer.partials.icon', ['name' => 'mail', 'size' => 16])</span> Messages
    </a>
    <a href="{{ route('buyer.account') }}" class="nav-item {{ request()->routeIs('buyer.account') ? 'active' : '' }}">
      <span class="ic">@include('buyer.partials.icon', ['name' => 'user', 'size' => 16])</span> My Account
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      <div class="avatar">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</div>
      <div class="who">
        <strong>{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</strong>
        <span>Buyer</span>
      </div>
    </div>
    <button class="logout-btn" data-logout>
      @include('buyer.partials.icon', ['name' => 'logout', 'size' => 14]) Sign out
    </button>
  </div>
</nav>
