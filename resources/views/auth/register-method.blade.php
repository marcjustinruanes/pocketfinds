<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — PocketFinds</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <style>
        .role-grid { display: grid; gap: 10px; margin-top: 16px; }
        .role-card {
            display: flex; align-items: center; gap: 16px; width: 100%;
            padding: 14px 16px; border: 1.5px solid var(--auth-border); border-radius: 14px;
            background: #fff; text-align: left; cursor: pointer; text-decoration: none;
            transition: border-color .18s, background .18s, box-shadow .18s, transform .18s;
        }
        .role-card:hover { border-color: var(--auth-primary); background: var(--auth-primary-soft); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(217,70,143,.10); }
        .role-icon { width: 44px; height: 44px; flex: 0 0 44px; display: grid; place-items: center; border-radius: 12px; background: var(--auth-primary-soft); color: var(--auth-primary); transition: background .18s; }
        .role-icon.role-icon-google { background: #fff; border: 1px solid var(--auth-border); }
        .role-card:hover .role-icon:not(.role-icon-google) { background: rgba(217,70,143,.18); }
        .role-copy { flex: 1; min-width: 0; }
        .role-name { display: block; font-size: 13px; font-weight: 800; color: var(--auth-text); margin-bottom: 2px; }
        .role-desc { display: block; font-size: 11.5px; color: var(--auth-muted); line-height: 1.4; }
        .role-arrow { color: #cbd5e1; display: flex; align-items: center; transition: color .18s, transform .18s; }
        .role-card:hover .role-arrow { color: var(--auth-primary); transform: translateX(2px); }
        .auth-form-panel { align-items: center; }
        .page-header { margin-bottom: 20px; }
        .page-header p { margin: 4px 0 0; font-size: 13px; color: var(--auth-muted); }
        .page-title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 0; }
        .page-title-row h2 { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -.03em; line-height: 1.2; }
        .back-home-link { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 18px; color: var(--auth-muted); font-size: 12.5px; font-weight: 600; text-decoration: none; transition: color .15s ease; }
        .back-home-link:hover { color: var(--auth-primary); }
    </style>
</head>
<body class="auth-page">
<div class="auth-shell">
    <main class="auth-card">
        <section class="auth-brand-panel">
            <div class="auth-brand-content">
                <a class="auth-logo" href="{{ url('/') }}">
                    <span class="auth-logo-mark"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span>
                    <span>PocketFinds</span>
                </a>
                <h1 class="auth-brand-title">Almost there.</h1>
                <p class="auth-brand-text">
                    Choose how you'd like to sign up as a {{ $type }}. You can always link Google to your account later.
                </p>
                <ul class="auth-brand-points">
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Quick, guided registration</li>
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Admin-reviewed accounts</li>
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Secure &amp; verified sign-up</li>
                </ul>
            </div>
            <div class="auth-brand-footer">© {{ date('Y') }} PocketFinds. All rights reserved.</div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <a href="{{ in_array($type, ['rider', 'logistics'], true) ? route('register.delivery-team') : route('register.type') }}" class="back-home-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                    Back
                </a>

                <div class="page-header">
                    <div class="page-title-row">
                        <h2>How would you like to sign up?</h2>
                    </div>
                    <p>Registering as a {{ $type }}. Choose one to continue.</p>
                </div>

                @if (!empty($errors) && $errors->any())
                    <div class="auth-error">{{ $errors->first() }}</div>
                @endif

                @php
                    // Every role has its own dedicated registration page.
                    $manualRoute = match ($type) {
                        'seller'     => route('register.seller'),
                        'rider'      => route('register.rider'),
                        'logistics'  => route('register.logistics'),
                        default      => route('register.buyer'),
                    };
                @endphp

                <div class="role-grid">
                    <a class="role-card" href="{{ route('google.redirect', ['type' => $type]) }}">
                        <span class="role-icon role-icon-google">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Continue with Google</span>
                            <span class="role-desc">Sign up using your Google account — fastest way to get started.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </a>

                    <a class="role-card" href="{{ $manualRoute }}">
                        <span class="role-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="M22 6l-10 7L2 6"/></svg>
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Sign up manually</span>
                            <span class="role-desc">Use your email and set your own password.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </a>
                </div>

                <p class="auth-bottom">
                    Already have an account?
                    <a class="auth-link" href="{{ url('/login') }}">Sign in</a>
                </p>
            </div>
        </section>
    </main>
</div>
</body>
</html>
