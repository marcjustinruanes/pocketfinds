<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Account Type</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
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
                <h1 class="auth-brand-title">Choose the way you use the platform.</h1>
                <p class="auth-brand-text">
                    Select the account that matches what you want to do. You can register in just a few steps.
                </p>
            </div>
            <div class="auth-brand-footer">Simple. Clear. Built for every user.</div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <button class="auth-back" type="button" onclick="history.back()"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg> Back</button>

                <div class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> Create account</div>
                <h2 class="auth-title">Choose account type</h2>
                <p class="auth-subtitle">Select one option to continue to the registration form.</p>

                <div class="auth-type-grid">
                    <button class="auth-type-card" type="button"
                            data-account-type="buyer" data-target="{{ url('/register/method') }}">
                        <span class="auth-type-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg></span>
                        <span class="auth-type-copy">
                            <span class="auth-type-name">Buyer</span>
                            <span class="auth-type-description">Browse products and place orders.</span>
                        </span>
                        <span class="auth-type-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>

                    <button class="auth-type-card" type="button"
                            data-account-type="rider" data-target="{{ url('/register/method') }}">
                        <span class="auth-type-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h-5l-3 6h11l-3-6z"/><path d="M12 6V3M15 6l2-3"/></svg></span>
                        <span class="auth-type-copy">
                            <span class="auth-type-name">Rider</span>
                            <span class="auth-type-description">Handle deliveries and manage rider tasks.</span>
                        </span>
                        <span class="auth-type-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>

                    <button class="auth-type-card" type="button"
                            data-account-type="seller" data-target="{{ url('/register/method') }}">
                        <span class="auth-type-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg></span>
                        <span class="auth-type-copy">
                            <span class="auth-type-name">Seller</span>
                            <span class="auth-type-description">List products and manage your store.</span>
                        </span>
                        <span class="auth-type-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>
                </div>

                <p class="auth-bottom">
                    Already have an account?
                    <a class="auth-link" href="{{ url('/login') }}">Sign in</a>
                </p>
            </div>
        </section>
    </main>
</div>
<script src="{{ asset('js/auth.js') }}"></script>
</body>
</html>
