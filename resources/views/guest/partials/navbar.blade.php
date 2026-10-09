<header class="pfx-header">
  <div class="container pfx-header-row">

    <a class="pfx-logo" href="{{ url('/') }}">
      <span class="pfx-logo-mark">
        <img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds">
      </span>
      <span class="pfx-logo-word">Pocket<span>Finds</span></span>
    </a>

      <form class="pfx-search" action="{{ url('/') }}" method="GET" role="search">
        <button type="submit" class="pfx-search-submit" aria-label="Search">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
        <input name="q" type="search" placeholder="Search" value="{{ $search ?? '' }}" aria-label="Search products">
      </form>

    <nav class="pfx-nav">
      <a href="{{ url('/' ) }}#categories">Categories</a>
      <a href="{{ url('/' ) }}#shops">Shops</a>
      @if(!isset($deals) || count($deals))<a href="{{ url('/' ) }}#deals">Deals</a>@endif
      <a href="{{ url('/' ) }}#about">About</a>
    </nav>

    <div class="pfx-header-actions">


      <button class="pfx-icon-btn" type="button" data-protected title="Wishlist" aria-label="Wishlist">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
      </button>
      <button class="pfx-icon-btn" type="button" data-protected title="Cart" aria-label="Cart">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
      </button>

      <div class="pfx-account-btn" id="pf-acct-btn">
        <button class="pfx-icon-btn" type="button" id="pf-acct-toggle" aria-label="Account" aria-expanded="false" aria-controls="pf-acct-dropdown">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </button>
        <div class="pfx-acct-dropdown" id="pf-acct-dropdown">
          <a href="{{ url('/login') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Sign In
          </a>
          <div class="pfx-acct-sep"></div>
          <a class="pfx-register-link" href="{{ url('/register/type') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            Create Account
          </a>
        </div>
      </div>
    </div>

  </div>
</header>
