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
  @php
    // Respect Settings → Notifications: a toggled-off alert never surfaces here,
    // no matter how many requests/unassigned shipments are actually waiting.
    $showRequestAlert    = ($pendingDeliveries ?? 0) > 0 && auth()->user()->notify_new_requests;
    $showUnassignedAlert = ($unassigned ?? 0) > 0 && auth()->user()->notify_unassigned_shipments;
  @endphp
  <div class="topbar-actions">
    <div class="dropdown">
      <button class="icon-btn" data-dropdown-toggle="logisticsNotifPanel" aria-label="Notifications">
        <x-admin-icon name="bell" />
        @if($showRequestAlert || $showUnassignedAlert)
        <span class="dot-badge"></span>
        @endif
      </button>
      <div class="dropdown-panel" id="logisticsNotifPanel">
        <div class="dropdown-head">
          <h3>Notifications</h3>
        </div>
        <div class="notif-list">
          @if($showRequestAlert)
          <a href="{{ route('logistics.requests') }}" class="notif-item" style="text-decoration:none;color:inherit;display:flex">
            <div class="dot"></div>
            <div><p>{{ $pendingDeliveries }} delivery request{{ $pendingDeliveries > 1 ? 's' : '' }} pending approval.</p></div>
          </a>
          @endif
          @if($showUnassignedAlert)
          <a href="{{ route('logistics.assignments') }}" class="notif-item" style="text-decoration:none;color:inherit;display:flex">
            <div class="dot"></div>
            <div><p>{{ $unassigned }} shipment{{ $unassigned > 1 ? 's' : '' }} unassigned.</p></div>
          </a>
          @endif
          @if(!$showRequestAlert && !$showUnassignedAlert)
          <div class="notif-item read" style="display:flex">
            <div class="dot"></div>
            <div><p>No new notifications.</p></div>
          </div>
          @endif
        </div>
      </div>
    </div>

    <a href="{{ route('logistics.account') }}" class="topbar-avatar" aria-label="My Account">
      @if(auth()->user()->profile_picture)
      <img src="{{ \Illuminate\Support\Facades\Storage::disk('profile_images')->url(auth()->user()->profile_picture) }}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
      @else
      {{ strtoupper(substr(auth()->user()->given_names, 0, 1)) }}
      @endif
    </a>
  </div>
</header>
