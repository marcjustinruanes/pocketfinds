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
      <span class="ic">⊞</span> Dashboard
    </a>
    <a href="{{ route('admin.registrations') }}" class="nav-item {{ request()->routeIs('admin.registrations') ? 'active' : '' }}">
      <span class="ic">✎</span> Registrations
      <span class="count">{{ $pendingRegistrations }}</span>
    </a>
    <a href="{{ route('admin.users') }}" class="nav-item {{ request()->routeIs('admin.users') ? 'active' : '' }}">
      <span class="ic">👤</span> User Accounts
    </a>

    <div class="nav-label">Compliance</div>
    <a href="{{ route('admin.compliance') }}" class="nav-item {{ request()->routeIs('admin.compliance') ? 'active' : '' }}">
      <span class="ic">🛡</span> Seller Compliance
    </a>
    <a href="{{ route('admin.complaints') }}" class="nav-item {{ request()->routeIs('admin.complaints') ? 'active' : '' }}">
      <span class="ic">⚑</span> Complaints &amp; Disputes
      <span class="count">{{ $openDisputes }}</span>
    </a>

    <div class="nav-label">Finance</div>
    <a href="{{ route('admin.commission') }}" class="nav-item {{ request()->routeIs('admin.commission') ? 'active' : '' }}">
      <span class="ic">₱</span> Commission
    </a>
    <a href="{{ route('admin.reports') }}" class="nav-item {{ request()->routeIs('admin.reports') ? 'active' : '' }}">
      <span class="ic">📊</span> Reports
    </a>

    <div class="nav-label">System</div>
    <a href="{{ route('admin.messages') }}" class="nav-item {{ request()->routeIs('admin.messages') ? 'active' : '' }}">
      <span class="ic">✉</span> Messages
    </a>
    <a href="{{ route('admin.settings') }}" class="nav-item {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
      <span class="ic">⚙</span> Settings
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      <div class="avatar">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</div>
      <div class="who">
        <strong>{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</strong>
        <span>{{ ucfirst(auth()->user()->account_type) }}</span>
      </div>
    </div>
    <button class="logout-btn" data-logout>✕ Sign out</button>
  </div>
</nav>
