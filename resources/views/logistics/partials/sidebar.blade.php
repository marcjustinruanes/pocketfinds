<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark"><img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img"></div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Logistics</span>
    </div>
  </div>

  <div class="nav-groups">
<a @if(request()->routeIs('logistics.dashboard')) aria-current="page" @endif href="{{ route('logistics.dashboard') }}" class="nav-item {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="dashboard" /></span> {{ auth()->user()->isHubStaff() ? 'My Hub' : 'Dashboard' }}
    </a>
@if(auth()->user()->isLogisticsAdmin())
<x-navigation-group id="logistics-operations" label="Operations" :active="request()->routeIs('logistics.transfers*', 'logistics.requests', 'logistics.hub-requests', 'logistics.assignments', 'logistics.scan')">
<a @if(request()->routeIs('logistics.transfers*')) aria-current="page" @endif href="{{ route('logistics.transfers') }}" class="nav-item {{ request()->routeIs('logistics.transfers*') ? 'active' : '' }}"><span class="ic"><x-admin-icon name="edit" /></span> Hub Transfers</a>
<a @if(request()->routeIs('logistics.requests')) aria-current="page" @endif href="{{ route('logistics.requests') }}" class="nav-item {{ request()->routeIs('logistics.requests') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="edit" /></span> Pickup Requests
      @if(($pendingDeliveries ?? 0) > 0)<span class="count">{{ $pendingDeliveries }}</span>@endif
    </a>
<a @if(request()->routeIs('logistics.hub-requests')) aria-current="page" @endif href="{{ route('logistics.hub-requests') }}" class="nav-item {{ request()->routeIs('logistics.hub-requests') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="edit" /></span> Hub Transfer Requests
      @if(($pendingHubTransferRequests ?? 0) > 0)<span class="count">{{ $pendingHubTransferRequests }}</span>@endif
    </a>
<a @if(request()->routeIs('logistics.assignments')) aria-current="page" @endif href="{{ route('logistics.assignments') }}" class="nav-item {{ request()->routeIs('logistics.assignments') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="users" /></span> Delivery Assignments
      @if(($unassigned ?? 0) > 0)<span class="count">{{ $unassigned }}</span>@endif
    </a>
<a @if(request()->routeIs('logistics.scan')) aria-current="page" @endif href="{{ route('logistics.scan') }}" class="nav-item {{ request()->routeIs('logistics.scan') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="scan" /></span> Scan Parcel
    </a>
</x-navigation-group>
<x-navigation-group id="logistics-personnel-resources" label="Personnel & Resources" :active="request()->routeIs('logistics.riders', 'logistics.staff', 'logistics.hubs*', 'logistics.vehicles*')">
<a @if(request()->routeIs('logistics.riders')) aria-current="page" @endif href="{{ route('logistics.riders') }}" class="nav-item {{ request()->routeIs('logistics.riders') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="shield" /></span> Rider Management
      @if(($pendingRiders ?? 0) > 0)<span class="count">{{ $pendingRiders }}</span>@endif
    </a>
<a @if(request()->routeIs('logistics.staff')) aria-current="page" @endif href="{{ route('logistics.staff') }}" class="nav-item {{ request()->routeIs('logistics.staff') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="account" /></span> Hub Staff
      @if(($pendingStaff ?? 0) > 0)<span class="count">{{ $pendingStaff }}</span>@endif
    </a>
<a @if(request()->routeIs('logistics.hubs*')) aria-current="page" @endif href="{{ route('logistics.hubs') }}" class="nav-item {{ request()->routeIs('logistics.hubs*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="pin" /></span> Coverage Hubs
    </a>
<a @if(request()->routeIs('logistics.vehicles*')) aria-current="page" @endif href="{{ route('logistics.vehicles') }}" class="nav-item {{ request()->routeIs('logistics.vehicles*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="shield" /></span> Vehicle Fleet
      @if(($pendingVehicles ?? 0) > 0)<span class="count">{{ $pendingVehicles }}</span>@endif
    </a>
</x-navigation-group>
<x-navigation-group id="logistics-tracking-reports" label="Tracking & Reports" :active="request()->routeIs('logistics.monitor', 'logistics.issues', 'logistics.history', 'logistics.reports')">
<a @if(request()->routeIs('logistics.monitor')) aria-current="page" @endif href="{{ route('logistics.monitor') }}" class="nav-item {{ request()->routeIs('logistics.monitor') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="chart" /></span> Live Monitor
      @if(($activeDeliveries ?? 0) > 0)<span class="count">{{ $activeDeliveries }}</span>@endif
    </a>
<a @if(request()->routeIs('logistics.issues')) aria-current="page" @endif href="{{ route('logistics.issues') }}" class="nav-item {{ request()->routeIs('logistics.issues') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="flag" /></span> Issues
    </a>
<a @if(request()->routeIs('logistics.history')) aria-current="page" @endif href="{{ route('logistics.history') }}" class="nav-item {{ request()->routeIs('logistics.history') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="shield" /></span> Delivery History
    </a>
<a @if(request()->routeIs('logistics.reports')) aria-current="page" @endif href="{{ route('logistics.reports') }}" class="nav-item {{ request()->routeIs('logistics.reports') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="chart" /></span> Reports
    </a>
</x-navigation-group>
<x-navigation-group id="logistics-administration" label="Administration" :active="request()->routeIs('logistics.accounts*', 'logistics.activity', 'logistics.messages*')">
<a @if(request()->routeIs('logistics.accounts*')) aria-current="page" @endif href="{{ route('logistics.accounts') }}" class="nav-item {{ request()->routeIs('logistics.accounts*') ? 'active' : '' }}"><span class="ic"><x-admin-icon name="users" /></span> User & Account Management</a>
<a @if(request()->routeIs('logistics.activity')) aria-current="page" @endif href="{{ route('logistics.activity') }}" class="nav-item {{ request()->routeIs('logistics.activity') ? 'active' : '' }}"><span class="ic"><x-admin-icon name="shield" /></span> Activity Logs</a>
<a @if(request()->routeIs('logistics.messages*')) aria-current="page" @endif href="{{ route('logistics.messages') }}" class="nav-item {{ request()->routeIs('logistics.messages*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="mail" /></span> Messages
    </a>
</x-navigation-group>
@elseif(auth()->user()->isHubStaff())
<x-navigation-group id="hub-staff-operations" label="Operations" :active="request()->routeIs('logistics.transfers*', 'logistics.assignments', 'logistics.scan')">
<a @if(request()->routeIs('logistics.transfers*')) aria-current="page" @endif href="{{ route('logistics.transfers') }}" class="nav-item {{ request()->routeIs('logistics.transfers*') ? 'active' : '' }}"><span class="ic"><x-admin-icon name="edit" /></span> Hub Transfers</a>
<a @if(request()->routeIs('logistics.assignments')) aria-current="page" @endif href="{{ route('logistics.assignments') }}" class="nav-item {{ request()->routeIs('logistics.assignments') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="users" /></span> Delivery Assignments
      @if(($unassigned ?? 0) > 0)<span class="count">{{ $unassigned }}</span>@endif
    </a>
<a @if(request()->routeIs('logistics.scan')) aria-current="page" @endif href="{{ route('logistics.scan') }}" class="nav-item {{ request()->routeIs('logistics.scan') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="scan" /></span> Scan Parcel
    </a>
</x-navigation-group>
<x-navigation-group id="hub-staff-hub-resources" label="Hub Resources" :active="request()->routeIs('logistics.hub.vehicles*')">
<a @if(request()->routeIs('logistics.hub.vehicles*')) aria-current="page" @endif href="{{ route('logistics.hub.vehicles') }}" class="nav-item {{ request()->routeIs('logistics.hub.vehicles*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="shield" /></span> Hub Vehicles
    </a>
</x-navigation-group>
<x-navigation-group id="hub-staff-tracking" label="Tracking" :active="request()->routeIs('logistics.monitor', 'logistics.issues', 'logistics.history')">
<a @if(request()->routeIs('logistics.monitor')) aria-current="page" @endif href="{{ route('logistics.monitor') }}" class="nav-item {{ request()->routeIs('logistics.monitor') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="chart" /></span> Live Monitor
      @if(($activeDeliveries ?? 0) > 0)<span class="count">{{ $activeDeliveries }}</span>@endif
    </a>
<a @if(request()->routeIs('logistics.issues')) aria-current="page" @endif href="{{ route('logistics.issues') }}" class="nav-item {{ request()->routeIs('logistics.issues') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="flag" /></span> Issues
    </a>
<a @if(request()->routeIs('logistics.history')) aria-current="page" @endif href="{{ route('logistics.history') }}" class="nav-item {{ request()->routeIs('logistics.history') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="shield" /></span> Delivery History
    </a>
</x-navigation-group>
<x-navigation-group id="hub-staff-communications" label="Communications" :active="request()->routeIs('logistics.messages*')">
<a @if(request()->routeIs('logistics.messages*')) aria-current="page" @endif href="{{ route('logistics.messages') }}" class="nav-item {{ request()->routeIs('logistics.messages*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="mail" /></span> Messages
    </a>
</x-navigation-group>
@endif
<a @if(request()->routeIs('logistics.account', 'logistics.account.*')) aria-current="page" @endif href="{{ route('logistics.account') }}" class="nav-item {{ request()->routeIs('logistics.account', 'logistics.account.*') ? 'active' : '' }}">
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
