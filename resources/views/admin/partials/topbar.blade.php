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
      if (($pendingRegistrations ?? 0) > 0)
          $notifItems[] = ['text' => $pendingRegistrations . ' registration' . ($pendingRegistrations > 1 ? 's' : '') . ' pending review.', 'link' => route('admin.registrations'), 'read' => false];
      if (($openDisputes ?? 0) > 0)
          $notifItems[] = ['text' => $openDisputes . ' open dispute' . ($openDisputes > 1 ? 's' : '') . ' need attention.', 'link' => route('admin.complaints'), 'read' => false];
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

    <a href="{{ route('admin.account') }}" class="topbar-avatar">
      {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
    </a>
  </div>
</header>
