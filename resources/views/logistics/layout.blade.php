<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Logistics') — PocketFinds</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/css/admin.css">
<link rel="stylesheet" href="{{ asset('css/notifications.css') }}?v={{ filemtime(public_path('css/notifications.css')) }}">
<link rel="stylesheet" href="{{ asset('css/navigation.css') }}?v={{ filemtime(public_path('css/navigation.css')) }}">
@stack('head')
<link rel="stylesheet" href="{{ asset('css/logistics-ui.css') }}?v={{ filemtime(public_path('css/logistics-ui.css')) }}">
@include('account.partials.notification-assets')
<link rel="stylesheet" href="{{ asset('css/logistics-hub.css') }}?v={{ filemtime(public_path('css/logistics-hub.css')) }}">
<link rel="stylesheet" href="{{ asset('css/logistics-modern.css') }}?v={{ filemtime(public_path('css/logistics-modern.css')) }}">
</head>
<body class="admin logistics-ui {{ auth()->user()->isHubStaff() ? 'hub-staff-ui' : '' }}">
<div class="shell" id="logisticsShell" data-navigation-shell data-collapse-key="logistics_sidebar_collapsed">
  @include('logistics.partials.sidebar')
  <div style="display:flex;flex-direction:column;min-width:0">
    @include('logistics.partials.topbar')
    <main class="content">
      @if(session('success'))<div class="logistics-alert" role="status">{{ session('success') }}</div>@endif
      @if($errors->any())<div class="logistics-alert" role="alert"><strong>Please check the form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
      @yield('content')
    </main>
  </div>
</div>

<div class="modal-overlay" id="logoutOverlay">
  <div class="modal" style="max-width:380px">
    <div class="modal-head">
      <div><h3>Sign out?</h3><p>You will be returned to the login screen.</p></div>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" data-modal-close>Cancel</button>
      <form method="POST" action="{{ route('logistics.logout') }}">
        @csrf
        <button class="btn btn-danger" type="submit">Sign out</button>
      </form>
    </div>
  </div>
</div>

<div class="modal-overlay" id="confirmActionModal">
  <section class="modal logistics-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmActionTitle" aria-describedby="confirmActionMessage">
    <header class="modal-head">
      <div class="modal-head-main">
        <span class="modal-icon"><x-admin-icon name="shield" /></span>
        <div class="modal-head-copy"><h3 id="confirmActionTitle">Confirm this action?</h3><p id="confirmActionMessage">Please review this change before continuing.</p></div>
      </div>
      <button type="button" class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
    </header>
    <footer class="modal-foot">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-primary" data-confirm-submit>Continue</button>
    </footer>
  </section>
</div>

<div class="toast-stack" id="toastStack"></div>
<script src="/js/admin.js"></script>
<script src="{{ asset('js/logistics-personnel-address.js') }}?v={{ filemtime(public_path('js/logistics-personnel-address.js')) }}"></script>
@stack('scripts')
<script src="{{ asset('js/navigation.js') }}?v={{ filemtime(public_path('js/navigation.js')) }}"></script>
</body>
<script src="{{ asset('js/address-postal-lookup.js') }}?v={{ filemtime(public_path('js/address-postal-lookup.js')) }}" defer></script>
</html>
