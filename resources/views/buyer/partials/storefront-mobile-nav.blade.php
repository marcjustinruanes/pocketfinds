<nav class="mobile-nav" aria-label="Buyer navigation">
  <a href="{{ route('buyer.dashboard') }}" @if(request()->routeIs('buyer.dashboard')) aria-current="page" @endif>@include('buyer.partials.icon', ['name' => 'home', 'size' => 20]) Home</a>
  <a href="{{ route('buyer.orders') }}" @if(request()->routeIs('buyer.orders')) aria-current="page" @endif>@include('buyer.partials.icon', ['name' => 'package', 'size' => 20]) Orders</a>
  <a href="{{ route('buyer.cart') }}" @if(request()->routeIs('buyer.cart')) aria-current="page" @endif>@include('buyer.partials.icon', ['name' => 'cart', 'size' => 20]) Cart</a>
  <a href="{{ route('buyer.account') }}" @if(request()->routeIs('buyer.account*')) aria-current="page" @endif>@include('buyer.partials.icon', ['name' => 'user', 'size' => 20]) Account</a>
</nav>
