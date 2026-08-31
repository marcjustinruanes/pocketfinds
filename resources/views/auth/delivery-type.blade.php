<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join the Delivery Team — PocketFinds</title>
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
        .customer-link {
            display: block;
            margin-top: 14px;
            font-size: 11.5px;
            color: var(--auth-muted);
            text-align: center;
        }
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
                <h1 class="auth-brand-title">Join our delivery team.</h1>
                <p class="auth-brand-text">
                    Work with PocketFinds behind the scenes — pick up and deliver orders, or coordinate the sorting center. Each role has its own registration flow.
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
                <a href="{{ route('register.type') }}" class="back-home-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                    Back
                </a>

                <div class="page-header">
                    <div class="page-title-row">
                        <h2>Delivery team</h2>
                    </div>
                    <p>Select the role that matches what you want to do.</p>
                </div>

                <div class="role-grid">
                    {{-- Rider --}}
                    <button class="role-card" type="button" data-account-type="rider" data-target="{{ url('/register/method') }}">
                        <span class="role-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h-5l-3 6h11l-3-6z"/><path d="M12 6V3M15 6l2-3"/></svg>
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Rider / Courier</span>
                            <span class="role-desc">Pick up and deliver orders, manage your routes and earnings.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>

                    {{-- Logistics --}}
                    <button class="role-card" type="button" data-account-type="logistics" data-target="{{ url('/register/method') }}">
                        <span class="role-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><path d="M9 14l2 2 4-4"/></svg>
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Logistics Staff</span>
                            <span class="role-desc">Coordinate pickups, dispatch couriers, and monitor deliveries.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>
                </div>

                <p class="auth-bottom">
                    Already have an account?
                    <a class="auth-link" href="{{ url('/login') }}">Sign in</a>
                </p>
                <a href="{{ route('register.type') }}" class="customer-link">Looking to buy or sell instead? Go to customer sign-up →</a>
            </div>
        </section>
    </main>
</div>
<script src="{{ asset('js/auth.js') }}"></script>
</body>
</html>
