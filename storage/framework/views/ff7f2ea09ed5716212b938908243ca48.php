<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Account Type — PocketFinds</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
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
    </style>
</head>
<body class="auth-page">
<div class="auth-shell">
    <main class="auth-card">
        <section class="auth-brand-panel">
            <div class="auth-brand-content">
                <a class="auth-logo" href="<?php echo e(url('/')); ?>">
                    <span class="auth-logo-mark"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span>
                    <span>PocketFinds</span>
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
            <div class="auth-brand-footer">© <?php echo e(date('Y')); ?> PocketFinds. All rights reserved.</div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <div class="page-header">
                    <div class="page-title-row">
                        <h2>Get started</h2>
                    </div>
                    <p>Select the account type that matches what you want to do.</p>
                </div>


                <div class="role-grid">
                    
                    <button class="role-card" type="button" data-account-type="buyer" data-target="<?php echo e(url('/register/method')); ?>">
                        <span class="role-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 001.95-1.57l1.65-8.42H6"/></svg>
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Buyer</span>
                            <span class="role-desc">Browse products, place orders, and track deliveries.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>

                    
                    <button class="role-card" type="button" data-account-type="seller" data-target="<?php echo e(url('/register/method')); ?>">
                        <span class="role-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Seller</span>
                            <span class="role-desc">List your products, manage your store, and grow your business.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>

                    
                    <button class="role-card" type="button" data-account-type="rider" data-target="<?php echo e(url('/register/method')); ?>">
                        <span class="role-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h-5l-3 6h11l-3-6z"/><path d="M12 6V3M15 6l2-3"/></svg>
                        </span>
                        <span class="role-copy">
                            <span class="role-name">Rider</span>
                            <span class="role-desc">Pick up and deliver orders, manage your routes and earnings.</span>
                        </span>
                        <span class="role-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
                    </button>
                </div>

                <p class="auth-bottom">
                    Already have an account?
                    <a class="auth-link" href="<?php echo e(url('/login')); ?>">Sign in</a>
                </p>
            </div>
        </section>
    </main>
</div>
<script src="<?php echo e(asset('js/auth.js')); ?>"></script>
</body>
</html>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views/auth/account-type.blade.php ENDPATH**/ ?>