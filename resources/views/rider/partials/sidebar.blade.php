<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">C</div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Courier</span>
    </div>
  </div>

  <div class="nav-groups">
<a @if(request()->routeIs('rider.dashboard')) aria-current="page" @endif href="{{ route('rider.dashboard') }}" class="nav-item {{ request()->routeIs('rider.dashboard') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="dashboard" /></span> Dashboard
    </a>
<x-navigation-group id="rider-pickup-operations" label="Pickup Operations" :active="request()->routeIs('rider.pickup-requests', 'rider.my-pickups')">
<a @if(request()->routeIs('rider.pickup-requests')) aria-current="page" @endif href="{{ route('rider.pickup-requests') }}" class="nav-item {{ request()->routeIs('rider.pickup-requests') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="box" /></span> Pickup Requests
      @if(($pickupRequests ?? 0) > 0)<span class="count">{{ $pickupRequests }}</span>@endif
    </a>
<a @if(request()->routeIs('rider.my-pickups')) aria-current="page" @endif href="{{ route('rider.my-pickups') }}" class="nav-item {{ request()->routeIs('rider.my-pickups') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="pin" /></span> My Pickups
      @if(($myPickups ?? 0) > 0)<span class="count">{{ $myPickups }}</span>@endif
    </a>
</x-navigation-group>
<x-navigation-group id="rider-transfer-operations" label="Transfer Operations" :active="request()->routeIs('rider.hub-transfers')">
<a @if(request()->routeIs('rider.hub-transfers')) aria-current="page" @endif href="{{ route('rider.hub-transfers') }}" class="nav-item {{ request()->routeIs('rider.hub-transfers') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="layers" /></span> My Hub Transfers
      @if(($myHubTransfers ?? 0) > 0)<span class="count">{{ $myHubTransfers }}</span>@endif
    </a>
</x-navigation-group>
<x-navigation-group id="rider-delivery-operations" label="Delivery Operations" :active="request()->routeIs('rider.requests', 'rider.deliveries*')">
<a @if(request()->routeIs('rider.requests')) aria-current="page" @endif href="{{ route('rider.requests') }}" class="nav-item {{ request()->routeIs('rider.requests') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="layers" /></span> Delivery Requests
      @if(($deliveryRequests ?? 0) > 0)<span class="count">{{ $deliveryRequests }}</span>@endif
    </a>
<a @if(request()->routeIs('rider.deliveries*')) aria-current="page" @endif href="{{ route('rider.deliveries') }}" class="nav-item {{ request()->routeIs('rider.deliveries*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="truck" /></span> My Deliveries
      @if(($activeDeliveries ?? 0) > 0)<span class="count">{{ $activeDeliveries }}</span>@endif
    </a>
</x-navigation-group>
<x-navigation-group id="rider-earnings-history" label="Earnings & History" :active="request()->routeIs('rider.profit', 'rider.history')">
<a @if(request()->routeIs('rider.profit')) aria-current="page" @endif href="{{ route('rider.profit') }}" class="nav-item {{ request()->routeIs('rider.profit') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="peso" /></span> Profit
    </a>
<a @if(request()->routeIs('rider.history')) aria-current="page" @endif href="{{ route('rider.history') }}" class="nav-item {{ request()->routeIs('rider.history') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="clock" /></span> Delivery History
    </a>
</x-navigation-group>
<x-navigation-group id="rider-communications" label="Communications" :active="request()->routeIs('rider.messages*')">
<a @if(request()->routeIs('rider.messages*')) aria-current="page" @endif href="{{ route('rider.messages') }}" class="nav-item {{ request()->routeIs('rider.messages*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="mail" /></span> Messages
      @if(($unreadMessages ?? 0) > 0)<span class="count">{{ $unreadMessages }}</span>@endif
    </a>
</x-navigation-group>
<a @if(request()->routeIs('rider.account*')) aria-current="page" @endif href="{{ route('rider.account') }}" class="nav-item {{ request()->routeIs('rider.account*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="account" /></span> My Account
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      @if(auth()->user()->profile_picture)
      <img class="avatar" src="{{ \Illuminate\Support\Facades\Storage::disk('profile_images')->url(auth()->user()->profile_picture) }}" alt="" style="object-fit:cover">
      @else
      <div class="avatar">{{ strtoupper(substr(auth()->user()->given_names, 0, 1)) }}</div>
      @endif
      <div class="who">
        <strong>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</strong>
        <span>Courier</span>
      </div>
    </div>
    <button class="logout-btn" data-logout><x-admin-icon name="logout" /> Sign out</button>
  </div>
</nav>
