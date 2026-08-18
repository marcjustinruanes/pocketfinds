<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">◆</div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Buyer Portal</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Shop</div>
    <a href="{{ route('buyer.dashboard') }}" class="nav-item {{ request()->routeIs('buyer.dashboard') ? 'active' : '' }}">
      <span class="ic">⊞</span> Dashboard
    </a>
    <a href="{{ route('buyer.browse') }}" class="nav-item {{ request()->routeIs('buyer.browse') ? 'active' : '' }}">
      <span class="ic">🛍</span> Browse Products
    </a>
    <a href="{{ route('buyer.cart') }}" class="nav-item {{ request()->routeIs('buyer.cart') ? 'active' : '' }}">
      <span class="ic">🛒</span> My Cart
    </a>

    <div class="nav-label">Orders</div>
    <a href="{{ route('buyer.orders') }}?tab=to_ship" class="nav-item {{ request()->routeIs('buyer.orders') ? 'active' : '' }}">
      <span class="ic">📦</span> My Orders
    </a>

    <div class="nav-label">Account</div>
    <a href="{{ route('buyer.messages') }}" class="nav-item {{ request()->routeIs('buyer.messages') ? 'active' : '' }}">
      <span class="ic">✉</span> Messages
    </a>
    <a href="{{ route('buyer.account') }}" class="nav-item {{ request()->routeIs('buyer.account') ? 'active' : '' }}">
      <span class="ic">👤</span> My Account
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
    <button class="logout-btn" data-logout>✕ Sign out</button>
  </div>
</nav>
