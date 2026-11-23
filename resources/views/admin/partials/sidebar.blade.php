<nav class="sidebar" id="sidebar" aria-label="Platform administration">
  <div class="sidebar-brand" data-sidebar-toggle role="button" tabindex="0" aria-label="Toggle navigation" aria-expanded="false">
    <div class="mark"><img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img"></div>
    <div class="platform-brand-copy"><div class="name">Pocket<span>Finds</span></div><span class="tag">Platform portal</span></div>
  </div>
  <div class="nav-groups platform-main-navigation">
    @foreach(\App\Support\PlatformWorkspace::sections() as $section)
      <a href="{{ route($section['items'][0][1], [], false) }}" class="nav-item {{ \App\Support\PlatformWorkspace::active($section) ? 'active' : '' }}" title="{{ $section['label'] }}" @if(\App\Support\PlatformWorkspace::active($section)) aria-current="page" @endif>
        <span class="ic">@if($section['icon']==='dashboard')
          @include('seller.partials.icon', ['name'=>'nav-dashboard', 'size'=>22])
        @else
          <x-admin-icon :name="$section['icon']" />
        @endif</span><span class="platform-nav-label">{{ $section['label'] }}</span>
      </a>
    @endforeach
  </div>
  <div class="sidebar-foot">
    <div class="sidebar-user"><x-user-avatar :user="auth()->user()" size="36" class="avatar" /><div class="who"><strong>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</strong><span>Platform admin</span></div></div>
    <button type="button" class="logout-btn" data-logout title="Sign out" aria-label="Sign out"><x-admin-icon name="logout" /><span>Sign out</span></button>
  </div>
</nav>
