<link rel="stylesheet" href="{{ asset('css/auth-modal.css') }}?v={{ filemtime(public_path('css/auth-modal.css')) }}">
<div id="authModal" class="am-overlay" aria-hidden="true">
  <div class="am-card" role="dialog" aria-modal="true" aria-labelledby="amDialogTitle" aria-describedby="amDialogDescription" tabindex="-1">
    <div class="am-form-panel">
      <div class="am-brand-header">
        <a class="am-logo" href="{{ url('/') }}">
          <span class="am-logo-mark"><img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img"></span>
          <span><span class="am-logo-pocket">Pocket</span><span class="am-logo-finds">Finds</span></span>
        </a>
      </div>
      <button id="authModalClose" class="am-close" type="button" aria-label="Close sign-in dialog" title="Close"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>

      {{-- Sign In pane --}}
      <div class="am-pane" id="amSignin">
        <div class="am-page-header">
          <div class="am-title-row"><h3 id="amDialogTitle">Sign in to continue</h3></div>
          <p id="amDialogDescription">Your next find is waiting.</p>
        </div>
        <form class="am-form" method="POST" action="{{ route('login.post') }}">
          @csrf
          <div class="am-field"><label class="am-label" for="am_email">Email or username</label><input class="am-input" id="am_email" name="email" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="Email or username" required></div>
          <div class="am-field"><label class="am-label" for="am_password">Password</label><div class="am-input-wrap"><input class="am-input has-toggle" id="am_password" name="password" type="password" autocomplete="current-password" placeholder="Your password" required><button class="am-pw-toggle" type="button" data-password-toggle="am_password" aria-label="Show password" aria-pressed="false" title="Show password"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="am-eye-slash" d="m3 3 18 18"/></svg></button></div></div>
          <div class="am-row"><label class="am-check-label"><input type="checkbox" name="remember"> Remember me</label><a class="am-link" href="{{ route('password.request') }}">Forgot password?</a></div>
          <button class="am-btn" type="submit">Sign in</button>
        </form>
        <p class="am-bottom">Don't have an account? <button class="am-link am-switch" type="button" data-tab="register">Create one</button></p>
        <button class="am-dismiss" type="button" data-auth-dismiss>Continue browsing</button>
      </div>

      {{-- Register pane --}}
      <div class="am-pane" id="amRegister" style="display:none">
        <div class="am-page-header">
          <div class="am-title-row">
            <h3 id="amRegisterTitle">Create your account</h3>
          </div>
          <p id="amRegisterDescription">Join PocketFinds and start shopping.</p>
        </div>
        <div class="am-method-grid">
          <a class="am-method-card" href="{{ route('google.redirect') }}?type=buyer">
            <span class="am-method-icon am-google"><svg width="22" height="22" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.08 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-3.59-13.46-8.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg></span>
            <span class="am-method-copy"><span class="am-method-name">Continue with Google</span></span>
            <span class="am-method-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
          </a>
          <div class="am-divider">or</div>
          <a class="am-method-card" href="{{ route('register.buyer') }}">
            <span class="am-method-icon am-manual"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span>
            <span class="am-method-copy"><span class="am-method-name">Continue with email</span></span>
            <span class="am-method-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
          </a>
        </div>
        <p class="am-bottom">Already have an account? <button class="am-link am-switch" type="button" data-tab="signin">Sign in</button></p>
      </div>

    </div>
  </div>
</div>
