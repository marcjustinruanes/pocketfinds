<header class="topbar">
  <div class="page-heading">
    <h1>@yield('page-title', 'Dashboard')</h1>
    @hasSection('page-sub')
      <p>@yield('page-sub')</p>
    @endif
  </div>
  <form class="topbar-search" method="GET" action="{{ route('seller.orders') }}" role="search">
    <button type="submit" class="ic" aria-label="Search orders">@include('seller.partials.icon', ['name' => 'search', 'size' => 16])</button>
    <input type="search" name="q" placeholder="Search orders" aria-label="Search orders" value="{{ request()->query('q') }}">
  </form>
  <div class="topbar-actions">
    @php
      $notifRows = \App\Models\Order::sellerNotificationsQuery((int) auth()->id())->orderByDesc('created_at')->limit(8)->get();
      $unreadNotifCount = \App\Models\Order::sellerNotificationsQuery((int) auth()->id())->where('is_read', false)->count();
    @endphp
    @include('account.partials.notification-menu', ['panelNotifications'=>$notifRows, 'panelUnreadCount'=>$unreadNotifCount, 'panelOpenRoute'=>'seller.notifications.open', 'panelFooterUrl'=>route('seller.notifications')])
    <a href="{{ route('seller.account') }}" class="topbar-avatar">
      {{ strtoupper(substr(auth()->user()->given_names, 0, 1)) }}
    </a>
  </div>
</header>
