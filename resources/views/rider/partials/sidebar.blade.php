<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">C</div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Courier</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Main</div>
    <a href="{{ route('rider.dashboard') }}" class="nav-item {{ request()->routeIs('rider.dashboard') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="dashboard" /></span> Dashboard
    </a>
    <a href="{{ route('rider.requests') }}" class="nav-item {{ request()->routeIs('rider.requests') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="box" /></span> Pickup Requests
      @if(($availableRequests ?? 0) > 0)<span class="count">{{ $availableRequests }}</span>@endif
    </a>
    <a href="{{ route('rider.deliveries') }}" class="nav-item {{ request()->routeIs('rider.deliveries') || request()->routeIs('rider.deliveries.show') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="truck" /></span> My Deliveries
      @if(($activeDeliveries ?? 0) > 0)<span class="count">{{ $activeDeliveries }}</span>@endif
    </a>

    <div class="nav-label">Earnings</div>
    <a href="{{ route('rider.profit') }}" class="nav-item {{ request()->routeIs('rider.profit') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="peso" /></span> Profit
    </a>
    <a href="{{ route('rider.history') }}" class="nav-item {{ request()->routeIs('rider.history') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="clock" /></span> Delivery History
    </a>

    <div class="nav-label">System</div>
    <a href="{{ route('rider.messages') }}" class="nav-item {{ request()->routeIs('rider.messages*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="mail" /></span> Messages
      @if(($unreadMessages ?? 0) > 0)<span class="count">{{ $unreadMessages }}</span>@endif
    </a>
    <a href="{{ route('rider.account') }}" class="nav-item {{ request()->routeIs('rider.account') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="account" /></span> My Account
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      @if(auth()->user()->profile_picture)
      <img class="avatar" src="{{ \Illuminate\Support\Facades\Storage::disk('profile_images')->url(auth()->user()->profile_picture) }}" alt="" style="object-fit:cover">
      @else
      <div class="avatar">{{ strtoupper(substr(auth()->user()->given_names, 0, 1)) }}</div>
      @endif
      <div class="who">
        <strong>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</strong>
        <span>Courier</span>
      </div>
    </div>
    <button class="logout-btn" data-logout><x-admin-icon name="logout" /> Sign out</button>
  </div>
</nav>
