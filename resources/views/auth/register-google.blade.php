<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Registration</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/register.css') }}">
    <style>
        .google-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid var(--auth-border);
            border-radius: var(--auth-radius-md);
            background: #f8fafc;
            margin-bottom: 16px;
        }
        .google-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--auth-primary-soft);
            display: grid;
            place-items: center;
            font-size: 16px;
            font-weight: 800;
            color: var(--auth-primary);
            flex: 0 0 38px;
            overflow: hidden;
        }
        .google-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .google-info { min-width: 0; }
        .google-name {
            font-size: 13px;
            font-weight: 700;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .google-email {
            font-size: 11px;
            color: var(--auth-muted);
            display: block;
        }
        .google-badge {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 800;
            color: #4285F4;
            white-space: nowrap;
        }
        .google-badge svg { flex: 0 0 12px; }
        .prefilled-note {
            font-size: 11px;
            color: var(--auth-muted);
            margin: 0 0 14px;
            padding: 8px 12px;
            background: var(--auth-primary-soft);
            border-radius: 8px;
            border-left: 3px solid var(--auth-primary);
        }
    </style>
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
                <h1 class="auth-brand-title">Almost there.</h1>
                <p class="auth-brand-text">
                    Your Google account has been connected. Just fill in a few more details to complete your
                    {{ ucfirst(request('type', 'buyer')) }} registration.
                </p>
                <ul class="auth-brand-points">
                    <li><span class="auth-check">✓</span> Google account connected</li>
                    <li><span class="auth-check">✓</span> Email pre-filled from Google</li>
                    <li><span class="auth-check">✓</span> Pending admin approval after submit</li>
                </ul>
            </div>
            <div class="auth-brand-footer">© {{ date('Y') }} PocketFinds. All rights reserved.</div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">

                <button class="auth-back" type="button" onclick="history.back()">← Back</button>

                {{-- Step indicator --}}
                <div class="steps" id="stepIndicator">
                    <div class="step-item active" data-step="1">
                        <div class="step-circle">1</div>
                        <span class="step-label">Personal</span>
                    </div>
                    <div class="step-item" data-step="2">
                        <div class="step-circle">2</div>
                        <span class="step-label">Contact</span>
                    </div>
                    <div class="step-item" data-step="3">
                        <div class="step-circle">3</div>
                        <span class="step-label">Address</span>
                    </div>
                    <div class="step-item" data-step="4">
                        <div class="step-circle">4</div>
                        <span class="step-label">ID Upload</span>
                    </div>
                </div>

                <form id="googleRegForm" method="POST" action="{{ route('register') }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="hidden" name="type" value="{{ request('type', 'buyer') }}">
                    <input type="hidden" name="auth_method" value="google">

                    {{-- ── STEP 1: Personal ── --}}
                    <div class="step-panel active" id="panel-1">
                        <div class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> Step 1 of 4</div>
                        <h2 class="auth-title">Personal Information</h2>
                        <p class="auth-subtitle">Tell us about yourself.</p>

                        {{-- Google profile preview --}}
                        <div class="google-profile">
                            <div class="google-avatar" id="gAvatar">G</div>
                            <div class="google-info">
                                <span class="google-name" id="gName">Google User</span>
                                <span class="google-email" id="gEmail">Connected via Google</span>
                            </div>
                            <div class="google-badge">
                                <svg viewBox="0 0 48 48" width="12" height="12"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.08 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-3.59-13.46-8.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                                Google
                            </div>
                        </div>

                        <p class="prefilled-note">✓ Your name and email are pre-filled from your Google account.</p>

                        <div class="auth-form-grid">
                            <div class="auth-field">
                                <label class="auth-label" for="last_name">Last name <span class="auth-required">*</span></label>
                                <input class="auth-input" id="last_name" name="last_name" type="text" placeholder="Dela Cruz" required>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="first_name">First name <span class="auth-required">*</span></label>
                                <input class="auth-input" id="first_name" name="first_name" type="text" placeholder="Juan" required>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="middle_initial">Middle initial</label>
                                <input class="auth-input" id="middle_initial" name="middle_initial" type="text" placeholder="M" maxlength="1" oninput="this.value=this.value.replace(/[^A-Za-z]/g,'').toUpperCase()">
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="sex">Sex <span class="auth-required">*</span></label>
                                <select class="auth-input auth-select" id="sex" name="sex" required>
                                    <option value="" disabled selected>Select sex</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="birthday">Birthday <span class="auth-required">*</span></label>
                                <input class="auth-input" id="birthday" name="birthday" type="date" required>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="age">Age</label>
                                <input class="auth-input input-readonly" id="age" name="age" type="text" placeholder="Auto-generated" readonly>
                            </div>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn-next" onclick="nextStep(1)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 2: Contact ── --}}
                    <div class="step-panel" id="panel-2">
                        <div class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> Step 2 of 4</div>
                        <h2 class="auth-title">Contact Details</h2>
                        <p class="auth-subtitle">How can we reach you?</p>

                        <div class="auth-form-grid">
                            <div class="auth-field full">
                                <label class="auth-label" for="email">Email address <span class="auth-required">*</span></label>
                                <input class="auth-input input-readonly" id="email" name="email" type="email" readonly required>
                                <span class="field-hint">Pre-filled from your Google account.</span>
                            </div>
                            <div class="auth-field full">
                                <label class="auth-label" for="contact_no">Contact number <span class="auth-required">*</span></label>
                                <input class="auth-input" id="contact_no" name="contact_no" type="tel" placeholder="09XXXXXXXXX" maxlength="11" required>
                                <span class="field-hint">11 digits, must start with 09</span>
                            </div>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn-prev" onclick="prevStep(2)">← Back</button>
                            <button type="button" class="btn-next" onclick="nextStep(2)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 3: Address ── --}}
                    <div class="step-panel" id="panel-3">
                        <div class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> Step 3 of 4</div>
                        <h2 class="auth-title">Address</h2>
                        <p class="auth-subtitle">Where are you located?</p>

                        <div class="address-grid">
                            <div class="auth-field full">
                                <label class="auth-label" for="province">Province <span class="auth-required">*</span></label>
                                <select class="auth-input auth-select" id="province" name="province" required>
                                    <option value="" disabled selected>Loading provinces…</option>
                                </select>
                            </div>
                            <div class="auth-field full">
                                <label class="auth-label" for="municipality">City / Municipality <span class="auth-required">*</span></label>
                                <select class="auth-input auth-select" id="municipality" name="municipality" required disabled>
                                    <option value="" disabled selected>Select province first</option>
                                </select>
                            </div>
                            <div class="auth-field full">
                                <label class="auth-label" for="barangay">Barangay <span class="auth-required">*</span></label>
                                <select class="auth-input auth-select" id="barangay" name="barangay" required disabled>
                                    <option value="" disabled selected>Select city / municipality first</option>
                                </select>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="house_no">House No. / Unit</label>
                                <input class="auth-input" id="house_no" name="house_no" type="text" placeholder="e.g. 123">
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="street">Street</label>
                                <input class="auth-input" id="street" name="street" type="text" placeholder="e.g. Rizal St.">
                            </div>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn-prev" onclick="prevStep(3)">← Back</button>
                            <button type="button" class="btn-next" onclick="nextStep(3)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 4: ID Upload only (no password needed) ── --}}
                    <div class="step-panel" id="panel-4">
                        <div class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> Step 4 of 4</div>
                        <h2 class="auth-title">ID Verification</h2>
                        <p class="auth-subtitle">Upload a valid government-issued ID to verify your identity.</p>

                        <p class="prefilled-note">✓ No password needed — you'll sign in with Google.</p>

                        <div class="auth-field">
                            <label class="file-upload-label" for="id_file" id="uploadLabel">
                                <span class="upload-icon">🪪</span>
                                <span class="upload-text">Click to upload a photo of your valid ID<br><small>JPG, PNG or PDF · max 5MB</small></span>
                                <span class="upload-name" id="uploadName"></span>
                            </label>
                            <input type="file" id="id_file" name="id_file" accept="image/*,.pdf" required>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn-prev" onclick="prevStep(4)">← Back</button>
                            <button type="submit" class="btn-submit">Submit Registration</button>
                        </div>
                    </div>
                </form>

                {{-- Success screen --}}
                <div class="success-screen" id="successScreen">
                    <div class="success-icon">✉️</div>
                    <h3>Registration Submitted!</h3>
                    <p>
                        Thank you for registering with Google. Please wait for the administrator's approval — a confirmation will be sent to your email.
                    </p>
                    <a class="success-btn" href="{{ url('/') }}">Back to Homepage</a>
                </div>

                <p class="auth-bottom" id="signinLink">
                    Already have an account?
                    <a class="auth-link" href="{{ url('/login') }}">Sign in</a>
                </p>
            </div>
        </section>
    </main>
</div>

<script src="{{ asset('js/auth.js') }}"></script>
<script src="{{ asset('js/register.js') }}"></script>
<script>
    // Simulate Google pre-fill (replace with real OAuth data when Socialite is set up)
    // These values will come from session after Google OAuth redirect
    const googleData = {
        name: '{{ session("google_name", "Google User") }}',
        email: '{{ session("google_email", "") }}',
        avatar: '{{ session("google_avatar", "") }}',
    };

    if (googleData.email) {
        document.getElementById('email').value = googleData.email;
    }
    if (googleData.name) {
        const parts = googleData.name.trim().split(' ');
        document.getElementById('first_name').value = parts[0] ?? '';
        document.getElementById('last_name').value = parts.slice(1).join(' ') ?? '';
        document.getElementById('gName').textContent = googleData.name;
    }
    if (googleData.email) {
        document.getElementById('gEmail').textContent = googleData.email;
    }
    if (googleData.avatar) {
        document.getElementById('gAvatar').innerHTML = `<img src="${googleData.avatar}" alt="avatar">`;
    }

    // Override form submit to target googleRegForm
    document.getElementById('googleRegForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!validateStep(4)) return;
        this.style.display = 'none';
        document.getElementById('stepIndicator').style.display = 'none';
        document.querySelector('.auth-back').style.display = 'none';
        document.getElementById('signinLink').style.display = 'none';
        document.getElementById('successScreen').classList.add('active');
    });
</script>
</body>
</html>
