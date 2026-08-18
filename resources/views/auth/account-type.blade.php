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
                    <span class="auth-logo-mark">◆</span>
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
                <button class="auth-back" type="button" onclick="history.back()">← Back</button>

                <div class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> Create account</div>
                <h2 class="auth-title">Choose account type</h2>
                <p class="auth-subtitle">Select one option to continue to the registration form.</p>

                <div class="auth-type-grid">
                    <button class="auth-type-card" type="button"
                            data-account-type="buyer" data-target="{{ url('/register/method') }}">
                        <span class="auth-type-icon">B</span>
                        <span class="auth-type-copy">
                            <span class="auth-type-name">Buyer</span>
                            <span class="auth-type-description">Browse products and place orders.</span>
                        </span>
                        <span class="auth-type-arrow">→</span>
                    </button>

                    <button class="auth-type-card" type="button"
                            data-account-type="rider" data-target="{{ url('/register/method') }}">
                        <span class="auth-type-icon">R</span>
                        <span class="auth-type-copy">
                            <span class="auth-type-name">Rider</span>
                            <span class="auth-type-description">Handle deliveries and manage rider tasks.</span>
                        </span>
                        <span class="auth-type-arrow">→</span>
                    </button>

                    <button class="auth-type-card" type="button"
                            data-account-type="seller" data-target="{{ url('/register/method') }}">
                        <span class="auth-type-icon">S</span>
                        <span class="auth-type-copy">
                            <span class="auth-type-name">Seller</span>
                            <span class="auth-type-description">List products and manage your store.</span>
                        </span>
                        <span class="auth-type-arrow">→</span>
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
