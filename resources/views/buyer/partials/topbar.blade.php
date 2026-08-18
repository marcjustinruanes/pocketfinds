<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle>☰</button>
  <div class="page-heading">
    <h1>@yield('page-title', 'Dashboard')</h1>
    <p>@yield('page-sub', '')</p>
  </div>
  <div class="topbar-search">
    <span class="ic">🔍</span>
    <input type="text" placeholder="Search products…">
  </div>
  <div class="topbar-actions">
    <a href="{{ route('buyer.cart') }}" class="icon-btn" title="Cart">🛒</a>
    <a href="{{ route('buyer.messages') }}" class="icon-btn" title="Messages">✉</a>
    <a href="{{ route('buyer.account') }}" class="topbar-avatar">
      {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
    </a>
  </div>
</header>
