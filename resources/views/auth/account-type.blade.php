<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Account Type — PocketFinds</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <style>
        .role-grid {
            display: grid;
            gap: 10px;
            margin-top: 16px;
        }
        .role-card {
            display: flex;
            align-items: center;
            gap: 16px;
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--auth-border);
            border-radius: 14px;
            background: #fff;
            text-align: left;
            cursor: pointer;
            transition: border-color .18s, background .18s, box-shadow .18s, transform .18s;
        }
        .role-card:hover {
            border-color: var(--auth-primary);
            background: var(--auth-primary-soft);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(217,70,143,.10);
        }
        .role-icon {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: var(--auth-primary-soft);
            color: var(--auth-primary);
            transition: background .18s;
        }
        .role-card:hover .role-icon { background: rgba(217,70,143,.18); }
        .role-copy { flex: 1; min-width: 0; }
        .role-name {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: var(--auth-text);
            margin-bottom: 2px;
        }
        .role-desc {
            display: block;
            font-size: 11.5px;
            color: var(--auth-muted);
            line-height: 1.4;
        }
        .role-arrow {
            color: #cbd5e1;
            display: flex;
            align-items: center;
            transition: color .18s, transform .18s;
        }
        .role-card:hover .role-arrow { color: var(--auth-primary); transform: translateX(2px); }
        /* Compact header for this page */
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
        .back-home-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 18px;
            color: var(--auth-muted);
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            transition: color .15s ease;
        }
        .back-home-link:hover { color: var(--auth-primary); }
        .delivery-team-link {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 14px;
            padding: 12px 14px;
            border: 1.5px dashed var(--auth-border);
            border-radius: 14px;
            text-decoration: none;
            transition: border-color .18s, background .18s;
        }
        .delivery-team-link:hover {
            border-color: var(--auth-primary);
            border-style: solid;
            background: var(--auth-primary-soft);
        }
        .dt-icon {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: var(--auth-primary-soft);
            color: var(--auth-primary);
        }
        .dt-copy { flex: 1; min-width: 0; }
        .dt-name { display: block; font-size: 12.5px; font-weight: 800; color: var(--auth-text); }
        .dt-desc { display: block; font-size: 11px; color: var(--auth-muted); margin-top: 1px; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/auth-refresh.css') }}?v={{ filemtime(public_path('css/auth-refresh.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/auth-registration.css') }}?v={{ filemtime(public_path('css/auth-registration.css')) }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Allura&family=DM+Serif+Display&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/auth-entry-layout.css') }}?v={{ filemtime(public_path('css/auth-entry-layout.css')) }}">
</head>
<body class="auth-page auth-register auth-entry">
<div class="auth-shell">
    <main class="auth-card">
        @include('auth.partials.brand-panel', ['signInBrand' => true])

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <a href="{{ url('/') }}" class="login-home-link">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    Back to homepage
                </a>

                <div class="page-header">
                    <p style="text-align:center;font-style:italic">Select the account type that matches what you want to do.</p>
                </div>

                <div class="role-grid">
                    {{-- Buyer --}}
                    <button class="role-card" type="button" data-account-type="buyer" data-target="{{ route('register.buyer') }}">
                        <span class="role-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 001.95-1.57l1.65-8.42H6"/></svg>
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Buyer</span>
                            <span class="role-desc">Browse products, place orders, and track deliveries.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>

                    {{-- Seller --}}
                    <button class="role-card" type="button" data-account-type="seller" data-target="{{ route('register.seller') }}">
                        <span class="role-icon">
                            @include('auth.partials.account-role-icon', ['role' => 'seller'])
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Seller</span>
                            <span class="role-desc">List your products, manage your store, and grow your business.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>

                </div>

                <a href="{{ route('register.delivery-team') }}" class="delivery-team-link">
                    <span class="dt-icon">@include('auth.partials.account-role-icon', ['role' => 'logistics', 'size' => 22])</span>
                    <span class="dt-copy">
                        <span class="dt-name">Want to join logistics or become a rider?</span>
                        <span class="dt-desc">Register your logistics company, apply as hub staff, or join a logistics company as a rider.</span>
                    </span>
                </a>

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
