<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">
      <img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img">
    </div>
    <div>
      <div class="name"><span>Pocket</span><span>Finds</span></div>
      <span class="tag">Seller Portal</span>
    </div>
  </div>

  <div class="nav-groups">
    <a @if(request()->routeIs('seller.dashboard')) aria-current="page" @endif href="{{ route('seller.dashboard') }}" class="nav-item {{ request()->routeIs('seller.dashboard') ? 'active' : '' }}">
      <span class="ic">@include('seller.partials.icon', ['name' => 'dashboard', 'size' => 16])</span> Dashboard
    </a>
    @php
      $catalogOpen = request()->routeIs('seller.inventory', 'seller.vouchers', 'seller.shop-categories.*', 'seller.account*');
      $customerOpen = request()->routeIs('seller.feedback', 'seller.messages*', 'seller.announcements*', 'account.cancellation-refunds');
      $financeOpen = request()->routeIs('seller.shipping*', 'seller.payouts*', 'seller.reports', 'commissions.*', 'gcash.*');
      $settingsOpen = request()->routeIs('seller.account*');
    @endphp
    <a @if(request()->routeIs('seller.orders*','seller.assignments.*')) aria-current="page" @endif href="{{ route('seller.orders') }}" class="nav-item {{ request()->routeIs('seller.orders*','seller.assignments.*') ? 'active' : '' }}"><span class="ic">@include('seller.partials.icon', ['name' => 'orders', 'size' => 16])</span> Order Management</a>
    <div class="navigation-group">
      <button class="navigation-group-toggle" type="button" data-navigation-group aria-controls="sellerNavCatalog" aria-expanded="{{ $catalogOpen ? 'true' : 'false' }}"><span>Products & Promotions</span><span class="navigation-chevron" aria-hidden="true">⌄</span></button>
      <div class="navigation-group-items" id="sellerNavCatalog" @if(!$catalogOpen) hidden @endif>
        <a @if(request()->routeIs('seller.inventory')) aria-current="page" @endif href="{{ route('seller.inventory') }}" class="nav-item {{ request()->routeIs('seller.inventory') ? 'active' : '' }}"><span class="ic">@include('seller.partials.icon', ['name' => 'inventory', 'size' => 16])</span> Inventory</a>
        <a class="nav-item {{ request()->routeIs('seller.shop-categories.*') ? 'active' : '' }}" href="{{ route('seller.shop-categories.index') }}" @if(request()->routeIs('seller.shop-categories.*')) aria-current="page" @endif>Shop Categories</a>
        <a @if(request()->routeIs('seller.vouchers')) aria-current="page" @endif href="{{ route('seller.vouchers') }}" class="nav-item {{ request()->routeIs('seller.vouchers') ? 'active' : '' }}"><span class="ic">@include('seller.partials.icon', ['name' => 'tag', 'size' => 16])</span> Vouchers</a>
        <a class="nav-item" href="{{ route('seller.account') }}#flash-sales">Promotions</a>
      </div>
    </div>
    <div class="navigation-group">
      <button class="navigation-group-toggle" type="button" data-navigation-group aria-controls="sellerNavCustomers" aria-expanded="{{ $customerOpen ? 'true' : 'false' }}"><span>Customers & Updates</span><span class="navigation-chevron" aria-hidden="true">⌄</span></button>
      <div class="navigation-group-items" id="sellerNavCustomers" @if(!$customerOpen) hidden @endif>
        <a @if(request()->routeIs('seller.feedback')) aria-current="page" @endif href="{{ route('seller.feedback') }}" class="nav-item {{ request()->routeIs('seller.feedback') ? 'active' : '' }}"><span class="ic">@include('seller.partials.icon', ['name' => 'star', 'size' => 16])</span> Customer Feedback</a>
        <a @if(request()->routeIs('seller.messages*')) aria-current="page" @endif href="{{ route('seller.messages') }}" class="nav-item {{ request()->routeIs('seller.messages*') ? 'active' : '' }}"><span class="ic">@include('seller.partials.icon', ['name' => 'mail', 'size' => 16])</span> Messages</a>
        <a @if(request()->routeIs('seller.announcements*')) aria-current="page" @endif href="{{ route('seller.announcements') }}" class="nav-item {{ request()->routeIs('seller.announcements*') ? 'active' : '' }}"><span class="ic">@include('seller.partials.icon', ['name' => 'megaphone', 'size' => 16])</span> Announcements</a>
        <a class="nav-item {{ request()->routeIs('account.cancellation-refunds') ? 'active' : '' }}" href="{{ route('account.cancellation-refunds') }}" @if(request()->routeIs('account.cancellation-refunds')) aria-current="page" @endif>Cancellation Refunds</a>
      </div>
    </div>
    <div class="navigation-group">
      <button class="navigation-group-toggle" type="button" data-navigation-group aria-controls="sellerNavFinance" aria-expanded="{{ $financeOpen ? 'true' : 'false' }}"><span>Finance & Reports</span><span class="navigation-chevron" aria-hidden="true">⌄</span></button>
      <div class="navigation-group-items" id="sellerNavFinance" @if(!$financeOpen) hidden @endif>
        @if(app(\App\Services\CommissionLedger::class)->installed())
        <a class="nav-item {{ request()->routeIs('commissions.*') ? 'active' : '' }}" href="{{ route('commissions.index') }}" @if(request()->routeIs('commissions.*')) aria-current="page" @endif><span class="ic">@include('seller.partials.icon', ['name'=>'chart','size'=>16])</span> Commission Statements</a>
        @endif
        @if(\App\Services\GcashInventory::installed())
        <a @if(request()->routeIs('gcash.*')) aria-current="page" @endif href="{{ route('gcash.index') }}" class="nav-item {{ request()->routeIs('gcash.*') ? 'active' : '' }}"><span class="ic">@include('seller.partials.icon', ['name' => 'orders', 'size' => 16])</span> Buyer GCash Payments</a>
        @endif
        <a href="{{ route('seller.shipping') }}" class="nav-item {{ request()->routeIs('seller.shipping*') ? 'active' : '' }}" @if(request()->routeIs('seller.shipping*')) aria-current="page" @endif><span class="ic">@include('seller.partials.icon', ['name' => 'truck', 'size' => 16])</span> Shipping Payments</a>
        <a href="{{ route('seller.payouts') }}" class="nav-item {{ request()->routeIs('seller.payouts*') ? 'active' : '' }}" @if(request()->routeIs('seller.payouts*')) aria-current="page" @endif><span class="ic">@include('seller.partials.icon', ['name' => 'orders', 'size' => 16])</span> COD Payouts</a>
        <a @if(request()->routeIs('seller.reports')) aria-current="page" @endif href="{{ route('seller.reports') }}" class="nav-item {{ request()->routeIs('seller.reports') ? 'active' : '' }}"><span class="ic">@include('seller.partials.icon', ['name' => 'chart', 'size' => 16])</span> Reports</a>
      </div>
    </div>
    <div class="navigation-group">
      <button class="navigation-group-toggle" type="button" data-navigation-group aria-controls="sellerNavSettings" aria-expanded="{{ $settingsOpen ? 'true' : 'false' }}"><span>Store Settings</span><span class="navigation-chevron" aria-hidden="true">⌄</span></button>
      <div class="navigation-group-items" id="sellerNavSettings" @if(!$settingsOpen) hidden @endif>
        <a class="nav-item" href="{{ route('seller.account') }}#shipping-rates">Shipping Rates & Availability</a>
        <a @if(request()->routeIs('seller.account*')) aria-current="page" @endif href="{{ route('seller.account') }}" class="nav-item {{ request()->routeIs('seller.account*') ? 'active' : '' }}"><span class="ic">@include('seller.partials.icon', ['name' => 'user', 'size' => 16])</span> My Account</a>
      </div>
    </div>
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
