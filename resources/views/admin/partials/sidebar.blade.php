<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">P</div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Admin Console</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Main</div>
    <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="2" width="7" height="7" rx="2"/><rect x="11" y="2" width="7" height="7" rx="2"/><rect x="2" y="11" width="7" height="7" rx="2"/><rect x="11" y="11" width="7" height="7" rx="2"/></svg>
      </span> Dashboard
    </a>
    <a href="{{ route('admin.registrations') }}" class="nav-item {{ request()->routeIs('admin.registrations') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M13 2H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/><path d="M7 7h6M7 10h6M7 13h4"/></svg>
      </span> Registrations
      @if($pendingRegistrations > 0)<span class="count">{{ $pendingRegistrations }}</span>@endif
    </a>
    <a href="{{ route('admin.users') }}" class="nav-item {{ request()->routeIs('admin.users') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="7" r="3.5"/><path d="M3 17c0-3.3 3.1-6 7-6s7 2.7 7 6"/></svg>
      </span> User Accounts
    </a>

    <div class="nav-label">Compliance</div>
    <a href="{{ route('admin.compliance') }}" class="nav-item {{ request()->routeIs('admin.compliance') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 2l7 3v5c0 4-3 7-7 8C7 17 4 14 3 10V5l7-3z"/><path d="M7 10l2 2 4-4"/></svg>
      </span> Seller Compliance
    </a>
    <a href="{{ route('admin.complaints') }}" class="nav-item {{ request()->routeIs('admin.complaints') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="8"/><path d="M10 6v4M10 14h.01"/></svg>
      </span> Complaints &amp; Disputes
      @if($openDisputes > 0)<span class="count">{{ $openDisputes }}</span>@endif
    </a>

    <div class="nav-label">Finance</div>
    <a href="{{ route('admin.commission') }}" class="nav-item {{ request()->routeIs('admin.commission') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="8"/><path d="M10 6v1m0 6v1m-2.5-5h4a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 0 0 3H13"/></svg>
      </span> Commission
    </a>
    <a href="{{ route('admin.reports') }}" class="nav-item {{ request()->routeIs('admin.reports') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 14l4-4 3 3 5-6"/><rect x="2" y="2" width="16" height="16" rx="2"/></svg>
      </span> Reports
    </a>

    <div class="nav-label">System</div>
    <a href="{{ route('admin.messages') }}" class="nav-item {{ request()->routeIs('admin.messages') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H5l-3 3V5a1 1 0 0 1 1-1z"/></svg>
      </span> Messages
    </a>
    <a href="{{ route('admin.settings') }}" class="nav-item {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
      <span class="ic">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="2.5"/><path d="M10 2v2m0 12v2M2 10h2m12 0h2m-3.2-4.8-1.4 1.4M6.6 13.4l-1.4 1.4m0-9.6 1.4 1.4m6.8 6.8 1.4 1.4"/></svg>
      </span> Settings
    </a>
  </div>

  <div class="sidebar-foot">
    <a href="{{ route('admin.account') }}" class="sidebar-user">
      <div class="avatar">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</div>
      <div class="who">
        <strong>{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</strong>
        <span>Administrator</span>
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
