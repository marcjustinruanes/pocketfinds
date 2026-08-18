<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Logistics') — PocketFinds</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/css/admin.css">
@stack('head')
</head>
<body class="admin">
<div class="shell" id="logisticsShell">
  @include('logistics.partials.sidebar')
  <div style="display:flex;flex-direction:column;min-width:0">
    @include('logistics.partials.topbar')
    <main class="content">
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

<div class="toast-stack" id="toastStack"></div>
<script src="/js/admin.js"></script>
<script>
  const lShell = document.getElementById('logisticsShell');
  const LKEY = 'logistics_sidebar_collapsed';
  if (localStorage.getItem(LKEY) === '1') lShell?.classList.add('collapsed');
  document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
    if (window.innerWidth > 860) {
      const c = lShell?.classList.toggle('collapsed');
      localStorage.setItem(LKEY, c ? '1' : '0');
    }
  });
</script>
@stack('scripts')
</body>
</html>
