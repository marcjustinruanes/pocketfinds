<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle>
    @include('seller.partials.icon', ['name' => 'menu', 'size' => 20])
  </button>
  <div class="page-heading">
    <h1>@yield('page-title', 'Dashboard')</h1>
    <p>@yield('page-sub', '')</p>
  </div>
  <div class="topbar-search">
    <span class="ic">@include('seller.partials.icon', ['name' => 'search', 'size' => 14])</span>
    <input type="text" placeholder="Search orders, products…">
  </div>
  <div class="topbar-actions">
    @php
      $notifRows = \Illuminate\Support\Facades\DB::table('notifications')->where('user_id', auth()->id())->orderByDesc('created_at')->limit(8)->get();
      $unreadNotifCount = $notifRows->where('is_read', false)->count();
    @endphp
    <div class="dropdown">
      <button type="button" class="icon-btn" data-dropdown-toggle="sellerNotifPanel" aria-label="Notifications" title="Notifications">
        @include('seller.partials.icon', ['name' => 'bell', 'size' => 16])
        @if($unreadNotifCount > 0)<span class="dot-badge"></span>@endif
      </button>
      <div class="dropdown-panel" id="sellerNotifPanel">
        <div class="dropdown-head"><h3>Notifications</h3>@if($unreadNotifCount > 0)<span class="notif-new-count">{{ $unreadNotifCount }} new</span>@endif</div>
        <div class="notif-list">
          @forelse($notifRows as $notif)
            <a href="{{ route('seller.notifications.open', $notif->id) }}" class="notif-item {{ $notif->is_read ? 'read' : '' }}">
              <div class="dot"></div><div><p>{{ $notif->message }}</p><div class="notif-item-time">{{ \Carbon\Carbon::parse($notif->created_at)->diffForHumans() }}</div></div>
            </a>
          @empty
            <div class="notif-item read"><div class="dot"></div><div><p>No notifications yet.</p></div></div>
          @endforelse
        </div>
        <a href="{{ route('seller.notifications') }}" class="notif-view-all">View all notifications</a>
      </div>
    </div>
    @php($hasUnreadMessages = \App\Models\Message::where('receiver_id', auth()->id())->where('read', false)->exists())
    <a href="{{ route('seller.messages') }}" class="icon-btn" title="Messages">
      @include('seller.partials.icon', ['name' => 'mail', 'size' => 16])
      <span style="{{ $hasUnreadMessages ? '' : 'display:none;' }}position:absolute;top:7px;right:7px;width:7px;height:7px;border-radius:50%;background:var(--pink);border:1px solid #fff"></span>
    </a>
    <a href="{{ route('seller.account') }}" class="topbar-avatar">
      {{ strtoupper(substr(auth()->user()->given_names, 0, 1)) }}
    </a>
  </div>
</header>
