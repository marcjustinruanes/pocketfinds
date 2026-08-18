<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">P</div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Logistics Console</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Overview</div>
    <a href="{{ route('logistics.dashboard') }}" class="nav-item {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="2" width="7" height="7" rx="2"/><rect x="11" y="2" width="7" height="7" rx="2"/><rect x="2" y="11" width="7" height="7" rx="2"/><rect x="11" y="11" width="7" height="7" rx="2"/></svg>
      </span> Dashboard
    </a>
    <a href="{{ route('logistics.notifications') }}" class="nav-item {{ request()->routeIs('logistics.notifications') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 2a6 6 0 0 1 6 6v3l1.5 2.5H2.5L4 11V8a6 6 0 0 1 6-6z"/><path d="M8 16a2 2 0 0 0 4 0"/></svg>
      </span> Notifications
      @if(($unreadNotifications ?? 0) > 0)<span class="count">{{ $unreadNotifications }}</span>@endif
    </a>

    <div class="nav-label">Deliveries</div>
    <a href="{{ route('logistics.requests') }}" class="nav-item {{ request()->routeIs('logistics.requests') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H5l-3 3V5a1 1 0 0 1 1-1z"/><path d="M7 9h6M7 12h4"/></svg>
      </span> Delivery Requests
      @if(($pendingDeliveries ?? 0) > 0)<span class="count">{{ $pendingDeliveries }}</span>@endif
    </a>
    <a href="{{ route('logistics.assign') }}" class="nav-item {{ request()->routeIs('logistics.assign') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="7" r="3"/><path d="M2 17c0-3 2.7-5 6-5m5-1v6m-3-3h6"/></svg>
      </span> Assign Courier
      @if(($unassigned ?? 0) > 0)<span class="count">{{ $unassigned }}</span>@endif
    </a>
    <a href="{{ route('logistics.monitor') }}" class="nav-item {{ request()->routeIs('logistics.monitor') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="7"/><circle cx="10" cy="10" r="3"/><path d="M10 3v2M10 15v2M3 10h2M15 10h2"/></svg>
      </span> Monitor Deliveries
      @if(($activeDeliveries ?? 0) > 0)<span class="count">{{ $activeDeliveries }}</span>@endif
    </a>
    <a href="{{ route('logistics.issues') }}" class="nav-item {{ request()->routeIs('logistics.issues') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 3L2 17h16L10 3z"/><path d="M10 9v4M10 15h.01"/></svg>
      </span> Delivery Issues
    </a>
    <a href="{{ route('logistics.history') }}" class="nav-item {{ request()->routeIs('logistics.history') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="8"/><path d="M10 6v4l3 2"/></svg>
      </span> Delivery History
    </a>

    <div class="nav-label">Reports &amp; Comms</div>
    <a href="{{ route('logistics.reports') }}" class="nav-item {{ request()->routeIs('logistics.reports') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 14l4-4 3 3 5-6"/><rect x="2" y="2" width="16" height="16" rx="2"/></svg>
      </span> Reports
    </a>
    <a href="{{ route('logistics.messages') }}" class="nav-item {{ request()->routeIs('logistics.messages') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H5l-3 3V5a1 1 0 0 1 1-1z"/></svg>
      </span> Messages
    </a>

    <div class="nav-label">Account</div>
    <a href="{{ route('logistics.account') }}" class="nav-item {{ request()->routeIs('logistics.account') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="2.5"/><path d="M10 2v2m0 12v2M2 10h2m12 0h2m-3.2-4.8-1.4 1.4M6.6 13.4l-1.4 1.4m0-9.6 1.4 1.4m6.8 6.8 1.4 1.4"/></svg>
      </span> My Account
    </a>
  </div>

  <div class="sidebar-foot">
    <a href="{{ route('logistics.account') }}" class="sidebar-user">
      <div class="avatar">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</div>
      <div class="who">
        <strong>{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</strong>
        <span>Logistics Admin</span>
      </div>
      <span class="ic-chevron">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4l4 4-4 4"/></svg>
      </span>
    </a>
    <button class="logout-btn" data-logout>
      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" width="14" height="14"><path d="M13 3h4v14h-4M9 14l4-4-4-4M3 10h10"/></svg>
      Sign out
    </button>
  </div>
</nav>
