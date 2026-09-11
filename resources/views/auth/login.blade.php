<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
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
                <a class="auth-logo" href="{{ url('/') }}">
                    <span class="auth-logo-mark">
                        <img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img">
                    </span>
                    <span><span class="auth-logo-pocket">Pocket</span><span class="auth-logo-finds">Finds</span></span>
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
                <a href="{{ url('/') }}" class="back-home-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                    Back to homepage
                </a>

                <div class="page-header">
                    <div class="page-title-row">
                        <h2>Welcome back</h2>
                    </div>
                    <p>Enter your credentials to continue.</p>
                </div>

                @if (!empty($errors) && $errors->any())
                    <div id="loginErrorBanner">
                    @if(session('accountStatus'))
                        <div class="auth-status-banner">
                            <span class="auth-status-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            </span>
                            <div class="auth-status-body">
                                <p class="auth-status-title">{{ session('accountStatus') === 'suspended' ? 'Account suspended' : 'Application rejected' }}</p>
                                <p class="auth-status-text">{{ $errors->first() }}</p>
                                <button type="button" class="auth-status-action" onclick="openSupportModal({{ Illuminate\Support\Js::from(session('accountStatus') === 'suspended' ? 'Account suspended' : 'Application rejected') }}, {{ Illuminate\Support\Js::from(old('email', '')) }})">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                                    Contact Support
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="auth-error">
                            {{ $errors->first() }}
                        </div>
                    @endif
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

                <div style="display:flex;align-items:center;gap:10px;margin:16px 0">
                    <div style="flex:1;height:1px;background:#e5e7eb"></div>
                    <span style="font-size:11px;color:var(--auth-muted)">or</span>
                    <div style="flex:1;height:1px;background:#e5e7eb"></div>
                </div>

                <a href="{{ route('google.login') }}" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;font-size:13px;font-weight:600;color:#374151;text-decoration:none;transition:border-color .15s" onmouseover="this.style.borderColor='var(--auth-primary)'" onmouseout="this.style.borderColor='#e5e7eb'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
                    Continue with Google
                </a>

                <p class="auth-bottom">
                    Don't have an account?
                    <a class="auth-link" href="{{ url('/register/type') }}">Create one</a>
                </p>
            </div>
        </section>
    </main>
</div>

{{-- Contact Support modal — sends the message through the server (see SupportController),
     so it works the same for every visitor regardless of what mail app their browser is
     set to hand mailto: links to. --}}
<div id="supportModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,15,25,.7);backdrop-filter:blur(4px);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:18px;width:min(440px,94vw);overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.25)">
        <div style="padding:20px 22px;border-bottom:1px solid #f1f5f9">
            <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--auth-primary)">Contact Support</span>
        </div>
        <div style="padding:20px 22px">
            <div id="supportSuccess" style="display:none;text-align:center;padding:12px 0">
                <p style="margin:0 0 4px;font-size:14px;font-weight:700;color:#16a34a">Message sent</p>
                <p style="margin:0;font-size:12.5px;color:var(--auth-muted)">Our support team will get back to you at the email you provided.</p>
            </div>
            <form id="supportForm" onsubmit="return submitSupportForm(event)">
                <div class="auth-field" style="margin-bottom:12px">
                    <label class="auth-label" for="supportEmail">Your email</label>
                    <input class="auth-input" id="supportEmail" name="email" type="email" placeholder="you@example.com" required>
                </div>
                <div class="auth-field" style="margin-bottom:6px">
                    <label class="auth-label" for="supportMessage">Message</label>
                    <textarea class="auth-input" id="supportMessage" name="message" rows="5" placeholder="Tell us what happened..." required style="resize:vertical;font-family:inherit"></textarea>
                </div>
                <span id="supportError" style="display:none;color:var(--auth-danger,#e74c3c);font-size:11.5px">Please fill in both fields.</span>
                <button class="auth-btn" type="submit" id="supportSubmitBtn" style="margin-top:10px">Send Message</button>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset('js/auth.js') }}"></script>
<script>
// Hide the "account suspended / rejected / invalid credentials" banner the moment the
// visitor starts fixing their input, instead of leaving it stuck on screen until they resubmit.
function hideLoginErrorBanner() {
    document.getElementById('loginErrorBanner')?.remove();
}
document.getElementById('email')?.addEventListener('input', hideLoginErrorBanner);
document.getElementById('password')?.addEventListener('input', hideLoginErrorBanner);

let supportContext = '';

function openSupportModal(context, email) {
    supportContext = context || '';
    document.getElementById('supportEmail').value = email || '';
    document.getElementById('supportMessage').value = context ? `My account status: ${context}. I'd like to appeal this decision.\n\n` : '';
    document.getElementById('supportForm').style.display = '';
    document.getElementById('supportSuccess').style.display = 'none';
    document.getElementById('supportError').style.display = 'none';
    document.getElementById('supportModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeSupportModal() {
    document.getElementById('supportModal').style.display = 'none';
    document.body.style.overflow = '';
}
document.getElementById('supportModal').addEventListener('click', function (e) {
    if (e.target === this) closeSupportModal();
});

function submitSupportForm(e) {
    e.preventDefault();
    const email = document.getElementById('supportEmail').value.trim();
    const message = document.getElementById('supportMessage').value.trim();
    const errEl = document.getElementById('supportError');
    if (!email || !message) { errEl.textContent = 'Please fill in both fields.'; errEl.style.display = 'block'; return false; }

    const btn = document.getElementById('supportSubmitBtn');
    btn.disabled = true; btn.textContent = 'Sending…';
    const csrfToken = document.querySelector('input[name="_token"]')?.value;

    fetch('{{ route('support.contact') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ email, message, context: supportContext }),
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false; btn.textContent = 'Send Message';
        if (data.success) {
            document.getElementById('supportForm').style.display = 'none';
            document.getElementById('supportSuccess').style.display = 'block';
        } else {
            errEl.textContent = data.message || 'Something went wrong. Please try again.';
            errEl.style.display = 'block';
        }
    })
    .catch(() => {
        btn.disabled = false; btn.textContent = 'Send Message';
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = 'block';
    });
    return false;
}
</script>
</body>
</html>
