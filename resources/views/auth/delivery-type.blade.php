<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
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
                <a href="{{ route('register.type') }}" class="back-home-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    Back
                </a>

                <div class="page-header">
                    <p style="text-align:center;font-style:italic">Select the role that matches what you want to do.</p>
                </div>

                <div class="role-grid">
                    {{-- Rider --}}
                    <button class="role-card" type="button" data-account-type="rider" data-target="{{ route('register.rider') }}">
                        <span class="role-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h-5l-3 6h11l-3-6z"/><path d="M12 6V3M15 6l2-3"/></svg>
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Rider / Courier</span>
                            <span class="role-desc">Pick up and deliver orders, manage your routes and earnings.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>

                    {{-- Logistics Company --}}
                    <button class="role-card" type="button" data-account-type="logistics" data-target="{{ route('register.logistics') }}">
                        <span class="role-icon">
                            @include('auth.partials.account-role-icon', ['role' => 'logistics'])
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Logistics Company</span>
                            <span class="role-desc">Register your logistics company to coordinate pickups, dispatch couriers, and monitor deliveries.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>

                    {{-- Hub Staff --}}
                    <button class="role-card" type="button" data-account-type="logistics-staff" data-target="{{ route('register.logistics-staff') }}">
                        <span class="role-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Hub Staff</span>
                            <span class="role-desc">Join an existing logistics company as sorting hub staff to process and dispatch parcels.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
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
