<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">L</div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Logistics</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Main</div>
    <a href="{{ route('logistics.dashboard') }}" class="nav-item {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="dashboard" /></span> Dashboard
    </a>
    <a href="{{ route('logistics.requests') }}" class="nav-item {{ request()->routeIs('logistics.requests') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="edit" /></span> Delivery Requests
      @if(($pendingDeliveries ?? 0) > 0)<span class="count">{{ $pendingDeliveries }}</span>@endif
    </a>
    <a href="{{ route('logistics.assignments') }}" class="nav-item {{ request()->routeIs('logistics.assignments') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="users" /></span> Courier Assignments
    </a>
    <a href="{{ route('logistics.scan') }}" class="nav-item {{ request()->routeIs('logistics.scan') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="scan" /></span> Scan Parcel
    </a>

    <div class="nav-label">Tracking</div>
    <a href="{{ route('logistics.monitor') }}" class="nav-item {{ request()->routeIs('logistics.monitor') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="chart" /></span> Live Monitor
      @if(($activeDeliveries ?? 0) > 0)<span class="count">{{ $activeDeliveries }}</span>@endif
    </a>
    <a href="{{ route('logistics.issues') }}" class="nav-item {{ request()->routeIs('logistics.issues') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="flag" /></span> Issues
    </a>
    <a href="{{ route('logistics.history') }}" class="nav-item {{ request()->routeIs('logistics.history') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="shield" /></span> Delivery History
    </a>

    <div class="nav-label">System</div>
    <a href="{{ route('logistics.reports') }}" class="nav-item {{ request()->routeIs('logistics.reports') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="chart" /></span> Reports
    </a>
    <a href="{{ route('logistics.messages') }}" class="nav-item {{ request()->routeIs('logistics.messages*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="mail" /></span> Messages
    </a>
    <a href="{{ route('logistics.account') }}" class="nav-item {{ request()->routeIs('logistics.account') ? 'active' : '' }}">
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
        <span>Logistics</span>
      </div>
    </div>
    <button class="logout-btn" data-logout><x-admin-icon name="logout" /> Sign out</button>
  </div>
</nav>
