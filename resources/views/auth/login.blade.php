<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <style>
        .auth-form-panel { align-items: center; }
        .page-header { margin-bottom: 20px; }
        .page-header p { margin: 4px 0 0; font-size: 13px; color: var(--auth-muted); }
        .page-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin: 0;
        }
        .page-title-row h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -.03em;
            line-height: 1.2;
        }
        .role-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--auth-primary-soft);
            border: 1px solid rgba(217,70,143,.2);
            color: var(--auth-primary);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
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

                <h1 class="auth-brand-title">Everything you need, in one place.</h1>
                <p class="auth-brand-text">
                    Sign in to manage your account and access the services available to your user type.
                </p>

                <ul class="auth-brand-points">
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Secure account access</li>
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Buyer, Rider, and Seller accounts</li>
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Simple and responsive experience</li>
                </ul>
            </div>

            <div class="auth-brand-footer">© {{ date('Y') }} PocketFinds. All rights reserved.</div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <button class="auth-back" type="button" onclick="history.back()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    Back
                </button>

                <div class="page-header">
                    <div class="page-title-row">
                        <h2>Welcome back</h2>
                        <span class="role-pill" style="margin-left:auto">
                            Sign in
                        </span>
                    </div>
                    <p>Enter your credentials to continue.</p>
                </div>

                @if (!empty($errors) && $errors->any())
                    <div class="auth-error">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form class="auth-form" method="POST" action="{{ route('login.post') }}">
                    @csrf

                    <div class="auth-field">
                        <label class="auth-label" for="email">Email or username</label>
                        <input class="auth-input" id="email" name="email"
                               type="text" value="{{ old('email') }}"
                               placeholder="you@example.com" required autofocus>
                    </div>

                    <div class="auth-field">
                        <label class="auth-label" for="password">Password</label>
                        <div class="auth-input-wrap">
                            <input class="auth-input has-toggle" id="password" name="password"
                                   type="password" placeholder="Enter your password" required>
                            <button class="auth-password-toggle" type="button"
                                    data-password-toggle="password">Show</button>
                        </div>
                    </div>

                    <div class="auth-row">
                        <label class="auth-check-label">
                            <input type="checkbox" name="remember">
                            Remember me
                        </label>

                        <a class="auth-link" href="{{ route('password.request') }}">Forgot password?</a>
                    </div>

                    <button class="auth-btn" type="submit">Sign in</button>
                </form>

                <p class="auth-bottom">
                    Don't have an account?
                    <a class="auth-link" href="{{ url('/register/type') }}">Create one</a>
                </p>
            </div>
        </section>
    </main>
</div>
<script src="{{ asset('js/auth.js') }}"></script>
</body>
</html>
