<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up Method — PocketFinds</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
    <style>
        .method-grid {
            display: grid;
            gap: 10px;
            margin-top: 16px;
        }
        .method-card {
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
            text-decoration: none;
            color: var(--auth-text);
            transition: border-color .18s, background .18s, box-shadow .18s, transform .18s;
        }
        .method-card:hover {
            border-color: var(--auth-primary);
            background: var(--auth-primary-soft);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(217,70,143,.10);
        }
        .method-icon {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            display: grid;
            place-items: center;
            border-radius: 12px;
        }
        .method-icon.google { background: #f1f3f4; }
        .method-icon.manual { background: var(--auth-primary-soft); color: var(--auth-primary); transition: background .18s; }
        .method-card:hover .method-icon.manual { background: rgba(217,70,143,.18); }
        .method-copy { flex: 1; min-width: 0; }
        .method-name { display: block; font-size: 13px; font-weight: 800; margin-bottom: 2px; }
        .method-desc { display: block; font-size: 11.5px; color: var(--auth-muted); line-height: 1.4; }
        .method-arrow { color: #cbd5e1; display: flex; align-items: center; transition: color .18s, transform .18s; }
        .method-card:hover .method-arrow { color: var(--auth-primary); transform: translateX(2px); }
        .method-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 600;
        }
        .method-divider::before, .method-divider::after { content: ''; flex: 1; height: 1px; background: var(--auth-border); }
        /* Role badge */
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
        /* Compact header */
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
            <div class="auth-brand-footer">© <?php echo e(date('Y')); ?> PocketFinds. All rights reserved.</div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <button class="auth-back" type="button" onclick="history.back()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    Change account type
                </button>

                <?php
                    $type = request('type', 'buyer');
                    $icons = [
                        'buyer'  => '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 001.95-1.57l1.65-8.42H6"/></svg>',
                        'seller' => '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>',
                        'rider'  => '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h-5l-3 6h11l-3-6z"/></svg>',
                    ];
                ?>

                <div class="page-header">
                    <div class="page-title-row">
                        <h2>Sign up as</h2>
                        <span class="role-pill">
                            <?php echo $icons[$type] ?? $icons['buyer']; ?>

                            <?php echo e(ucfirst($type)); ?>

                        </span>
                    </div>
                    <p>How would you like to create your account?</p>
                </div>

                <div class="method-grid">
                    
                    <a class="method-card" href="<?php echo e(route('google.redirect')); ?>?type=<?php echo e($type); ?>">
                        <span class="method-icon google">
                            <svg width="22" height="22" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.08 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-3.59-13.46-8.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                        </span>
                        <span class="method-copy">
                            <span class="method-name">Continue with Google</span>
                            <span class="method-desc">Use your Google account — fast and no password needed.</span>
                        </span>
                        <span class="method-arrow">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </span>
                    </a>

                    <div class="method-divider">or</div>

                    
                    <a class="method-card" href="<?php echo e(url('/register')); ?>?type=<?php echo e($type); ?>">
                        <span class="method-icon manual">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </span>
                        <span class="method-copy">
                            <span class="method-name">Sign up manually</span>
                            <span class="method-desc">Fill in your details, verify your email, and upload a valid ID.</span>
                        </span>
                        <span class="method-arrow">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </span>
                    </a>
                </div>

                <p class="auth-bottom">
                    Already have an account?
                    <a class="auth-link" href="<?php echo e(url('/login')); ?>">Sign in</a>
                </p>
            </div>
        </section>
    </main>
</div>
</body>
</html>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\auth\signup-method.blade.php ENDPATH**/ ?>