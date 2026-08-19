<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up Method</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <style>
        .method-grid { display:grid;gap:12px;margin-top:8px }
        .method-card { display:flex;align-items:center;gap:14px;width:100%;padding:16px 18px;border:1px solid var(--auth-border);border-radius:14px;background:#fff;text-align:left;cursor:pointer;text-decoration:none;color:var(--auth-text);transition:border-color .18s,background .18s,transform .18s,box-shadow .18s }
        .method-card:hover { border-color:#f0a4c7;background:#fff8fb;transform:translateY(-1px);box-shadow:var(--auth-shadow-sm) }
        .method-icon { width:42px;height:42px;flex:0 0 42px;display:grid;place-items:center;border-radius:12px }
        .method-icon.google { background:#f1f3f4 }
        .method-icon.manual { background:var(--auth-primary-soft);color:var(--auth-primary) }
        .method-copy { min-width:0 }
        .method-name { display:block;font-size:14px;font-weight:800 }
        .method-desc { display:block;margin-top:3px;font-size:12px;color:var(--auth-muted);line-height:1.4 }
        .method-arrow { margin-left:auto;color:#94a3b8;display:flex;align-items:center }
        .type-badge { display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:var(--auth-primary-soft);color:var(--auth-primary);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;margin-bottom:16px }
    </style>
</head>
<body class="auth-page">
<div class="auth-shell">
    <main class="auth-card">

        <section class="auth-brand-panel">
            <div class="auth-brand-content">
                <a class="auth-logo" href="{{ url('/') }}">
                    <span class="auth-logo-mark">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                    </span>
                    <span>PocketFinds</span>
                </a>
                <h1 class="auth-brand-title">How would you like to sign up?</h1>
                <p class="auth-brand-text">
                    Choose how you want to create your account. Either way, your account will need admin approval before you can start.
                </p>
                <ul class="auth-brand-points">
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Google sign-up is quick and easy</li>
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Manual sign-up gives full control</li>
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Both require admin approval</li>
                </ul>
            </div>
            <div class="auth-brand-footer">© {{ date('Y') }} PocketFinds. All rights reserved.</div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <button class="auth-back" type="button" onclick="history.back()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    Change account type
                </button>

                <div class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> Registration</div>
                <h2 class="auth-title">Create your account</h2>
                <p class="auth-subtitle">You selected:</p>

                <div class="type-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                    {{ ucfirst(request('type', 'buyer')) }}
                </div>

                <p class="auth-subtitle" style="margin-top:0;">How would you like to sign up?</p>

                <div class="method-grid">
                    {{-- Google --}}
                    <a class="method-card" href="{{ route('google.redirect') }}?type={{ request('type', 'buyer') }}">
                        <span class="method-icon google">
                            <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.08 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-3.59-13.46-8.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
                        </span>
                        <span class="method-copy">
                            <span class="method-name">Continue with Google</span>
                            <span class="method-desc">Choose your Google account to sign up. You'll still fill in your details and wait for approval.</span>
                        </span>
                        <span class="method-arrow">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </span>
                    </a>

                    {{-- Manual --}}
                    <a class="method-card" href="{{ url('/register') }}?type={{ request('type', 'buyer') }}">
                        <span class="method-icon manual">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </span>
                        <span class="method-copy">
                            <span class="method-name">Sign up manually</span>
                            <span class="method-desc">Fill in your personal details, contact info, address, and upload a valid ID.</span>
                        </span>
                        <span class="method-arrow">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </span>
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
