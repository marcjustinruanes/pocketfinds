<div id="authModal" class="am-overlay">
  <div class="am-card">
    <div class="am-brand">
      <div class="am-brand-content">
        <a class="am-logo" href="<?php echo e(url('/')); ?>">
          <span class="am-logo-mark"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span>
          <span>PocketFinds</span>
        </a>
        <h2 class="am-brand-title">Everything you need, in one place.</h2>
        <p class="am-brand-text">Sign in to save items, checkout, and track your orders.</p>
        <ul class="am-brand-points">
          <li><span class="am-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Secure account access</li>
          <li><span class="am-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Buyer, Rider, and Seller accounts</li>
          <li><span class="am-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Simple and responsive experience</li>
        </ul>
      </div>
      <div class="am-brand-footer">© <?php echo e(date('Y')); ?> PocketFinds. All rights reserved.</div>
    </div>
    <div class="am-form-panel">
      <button id="authModalClose" class="am-close" type="button"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>

      
      <div class="am-pane" id="amSignin">
        <div class="am-page-header">
          <div class="am-title-row"><h3>Welcome back</h3><span class="am-role-pill" style="margin-left:auto">Sign in</span></div>
          <p>Enter your credentials to continue.</p>
        </div>
        <form class="am-form" method="POST" action="<?php echo e(route('login.post')); ?>">
          <?php echo csrf_field(); ?>
          <div class="am-field"><label class="am-label" for="am_email">Email or username</label><input class="am-input" id="am_email" name="email" type="text" placeholder="you@example.com" required></div>
          <div class="am-field"><label class="am-label" for="am_password">Password</label><div class="am-input-wrap"><input class="am-input has-toggle" id="am_password" name="password" type="password" placeholder="Enter your password" required><button class="am-pw-toggle" type="button" data-password-toggle="am_password">Show</button></div></div>
          <div class="am-row"><label class="am-check-label"><input type="checkbox" name="remember"> Remember me</label><a class="am-link" href="<?php echo e(route('password.request')); ?>">Forgot password?</a></div>
          <button class="am-btn" type="submit">Sign in</button>
        </form>
        <p class="am-bottom">Don't have an account? <button class="am-link am-switch" type="button" data-tab="register">Create one</button></p>
      </div>

      
      <div class="am-pane" id="amRegister" style="display:none">
        <div class="am-page-header">
          <div class="am-title-row">
            <h3>Sign up as</h3>
            <span class="am-role-pill"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 001.95-1.57l1.65-8.42H6"/></svg> Buyer</span>
          </div>
          <p>How would you like to create your account?</p>
        </div>
        <div class="am-method-grid">
          <a class="am-method-card" href="<?php echo e(route('google.redirect')); ?>?type=buyer">
            <span class="am-method-icon am-google"><svg width="22" height="22" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.08 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-3.59-13.46-8.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg></span>
            <span class="am-method-copy"><span class="am-method-name">Continue with Google</span><span class="am-method-desc">Fast and no password needed.</span></span>
            <span class="am-method-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
          </a>
          <div class="am-divider">or</div>
          <a class="am-method-card" href="<?php echo e(url('/register')); ?>?type=buyer">
            <span class="am-method-icon am-manual"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span>
            <span class="am-method-copy"><span class="am-method-name">Sign up manually</span><span class="am-method-desc">Fill in your details and verify your email.</span></span>
            <span class="am-method-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
          </a>
        </div>
        <p class="am-bottom">Already have an account? <button class="am-link am-switch" type="button" data-tab="signin">Sign in</button></p>
      </div>

    </div>
  </div>
</div>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views/guest/auth-modal.blade.php ENDPATH**/ ?>