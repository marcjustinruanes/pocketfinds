<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark"><img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img"></div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Logistics</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Main</div>
    <a href="{{ route('logistics.dashboard') }}" class="nav-item {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="dashboard" /></span> {{ auth()->user()->isHubStaff() ? 'My Hub' : 'Dashboard' }}
    </a>
    @if(auth()->user()->isLogisticsAdmin())
    <a href="{{ route('logistics.requests') }}" class="nav-item {{ request()->routeIs('logistics.requests') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="edit" /></span> Pickup Requests
      @if(($pendingDeliveries ?? 0) > 0)<span class="count">{{ $pendingDeliveries }}</span>@endif
    </a>
    <a href="{{ route('logistics.hub-requests') }}" class="nav-item {{ request()->routeIs('logistics.hub-requests') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="edit" /></span> Hub Transfer Requests
      @if(($pendingHubTransferRequests ?? 0) > 0)<span class="count">{{ $pendingHubTransferRequests }}</span>@endif
    </a>
    @endif
    <a href="{{ route('logistics.assignments') }}" class="nav-item {{ request()->routeIs('logistics.assignments') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="users" /></span> Delivery Assignments
      @if(($unassigned ?? 0) > 0)<span class="count">{{ $unassigned }}</span>@endif
    </a>
    <a href="{{ route('logistics.scan') }}" class="nav-item {{ request()->routeIs('logistics.scan') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="scan" /></span> Scan Parcel
    </a>
    @if(auth()->user()->isLogisticsAdmin())
    <a href="{{ route('logistics.riders') }}" class="nav-item {{ request()->routeIs('logistics.riders') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="shield" /></span> Rider Management
      @if(($pendingRiders ?? 0) > 0)<span class="count">{{ $pendingRiders }}</span>@endif
    </a>
    <a href="{{ route('logistics.staff') }}" class="nav-item {{ request()->routeIs('logistics.staff') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="account" /></span> Hub Staff
      @if(($pendingStaff ?? 0) > 0)<span class="count">{{ $pendingStaff }}</span>@endif
    </a>
    <a href="{{ route('logistics.hubs') }}" class="nav-item {{ request()->routeIs('logistics.hubs*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="pin" /></span> Coverage Hubs
    </a>
    <a href="{{ route('logistics.vehicles') }}" class="nav-item {{ request()->routeIs('logistics.vehicles*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="shield" /></span> Vehicle Fleet
      @if(($pendingVehicles ?? 0) > 0)<span class="count">{{ $pendingVehicles }}</span>@endif
    </a>
    @else
    <a href="{{ route('logistics.hub.vehicles') }}" class="nav-item {{ request()->routeIs('logistics.hub.vehicles*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="shield" /></span> Hub Vehicles
    </a>
    @endif

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
    @if(auth()->user()->isLogisticsAdmin())
    <a href="{{ route('logistics.reports') }}" class="nav-item {{ request()->routeIs('logistics.reports') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="chart" /></span> Reports
    </a>
    @endif
    <a href="{{ route('logistics.messages') }}" class="nav-item {{ request()->routeIs('logistics.messages*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="mail" /></span> Messages
    </a>
    <a href="{{ route('logistics.account') }}" class="nav-item {{ request()->routeIs('logistics.account') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="account" /></span> My Account
    </a>

  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      <x-user-avatar :user="auth()->user()" size="36" class="avatar" />
      <div class="who">
        <strong>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</strong>
        <span>{{ auth()->user()->isHubStaff() ? (auth()->user()->logisticsHub->municipality ?? 'Hub Staff') . ' Hub' : 'Logistics Admin' }}</span>
      </div>
    </div>
    <button class="logout-btn" data-logout><x-admin-icon name="logout" /> Sign out</button>
  </div>
</nav>
