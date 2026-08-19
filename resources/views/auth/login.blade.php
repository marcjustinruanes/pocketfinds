<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
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
                <div class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> Welcome back</div>
                <h2 class="auth-title">Sign in to your account</h2>
                <p class="auth-subtitle">Enter your credentials to continue.</p>

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
