@if((auth()->user()->isLogisticsAdmin() || auth()->user()->isHubStaff()))
@include('logistics.partials.workspace-sidebar')
@else
<nav class="sidebar" id="sidebar" data-sidebar-hover aria-label="Logistics navigation">
  <div class="sidebar-brand" role="button" tabindex="0" data-sidebar-toggle aria-label="Toggle navigation" aria-expanded="false" aria-controls="sidebar">
    <div class="mark"><img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="" class="brand-logo-img"></div>
    <div class="logistics-brand-copy">
      <div class="name">Pocket<span>Finds</span></div>
      <span class="tag">Logistics</span>
    </div>
  </div>

  <div class="nav-groups">
    <a href="{{ route('logistics.dashboard') }}" class="nav-item {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}" @if(request()->routeIs('logistics.dashboard')) aria-current="page" @endif>
      <span class="ic"><x-admin-icon name="dashboard" /></span>
      <span class="logistics-nav-label">Dashboard</span>
    </a>

    @if(auth()->user()->isHubStaff())
    <a href="{{ route('logistics.transfers') }}" class="nav-item {{ request()->routeIs('logistics.transfers*') ? 'active' : '' }}" @if(request()->routeIs('logistics.transfers*')) aria-current="page" @endif>
      <span class="ic"><x-admin-icon name="edit" /></span>
      <span class="logistics-nav-label">Hub Transfers</span>
    </a>
    <a href="{{ route('logistics.assignments') }}" class="nav-item {{ request()->routeIs('logistics.assignments*') ? 'active' : '' }}" @if(request()->routeIs('logistics.assignments*')) aria-current="page" @endif>
      <span class="ic">
        <x-admin-icon name="users" />
        @if(($unassigned ?? 0) > 0)<span class="logistics-nav-count">{{ $unassigned }}</span>@endif
      </span>
      <span class="logistics-nav-label">Delivery Assignments</span>
    </a>
    <a href="{{ route('logistics.scan') }}" class="nav-item {{ request()->routeIs('logistics.scan') ? 'active' : '' }}" @if(request()->routeIs('logistics.scan')) aria-current="page" @endif>
      <span class="ic"><x-admin-icon name="scan" /></span>
      <span class="logistics-nav-label">Scan Parcel</span>
    </a>
    <a href="{{ route('logistics.hub.vehicles') }}" class="nav-item {{ request()->routeIs('logistics.hub.vehicles*') ? 'active' : '' }}" @if(request()->routeIs('logistics.hub.vehicles*')) aria-current="page" @endif>
      <span class="ic"><x-admin-icon name="shield" /></span>
      <span class="logistics-nav-label">Hub Vehicles</span>
    </a>
    <a href="{{ route('logistics.monitor') }}" class="nav-item {{ request()->routeIs('logistics.monitor') ? 'active' : '' }}" @if(request()->routeIs('logistics.monitor')) aria-current="page" @endif>
      <span class="ic">
        <x-admin-icon name="chart" />
        @if(($activeDeliveries ?? 0) > 0)<span class="logistics-nav-count">{{ $activeDeliveries }}</span>@endif
      </span>
      <span class="logistics-nav-label">Live Monitor</span>
    </a>
    <a href="{{ route('logistics.issues') }}" class="nav-item {{ request()->routeIs('logistics.issues') ? 'active' : '' }}" @if(request()->routeIs('logistics.issues')) aria-current="page" @endif>
      <span class="ic"><x-admin-icon name="flag" /></span>
      <span class="logistics-nav-label">Issues</span>
    </a>
    <a href="{{ route('logistics.history') }}" class="nav-item {{ request()->routeIs('logistics.history') ? 'active' : '' }}" @if(request()->routeIs('logistics.history')) aria-current="page" @endif>
      <span class="ic"><x-admin-icon name="shield" /></span>
      <span class="logistics-nav-label">Delivery History</span>
    </a>
    @if(config('features.attendance'))
    <a href="{{ route('logistics.my-attendance') }}" class="nav-item {{ request()->routeIs('logistics.my-attendance*') ? 'active' : '' }}" @if(request()->routeIs('logistics.my-attendance*')) aria-current="page" @endif>
      <span class="ic"><x-admin-icon name="account" /></span>
      <span class="logistics-nav-label">My Attendance</span>
    </a>
    @endif
    <a href="{{ route('logistics.cash-desk') }}" class="nav-item {{ request()->routeIs('logistics.cash-desk*') ? 'active' : '' }}" @if(request()->routeIs('logistics.cash-desk*')) aria-current="page" @endif>
      <span class="ic"><x-admin-icon name="peso" /></span>
      <span class="logistics-nav-label">Cash Desk</span>
    </a>
    <a href="{{ route('logistics.my-salary') }}" class="nav-item {{ request()->routeIs('logistics.my-salary*') ? 'active' : '' }}" @if(request()->routeIs('logistics.my-salary*')) aria-current="page" @endif>
      <span class="ic"><x-admin-icon name="peso" /></span>
      <span class="logistics-nav-label">My Salary</span>
    </a>
    <a href="{{ route('logistics.messages') }}" class="nav-item {{ request()->routeIs('logistics.messages*') ? 'active' : '' }}" @if(request()->routeIs('logistics.messages*')) aria-current="page" @endif>
      <span class="ic"><x-admin-icon name="mail" /></span>
      <span class="logistics-nav-label">Messages</span>
    </a>
    @endif

    <a href="{{ route('logistics.account') }}" class="nav-item {{ request()->routeIs('logistics.account', 'logistics.account.*') ? 'active' : '' }}" @if(request()->routeIs('logistics.account', 'logistics.account.*')) aria-current="page" @endif>
      <span class="ic"><x-admin-icon name="account" /></span>
      <span class="logistics-nav-label">My Account</span>
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      <x-user-avatar :user="auth()->user()" size="36" class="avatar" />
      <div class="who">
        <strong>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</strong>
        <span>{{ auth()->user()->isHubStaff() ? (auth()->user()->logisticsHub?->municipality ?? 'My').' Hub' : 'Logistics' }}</span>
      </div>
    </div>
    <button type="button" class="logout-btn" data-logout title="Sign out" aria-label="Sign out">
      <x-admin-icon name="logout" /><span>Sign out</span>
    </button>
  </div>
</nav>
@endif
