<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle aria-label="Toggle navigation">
    <x-admin-icon name="menu" />
  </button>
  <div class="page-heading">
    <h1>@yield('page-title', 'Courier')</h1>
    <p>@yield('page-sub', '')</p>
  </div>
  <div class="topbar-actions">
    <div class="dropdown">
      <button class="icon-btn" data-dropdown-toggle="riderNotifPanel" aria-label="Notifications">
        <x-admin-icon name="bell" />
        @if(($availableRequests ?? 0) > 0)<span class="dot-badge"></span>@endif
      </button>
      <div class="dropdown-panel" id="riderNotifPanel">
        <div class="dropdown-head"><h3>Notifications</h3></div>
        <div class="notif-list">
          @if(($availableRequests ?? 0) > 0)
          <a href="{{ route('rider.requests') }}" class="notif-item" style="text-decoration:none;color:inherit;display:flex">
            <div class="dot"></div>
            <div><p>{{ $availableRequests }} pickup request{{ $availableRequests > 1 ? 's' : '' }} available.</p></div>
          </a>
          @endif
          @if(($activeDeliveries ?? 0) > 0)
          <a href="{{ route('rider.deliveries') }}" class="notif-item" style="text-decoration:none;color:inherit;display:flex">
            <div class="dot"></div>
            <div><p>{{ $activeDeliveries }} delivery{{ $activeDeliveries > 1 ? 'ies' : 'y' }} in progress.</p></div>
          </a>
          @endif
          @if(($availableRequests ?? 0) === 0 && ($activeDeliveries ?? 0) === 0)
          <div class="notif-item read" style="display:flex">
            <div class="dot"></div>
            <div><p>No new notifications.</p></div>
          </div>
          @endif
        </div>
      </div>
    </div>

    <a href="{{ route('rider.account') }}" class="topbar-avatar" aria-label="My Account">
      @if(auth()->user()->profile_picture)
      <img src="{{ \Illuminate\Support\Facades\Storage::disk('profile_images')->url(auth()->user()->profile_picture) }}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
      @else
      {{ strtoupper(substr(auth()->user()->given_names, 0, 1)) }}
      @endif
    </a>
  </div>
</header>
