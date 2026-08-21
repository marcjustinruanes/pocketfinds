<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle>
    @include('buyer.partials.icon', ['name' => 'menu', 'size' => 20])
  </button>
  <div class="page-heading">
    <h1>@yield('page-title', 'Dashboard')</h1>
    <p>@yield('page-sub', '')</p>
  </div>
  <div class="topbar-search">
    <span class="ic">@include('buyer.partials.icon', ['name' => 'search', 'size' => 14])</span>
    <input type="text" placeholder="Search products…">
  </div>
  <div class="topbar-actions">
    <a href="{{ route('buyer.cart') }}" class="icon-btn" id="cartIconBtn" title="Cart">
      @include('buyer.partials.icon', ['name' => 'cart', 'size' => 16])
      @php($cartCount = array_sum(array_column(session('cart', []), 'qty')))
      <span id="cartBadge" style="{{ $cartCount ? '' : 'display:none;' }}position:absolute;top:-5px;right:-5px;min-width:16px;height:16px;padding:0 4px;border-radius:8px;background:var(--pink);color:#fff;font-size:10px;font-weight:700;line-height:16px;text-align:center">{{ $cartCount ?: '' }}</span>
    </a>
    <a href="{{ route('buyer.messages') }}" class="icon-btn" title="Messages">
      @include('buyer.partials.icon', ['name' => 'mail', 'size' => 16])
    </a>
    <a href="{{ route('buyer.account') }}" class="topbar-avatar">
      {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
    </a>
  </div>
</header>
