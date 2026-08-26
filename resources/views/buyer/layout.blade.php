<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Buyer') — PocketFinds</title>
<link rel="stylesheet" href="/css/buyer.css">
@stack('head')
</head>
<body class="buyer">
<div class="shell" id="appShell">
  @include('buyer.partials.sidebar')
  <div style="display:flex;flex-direction:column;min-width:0">
    @include('buyer.partials.topbar')
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
      <form method="POST" action="{{ route('buyer.logout') }}">
        @csrf
        <button class="btn btn-danger" type="submit">Sign out</button>
      </form>
    </div>
  </div>
</div>

{{-- Cart / variant modal --}}
<div class="modal-overlay" id="cartOverlay">
  <div class="modal" style="max-width:440px">
    <div class="modal-head">
      <div style="display:flex;align-items:center;gap:10px">
        <div id="cmIconWrap" style="width:36px;height:36px;border-radius:9px;display:grid;place-items:center;flex:none">
          <svg id="cmHeaderIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></svg>
        </div>
        <div>
          <h3 id="cmTitle" style="margin:0;font-family:var(--font-display);font-size:17px;font-weight:600">Add to Cart</h3>
          <p id="cmSub" style="margin:2px 0 0;font-size:12px;color:var(--muted)"></p>
        </div>
      </div>
      <button class="modal-close" onclick="closeCart()">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" style="padding:20px 22px;display:flex;flex-direction:column;gap:16px">

      <div id="cmColorGroup" style="display:none">
        <div class="cm-label" style="display:flex;align-items:center;gap:6px">
          <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 0 20"/></svg>
          Color
        </div>
        <div class="cm-options" id="cmColors"></div>
      </div>

      <div id="cmSizeGroup" style="display:none">
        <div class="cm-label" style="display:flex;align-items:center;gap:6px">
          <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
          <span id="cmSizeLabel">Size</span>
        </div>
        <div class="cm-options" id="cmSizes"></div>
      </div>

      <div>
        <div class="cm-label" style="display:flex;align-items:center;gap:6px">
          <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Quantity
        </div>
        <div class="cm-qty">
          <button class="cm-qty-btn" onclick="cmChangeQty(-1)">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </button>
          <span class="cm-qty-val" id="cmQty">1</span>
          <button class="cm-qty-btn" onclick="cmChangeQty(1)">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </button>
        </div>
      </div>

      <div class="cm-price-row">
        <div style="display:flex;align-items:center;gap:7px">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          <span class="cm-price-label">Total</span>
        </div>
        <span class="cm-price-val" id="cmTotal"></span>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeCart()" style="display:flex;align-items:center;gap:7px">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Cancel
      </button>
      <button class="btn btn-primary" id="cmConfirmBtn" onclick="cmConfirm()" style="display:flex;align-items:center;gap:7px">
        <svg id="cmConfirmIcon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></svg>
        <span id="cmConfirmLabel">Add to Cart</span>
      </button>
    </div>
  </div>
</div>

{{-- Flying cart item --}}
<div id="flyItem" style="position:fixed;z-index:999;pointer-events:none;opacity:0;width:28px;height:28px;border-radius:50%;background:var(--pink);display:grid;place-items:center;color:#fff;transition:none">
  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
</div>

<div class="toast-stack" id="toastStack"></div>
<script src="/js/buyer.js"></script>
</body>
</html>
