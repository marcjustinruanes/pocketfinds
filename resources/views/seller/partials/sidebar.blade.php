<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">
      @include('seller.partials.icon', ['name' => 'bag', 'size' => 18])
    </div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Seller Portal</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Overview</div>
    <a href="{{ route('seller.dashboard') }}" class="nav-item {{ request()->routeIs('seller.dashboard') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'dashboard', 'size' => 16])</span> Dashboard
    </a>

    <div class="nav-label">Orders</div>
    @php($unreadNotifCount = \Illuminate\Support\Facades\DB::table('notifications')->where('user_id', auth()->id())->where('is_read', false)->count())
    <a href="{{ route('seller.notifications') }}" class="nav-item {{ request()->routeIs('seller.notifications') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'bell', 'size' => 16])</span> Notifications
      @if($unreadNotifCount > 0)<span class="badge">{{ $unreadNotifCount }}</span>@endif
    </a>
    <a href="{{ route('seller.orders') }}" class="nav-item {{ request()->routeIs('seller.orders') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'orders', 'size' => 16])</span> Order Management
    </a>

    <div class="nav-label">Store</div>
    <a href="{{ route('seller.inventory') }}" class="nav-item {{ request()->routeIs('seller.inventory') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'inventory', 'size' => 16])</span> Inventory
    </a>
    <a href="{{ route('seller.vouchers') }}" class="nav-item {{ request()->routeIs('seller.vouchers') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'tag', 'size' => 16])</span> Vouchers
    </a>
    <a href="{{ route('seller.feedback') }}" class="nav-item {{ request()->routeIs('seller.feedback') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'star', 'size' => 16])</span> Customer Feedback
    </a>
    <a href="{{ route('seller.reports') }}" class="nav-item {{ request()->routeIs('seller.reports') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'chart', 'size' => 16])</span> Reports
    </a>

    <div class="nav-label">Account</div>
    <a href="{{ route('seller.messages') }}" class="nav-item {{ request()->routeIs('seller.messages') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'mail', 'size' => 16])</span> Messages
    </a>
    <a href="{{ route('seller.account') }}" class="nav-item {{ request()->routeIs('seller.account') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'user', 'size' => 16])</span> My Account
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      <div class="avatar">{{ strtoupper(substr(auth()->user()->given_names, 0, 1)) }}</div>
      <div class="who">
        <strong>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</strong>
        <span>Seller</span>
      </div>
    </div>
    <button class="logout-btn" data-logout>
      @include('seller.partials.icon', ['name' => 'logout', 'size' => 14]) Sign out
    </button>
  </div>
</nav>
