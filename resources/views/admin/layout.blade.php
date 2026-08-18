<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Admin') — Console</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/css/admin.css">
@stack('head')
</head>
<body class="admin">
<div class="shell" id="appShell">
  @include('admin.partials.sidebar')
  <div style="display:flex;flex-direction:column;min-width:0">
    @include('admin.partials.topbar')
    <main class="content">
      @yield('content')
    </main>
  </div>
</div>

{{-- Logout confirm modal --}}
<div class="modal-overlay" id="logoutOverlay">
  <div class="modal" style="max-width:380px">
    <div class="modal-head">
      <div><h3>Sign out?</h3><p>You will be returned to the login screen.</p></div>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" data-modal-close>Cancel</button>
      <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button class="btn btn-danger" type="submit">Sign out</button>
      </form>
    </div>
  </div>
</div>

<div class="toast-stack" id="toastStack"></div>
<script src="/js/admin.js"></script>
</body>
</html>
