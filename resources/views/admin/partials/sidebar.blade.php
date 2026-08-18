<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">A</div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Admin Console</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Main</div>
    <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="dashboard" /></span> Dashboard
    </a>
    <a href="{{ route('admin.registrations') }}" class="nav-item {{ request()->routeIs('admin.registrations') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="edit" /></span> Registrations
      <span class="count">{{ $pendingRegistrations }}</span>
    </a>
    <a href="{{ route('admin.users') }}" class="nav-item {{ request()->routeIs('admin.users') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="users" /></span> User Accounts
    </a>

    <div class="nav-label">Compliance</div>
    <a href="{{ route('admin.compliance') }}" class="nav-item {{ request()->routeIs('admin.compliance') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="shield" /></span> Seller Compliance
    </a>
    <a href="{{ route('admin.complaints') }}" class="nav-item {{ request()->routeIs('admin.complaints') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="flag" /></span> Complaints &amp; Disputes
      <span class="count">{{ $openDisputes }}</span>
    </a>

    <div class="nav-label">Finance</div>
    <a href="{{ route('admin.commission') }}" class="nav-item {{ request()->routeIs('admin.commission') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="peso" /></span> Commission
    </a>
    <a href="{{ route('admin.reports') }}" class="nav-item {{ request()->routeIs('admin.reports') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="chart" /></span> Reports
    </a>

    <div class="nav-label">System</div>
    <a href="{{ route('admin.messages') }}" class="nav-item {{ request()->routeIs('admin.messages*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="mail" /></span> Messages
      @if(!empty($unreadMessages))
      <span class="count">{{ $unreadMessages }}</span>
      @endif
    </a>
    <a href="{{ route('admin.account') }}" class="nav-item {{ request()->routeIs('admin.account') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="account" /></span> My Account
    </a>
    <a href="{{ route('admin.settings') }}" class="nav-item {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="settings" /></span> Settings
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      <div class="avatar">
        @if(auth()->user()->profile_picture)
          <img src="{{ asset('storage/'.auth()->user()->profile_picture) }}" alt="{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}">
        @else
          {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
        @endif
      </div>
      <div class="who">
        <strong>{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</strong>
        <span>{{ ucfirst(auth()->user()->account_type) }}</span>
      </div>
    </div>
    <button class="logout-btn" data-logout><x-admin-icon name="logout" /> Sign out</button>
  </div>
</nav>
