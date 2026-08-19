<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Registration</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/register.css') }}">
</head>
<body class="auth-page">
<div class="auth-shell">
    <main class="auth-card">

        {{-- Brand panel --}}
        <section class="auth-brand-panel">
            <div class="auth-brand-content">
                <a class="auth-logo" href="{{ url('/') }}">
                    <span class="auth-logo-mark"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span>
                    <span>PocketFinds</span>
                </a>
                <h1 class="auth-brand-title">Create your buyer account.</h1>
                <p class="auth-brand-text">
                    Fill in your details across a few quick steps. Your account will be reviewed and activated by our admin team.
                </p>
                <ul class="auth-brand-points">
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Secure &amp; verified registration</li>
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Admin-approved accounts</li>
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Confirmation sent to your email</li>
                </ul>
            </div>
            <div class="auth-brand-footer">© {{ date('Y') }} PocketFinds. All rights reserved.</div>
        </section>

        {{-- Form panel --}}
        <section class="auth-form-panel">
            <div class="auth-form-wrap">

                <button class="auth-back" type="button" onclick="history.back()"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg> Change account type</button>

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
                        <span class="step-label">Account</span>
                    </div>
                </div>

                <form id="buyerForm" method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="hidden" name="account_type" value="{{ request('type', 'buyer') }}">
                    <input type="hidden" name="auth_method" value="manual">

                    {{-- ── STEP 1: Personal Info ── --}}
                    <div class="step-panel active" id="panel-1">
                        <div class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> Step 1 of 3</div>
                        <h2 class="auth-title">Personal Information</h2>
                        <p class="auth-subtitle">Tell us about yourself.</p>

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
                                <input class="auth-input" id="email" name="email" type="email" placeholder="juan@gmail.com" required>
                                <span class="field-hint">Must be a Gmail address (@gmail.com)</span>
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

                    {{-- ── STEP 4: Account + ID Upload ── --}}
                    <div class="step-panel" id="panel-4">
                        <div class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> Step 4 of 4</div>
                        <h2 class="auth-title">Account & Verification</h2>
                        <p class="auth-subtitle">Set your password and upload a valid ID.</p>

                        <p class="form-section-title">Password</p>
                        <div class="auth-form-grid">
                            <div class="auth-field full">
                                <label class="auth-label" for="password">Password <span class="auth-required">*</span></label>
                                <div class="auth-input-wrap">
                                    <input class="auth-input has-toggle" id="password" name="password" type="password" placeholder="Create a password" required>
                                    <button class="auth-password-toggle" type="button" data-password-toggle="password">Show</button>
                                </div>
                            </div>
                            <div class="auth-field full">
                                <label class="auth-label" for="password_confirmation">Confirm password <span class="auth-required">*</span></label>
                                <input class="auth-input" id="password_confirmation" name="password_confirmation" type="password" placeholder="Repeat your password" required>
                            </div>
                        </div>

                        <p class="form-section-title">Valid ID</p>
                        <div class="auth-field">
                            <label class="file-upload-label" for="id_file" id="uploadLabel">
                                <span class="upload-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 4V2M16 4V2M3 10h18"/><circle cx="12" cy="15" r="2"/></svg></span>
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
                    <div class="success-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
                    <h3>Registration Submitted!</h3>
                    <p>
                        Thank you for registering. Please wait for the administrator's approval — a confirmation will be sent to your email.
                    </p>
                    <a class="success-btn" href="{{ url('/') }}">Back to Homepage</a>
                </div>

                <p class="auth-bottom" id="signinLink">
                    Already registered?
                    <a class="auth-link" href="{{ url('/login') }}">Sign in</a>
                </p>

            </div>
        </section>
    </main>
</div>

<script src="{{ asset('js/auth.js') }}"></script>
<script src="{{ asset('js/register.js') }}"></script>
</body>
</html>
