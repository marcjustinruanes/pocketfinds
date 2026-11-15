    <div class="card" data-account-panel="account-security">
      <div class="card-head" id="account-security"><h2>Change password</h2></div>
      <div class="card-pad">
        @if(session('password_success'))
        <div class="auth-success" style="margin-bottom:16px">{{ session('password_success') }}</div>
        @endif
        @error('current_password')
        <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ $message }}</div>
        @enderror
        @php
          $passwordPending = session('buyer_password_change_pending', []);
          $showPasswordForm = (string)($passwordPending['user_id'] ?? '') === (string)auth()->id() && ($passwordPending['expires_at'] ?? 0) > now()->timestamp;
          $passwordAuthorized = $showPasswordForm && is_string(session('buyer_password_change_token')) && \Illuminate\Support\Facades\Password::tokenExists(auth()->user(), session('buyer_password_change_token'));
        @endphp
        @if($passwordAuthorized)
        <p class="account-password-instructions" role="status">Your email link is verified. Update your username and password below.</p>
        <form method="POST" action="{{ route(($securityRole ?? 'buyer').'.account.password-change') }}" class="account-password-change-form">
          @csrf
          <div class="form-row"><label for="buyerUsername">Username</label><input id="buyerUsername" name="username" value="{{ old('username',auth()->user()->username) }}" autocomplete="off" maxlength="50" readonly data-unlock-on-focus required></div>
          <div class="account-password-fields"><div>
            <div class="form-row"><label for="buyerNewPassword">New password</label><input id="buyerNewPassword" type="password" name="password" placeholder=" " minlength="8" maxlength="128" autocomplete="new-password" required></div>
            <div class="form-row"><label for="buyerConfirmPassword">Confirm password</label><input id="buyerConfirmPassword" type="password" name="password_confirmation" placeholder=" " maxlength="128" autocomplete="new-password" required></div>
          </div><div id="accountPasswordRequirements"></div></div>
          @error('username')<p class="profile-field-error">{{ $message }}</p>@enderror
          @error('password')<p class="profile-field-error">{{ $message }}</p>@enderror
          <button class="btn btn-primary" type="submit" @disabled(!$passwordAuthorized)>Save login details</button>
        </form>
        @else
        @if($showPasswordForm)
        <section class="account-password-link account-password-start"><div><p role="status">A password-change link has been sent to {{ auth()->user()->email }}. Check your inbox and spam folder, then open the link to choose a new password. Please wait a minute before requesting another link.</p></div><form method="POST" action="{{ route(($securityRole ?? 'buyer').'.account.password-link') }}">@csrf<button class="btn btn-outline" type="submit">Resend link</button></form></section>
        @else
        <section class="account-password-link account-password-start"><div><p>We'll email a secure link to {{ auth()->user()->email }} so you can choose a new password.</p></div><form method="POST" action="{{ route(($securityRole ?? 'buyer').'.account.password-link') }}">@csrf<button class="btn btn-primary" type="submit">Email change-password link</button></form></section>
        @endif
        @endif
        @error('password_link')<p class="profile-field-error" role="alert">{{ $message }}</p>@enderror
      </div>
    </div>
