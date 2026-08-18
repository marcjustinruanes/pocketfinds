<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle aria-label="Toggle navigation">
    <x-admin-icon name="menu" />
  </button>
  <div class="page-heading">
    <h1>@yield('page-title', 'Logistics')</h1>
    <p>@yield('page-sub', '')</p>
  </div>
  <div class="topbar-search">
    <span class="ic"><x-admin-icon name="search" /></span>
    <input type="text" placeholder="Search...">
  </div>
  <div class="topbar-actions">
    <div class="dropdown">
      <button class="icon-btn" data-dropdown-toggle="logisticsNotifPanel" aria-label="Notifications">
        <x-admin-icon name="bell" />
        @if(($pendingDeliveries ?? 0) > 0 || ($unassigned ?? 0) > 0)
        <span class="dot-badge"></span>
        @endif
      </button>
      <div class="dropdown-panel" id="logisticsNotifPanel">
        <div class="dropdown-head">
          <h3>Notifications</h3>
        </div>
        <div class="notif-list">
          @if(($pendingDeliveries ?? 0) > 0)
          <a href="{{ route('logistics.requests') }}" class="notif-item" style="text-decoration:none;color:inherit;display:flex">
            <div class="dot"></div>
            <div><p>{{ $pendingDeliveries }} delivery request{{ $pendingDeliveries > 1 ? 's' : '' }} pending approval.</p></div>
          </a>
          @endif
          @if(($unassigned ?? 0) > 0)
          <a href="{{ route('logistics.assign') }}" class="notif-item" style="text-decoration:none;color:inherit;display:flex">
            <div class="dot"></div>
            <div><p>{{ $unassigned }} shipment{{ $unassigned > 1 ? 's' : '' }} unassigned.</p></div>
          </a>
          @endif
          @if(($pendingDeliveries ?? 0) === 0 && ($unassigned ?? 0) === 0)
          <div class="notif-item read" style="display:flex">
            <div class="dot"></div>
            <div><p>No new notifications.</p></div>
          </div>
          @endif
        </div>
      </div>
    </div>

    <a href="{{ route('logistics.account') }}" class="topbar-avatar" aria-label="My Account">
      {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
    </a>
  </div>
</header>
