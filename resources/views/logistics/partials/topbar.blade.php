<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle>
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="20" height="20"><path d="M3 5h14M3 10h14M3 15h14"/></svg>
  </button>
  <div class="page-heading">
    <h1>@yield('page-title', 'Dashboard')</h1>
    <p>@yield('page-sub', '')</p>
  </div>
  <div class="topbar-search">
    <svg class="ic" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" width="14" height="14"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M15 15l-3-3"/></svg>
    <input type="text" placeholder="Search…">
  </div>
  <div class="topbar-actions">
    @php
      $notifItems = [];
      if (($pendingDeliveries ?? 0) > 0)
          $notifItems[] = ['text' => ($pendingDeliveries ?? 0) . ' delivery request(s) pending.', 'link' => route('logistics.requests'), 'read' => false];
      if (($unassigned ?? 0) > 0)
          $notifItems[] = ['text' => ($unassigned ?? 0) . ' delivery(s) unassigned.', 'link' => route('logistics.assign'), 'read' => false];
      if (($activeDeliveries ?? 0) > 0)
          $notifItems[] = ['text' => ($activeDeliveries ?? 0) . ' active delivery(s) in progress.', 'link' => route('logistics.monitor'), 'read' => true];
      if (empty($notifItems))
          $notifItems[] = ['text' => 'No new notifications.', 'link' => '#', 'read' => true];
      $unreadCount = collect($notifItems)->where('read', false)->count();
    @endphp

    <div class="dropdown">
      <button class="icon-btn" data-dropdown-toggle="notifPanel">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" width="17" height="17"><path d="M10 2a6 6 0 0 1 6 6v3l1.5 2.5H2.5L4 11V8a6 6 0 0 1 6-6z"/><path d="M8 16a2 2 0 0 0 4 0"/></svg>
        @if($unreadCount > 0)<span class="dot-badge"></span>@endif
      </button>
      <div class="dropdown-panel" id="notifPanel">
        <div class="dropdown-head">
          <h3>Notifications</h3>
          @if($unreadCount > 0)<span style="font-size:11px;color:var(--pink-dark);font-weight:700">{{ $unreadCount }} new</span>@endif
        </div>
        <div class="notif-list">
          @foreach($notifItems as $notif)
          <a href="{{ $notif['link'] }}" class="notif-item {{ $notif['read'] ? 'read' : '' }}" style="text-decoration:none;color:inherit;display:flex">
            <div class="dot"></div>
            <div><p>{{ $notif['text'] }}</p></div>
          </a>
          @endforeach
        </div>
      </div>
    </div>

    <a href="{{ route('logistics.account') }}" class="topbar-profile">
      <div class="topbar-avatar">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</div>
      <div class="topbar-profile-info">
        <strong>{{ auth()->user()->first_name }}</strong>
        <span>Logistics Admin</span>
      </div>
    </a>
  </div>
</header>
