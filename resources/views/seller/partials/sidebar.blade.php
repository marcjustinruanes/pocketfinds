<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">
      <img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img">
    </div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Seller Portal</span>
    </div>
  </div>

  <div class="nav-groups">
    @php($unreadNotifCount = \Illuminate\Support\Facades\DB::table('notifications')->where('user_id', auth()->id())->where('is_read', false)->count())
<a @if(request()->routeIs('seller.dashboard')) aria-current="page" @endif href="{{ route('seller.dashboard') }}" class="nav-item {{ request()->routeIs('seller.dashboard') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'dashboard', 'size' => 16])</span> Dashboard
    </a>
<x-navigation-group id="seller-orders" label="Orders" :active="request()->routeIs('seller.notifications', 'seller.orders*', 'gcash.*')">
<a @if(request()->routeIs('seller.notifications')) aria-current="page" @endif href="{{ route('seller.notifications') }}" class="nav-item {{ request()->routeIs('seller.notifications') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'bell', 'size' => 16])</span> Notifications
      @if($unreadNotifCount > 0)<span class="badge">{{ $unreadNotifCount }}</span>@endif
    </a>
<a @if(request()->routeIs('seller.orders*')) aria-current="page" @endif href="{{ route('seller.orders') }}" class="nav-item {{ request()->routeIs('seller.orders*') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'orders', 'size' => 16])</span> Order Management
    </a>
@if(\App\Services\GcashInventory::installed())
<a @if(request()->routeIs('gcash.*')) aria-current="page" @endif href="{{ route('gcash.index') }}" class="nav-item {{ request()->routeIs('gcash.*') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'orders', 'size' => 16])</span> GCash payments
    </a>
@endif
</x-navigation-group>
<x-navigation-group id="seller-store" label="Store" :active="request()->routeIs('seller.inventory', 'seller.vouchers', 'seller.feedback')">
<a @if(request()->routeIs('seller.inventory')) aria-current="page" @endif href="{{ route('seller.inventory') }}" class="nav-item {{ request()->routeIs('seller.inventory') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'inventory', 'size' => 16])</span> Inventory
    </a>
<a @if(request()->routeIs('seller.vouchers')) aria-current="page" @endif href="{{ route('seller.vouchers') }}" class="nav-item {{ request()->routeIs('seller.vouchers') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'tag', 'size' => 16])</span> Vouchers
    </a>
<a @if(request()->routeIs('seller.feedback')) aria-current="page" @endif href="{{ route('seller.feedback') }}" class="nav-item {{ request()->routeIs('seller.feedback') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'star', 'size' => 16])</span> Customer Feedback
    </a>
</x-navigation-group>
<a class="nav-item" href="{{ route('seller.account') }}#shipping-rates">Shipping Rates & Availability</a>
<a class="nav-item" href="{{ route('seller.inventory') }}#flash-sales">Flash Sales</a>
<x-navigation-group id="seller-tracking-reports" label="Tracking & Reports" :active="request()->routeIs('seller.reports')">
<a @if(request()->routeIs('seller.reports')) aria-current="page" @endif href="{{ route('seller.reports') }}" class="nav-item {{ request()->routeIs('seller.reports') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'chart', 'size' => 16])</span> Reports
    </a>
</x-navigation-group>
<x-navigation-group id="seller-communications" label="Communications" :active="request()->routeIs('seller.messages*', 'seller.announcements*')">
<a @if(request()->routeIs('seller.messages*')) aria-current="page" @endif href="{{ route('seller.messages') }}" class="nav-item {{ request()->routeIs('seller.messages*') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'mail', 'size' => 16])</span> Messages
    </a>
<a @if(request()->routeIs('seller.announcements*')) aria-current="page" @endif href="{{ route('seller.announcements') }}" class="nav-item {{ request()->routeIs('seller.announcements*') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'megaphone', 'size' => 16])</span> Announcements
    </a>
</x-navigation-group>
<a @if(request()->routeIs('seller.account*')) aria-current="page" @endif href="{{ route('seller.account') }}" class="nav-item {{ request()->routeIs('seller.account*') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'user', 'size' => 16])</span> My Account
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      <div class="avatar">{{ strtoupper(substr(auth()->user()->given_names, 0, 1)) }}</div>
      <div class="who">
        <strong>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</strong>
        <span>Seller</span>
      </div>
    </div>
    <button class="logout-btn" data-logout>
      @include('seller.partials.icon', ['name' => 'logout', 'size' => 14]) Sign out
    </button>
  </div>
</nav>
