<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
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

            <div class="auth-brand-footer">© <?php echo e(date('Y')); ?> PocketFinds. All rights reserved.</div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <a href="<?php echo e(url('/')); ?>" class="back-home-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                    Back to homepage
                </a>

                <div class="page-header">
                    <div class="page-title-row">
                        <h2>Welcome back</h2>
                    </div>
                    <p>Enter your credentials to continue.</p>
                </div>

                <?php if(!empty($errors) && $errors->any()): ?>
                    <?php if(session('accountStatus')): ?>
                        <div class="auth-status-banner">
                            <span class="auth-status-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            </span>
                            <div class="auth-status-body">
                                <p class="auth-status-title"><?php echo e(session('accountStatus') === 'suspended' ? 'Account suspended' : 'Application rejected'); ?></p>
                                <p class="auth-status-text"><?php echo e($errors->first()); ?></p>
                                <a href="mailto:pocketfindssupport@gmail.com" class="auth-status-action">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                                    Contact Support
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="auth-error">
                            <?php echo e($errors->first()); ?>

                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form class="auth-form" method="POST" action="<?php echo e(route('login.post')); ?>">
                    <?php echo csrf_field(); ?>

                    <div class="auth-field">
                        <label class="auth-label" for="email">Email or username</label>
                        <input class="auth-input" id="email" name="email"
                               type="text" value="<?php echo e(old('email')); ?>"
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

                        <a class="auth-link" href="<?php echo e(route('password.request')); ?>">Forgot password?</a>
                    </div>

                    <button class="auth-btn" type="submit">Sign in</button>
                </form>

                <div style="display:flex;align-items:center;gap:10px;margin:16px 0">
                    <div style="flex:1;height:1px;background:#e5e7eb"></div>
                    <span style="font-size:11px;color:var(--auth-muted)">or</span>
                    <div style="flex:1;height:1px;background:#e5e7eb"></div>
                </div>

                <a href="<?php echo e(route('google.login')); ?>" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;font-size:13px;font-weight:600;color:#374151;text-decoration:none;transition:border-color .15s" onmouseover="this.style.borderColor='var(--auth-primary)'" onmouseout="this.style.borderColor='#e5e7eb'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
                    Continue with Google
                </a>

                <p class="auth-bottom">
                    Don't have an account?
                    <a class="auth-link" href="<?php echo e(url('/register/type')); ?>">Create one</a>
                </p>
            </div>
        </section>
    </main>
</div>
<script src="<?php echo e(asset('js/auth.js')); ?>"></script>
</body>
</html>
<?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/auth/login.blade.php ENDPATH**/ ?>