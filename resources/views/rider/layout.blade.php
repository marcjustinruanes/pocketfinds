<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Courier') — PocketFinds</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/css/admin.css">
@stack('head')
</head>
<body class="admin">
<div class="shell" id="riderShell">
  @include('rider.partials.sidebar')
  <div style="display:flex;flex-direction:column;min-width:0">
    @include('rider.partials.topbar')
    <main class="content">
      @if(session('success'))
      <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>
      @endif
      @if($errors->has('status'))
      <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ $errors->first('status') }}</div>
      @endif
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
      <form method="POST" action="{{ route('rider.logout') }}">
        @csrf
        <button class="btn btn-danger" type="submit">Sign out</button>
      </form>
    </div>
  </div>
</div>

<div class="toast-stack" id="toastStack"></div>
<script src="/js/admin.js"></script>
<script>
  const rShell = document.getElementById('riderShell');
  const RKEY = 'rider_sidebar_collapsed';
  if (localStorage.getItem(RKEY) === '1') rShell?.classList.add('collapsed');
  document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
    if (window.innerWidth > 860) {
      const c = rShell?.classList.toggle('collapsed');
      localStorage.setItem(RKEY, c ? '1' : '0');
    }
  });
</script>
@stack('scripts')
</body>
</html>
