<!DOCTYPE html>
<html lang="en">
<head>
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
</head>
<body class="auth-page">
<div class="auth-shell">
    <main class="auth-card">
        <section class="auth-brand-panel">
            <div class="auth-brand-content">
                <a class="auth-logo" href="{{ url('/') }}">
                    <span class="auth-logo-mark"><img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img"></span>
                    <span><span class="auth-logo-pocket">Pocket</span><span class="auth-logo-finds">Finds</span></span>
                </a>
                <h1 class="auth-brand-title">Who are you on PocketFinds?</h1>
                <p class="auth-brand-text">
                    Pick the role that fits you. Each account type has its own features and registration flow.
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
                <a href="{{ url('/') }}" class="back-home-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                    Back to homepage
                </a>

                <div class="page-header">
                    <div class="page-title-row">
                        <h2>Get started</h2>
                    </div>
                    <p>Select the account type that matches what you want to do.</p>
                </div>

                <div class="role-grid">
                    {{-- Buyer --}}
                    <button class="role-card" type="button" data-account-type="buyer" data-target="{{ url('/register/method') }}">
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
                    <button class="role-card" type="button" data-account-type="seller" data-target="{{ url('/register/method') }}">
                        <span class="role-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Seller</span>
                            <span class="role-desc">List your products, manage your store, and grow your business.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>

                </div>

                <a href="{{ route('register.delivery-team') }}" class="delivery-team-link">
                    <span class="dt-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8Z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
                    <span class="dt-copy">
                        <span class="dt-name">Want to deliver or work logistics?</span>
                        <span class="dt-desc">Join our delivery team as a Rider or Logistics staff →</span>
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
