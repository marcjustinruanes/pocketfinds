@if((auth()->user()->isLogisticsAdmin() || auth()->user()->isHubStaff()))
@include('logistics.partials.workspace-sidebar')
@else
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
@if(auth()->user()->isHubStaff())
<x-navigation-group id="hub-staff-operations" label="Operations" :active="request()->routeIs('logistics.transfers*', 'logistics.assignments*', 'logistics.scan', 'logistics.hub.vehicles*')">
<a @if(request()->routeIs('logistics.transfers*')) aria-current="page" @endif href="{{ route('logistics.transfers') }}" class="nav-item {{ request()->routeIs('logistics.transfers*') ? 'active' : '' }}"><span class="ic"><x-admin-icon name="edit" /></span> Hub Transfers</a>
<a @if(request()->routeIs('logistics.assignments*')) aria-current="page" @endif href="{{ route('logistics.assignments') }}" class="nav-item {{ request()->routeIs('logistics.assignments*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="users" /></span> Delivery Assignments
      @if(($unassigned ?? 0) > 0)<span class="count">{{ $unassigned }}</span>@endif
    </a>
<a @if(request()->routeIs('logistics.scan')) aria-current="page" @endif href="{{ route('logistics.scan') }}" class="nav-item {{ request()->routeIs('logistics.scan') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="scan" /></span> Scan Parcel
    </a>
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
<x-navigation-group id="hub-staff-staff-finance" label="Staff & Finance" :active="request()->routeIs('logistics.my-attendance*', 'logistics.cash-desk*', 'logistics.my-salary*')">
@if(config('features.attendance')) <a href="{{ route('logistics.my-attendance') }}" class="nav-item {{ request()->routeIs('logistics.my-attendance*') ? 'active' : '' }}" @if(request()->routeIs('logistics.my-attendance*')) aria-current="page" @endif><span class="ic"><x-admin-icon name="account" /></span> My Attendance</a> @endif
<a href="{{ route('logistics.cash-desk') }}" class="nav-item {{ request()->routeIs('logistics.cash-desk*') ? 'active' : '' }}" @if(request()->routeIs('logistics.cash-desk*')) aria-current="page" @endif><span class="ic"><x-admin-icon name="peso" /></span> Cash Desk</a>
<a href="{{ route('logistics.my-salary') }}" class="nav-item {{ request()->routeIs('logistics.my-salary*') ? 'active' : '' }}" @if(request()->routeIs('logistics.my-salary*')) aria-current="page" @endif><span class="ic"><x-admin-icon name="peso" /></span> My Salary</a>
</x-navigation-group>
<a @if(request()->routeIs('logistics.messages*')) aria-current="page" @endif href="{{ route('logistics.messages') }}" class="nav-item {{ request()->routeIs('logistics.messages*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="mail" /></span> Messages
    </a>
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

@endif
