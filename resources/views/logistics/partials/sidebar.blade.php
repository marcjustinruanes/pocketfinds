<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">L</div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Logistics Console</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Overview</div>
    <a href="{{ route('logistics.dashboard') }}" class="nav-item {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}">
      <span class="ic">⊞</span> Dashboard
    </a>
    <a href="{{ route('logistics.notifications') }}" class="nav-item {{ request()->routeIs('logistics.notifications') ? 'active' : '' }}">
      <span class="ic">🔔</span> Notifications
      @if(($unreadNotifications ?? 0) > 0)
        <span class="count">{{ $unreadNotifications }}</span>
      @endif
    </a>

    <div class="nav-label">Deliveries</div>
    <a href="{{ route('logistics.requests') }}" class="nav-item {{ request()->routeIs('logistics.requests') ? 'active' : '' }}">
      <span class="ic">📥</span> Delivery Requests
      @if(($pendingDeliveries ?? 0) > 0)
        <span class="count">{{ $pendingDeliveries }}</span>
      @endif
    </a>
    <a href="{{ route('logistics.assign') }}" class="nav-item {{ request()->routeIs('logistics.assign') ? 'active' : '' }}">
      <span class="ic">👤</span> Assign Courier
      @if(($unassigned ?? 0) > 0)
        <span class="count">{{ $unassigned }}</span>
      @endif
    </a>
    <a href="{{ route('logistics.monitor') }}" class="nav-item {{ request()->routeIs('logistics.monitor') ? 'active' : '' }}">
      <span class="ic">📡</span> Monitor Deliveries
      @if(($activeDeliveries ?? 0) > 0)
        <span class="count">{{ $activeDeliveries }}</span>
      @endif
    </a>
    <a href="{{ route('logistics.issues') }}" class="nav-item {{ request()->routeIs('logistics.issues') ? 'active' : '' }}">
      <span class="ic">⚠</span> Delivery Issues
    </a>
    <a href="{{ route('logistics.history') }}" class="nav-item {{ request()->routeIs('logistics.history') ? 'active' : '' }}">
      <span class="ic">📋</span> Delivery History
    </a>

    <div class="nav-label">Reports & Comms</div>
    <a href="{{ route('logistics.reports') }}" class="nav-item {{ request()->routeIs('logistics.reports') ? 'active' : '' }}">
      <span class="ic">📊</span> Reports
    </a>
    <a href="{{ route('logistics.messages') }}" class="nav-item {{ request()->routeIs('logistics.messages') ? 'active' : '' }}">
      <span class="ic">✉</span> Messages
    </a>

    <div class="nav-label">Account</div>
    <a href="{{ route('logistics.account') }}" class="nav-item {{ request()->routeIs('logistics.account') ? 'active' : '' }}">
      <span class="ic">⚙</span> My Account
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      <div class="avatar">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</div>
      <div class="who">
        <strong>{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</strong>
        <span>Logistics Admin</span>
      </div>
    </div>
    <button class="logout-btn" data-logout>✕ Sign out</button>
  </div>
</nav>
