<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle>
    @include('seller.partials.icon', ['name' => 'menu', 'size' => 20])
  </button>
  <div class="page-heading">
    <h1>@yield('page-title', 'Dashboard')</h1>
    <p>@yield('page-sub', '')</p>
  </div>
  <div class="topbar-search">
    <span class="ic">@include('seller.partials.icon', ['name' => 'search', 'size' => 14])</span>
    <input type="text" placeholder="Search orders, products…">
  </div>
  <div class="topbar-actions">
    <a href="{{ route('seller.notifications') }}" class="icon-btn" title="Notifications">
      @include('seller.partials.icon', ['name' => 'bell', 'size' => 16])
      <span class="dot"></span>
    </a>
    <a href="{{ route('seller.messages') }}" class="icon-btn" title="Messages">
      @include('seller.partials.icon', ['name' => 'mail', 'size' => 16])
    </a>
    <a href="{{ route('seller.account') }}" class="topbar-avatar">
      {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
    </a>
  </div>
</header>
