@php($pendingEmailChange = \Illuminate\Support\Facades\Cache::get('buyer-email-change:'.auth()->id()))
<div class="card" id="change-email">
  <div class="card-head"><div><h2>Email verification</h2><p>Verify a new Gmail address to update your login email.</p></div></div>
  <div class="card-pad" id="accountEmailVerification">
    <form id="accountEmailCodeForm" method="POST" action="{{ route('buyer.commerce.email.code') }}" autocomplete="off" data-verified-email="{{ session('account_google_email','') }}">@csrf
      <div class="account-email-row"><div class="form-row"><div class="account-floating-field"><input class="auth-input" id="newBuyerEmail" name="email" type="email" value="{{ session('account_google_email', $pendingEmailChange['email'] ?? '') }}" placeholder=" " required autocomplete="off"><label for="newBuyerEmail">Email address</label><fieldset aria-hidden="true"><legend><span>Email address</span></legend></fieldset></div></div><button class="btn btn-outline" id="accountSendEmailCode" type="submit">Send Code</button></div>
    </form>
    <div class="account-google-email" id="accountGoogleEmail"><span class="account-email-or">or</span><a href="{{ route('buyer.account.google.email') }}" class="account-google-button"><svg width="19" height="19" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.9 14.9 0 0 1 0-9.18l-7.98-6.19a24 24 0 0 0 0 21.56z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6C30 37.67 27.48 38.5 24 38.5c-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>Continue with Google</a><small>Verify your email with Google instead of a code.</small></div>
    <form id="accountEmailVerifyForm" method="POST" action="{{ route('buyer.commerce.email.verify') }}" @if(!$pendingEmailChange) hidden @endif>@csrf
      <input type="hidden" name="email" id="accountPendingEmail" value="{{ $pendingEmailChange['email'] ?? '' }}">
      <div class="account-email-row"><div class="form-row"><label for="buyerEmailOtp">Verification code</label><input type="text" id="buyerEmailOtp" name="otp" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" placeholder=" " required autocomplete="one-time-code"></div><button class="btn btn-primary" type="submit">Verify</button></div>
      <small class="account-email-hint">Enter the six-digit code sent to your email. It expires in five minutes.</small>
    </form>
    <p id="accountEmailFeedback" role="status" hidden></p>
    <p id="accountEmailVerified" class="account-email-verified" role="status" hidden>✓ Email verified</p>
  </div>
</div>
