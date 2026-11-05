<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>{{ $resetting ? 'Reset password' : 'Forgot password' }} — PocketFinds</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Allura&family=DM+Serif+Display&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth-refresh.css') }}?v={{ filemtime(public_path('css/auth-refresh.css')) }}">
</head>
<body class="auth-page auth-login auth-password-recovery">
<div class="auth-shell">
    <main class="auth-card">
        @include('auth.partials.brand-panel', ['signInBrand' => true])
        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <a class="login-home-link" href="{{ route('login') }}">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    Back to sign in
                </a>
                <div class="recovery-heading">
                    <h1 class="auth-title">{{ $resetting ? 'Reset password' : 'Forgot password?' }}</h1>
                    <p class="auth-subtitle">{{ $resetting ? 'Use at least 8 characters with uppercase and lowercase letters, a number, and a symbol.' : 'Enter your account email and we’ll send you a link to reset your password.' }}</p>
                </div>
                @if(session('status'))
                    <div class="auth-recovery-status" role="status">{{ session('status') }}</div>
                @endif
                @if($errors->any())
                    <div id="loginErrorBanner" role="alert" aria-atomic="true"><div class="auth-error">
                        <svg class="login-error-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 4h.01"/></svg>
                        <p class="login-error-message">{{ $errors->first() }}</p>
                    </div></div>
                @endif
                <form class="auth-form" method="POST" action="{{ route($resetting ? 'password.update' : 'password.email') }}">
                    @csrf
                    @if($resetting)<input type="hidden" name="token" value="{{ $token }}">@endif
                    <div class="auth-field auth-floating-field">
                        <input class="auth-input" id="email" type="email" name="email" value="{{ old('email', $email ?? '') }}" placeholder=" " autocomplete="email" required maxlength="254">
                        <label class="auth-label" for="email">Email address</label>
                        <fieldset class="auth-field-outline" aria-hidden="true"><legend><span>Email address</span></legend></fieldset>
                    </div>
                    @if($resetting)
                        @foreach(['password' => 'New password', 'password_confirmation' => 'Confirm new password'] as $field => $label)
                            <div class="auth-field">
                                <div class="auth-input-wrap auth-floating-field">
                                    <input class="auth-input has-toggle" id="{{ $field }}" type="password" name="{{ $field }}" placeholder=" " autocomplete="new-password" required minlength="8" maxlength="128">
                                    <label class="auth-label" for="{{ $field }}">{{ $label }}</label>
                                    <fieldset class="auth-field-outline" aria-hidden="true"><legend><span>{{ $label }}</span></legend></fieldset>
                                    <button class="auth-password-toggle auth-password-icon" type="button" data-password-toggle="{{ $field }}" data-password-icon aria-label="Show password" aria-controls="{{ $field }}" aria-pressed="false">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="password-eye-slash" d="m3 3 18 18"/></svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @endif
                    <button class="auth-btn" type="submit">{{ $resetting ? 'Reset password' : 'Send reset link' }}</button>
                </form>
                @if($resetting)
                    <p class="auth-bottom"><a class="auth-link" href="{{ route('password.request') }}">Request a new reset link</a></p>
                @endif
            </div>
        </section>
    </main>
</div>
<script src="{{ asset('js/auth.js') }}?v={{ filemtime(public_path('js/auth.js')) }}"></script>
</body>
</html>
