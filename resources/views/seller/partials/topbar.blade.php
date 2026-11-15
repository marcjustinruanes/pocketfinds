<div class="seller-utility-row" role="group" aria-label="Account and notifications">
  <div class="seller-page-heading">
    <p class="seller-page-welcome">Welcome, {{ auth()->user()->given_names }}</p>
    <h1 class="seller-page-name">@yield('page-title', 'Dashboard')</h1>
  </div>
  <div class="topbar-actions">
    <time class="seller-clock" datetime="{{ now()->toIso8601String() }}" data-seller-clock data-time-zone="{{ config('app.timezone') }}"><span data-clock-time>{{ now()->format('g:i A') }}</span><span data-clock-date>{{ now()->format('M j, Y') }}</span></time>
    @php
      $notifRows = \App\Models\Order::sellerNotificationsQuery((int) auth()->id())->orderByDesc('created_at')->limit(8)->get();
      $unreadNotifCount = \App\Models\Order::sellerNotificationsQuery((int) auth()->id())->where('is_read', false)->count();
    @endphp
    @include('account.partials.notification-menu', ['panelNotifications'=>$notifRows, 'panelUnreadCount'=>$unreadNotifCount, 'panelOpenRoute'=>'seller.notifications.open', 'panelFooterUrl'=>route('seller.notifications'), 'notificationIconView'=>'seller.partials.icon', 'notificationIconSize'=>16])
    <a href="{{ route('seller.account') }}" class="topbar-avatar seller-account-link" aria-label="My account" @if(request()->routeIs('seller.account')) aria-current="page" @endif>
      <span class="seller-account-initial" aria-hidden="true">{{ strtoupper(substr(auth()->user()->given_names, 0, 1)) }}</span>
    </a>
  </div>
</div>
