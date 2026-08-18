<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle>☰</button>
  <div class="page-heading">
    <h1>@yield('page-title', 'Dashboard')</h1>
    <p>@yield('page-sub', '')</p>
  </div>
  <div class="topbar-search">
    <span class="ic">🔍</span>
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
        🔔@if($unreadCount > 0)<span class="dot-badge"></span>@endif
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

    <a href="{{ route('logistics.account') }}" class="topbar-avatar">
      {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
    </a>
  </div>
</header>
