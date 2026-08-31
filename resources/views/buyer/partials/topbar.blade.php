<header class="topbar">
  <a href="{{ route('buyer.dashboard') }}" class="topbar-brand" title="PocketFinds Home">
    <span class="mark">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
    </span>
  </a>
  <div class="page-heading">
    <h1>@yield('page-title', 'Dashboard')</h1>
    <p>@yield('page-sub', '')</p>
  </div>
  <form class="topbar-search" action="{{ route('buyer.browse') }}" method="GET" data-topbar-search>
    <input type="text" name="q" placeholder="Search for products, brands and categories" value="{{ request('q') }}">
    <button type="submit" aria-label="Search">
      @include('buyer.partials.icon', ['name' => 'search', 'size' => 15])
    </button>
  </form>
  <div class="topbar-actions">
    @php
      $notifRows = \Illuminate\Support\Facades\DB::table('notifications')
        ->where('user_id', auth()->id())
        ->orderByDesc('created_at')
        ->limit(8)
        ->get();
      $unreadNotifCount = $notifRows->where('is_read', false)->count();
    @endphp
    <div class="dropdown">
      <button class="icon-btn" data-dropdown-toggle="buyerNotifPanel" aria-label="Notifications" title="Notifications">
        @include('buyer.partials.icon', ['name' => 'bell', 'size' => 16])
        @if($unreadNotifCount > 0)<span class="dot-badge"></span>@endif
      </button>
      <div class="dropdown-panel" id="buyerNotifPanel">
        <div class="dropdown-head">
          <h3>Notifications</h3>
          @if($unreadNotifCount > 0)<span style="font-size:11px;font-weight:700;color:var(--pink-dark)">{{ $unreadNotifCount }} new</span>@endif
        </div>
        <div class="notif-list">
          @forelse($notifRows as $notif)
            <a href="{{ route('buyer.notifications.open', $notif->id) }}" class="notif-item {{ $notif->is_read ? 'read' : '' }}">
              <div class="dot"></div>
              <div>
                <p>{{ $notif->message }}</p>
                <div class="notif-time">{{ \Carbon\Carbon::parse($notif->created_at)->diffForHumans() }}</div>
              </div>
            </a>
          @empty
            <a href="#" class="notif-item read" style="pointer-events:none">
              <div class="dot"></div>
              <div><p>No notifications yet.</p></div>
            </a>
          @endforelse
        </div>
      </div>
    </div>
    <a href="{{ route('buyer.cart') }}" class="icon-btn" id="cartIconBtn" title="Cart">
      @include('buyer.partials.icon', ['name' => 'cart', 'size' => 16])
      @php($cartCount = (int) \App\Models\CartItem::where('buyer_id', auth()->id())->sum('qty'))
      <span id="cartBadge" style="{{ $cartCount ? '' : 'display:none;' }}position:absolute;top:-5px;right:-5px;min-width:16px;height:16px;padding:0 4px;border-radius:8px;background:var(--pink);color:#fff;font-size:10px;font-weight:700;line-height:16px;text-align:center">{{ $cartCount ?: '' }}</span>
    </a>
    <button type="button" class="icon-btn" title="Messages" data-messages-trigger data-messages-url="{{ route('buyer.messages') }}">
      @include('buyer.partials.icon', ['name' => 'mail', 'size' => 16])
    </button>
    <div class="dropdown">
      <button type="button" class="topbar-avatar" data-dropdown-toggle="buyerAccountPanel" aria-label="Account menu">
        {{ strtoupper(substr(auth()->user()->given_names, 0, 1)) }}
      </button>
      <div class="dropdown-panel account-menu-panel" id="buyerAccountPanel">
        <a href="{{ route('buyer.account') }}" class="account-menu-item">
          @include('buyer.partials.icon', ['name' => 'user', 'size' => 15])
          <span>Account Settings</span>
        </a>
        <a href="{{ route('buyer.orders') }}" class="account-menu-item">
          @include('buyer.partials.icon', ['name' => 'package', 'size' => 15])
          <span>My Orders</span>
        </a>
        <button type="button" class="account-menu-item" data-logout>
          @include('buyer.partials.icon', ['name' => 'logout', 'size' => 15])
          <span>Logout</span>
        </button>
      </div>
    </div>
  </div>
</header>
