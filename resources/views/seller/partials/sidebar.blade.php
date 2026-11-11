<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand" role="button" tabindex="0" data-sidebar-toggle aria-label="Expand or collapse navigation">
    <div class="mark">
      <img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img">
    </div>
    <div>
      <div class="name"><span>Pocket</span><span>Finds</span></div>
      <span class="tag">Seller Portal</span>
    </div>
  </div>

  <div class="nav-groups">
    @include('seller.partials.workspace-navigation')
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
