<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Logistics') — PocketFinds</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/css/admin.css">
<style>
.logistics-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:16px 0}.logistics-form-grid label,.logistics-toolbar label{display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--muted)}.logistics-form-grid input,.logistics-form-grid select,.logistics-form-grid textarea,.logistics-toolbar input,.logistics-toolbar select{width:100%;background:var(--surface);color:var(--text);border:1px solid var(--border);padding:10px;border-radius:8px}.logistics-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-bottom:16px}.logistics-toolbar>input,.logistics-toolbar>select{width:auto;max-width:100%}.logistics-note{font-size:12px;color:var(--muted);line-height:1.6}.logistics-pagination{margin:16px 0}.logistics-pagination svg{max-width:20px}.logistics-kpis{grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}.logistics-alert{padding:12px 16px;border:1px solid var(--border);border-radius:8px;margin-bottom:16px;background:var(--surface)}@media(max-width:560px){.logistics-form-grid{grid-template-columns:1fr}.logistics-toolbar>*{width:100%!important}}
</style>
<link rel="stylesheet" href="{{ asset('css/navigation.css') }}?v={{ filemtime(public_path('css/navigation.css')) }}">
@stack('head')
</head>
<body class="admin">
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

<div class="toast-stack" id="toastStack"></div>
<script src="/js/admin.js"></script>
@stack('scripts')
<script src="{{ asset('js/navigation.js') }}?v={{ filemtime(public_path('js/navigation.js')) }}"></script>
</body>
</html>
