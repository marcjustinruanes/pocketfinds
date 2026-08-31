const PSGC = 'https://psgc.gitlab.io/api';
const isGoogleForm = !!document.getElementById('googleRegForm');
const accountTypeVal = document.querySelector('input[name="account_type"]')?.value;
const isSeller = accountTypeVal === 'seller';
const isRider  = accountTypeVal === 'rider';

if (isSeller) {
    const businessNameField = document.getElementById('businessNameField');
    const businessPermitField = document.getElementById('businessPermitField');
    const accountGrid = document.querySelector(`#panel-${isGoogleForm ? 5 : 6} .auth-form-grid`);
    const usernameField = document.getElementById('username')?.closest('.auth-field');
    const passwordField = document.getElementById('password')?.closest('.auth-field');
    const confirmationField = document.getElementById('password_confirmation')?.closest('.auth-field');
    if (businessNameField && businessPermitField && accountGrid) {
        businessNameField.classList.add('full');
        businessNameField.style.margin = '0 0 2px';
        businessPermitField.style.margin = '0';
        accountGrid.style.rowGap = '6px';
        accountGrid.prepend(businessNameField);
        businessNameField.insertAdjacentElement('afterend', businessPermitField);
        const credentials = document.createElement('div');
        credentials.style.cssText = 'display:flex;flex-direction:column;gap:10px;min-width:0';
        [usernameField, passwordField, confirmationField].forEach(field => {
            field?.classList.remove('full');
            if (field) credentials.appendChild(field);
        });
        businessPermitField.insertAdjacentElement('afterend', credentials);
    }
}

// For Google buyers, activate panel-1 (ID step) since it has no active class by default
if (isGoogleForm && !isSeller) {
    document.getElementById('panel-1')?.classList.add('active');
}

// Show rider-only step indicators
if (isRider) {
    document.querySelectorAll('.rider-only').forEach(el => el.style.display = '');
    // Riders: account step continues to vehicle instead of submitting
    document.getElementById('btnStep6RiderNext')?.style.setProperty('display', '');
    document.getElementById('btnStep6Submit')?.style.setProperty('display', 'none');
} else {
    document.getElementById('stepIndicator')?.classList.add('account-is-last');
}

// ── Seller setup ──
if (isSeller) {
    const sellerOnlyEl = document.querySelector('.seller-only');
    if (sellerOnlyEl) sellerOnlyEl.style.display = '';
    document.getElementById('personalStepCircle').textContent = '2';
    document.getElementById('contactStepCircle').textContent = '3';
    document.getElementById('addressStepCircle').textContent = '4';
    document.getElementById('accountStepCircle').textContent = '5';

    // Google form: start on panel-0 (category), deactivate panel-1 (ID)
    // Manual form: start on panel-1 (category), deactivate panel-2 (ID)
    const categoryPanelId = isGoogleForm ? 0 : 1;
    const idPanelId       = isGoogleForm ? 1 : 2;
    const categoryStep    = isGoogleForm ? 0 : 1;
    const idStep          = isGoogleForm ? 1 : 2;

    document.getElementById(`panel-${idPanelId}`).classList.remove('active');
    document.getElementById(`panel-${categoryPanelId}`).classList.add('active');
    document.querySelector(`[data-step="${idStep}"]`)?.classList.remove('active');
    document.querySelector(`[data-step="${categoryStep}"]`)?.classList.add('active');

    // Show back button on ID step for sellers
    document.querySelectorAll(`#panel-${idPanelId} .btn-prev.seller-only`).forEach(btn => btn.style.display = '');

    const categoryIcons = {
        pet:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="4" r="2"/><circle cx="18" cy="8" r="2"/><circle cx="20" cy="16" r="2"/><path d="M9 10a5 5 0 015 5v3.5a3.5 3.5 0 01-6.84 1.045Q6.52 17.48 4.46 16.84A3.5 3.5 0 018 10z"/></svg>`,
        drink:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8l1 7H7L8 2z"/><path d="M7 9c0 5 2 8 5 8s5-3 5-8"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/></svg>`,
        automotive:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>`,
        music:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>`,
        art:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32"/></svg>`,
        craft:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32"/></svg>`,
        electronics:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>`,
        women:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="2"/><path d="M9 9h6l3 11H6l3-11z"/><line x1="12" y1="20" x2="12" y2="23"/></svg>`,
        men:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="2"/><path d="M9 9h6l1 5h-2l-1 6h-2l-1-6H8l1-5z"/></svg>`,
        kid:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4.5" r="2.5"/><path d="M9 10c0 0-3 .5-3 3.5h3v6h6v-6h3c0-3-3-3.5-3-3.5"/><path d="M9 10h6"/><circle cx="7" cy="14" r="1"/><circle cx="17" cy="14" r="1"/></svg>`,
        fashion:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.57a2 2 0 00-1.34-2.23z"/></svg>`,
        clothing:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.57a2 2 0 00-1.34-2.23z"/></svg>`,
        food:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>`,
        beverage:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8l1 7H7L8 2z"/><path d="M7 9c0 5 2 8 5 8s5-3 5-8"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/></svg>`,
        health:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>`,
        beauty:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>`,
        home:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>`,
        garden:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22V12"/><path d="M5 12C5 7 8 4 12 4c4 0 7 3 7 8"/><path d="M5 12c0-3 2-5 7-5"/></svg>`,
        living:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>`,
        sports:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l4.24 4.24M14.83 9.17l4.24-4.24M14.83 14.83l4.24 4.24M9.17 14.83l-4.24 4.24"/><circle cx="12" cy="12" r="4"/></svg>`,
        outdoors:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l4-8 4 5 3-3 4 6H3z"/><circle cx="18" cy="5" r="2"/></svg>`,
        toys:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 10c-.83 0-1.5-.67-1.5-1.5v-5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5z"/><path d="M20.5 10H19V8.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/><path d="M9.5 14.5c.83 0 1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5S8 21.83 8 21v-5c0-.83.67-1.5 1.5-1.5z"/><path d="M3.5 14H5v1.5c0 .83-.67 1.5-1.5 1.5S2 16.33 2 15.5 2.67 14 3.5 14z"/><rect x="2" y="10" width="20" height="4" rx="2"/></svg>`,
        games:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4M8 10v4M15 11h.01M17 13h.01"/></svg>`,
        books:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>`,
        stationery:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><path d="M5 12l7-7 7 7"/></svg>`,
        default:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="7" height="7"/><rect x="15" y="3" width="7" height="7"/><rect x="15" y="14" width="7" height="7"/><rect x="2" y="14" width="7" height="7"/></svg>`,
    };

    function getCategoryIcon(name) {
        const lower = name.toLowerCase();
        for (const [key, svg] of Object.entries(categoryIcons)) {
            if (key !== 'default' && lower.includes(key)) return svg;
        }
        return categoryIcons.default;
    }

    // Sellers pick exactly one category — the account only ever stores a
    // single category_id, so the picker is single-select (an "Other" tile
    // with custom text counts as that one pick).
    let selectedCategoryId = null;

    function categorySelectionCount() {
        const otherFilled = (document.getElementById('category_other_input')?.value.trim().length ?? 0) > 0;
        return (selectedCategoryId !== null ? 1 : 0) + (otherFilled ? 1 : 0);
    }

    function syncCategorySelection() {
        const container = document.getElementById('categoryIdsContainer');
        container.innerHTML = '';
        if (selectedCategoryId !== null) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'category_ids[]';
            input.value = selectedCategoryId;
            container.appendChild(input);
        }
        const nextBtn = document.getElementById('categoryNextBtn');
        if (nextBtn) nextBtn.disabled = categorySelectionCount() !== 1;
        document.getElementById('categoryError')?.remove();
    }

    document.getElementById('categoryNextBtn').disabled = true;

    fetch('/register/categories')
        .then(r => r.json())
        .then(data => {
            const grid = document.getElementById('categoryGrid');
            grid.innerHTML = '';
            const categoryBoxes = [];
            let otherBtn;

            function selectCategory(id, btn) {
                selectedCategoryId = id;
                categoryBoxes.forEach(b => b.classList.toggle('selected', b === btn));
                // Picking a real category clears any "Other" text — only one selection counts.
                otherBtn.classList.remove('selected');
                document.getElementById('categoryOtherWrap').style.display = 'none';
                document.getElementById('category_other_input').value = '';
                syncCategorySelection();
            }

            data.forEach(cat => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'category-box';
                btn.dataset.categoryId = cat.id;
                btn.innerHTML = `<span class="category-box-icon">${getCategoryIcon(cat.name)}</span><span class="category-box-name">${cat.name}</span>`;
                btn.addEventListener('click', () => {
                    if (btn.classList.contains('selected')) {
                        // Clicking the already-selected tile deselects it.
                        selectedCategoryId = null;
                        btn.classList.remove('selected');
                        syncCategorySelection();
                        return;
                    }
                    selectCategory(String(cat.id), btn);
                });
                categoryBoxes.push(btn);
                grid.appendChild(btn);
            });

            // "Other" tile — lets the seller type a category that isn't listed.
            otherBtn = document.createElement('button');
            otherBtn.type = 'button';
            otherBtn.className = 'category-box';
            otherBtn.id = 'categoryOtherBox';
            otherBtn.innerHTML = `<span class="category-box-icon">${categoryIcons.default}</span><span class="category-box-name">Other</span>`;
            otherBtn.addEventListener('click', () => {
                const show = !otherBtn.classList.contains('selected');
                otherBtn.classList.toggle('selected', show);
                // Picking "Other" clears any selected category tile — only one selection counts.
                if (show) {
                    selectedCategoryId = null;
                    categoryBoxes.forEach(b => b.classList.remove('selected'));
                }
                const wrap = document.getElementById('categoryOtherWrap');
                const input = document.getElementById('category_other_input');
                wrap.style.display = show ? '' : 'none';
                if (show) input.focus(); else input.value = '';
                syncCategorySelection();
            });
            grid.appendChild(otherBtn);
        })
        .catch(() => {
            document.getElementById('categoryGrid').innerHTML = '<p style="color:var(--auth-danger);font-size:13px">Failed to load categories. Please refresh.</p>';
        });

    document.getElementById('category_other_input')?.addEventListener('input', syncCategorySelection);
}

// ── Email OTP (manual form only) ──
// Google sign-ups arrive with an already-verified email (prefilled and
// locked in the blade view) — skip the send/verify-code requirement.
let emailVerified = typeof IS_GOOGLE_SIGNUP !== 'undefined' && IS_GOOGLE_SIGNUP;
let otpCountdown  = null;

function sendOtp() {
    const emailEl = document.getElementById('email');
    const email   = emailEl.value.trim();
    if (!email || !/@gmail\.com$/i.test(email)) { showError(emailEl, 'Enter a valid Gmail address first.'); return; }
    clearError(emailEl);
    const btn = document.getElementById('sendOtpBtn');
    btn.disabled = true; btn.textContent = 'Sending…';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]').value;
    fetch('/register/send-otp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ email }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.textContent = 'Sent ✓';
            document.getElementById('emailHint').textContent = 'Code sent! Check your inbox (and spam folder).';
            document.getElementById('otpField').style.display = '';
            document.getElementById('otp_code').value = '';
            document.getElementById('otpHint').style.color = '';
            document.getElementById('otpHint').innerHTML = 'Enter the code sent to your email. <button type="button" class="btn-inline-link" id="resendOtpBtn" onclick="resendOtp()" disabled>Resend</button>';
            startOtpCountdown();
        } else {
            btn.disabled = false; btn.textContent = 'Send Code';
            const msg = data.message ?? 'Could not send code.';
            const hint = document.getElementById('emailHint');
            if (hint) { hint.style.color = 'var(--auth-danger)'; hint.textContent = msg; }
            showError(emailEl, msg);
            // show just a retry link below the email field — no code input yet
            document.getElementById('otpField').style.display = 'none';
            let retryRow = document.getElementById('otpRetryRow');
            if (!retryRow) {
                retryRow = document.createElement('div');
                retryRow.id = 'otpRetryRow';
                retryRow.style.cssText = 'margin-top:6px;font-size:12px';
                document.getElementById('otpField').after(retryRow);
            }
            retryRow.innerHTML = `<span style="color:var(--auth-danger)">${msg}</span> &nbsp;<button type="button" class="btn-inline-link" onclick="resendOtp()">Try again</button>`;
            retryRow.style.display = 'block';
        }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Send Code'; showError(emailEl, 'Network error. Try again.'); });
}

function resendOtp() {
    const retryRow = document.getElementById('otpRetryRow');
    if (retryRow) retryRow.style.display = 'none';
    document.getElementById('otpField').style.display = 'none';
    const btn = document.getElementById('sendOtpBtn');
    btn.disabled = false; btn.textContent = 'Send Code';
    sendOtp();
}

function startOtpCountdown() {
    let secs = 60;
    clearInterval(otpCountdown);
    otpCountdown = setInterval(() => {
        secs--;
        const resendBtn = document.getElementById('resendOtpBtn');
        if (!resendBtn) { clearInterval(otpCountdown); return; }
        if (secs <= 0) { clearInterval(otpCountdown); resendBtn.disabled = false; resendBtn.textContent = 'Resend'; }
        else { resendBtn.disabled = true; resendBtn.textContent = `Resend (${secs}s)`; }
    }, 1000);
}

function verifyOtp() {
    const email = document.getElementById('email').value.trim();
    const otp   = document.getElementById('otp_code').value.trim();
    const hintEl = document.getElementById('otpHint');
    const verifyBtn = document.getElementById('verifyOtpBtn');
    if (otp.length !== 6) { hintEl.style.color = 'var(--auth-danger)'; hintEl.textContent = 'Please enter the full 6-digit code.'; return; }
    verifyBtn.disabled = true; verifyBtn.textContent = 'Verifying…';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]').value;
    fetch('/register/verify-otp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ email, otp }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            emailVerified = true;
            clearInterval(otpCountdown);
            document.getElementById('otpField').style.display      = 'none';
            document.getElementById('verifiedBadge').style.display = '';
            document.getElementById('sendOtpBtn').style.display    = 'none';
            document.getElementById('emailHint').textContent       = '';
        } else {
            verifyBtn.disabled = false; verifyBtn.textContent = 'Verify';
            hintEl.style.color = 'var(--auth-danger)'; hintEl.textContent = data.message ?? 'Invalid or expired code.';
        }
    })
    .catch(() => { verifyBtn.disabled = false; verifyBtn.textContent = 'Verify'; hintEl.style.color = 'var(--auth-danger)'; hintEl.textContent = 'Network error. Try again.'; });
}

let lastEmailValue = '';
// OTP input: digits only, max 6
document.getElementById('otp_code')?.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
});

document.getElementById('email')?.addEventListener('input', function () {
    if (this.value === lastEmailValue) return;
    lastEmailValue = this.value;
    emailVerified = false;
    clearInterval(otpCountdown);
    document.getElementById('otpField').style.display      = 'none';
    document.getElementById('verifiedBadge').style.display = 'none';
    document.getElementById('emailHint').textContent       = '';
    const retryRow = document.getElementById('otpRetryRow');
    if (retryRow) retryRow.style.display = 'none';
    const btn = document.getElementById('sendOtpBtn');
    if (btn) { btn.disabled = false; btn.textContent = 'Send Code'; btn.style.display = ''; }
});

// ── Birthday → age ──
document.getElementById('birthday')?.addEventListener('change', function () {
    const dob = new Date(this.value);
    if (isNaN(dob)) return;
    const age = Math.floor((Date.now() - dob) / 31557600000);
    document.getElementById('age').value = age >= 0 ? age : '';
});

// ── ID type gate ──
document.getElementById('id_type_id')?.addEventListener('change', function () {
    const idBox     = document.getElementById('idPhotoBox');
    const selfieBox = document.getElementById('selfieBox');
    if (this.value) {
        idBox.style.opacity      = '1'; idBox.style.pointerEvents      = 'auto';
        selfieBox.style.opacity  = '1'; selfieBox.style.pointerEvents  = 'auto';
    }
    if (this.dataset.selectedIdType && this.dataset.selectedIdType !== this.value) {
        resetIdentityUploads();
    } else if (!this.dataset.selectedIdType) {
        // First selection — clear any stale OCR notice
        clearOcrPrefill();
    }
    this.dataset.selectedIdType = this.value;
});

// ── ID camera & upload ──
let idPhotoBlob = null;
let idPhotoUrl  = null;
let idCameraStream = null;

function startIdCamera() {
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
        .then(stream => {
            idCameraStream = stream;
            document.getElementById('idVideo').srcObject = stream;
            document.getElementById('idPhotoIdle').style.display = 'none';
            document.getElementById('idCamera').style.display    = 'block';
        })
        .catch(() => document.getElementById('id_file').click());
}

function snapIdPhoto() {
    const video = document.getElementById('idVideo');
    const canvas = document.getElementById('idCanvas');
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    if (idCameraStream) { idCameraStream.getTracks().forEach(t => t.stop()); idCameraStream = null; }
    document.getElementById('idVideo').srcObject = null;
    canvas.toBlob(blob => {
        idPhotoBlob = blob;
        if (idPhotoUrl) URL.revokeObjectURL(idPhotoUrl);
        idPhotoUrl = URL.createObjectURL(blob);
        document.getElementById('idPhotoImg').src = idPhotoUrl;
        document.getElementById('idPhotoImg').style.display = '';
        document.getElementById('idCamera').style.display       = 'none';
        document.getElementById('idPhotoPreview').style.display = 'block';
        const idErrEl = document.getElementById('idPhotoError');
        if (idErrEl) idErrEl.style.display = 'none';
        runOcr(blob);
    }, 'image/jpeg', 0.92);
}

function retakeIdPhoto() {
    idPhotoBlob = null;
    if (idPhotoUrl) { URL.revokeObjectURL(idPhotoUrl); idPhotoUrl = null; }
    document.getElementById('idPhotoPreview').style.display = 'none';
    document.getElementById('idPhotoIdle').style.display    = 'block';
    document.getElementById('id_file').value = '';
    const imgEl = document.getElementById('idPhotoImg');
    if (imgEl) { imgEl.src = ''; imgEl.style.display = ''; }
    const pdfCard = document.getElementById('idPhotoPreview')?.querySelector('.pdf-card');
    if (pdfCard) pdfCard.style.display = 'none';
    clearOcrPrefill();
    // Reset OCR result box to hidden/empty
    const ocrBox = document.getElementById('ocrResult');
    if (ocrBox) { ocrBox.className = 'ocr-result'; ocrBox.innerHTML = ''; }
}

document.getElementById('id_file')?.addEventListener('change', function () {
    if (!this.files[0]) return;
    const file = this.files[0];
    idPhotoBlob = file;
    if (idPhotoUrl) URL.revokeObjectURL(idPhotoUrl);
    idPhotoUrl = URL.createObjectURL(file);
    const isPdf = file.type === 'application/pdf';
    const imgEl  = document.getElementById('idPhotoImg');
    const prevEl = document.getElementById('idPhotoPreview');
    document.getElementById('idPhotoIdle').style.display    = 'none';
    prevEl.style.display = 'block';
    if (isPdf) {
        imgEl.style.display = 'none';
        let pdfCard = prevEl.querySelector('.pdf-card');
        if (!pdfCard) {
            pdfCard = document.createElement('div');
            pdfCard.className = 'pdf-card';
            pdfCard.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:14px 8px;min-height:90px;text-align:center';
            imgEl.after(pdfCard);
        }
        pdfCard.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d9468f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><span style="font-size:10px;color:#555;word-break:break-all;max-width:100%">${file.name}</span><span style="font-size:10px;font-weight:700;color:var(--auth-primary);background:var(--auth-primary-soft);padding:2px 8px;border-radius:4px">PDF</span>`;
        pdfCard.style.display = 'flex';
        const enlargeBtn = prevEl.querySelector('.enlarge-btn');
        if (enlargeBtn) enlargeBtn.onclick = (e) => { e.stopPropagation(); window.open(idPhotoUrl, '_blank'); };
    } else {
        imgEl.src = idPhotoUrl;
        imgEl.style.display = '';
        const pdfCard = prevEl.querySelector('.pdf-card');
        if (pdfCard) pdfCard.style.display = 'none';
        const enlargeBtn = prevEl.querySelector('.enlarge-btn');
        if (enlargeBtn) enlargeBtn.onclick = (e) => { e.stopPropagation(); openLightbox('idPhotoImg'); };
    }
    const idErrEl = document.getElementById('idPhotoError');
    if (idErrEl) idErrEl.style.display = 'none';
    runOcr(file);
});

// ── Selfie / Camera ──
let selfieBlob = null;
let cameraStream = null;

function startCamera() {
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
        .then(stream => {
            cameraStream = stream;
            document.getElementById('selfieVideo').srcObject = stream;
            document.getElementById('selfieIdle').style.display   = 'none';
            document.getElementById('selfieCamera').style.display = 'block';
        })
        .catch(() => {});
}

function snapSelfie() {
    const video = document.getElementById('selfieVideo');
    const canvas = document.getElementById('selfieCanvas');
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    stopCamera();
    document.getElementById('selfieVideo').srcObject = null;
    canvas.toBlob(blob => {
        selfieBlob = blob;
        document.getElementById('selfieImg').src = URL.createObjectURL(blob);
        document.getElementById('selfieCamera').style.display  = 'none';
        document.getElementById('selfiePreview').style.display = 'block';
        document.getElementById('selfieError').style.display   = 'none';
    }, 'image/jpeg', 0.9);
}

function retakeSelfie() {
    selfieBlob = null;
    document.getElementById('selfiePreview').style.display = 'none';
    document.getElementById('selfieIdle').style.display    = 'block';
    startCamera();
}

function stopCamera() {
    if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
}

function resetIdentityUploads() {
    retakeIdPhoto();
    if (idCameraStream) { idCameraStream.getTracks().forEach(t => t.stop()); idCameraStream = null; }
    document.getElementById('idCamera').style.display = 'none';
    document.getElementById('idVideo').srcObject = null;
    stopCamera();
    selfieBlob = null;
    document.getElementById('selfiePreview').style.display = 'none';
    document.getElementById('selfieCamera').style.display = 'none';
    document.getElementById('selfieIdle').style.display = 'block';
    document.getElementById('idPhotoError').style.display = 'none';
    document.getElementById('selfieError').style.display = 'none';
    clearOcrPrefill();
}

// ── Lightbox ──
function openLightbox(imgId) {
    const imgEl = document.getElementById(imgId);
    const lightboxImg = document.getElementById('lightboxImg');
    const lightboxPdf = document.getElementById('lightboxPdf');
    lightboxImg.style.display = 'block';
    lightboxPdf.style.display = 'none';
    lightboxImg.src = imgEl.src;
    document.getElementById('imgLightbox').classList.add('open');
}
function openDocLightbox(key) {
    const url  = docUrls[key];
    const file = docBlobs[key];
    if (!url || !file) return;
    if (file.type === 'application/pdf') {
        window.open(url, '_blank');
        return;
    }
    const lightboxImg = document.getElementById('lightboxImg');
    const lightboxPdf = document.getElementById('lightboxPdf');
    lightboxImg.style.display = 'block';
    lightboxPdf.style.display = 'none';
    lightboxImg.src = url;
    document.getElementById('imgLightbox').classList.add('open');
}
function closeLightbox() {
    document.getElementById('imgLightbox').classList.remove('open');
    document.getElementById('lightboxImg').src = '';
}

// ── Terms & Conditions quiz ──
let tcDone = false;
let tcCurrent = 0;
const TC_TOTAL = 5;

const SVG_CHECK = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:5px"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>';
const SVG_CROSS = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:5px"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';

// The fourth policy is tailored to the registration type; the other terms apply to everyone.
const accountTerms = {
    buyer: {
        title: 'Buyer Responsibilities',
        policy: 'Review item details before purchasing, communicate respectfully, and use safe, agreed hand-off or delivery arrangements. Do not ask sellers to make payments or share sensitive information outside PocketFinds.',
        question: 'What should you do before completing a purchase?',
        answers: ['Send personal payment details by chat', 'Review the listing and arrange a safe hand-off or delivery', 'Ask the seller to change the listing after payment'],
        correct: 1,
    },
    seller: {
        title: 'Seller Listings & Fulfillment',
        policy: 'Keep listings accurate, disclose an item\'s condition, and fulfil confirmed orders as described. You must not post misleading prices, counterfeit goods, or items you cannot provide.',
        question: 'What is required when creating a listing?',
        answers: ['Use accurate details and disclose the item\'s condition', 'Use any product photos, even if they are unrelated', 'List an item before you have it available'],
        correct: 0,
    },
    rider: {
        title: 'Rider Delivery & Safety',
        policy: 'Follow traffic laws, handle orders carefully, protect customer information, and communicate delivery updates through PocketFinds. Never mark an order delivered before it is safely handed over.',
        question: 'When may a rider mark an order as delivered?',
        answers: ['After safely handing the order to the customer', 'As soon as the rider accepts the delivery', 'Before leaving the pickup location'],
        correct: 0,
    },
};

const typeTerm = accountTerms[accountTypeVal] || accountTerms.buyer;
const accountTermSlide = document.querySelectorAll('.tc-slide')[3];
if (accountTermSlide) {
    accountTermSlide.innerHTML = `
        <div style="background:var(--auth-primary-soft);border-left:3px solid var(--auth-primary);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:12px;line-height:1.7;color:#374151">
            <strong style="display:block;margin-bottom:4px">${typeTerm.title}</strong>
            ${typeTerm.policy}
        </div>
        <p style="font-size:13px;font-weight:700;color:#111;margin:0 0 12px">${typeTerm.question}</p>
        <div class="tc-options" style="display:flex;flex-direction:column;gap:8px">
            ${typeTerm.answers.map((answer, index) => `<button type="button" class="tc-opt" data-correct="${index === typeTerm.correct}" onclick="tcAnswer(this)">${answer}</button>`).join('')}
        </div>
        <p class="tc-feedback" style="display:none;margin:10px 0 0;font-size:12px;border-radius:8px;padding:8px 12px"></p>`;
}

let businessPermitBlob = null;
let businessPermitUrl  = null;

// Prevent the box onclick from firing when clicking preview buttons
document.getElementById('businessPermitBox')?.addEventListener('click', function (e) {
    if (businessPermitBlob) return; // preview is showing — don't open file picker
    document.getElementById('business_permit_file').click();
});

document.getElementById('business_permit_file')?.addEventListener('change', function () {
    if (!this.files[0]) return;
    businessPermitBlob = this.files[0];
    if (businessPermitUrl) URL.revokeObjectURL(businessPermitUrl);
    businessPermitUrl = URL.createObjectURL(this.files[0]);
    const isPdf = this.files[0].type === 'application/pdf';
    const imgEl = document.getElementById('businessPermitImg');
    const prevEl = document.getElementById('businessPermitPreview');
    if (isPdf) {
        imgEl.style.display = 'none';
        let pdfCard = prevEl.querySelector('.pdf-card');
        if (!pdfCard) {
            pdfCard = document.createElement('div');
            pdfCard.className = 'pdf-card';
            pdfCard.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:14px 8px;min-height:90px;text-align:center';
            imgEl.after(pdfCard);
        }
        pdfCard.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d9468f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><span style="font-size:10px;color:#555;word-break:break-all;max-width:100%">${this.files[0].name}</span><span style="font-size:10px;font-weight:700;color:var(--auth-primary);background:var(--auth-primary-soft);padding:2px 8px;border-radius:4px">PDF</span>`;
        pdfCard.style.display = 'flex';
        const enlargeBtn = prevEl.querySelector('.enlarge-btn');
        if (enlargeBtn) enlargeBtn.onclick = (e) => { e.stopPropagation(); window.open(businessPermitUrl, '_blank'); };
    } else {
        imgEl.src = businessPermitUrl;
        imgEl.style.display = '';
        const pdfCard = prevEl.querySelector('.pdf-card');
        if (pdfCard) pdfCard.style.display = 'none';
        const enlargeBtn = prevEl.querySelector('.enlarge-btn');
        if (enlargeBtn) enlargeBtn.onclick = (e) => { e.stopPropagation(); openLightbox('businessPermitImg'); };
    }
    document.getElementById('businessPermitIdle').style.display = 'none';
    prevEl.style.display = 'block';
    document.getElementById('businessPermitError').style.display = 'none';
});

function clearBusinessPermit() {
    businessPermitBlob = null;
    if (businessPermitUrl) { URL.revokeObjectURL(businessPermitUrl); businessPermitUrl = null; }
    document.getElementById('business_permit_file').value = '';
    document.getElementById('businessPermitIdle').style.display = 'block';
    document.getElementById('businessPermitPreview').style.display = 'none';
    const imgEl = document.getElementById('businessPermitImg');
    if (imgEl) { imgEl.src = ''; imgEl.style.display = ''; }
    const pdfCard = document.getElementById('businessPermitPreview')?.querySelector('.pdf-card');
    if (pdfCard) pdfCard.style.display = 'none';
}

let businessNameAvailable = null;
let businessNameTimer = null;
const businessNameInput = document.getElementById('business_name');
if (businessNameInput) {
    // Inline status icon inside the input (same pattern as usernameStatus)
    const bnWrap = document.createElement('div');
    bnWrap.style.cssText = 'position:relative';
    businessNameInput.parentNode.insertBefore(bnWrap, businessNameInput);
    bnWrap.appendChild(businessNameInput);
    const bnStatus = document.createElement('span');
    bnStatus.id = 'businessNameStatus';
    bnStatus.style.cssText = 'position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:12px;pointer-events:none';
    bnWrap.appendChild(bnStatus);

    businessNameInput.addEventListener('input', function () {
        const value = this.value.trim();
        businessNameAvailable = null;
        bnStatus.innerHTML = '';
        clearTimeout(businessNameTimer);
        if (value.length < 2) return;
        // Show clock icon while waiting
        bnStatus.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;color:#94a3b8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
        businessNameTimer = setTimeout(() => {
            fetch(`/register/check-business-name?business_name=${encodeURIComponent(value)}`)
                .then(r => r.json())
                .then(data => {
                    businessNameAvailable = data.available;
                    if (data.available) {
                        bnStatus.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>';
                        bnStatus.style.color = '#16a34a';
                    } else {
                        bnStatus.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
                        bnStatus.style.color = '#dc2626';
                    }
                })
                .catch(() => { businessNameAvailable = null; bnStatus.innerHTML = ''; });
        }, 500);
    });
}

// mini popup for answer feedback
function showTcPopup(correct, onClose) {
    let popup = document.getElementById('tcPopup');
    if (!popup) {
        popup = document.createElement('div');
        popup.id = 'tcPopup';
        popup.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(15,15,25,.55);backdrop-filter:blur(2px);z-index:10;border-radius:0 0 18px 18px';
        document.getElementById('tcSlides').appendChild(popup);
    }
    if (correct) {
        popup.innerHTML = `<div style="background:#fff;border-radius:14px;padding:22px 26px;text-align:center;max-width:260px;box-shadow:0 8px 32px rgba(0,0,0,.18)">
            <div style="width:48px;height:48px;border-radius:50%;background:#f0fdf4;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <p style="font-size:14px;font-weight:700;color:#15803d;margin:0 0 4px">Correct!</p>
            <p style="font-size:12px;color:#64748b;margin:0 0 16px">${tcCurrent === TC_TOTAL - 1 ? 'All done — you can now agree.' : 'Great, on to the next one.'}</p>
            <button type="button" onclick="closeTcPopup()" style="padding:8px 24px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">${tcCurrent === TC_TOTAL - 1 ? 'I Agree ✓' : 'Next →'}</button>
        </div>`;
    } else {
        popup.innerHTML = `<div style="background:#fff;border-radius:14px;padding:22px 26px;text-align:center;max-width:260px;box-shadow:0 8px 32px rgba(0,0,0,.18)">
            <div style="width:48px;height:48px;border-radius:50%;background:#fff7ed;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c2410c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <p style="font-size:14px;font-weight:700;color:#c2410c;margin:0 0 4px">Not quite!</p>
            <p style="font-size:12px;color:#64748b;margin:0 0 16px">Read the rule above carefully and try again.</p>
            <button type="button" onclick="closeTcPopup(true)" style="padding:8px 24px;background:#c2410c;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Retry</button>
        </div>`;
    }
    popup.style.display = 'flex';
    popup._onClose = onClose;
}

function closeTcPopup(retry) {
    const popup = document.getElementById('tcPopup');
    if (!popup) return;
    const cb = popup._onClose;
    popup.style.display = 'none';
    if (cb) cb(retry);
}

function openTc() {
    const slides = document.querySelectorAll('.tc-slide');
    if (tcDone) {
        // review mode: show all answers highlighted, Next always enabled
        tcCurrent = 0;
        slides.forEach((s, i) => {
            s.style.transform = i === 0 ? 'translateX(0)' : 'translateX(100%)';
            s.style.opacity   = i === 0 ? '1' : '0';
            // highlight correct answers
            s.querySelectorAll('.tc-opt').forEach(b => {
                b.disabled = true;
                if (b.dataset.correct === 'true') {
                    b.style.background = '#f0fdf4'; b.style.color = '#15803d'; b.style.borderColor = '#86efac';
                } else {
                    b.style.background = '#f8fafc'; b.style.color = '#94a3b8'; b.style.borderColor = '#e2e8f0';
                }
            });
            const fb = s.querySelector('.tc-feedback');
            fb.style.display = 'block';
            fb.style.background = '#f0fdf4'; fb.style.color = '#15803d'; fb.style.border = '1px solid #bbf7d0';
            fb.innerHTML = SVG_CHECK + 'Correct answer highlighted above.';
        });
        document.getElementById('tcProgress').textContent = `Review — Question 1 of ${TC_TOTAL}`;
        document.getElementById('tcBar').style.width = '100%';
        const nextBtn = document.getElementById('tcNextBtn');
        nextBtn.style.display = tcDone ? 'inline-flex' : 'none';
        nextBtn.disabled = false; nextBtn.style.opacity = '1'; nextBtn.textContent = 'Next →';
        document.getElementById('tcModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        return;
    }
    tcCurrent = 0;
    slides.forEach((s, i) => {
        s.style.transform = i === 0 ? 'translateX(0)' : 'translateX(100%)';
        s.style.opacity   = i === 0 ? '1' : '0';
        s.querySelectorAll('.tc-opt').forEach(b => { b.disabled = false; b.style.background = '#fff'; b.style.color = ''; b.style.borderColor = '#e5e7eb'; });
        s.querySelector('.tc-feedback').style.display = 'none';
    });
    document.getElementById('tcProgress').textContent = `Question 1 of ${TC_TOTAL}`;
    document.getElementById('tcBar').style.width = `${100 / TC_TOTAL}%`;
    const nextBtn = document.getElementById('tcNextBtn');
    nextBtn.style.display = tcDone ? 'inline-flex' : 'none';
    nextBtn.disabled = true; nextBtn.style.opacity = '.4'; nextBtn.textContent = 'Next →';
    document.getElementById('tcModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeTc() {
    document.getElementById('tcModal').style.display = 'none';
    document.body.style.overflow = '';
}

function tcAnswer(btn) {
    if (tcDone) return;
    const slide = btn.closest('.tc-slide');
    const opts  = slide.querySelectorAll('.tc-opt');
    const correct = btn.dataset.correct === 'true';
    if (correct) {
        opts.forEach(b => { b.disabled = true; });
        btn.style.background = '#f0fdf4'; btn.style.color = '#15803d'; btn.style.borderColor = '#86efac';
        const fb = slide.querySelector('.tc-feedback');
        fb.style.background = '#f0fdf4'; fb.style.color = '#15803d'; fb.style.border = '1px solid #bbf7d0';
        fb.innerHTML = SVG_CHECK + (tcCurrent === TC_TOTAL - 1 ? 'All done — you can now agree.' : 'Next question coming up.');
        fb.style.display = 'block';
        showTcPopup(true, () => tcNext());
    } else {
        showTcPopup(false, (retry) => {
            if (retry) {
                // re-enable options so they can try again
                opts.forEach(b => { b.disabled = false; b.style.background = '#fff'; b.style.color = ''; b.style.borderColor = '#e5e7eb'; });
            }
        });
    }
}

function tcNext() {
    if (tcCurrent === TC_TOTAL - 1) {
        tcDone = true;
        closeTc();
        const cb = document.getElementById('tcCheckbox');
        cb.disabled = false; cb.checked = true;
        document.getElementById('tcBadge').style.display = 'inline';
        document.getElementById('tcOpenBtn').innerHTML = 'Terms &amp; Conditions ' + SVG_CHECK.replace('margin-right:5px', 'margin-right:0;margin-left:3px');
        document.getElementById('tcError').style.display = 'none';
        return;
    }
    const slides = document.querySelectorAll('.tc-slide');
    slides[tcCurrent].style.transform = 'translateX(-100%)';
    slides[tcCurrent].style.opacity   = '0';
    tcCurrent++;
    slides[tcCurrent].style.transform = 'translateX(0)';
    slides[tcCurrent].style.opacity   = '1';
    const label = tcDone ? `Review — Question ${tcCurrent + 1} of ${TC_TOTAL}` : `Question ${tcCurrent + 1} of ${TC_TOTAL}`;
    document.getElementById('tcProgress').textContent = label;
    document.getElementById('tcBar').style.width = `${(tcCurrent + 1) * 100 / TC_TOTAL}%`;
    if (!tcDone) {
        const nextBtn = document.getElementById('tcNextBtn');
        nextBtn.style.display = tcDone ? 'inline-flex' : 'none';
        nextBtn.disabled = true; nextBtn.style.opacity = '.4';
        nextBtn.textContent = tcCurrent === TC_TOTAL - 1 ? 'I Agree ✓' : 'Next →';
    }
}

// style tc-opt buttons
document.querySelectorAll('.tc-opt').forEach(b => {
    b.style.cssText += 'text-align:left;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-size:12px;cursor:pointer;transition:background .15s,border-color .15s;width:100%';
    b.addEventListener('mouseenter', () => { if (!b.disabled) b.style.borderColor = 'var(--auth-primary)'; });
    b.addEventListener('mouseleave', () => { if (!b.disabled && !b.style.background.includes('f0fdf4') && !b.style.background.includes('fff7ed')) b.style.borderColor = '#e5e7eb'; });
});

// ── Username live check ──
let usernameAvailable = null;
let usernameTimer = null;
const usernameInput = document.getElementById('username');
if (usernameInput) {
    const usernameStatus = document.getElementById('usernameStatus');
    const usernameSugg   = document.getElementById('usernameSuggestions');
    usernameInput.addEventListener('input', function () {
        const val = this.value.trim();
        usernameStatus.textContent = ''; usernameSugg.style.display = 'none'; usernameAvailable = null;
        clearTimeout(usernameTimer);
        if (!val) return;
        if (val.length < 8) {
            usernameStatus.textContent = 'Use at least 8 characters.';
            usernameStatus.style.color = '#dc2626';
            return;
        }
        usernameStatus.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;color:#94a3b8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'; usernameStatus.style.color = '';
        usernameTimer = setTimeout(() => {
            fetch(`/register/check-username?username=${encodeURIComponent(val)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    usernameAvailable = data.available;
                    if (data.available) {
                        usernameStatus.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>'; usernameStatus.style.color = '#16a34a';
                        usernameSugg.style.display = 'none';
                    } else {
                        usernameStatus.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'; usernameStatus.style.color = '#dc2626';
                        if (data.message) {
                            usernameSugg.textContent = data.message;
                            usernameSugg.style.display = 'block';
                        } else if (data.suggestions?.length) {
                            usernameSugg.innerHTML = 'Suggestions: ' + data.suggestions.map(s =>
                                `<a href="#" style="margin-right:8px;color:var(--auth-primary)" onclick="event.preventDefault();usernameInput.value='${s}';usernameInput.dispatchEvent(new Event('input'))">${s}</a>`
                            ).join('');
                            usernameSugg.style.display = 'block';
                        }
                    }
                })
                .catch(() => { usernameStatus.textContent = ''; usernameAvailable = null; });
        }, 500);
    });
}

// ── OCR prefill ──
function clearOcrPrefill() {
    const ocrBox = document.getElementById('ocrResult');
    const noteBox = document.getElementById('ocrPrefillNote');
    if (ocrBox) { ocrBox.className = 'ocr-result'; ocrBox.innerHTML = ''; }
    if (noteBox) { noteBox.innerHTML = ''; noteBox.style.display = 'none'; }
}

function runOcr(source) {
    const ocrBox  = document.getElementById('ocrResult');
    const noteBox = document.getElementById('ocrPrefillNote');
    if (source && source.type === 'application/pdf') {
        ocrBox.className = 'ocr-result mismatch';
        ocrBox.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> PDF detected — please fill in your details manually.';
        noteBox.innerHTML = '<em>PDF uploaded — please fill in your details below manually.</em>';
        noteBox.style.display = 'block';
        return;
    }
    ocrBox.className = 'ocr-result checking';
    ocrBox.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Reading your ID…';
    Tesseract.recognize(source, 'eng', { logger: () => {} })
        .then(async ({ data: { text } }) => {
            const lines = text.split('\n').map(l => l.trim()).filter(Boolean);
            const raw   = text.toLowerCase();
            let filled  = [];
            const months = 'jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|january|february|march|april|june|july|august|september|october|november|december';
            let iso = null;
            const numDate = raw.match(/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})|(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/);
            if (numDate) {
                iso = numDate[1]
                    ? `${numDate[1]}-${numDate[2].padStart(2,'0')}-${numDate[3].padStart(2,'0')}`
                    : `${numDate[6]}-${numDate[4].padStart(2,'0')}-${numDate[5].padStart(2,'0')}`;
            } else {
                const txtDate = raw.match(new RegExp(`(\\d{1,2})[\\s]+(?:${months})[\\s,]+(\\d{4})|((?:${months})[\\s]+(\\d{1,2})[\\s,]+(\\d{4}))`, 'i'));
                if (txtDate) {
                    const monthMap = {jan:1,feb:2,mar:3,apr:4,may:5,jun:6,jul:7,aug:8,sep:9,oct:10,nov:11,dec:12,january:1,february:2,march:3,april:4,june:6,july:7,august:8,september:9,october:10,november:11,december:12};
                    const mMatch = txtDate[0].match(new RegExp(`(${months})`, 'i'));
                    const dMatch = txtDate[0].match(/(\d{1,2})(?!\d{3})/);
                    const yMatch = txtDate[0].match(/(\d{4})/);
                    if (mMatch && dMatch && yMatch) {
                        const m = monthMap[mMatch[1].toLowerCase().slice(0,3)];
                        iso = `${yMatch[1]}-${String(m).padStart(2,'0')}-${dMatch[1].padStart(2,'0')}`;
                    }
                }
            }
            if (iso) { document.getElementById('birthday').value = iso; document.getElementById('birthday').dispatchEvent(new Event('change')); filled.push('birthday'); }
            const sexLabelMatch = raw.match(/\b(?:sex|gender)\s*[:\/]?\s*(male|female|m\b|f\b)/i);
            if (sexLabelMatch) {
                document.getElementById('sex').value = sexLabelMatch[1].toLowerCase().startsWith('f') ? 'female' : 'male';
                filled.push('sex');
            } else if (/\bfemale\b/i.test(raw)) {
                document.getElementById('sex').value = 'female'; filled.push('sex');
            } else if (/\bmale\b/i.test(raw)) {
                document.getElementById('sex').value = 'male'; filled.push('sex');
            }
            const addrKeywords = /\b(st\.?|street|ave\.?|avenue|blvd|road|rd\.?|brgy\.?|barangay|purok|sitio|subd|subdivision|village|city|municipality|province|district|zone|block|lot|unit|floor|bldg|building)\b/i;
            const addrLines = lines.filter(l => addrKeywords.test(l) && l.length > 6);
            if (addrLines.length > 0) {
                const streetLine = addrLines.find(l => /\b(st\.?|street|ave\.?|avenue|blvd|road|rd\.?)\b/i.test(l));
                if (streetLine) { document.getElementById('street').value = streetLine.replace(/^[,\s]+|[,\s]+$/g, ''); filled.push('street'); }
                const houseMatch = addrLines.join(', ').match(/^(\d+[A-Za-z]?)[,\s]/);
                if (houseMatch && !document.getElementById('house_no').value) { document.getElementById('house_no').value = houseMatch[1]; filled.push('house no.'); }
            }
            if (psgcProvinces.length > 0) filled.push(...(await ocrMatchAddress(text)));
            if (filled.length > 0) {
                ocrBox.className = 'ocr-result match';
                ocrBox.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><polyline points="20 6 9 17 4 12"/></svg><em>Pre-filled: ' + filled.join(', ') + ' — please review.</em>';
                noteBox.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><polyline points="20 6 9 17 4 12"/></svg><em>Some fields were pre-filled from your ID — please review.</em>';
                noteBox.style.display = 'block';
            } else {
                ocrBox.className = 'ocr-result mismatch';
                ocrBox.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Could not read your ID — please fill in your details manually.';
                noteBox.innerHTML = '<em>Could not read your ID — please fill in your details manually.</em>';
                noteBox.style.display = 'block';
            }
        })
        .catch(() => {
            ocrBox.className = 'ocr-result mismatch';
            ocrBox.innerHTML = 'Could not read your ID — please fill in your details manually.';
        });
}

// ── PSGC helpers ──
async function fetchJSON(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error('Network error');
    return res.json();
}

function setLoading(sel, msg) {
    sel.innerHTML = `<option value="" disabled selected>${msg}</option>`;
    sel.disabled = true;
}

function populateSelect(sel, items, valueKey, labelKey, placeholder) {
    sel.innerHTML = `<option value="" disabled selected>${placeholder}</option>`;
    [...items].sort((a, b) => a[labelKey].localeCompare(b[labelKey])).forEach(item => {
        const o = document.createElement('option');
        // Store the name as the submitted value; keep code in data-code for cascade fetches
        o.value = item[labelKey]; o.dataset.code = item[valueKey]; o.textContent = item[labelKey];
        sel.appendChild(o);
    });
    sel.disabled = false;
}

function normAddr(s) { return s.toLowerCase().replace(/[^a-z0-9\s]/g, '').trim(); }

function matchFromList(items, rawText) {
    const norm = normAddr(rawText);
    return [...items].filter(i => norm.includes(normAddr(i.name))).sort((a, b) => b.name.length - a.name.length)[0] ?? null;
}

async function ocrMatchAddress(rawText) {
    const provSel = document.getElementById('province');
    const muniSel = document.getElementById('municipality');
    const brgysel = document.getElementById('barangay');
    const filled  = [];
    const provMatch = matchFromList(psgcProvinces, rawText);
    if (!provMatch) return filled;
    provSel.value = provMatch.name; provSel.dispatchEvent(new Event('change')); filled.push('province');
    const munis = await fetchJSON(`${PSGC}/provinces/${provMatch.code}/cities-municipalities/`);
    populateSelect(muniSel, munis, 'code', 'name', 'Select city / municipality');
    const muniMatch = matchFromList(munis, rawText);
    if (!muniMatch) return filled;
    muniSel.value = muniMatch.name; muniSel.dispatchEvent(new Event('change')); filled.push('city/municipality');
    const brgys = await fetchJSON(`${PSGC}/cities-municipalities/${muniMatch.code}/barangays/`);
    populateSelect(brgysel, brgys, 'code', 'name', 'Select barangay');
    const brgyMatch = matchFromList(brgys, rawText);
    if (!brgyMatch) return filled;
    brgysel.value = brgyMatch.name; filled.push('barangay');
    return filled;
}

let psgcProvinces = [];
fetchJSON(`${PSGC}/provinces/`)
    .then(data => { psgcProvinces = data; populateSelect(document.getElementById('province'), data, 'code', 'name', 'Select province'); })
    .catch(() => { const sel = document.getElementById('province'); sel.innerHTML = '<option value="" disabled selected>Failed to load provinces</option>'; sel.disabled = false; });

document.getElementById('province')?.addEventListener('change', function () {
    const munSel = document.getElementById('municipality');
    const barSel = document.getElementById('barangay');
    setLoading(munSel, 'Loading cities / municipalities…');
    setLoading(barSel, 'Select municipality first');
    const code = this.options[this.selectedIndex]?.dataset.code ?? this.value;
    fetchJSON(`${PSGC}/provinces/${code}/cities-municipalities/`)
        .then(data => populateSelect(munSel, data, 'code', 'name', 'Select city / municipality'))
        .catch(() => setLoading(munSel, 'Failed to load'));
});

document.getElementById('municipality')?.addEventListener('change', function () {
    const barSel = document.getElementById('barangay');
    setLoading(barSel, 'Loading barangays…');
    const code = this.options[this.selectedIndex]?.dataset.code ?? this.value;
    fetchJSON(`${PSGC}/cities-municipalities/${code}/barangays/`)
        .then(data => populateSelect(barSel, data, 'code', 'name', 'Select barangay'))
        .catch(() => setLoading(barSel, 'Failed to load'));
});

// ── Field rules ──
const rules = {
    middle_name: () => null,
    email: (v) => {
        if (!v) return 'Email is required.';
        if (!/@gmail\.com$/i.test(v)) return 'Must be a Gmail address (@gmail.com).';
        return null;
    },
    contact_no: (v) => {
        if (!v) return 'Contact number is required.';
        if (!/^09\d{9}$/.test(v)) return 'Must be 11 digits starting with 09.';
        return null;
    },
    age: (v) => {
        if (!v) return 'Birthday is required.';
        if (parseInt(v, 10) < 16) return 'You must be at least 16 years old to register.';
        return null;
    },
};

// Contact number: strip anything that isn't a digit as the user types, so
// letters can't be entered at all.
document.getElementById('contact_no')?.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 11);
});

function showError(el, msg) {
    el.classList.add('error');
    if (el.id === 'email') { const h = document.getElementById('emailHint'); if (h) { h.style.color = 'var(--auth-danger)'; h.textContent = msg; } return; }
    if (el.id === 'contact_no') { const h = document.getElementById('contactHint'); if (h) { h.style.color = 'var(--auth-danger)'; h.textContent = msg; } return; }
    el.parentElement.querySelector('.field-error')?.remove();
    const err = document.createElement('span'); err.className = 'field-error'; err.textContent = msg;
    (el.closest('.auth-input-wrap') ?? el).after(err);
}

function clearError(el) {
    el.classList.remove('error');
    if (el.id === 'email') { const h = document.getElementById('emailHint'); if (h) { h.style.color = ''; h.textContent = ''; } return; }
    if (el.id === 'contact_no') { const h = document.getElementById('contactHint'); if (h) { h.style.color = ''; h.textContent = ''; } return; }
    el.parentElement.querySelector('.field-error')?.remove();
}

// ── Vehicle type change ──
function onVehicleTypeChange() {
    const vt = document.querySelector('input[name="vehicle_type"]:checked')?.value;
    const isBike = vt === 'bicycle';
    document.getElementById('plateField').style.display       = isBike ? 'none' : '';
    document.getElementById('vehicleDocsSection').style.display = isBike ? 'none' : '';
    // highlight selected card
    document.querySelectorAll('.vehicle-type-card').forEach(card => {
        const radio = document.getElementById(card.dataset.for);
        card.style.borderColor = radio?.checked ? 'var(--auth-primary)' : '#e5e7eb';
        card.style.background  = radio?.checked ? 'var(--auth-primary-soft)' : '#fff';
        card.style.color       = radio?.checked ? 'var(--auth-primary)' : '#374151';
    });
    document.getElementById('vehicleTypeError').style.display = 'none';
}

// ── Doc upload handlers (OR, CR, license) ──
const docBlobs = { or: null, cr: null, license: null };
const docUrls  = { or: null, cr: null, license: null };

function handleDocUpload(key, input) {
    if (!input.files[0]) return;
    const file = input.files[0];
    docBlobs[key] = file;
    if (docUrls[key]) URL.revokeObjectURL(docUrls[key]);
    docUrls[key] = URL.createObjectURL(file);
    const isPdf = file.type === 'application/pdf';
    const idleEl   = document.getElementById(`${key}Idle`);
    const prevEl   = document.getElementById(`${key}Preview`);
    const imgEl    = document.getElementById(`${key}Img`);
    const errEl    = document.getElementById(`${key}Error`);
    idleEl.style.display  = 'none';
    prevEl.style.display  = '';
    if (isPdf) {
        // Show PDF card instead of broken image
        imgEl.style.display = 'none';
        let pdfCard = prevEl.querySelector('.pdf-card');
        if (!pdfCard) {
            pdfCard = document.createElement('div');
            pdfCard.className = 'pdf-card';
            pdfCard.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:14px 8px;min-height:90px;text-align:center';
            imgEl.after(pdfCard);
        }
        pdfCard.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d9468f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><span style="font-size:10px;color:#555;word-break:break-all;max-width:100%">${file.name}</span><span style="font-size:10px;font-weight:700;color:var(--auth-primary);background:var(--auth-primary-soft);padding:2px 8px;border-radius:4px">PDF</span>`;
        pdfCard.style.display = 'flex';
        // Update enlarge button to open PDF
        const enlargeBtn = prevEl.querySelector('.enlarge-btn');
        if (enlargeBtn) enlargeBtn.onclick = (e) => { e.stopPropagation(); openDocLightbox(key); };
    } else {
        imgEl.src = docUrls[key];
        imgEl.style.display = '';
        const pdfCard = prevEl.querySelector('.pdf-card');
        if (pdfCard) pdfCard.style.display = 'none';
        // Restore enlarge button for image
        const enlargeBtn = prevEl.querySelector('.enlarge-btn');
        if (enlargeBtn) enlargeBtn.onclick = (e) => { e.stopPropagation(); openDocLightbox(key); };
    }
    if (errEl) errEl.style.display = 'none';
}

function clearUpload(key) {
    if (docUrls[key]) { URL.revokeObjectURL(docUrls[key]); docUrls[key] = null; }
    docBlobs[key] = null;
    document.getElementById(`${key}_file`).value = '';
    document.getElementById(`${key}Idle`).style.display    = '';
    document.getElementById(`${key}Preview`).style.display = 'none';
    const imgEl = document.getElementById(`${key}Img`);
    if (imgEl) { imgEl.src = ''; imgEl.style.display = ''; }
    const pdfCard = document.getElementById(`${key}Preview`)?.querySelector('.pdf-card');
    if (pdfCard) pdfCard.style.display = 'none';
}

// ── Validation ──
// Manual form: category=step1, id/selfie=step2, personal=step3, contact=step4(emailVerified), address=step5, account=step6
// Google form: category=step0, id/selfie=step1, personal=step2, contact=step3, address=step4, account=step5
// Rider extra: vehicle=step7, license=step8
const idSelfieStep = isGoogleForm ? 1 : 2;
const contactStep  = isGoogleForm ? 3 : 4;
const accountStep  = isGoogleForm ? 5 : 6;

// Rider registration ends with account creation, after vehicle and license details.
if (isRider) {
    const indicator = document.getElementById('stepIndicator');
    const accountItem = indicator?.querySelector(`[data-step="${accountStep}"]`);
    const vehicleItem = indicator?.querySelector('[data-step="7"]');
    const licenseItem = indicator?.querySelector('[data-step="8"]');
    if (accountItem && vehicleItem && licenseItem) {
        accountItem.dataset.step = '8';
        vehicleItem.dataset.step = '6';
        licenseItem.dataset.step = '7';
        accountItem.querySelector('.step-circle').textContent = '7';
        vehicleItem.querySelector('.step-circle').textContent = '5';
        licenseItem.querySelector('.step-circle').textContent = '6';
        indicator.append(vehicleItem, licenseItem, accountItem);
    }
    const accountNext = document.getElementById('btnStep6RiderNext');
    if (accountNext) accountNext.textContent = 'Submit Registration';
}

function indicatorStepFor(panelStep) {
    if (!isRider) return panelStep;
    if (panelStep === accountStep) return 8;
    if (panelStep === 7) return 6;
    if (panelStep === 8) return 7;
    return panelStep;
}

function validateStep(step) {
    // Category step
    if ((isGoogleForm && step === 0) || (!isGoogleForm && step === 1)) {
        const count = typeof categorySelectionCount === 'function' ? categorySelectionCount() : 0;
        const wrap = document.getElementById('categoryGrid').closest('.category-scroll-wrap');
        document.getElementById('categoryError')?.remove();
        if (count !== 1) {
            wrap.style.outline = '2px solid var(--auth-danger,#e74c3c)';
            const err = document.createElement('p');
            err.id = 'categoryError';
            err.style.cssText = 'color:var(--auth-danger,#e74c3c);font-size:12px;margin:6px 0 0';
            err.textContent = 'Please select one category to continue.';
            wrap.after(err);
            return false;
        }
        wrap.style.outline = '';
        return true;
    }

    // Contact step: require email verification on manual form only
    if (step === contactStep && !isGoogleForm && !emailVerified) {
        showError(document.getElementById('email'), 'Please verify your email address first.');
        return false;
    }

    const panel = document.getElementById(`panel-${step}`);
    let valid = true;

    panel.querySelectorAll('input, select').forEach(el => {
        clearError(el);
        const val = el.value.trim();
        if (rules[el.id]) { const msg = rules[el.id](val); if (msg) { showError(el, msg); valid = false; return; } }
        if (el.hasAttribute('required') && !val) { showError(el, 'This field is required.'); valid = false; }
    });

    // ID/Selfie step checks
    if (step === idSelfieStep) {
        const idErrEl = document.getElementById('idPhotoError');
        if (!idPhotoBlob) {
            if (idErrEl) idErrEl.style.display = 'block';
            valid = false;
        } else {
            if (idErrEl) idErrEl.style.display = 'none';
        }
        if (!selfieBlob) { document.getElementById('selfieError').style.display = 'block'; valid = false; }
        else { document.getElementById('selfieError').style.display = 'none'; }
    }

    // Account step: username checks
    if (step === accountStep) {
        const tcCb = document.getElementById('tcCheckbox');
        if (tcCb && !tcCb.checked) {
            document.getElementById('tcError').style.display = 'block';
            valid = false;
        } else if (tcCb) {
            document.getElementById('tcError').style.display = 'none';
        }
        const uEl = document.getElementById('username');
        if (uEl) {
            if (!uEl.value.trim()) { showError(uEl, 'Username is required.'); valid = false; }
            else if (uEl.value.trim().length < 8) { showError(uEl, 'Username must be at least 8 characters.'); valid = false; }
            else if (!/^[a-zA-Z0-9_-]+$/.test(uEl.value.trim())) { showError(uEl, 'Only letters, numbers, underscores and dashes.'); valid = false; }
            else if (usernameAvailable === false) { showError(uEl, 'Username is already taken.'); valid = false; }
            else if (usernameAvailable === null) { showError(uEl, 'Please wait for username check to complete.'); valid = false; }
        }
        const businessName = document.getElementById('business_name');
        if (businessName) {
            if (!businessName.value.trim()) { showError(businessName, 'Business name is required.'); valid = false; }
            else if (businessNameAvailable === false) { showError(businessName, 'Business name is already registered.'); valid = false; }
        }
        if (isSeller && !businessPermitBlob) {
            document.getElementById('businessPermitError').style.display = 'block';
            valid = false;
        } else if (isSeller) {
            document.getElementById('businessPermitError').style.display = 'none';
        }
        // password confirmation
        const pw = document.getElementById('password');
        const pc = document.getElementById('password_confirmation');
        if (pw && pc && pc.value !== pw.value) { showError(pc, 'Passwords do not match.'); valid = false; }
    }

    // Vehicle step
    if (step === 7) {
        const vt = document.querySelector('input[name="vehicle_type"]:checked')?.value;
        if (!vt) { document.getElementById('vehicleTypeError').style.display = 'block'; valid = false; }
        const isBike = vt === 'bicycle';
        ['vehicle_brand','vehicle_model'].forEach(id => {
            const el = document.getElementById(id);
            clearError(el);
            if (!el.value.trim()) { showError(el, 'This field is required.'); valid = false; }
        });
        if (!isBike) {
            const pn = document.getElementById('plate_number');
            clearError(pn);
            if (!pn.value.trim()) { showError(pn, 'Plate number is required.'); valid = false; }
            if (!docBlobs.or)  { document.getElementById('orError').style.display  = 'block'; valid = false; }
            if (!docBlobs.cr)  { document.getElementById('crError').style.display  = 'block'; valid = false; }
        }
        if (!valid) { const first = document.getElementById('panel-7').querySelector('.error'); first?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        return valid;
    }

    // License step
    if (step === 8) {
        const ln = document.getElementById('license_number');
        const le = document.getElementById('license_expiry');
        clearError(ln); clearError(le);
        if (!ln.value.trim()) { showError(ln, 'License number is required.'); valid = false; }
        if (!le.value)        { showError(le, 'Expiry date is required.'); valid = false; }
        if (!docBlobs.license) { document.getElementById('licenseError').style.display = 'block'; valid = false; }
        return valid;
    }

    if (!valid) { const first = panel.querySelector('.error'); first?.scrollIntoView({ behavior: 'smooth', block: 'center' }); first?.focus(); }
    return valid;
}

// ── Step switching ──
function getPrevStep(current) {
    if (current === idSelfieStep && isSeller) return isGoogleForm ? 0 : 1;
    if (current === idSelfieStep) return idSelfieStep;
    if (isRider && current === accountStep) {
        return document.querySelector('input[name="vehicle_type"]:checked')?.value === 'bicycle' ? 7 : 8;
    }
    if (current === 7) return isRider ? accountStep - 1 : accountStep;
    if (current === 8) return 7;
    return current - 1;
}

function setStep(current, target) {
    document.getElementById(`panel-${current}`).classList.remove('active');
    document.getElementById(`panel-${target}`).classList.add('active');
    if (current === idSelfieStep) stopCamera();
    document.querySelectorAll('.step-item').forEach(item => {
        const s = parseInt(item.dataset.step);
        item.classList.remove('active', 'done');
        const indicatorTarget = indicatorStepFor(target);
        if (s === indicatorTarget) item.classList.add('active');
        if (s < indicatorTarget) item.classList.add('done');
    });
    document.querySelector('.auth-form-panel').scrollTop = 0;
    // Account fields should stay clean until the user explicitly continues or submits.
    if (target === accountStep) {
        document.getElementById(`panel-${target}`)?.querySelectorAll('input, select').forEach(clearError);
        const tcError = document.getElementById('tcError');
        if (tcError) tcError.style.display = 'none';
    }
}

function nextStep(current) {
    if (!validateStep(current)) return;
    // Riders complete vehicle and license details before the final account step.
    if (isRider && current === accountStep - 1) { setStep(current, 7); return; }
    if (isRider && current === 7) {
        const vt = document.querySelector('input[name="vehicle_type"]:checked')?.value;
        if (vt === 'bicycle') { setStep(current, accountStep); return; }
        setStep(current, 8); return;
    }
    if (isRider && current === 8) { setStep(current, accountStep); return; }
    if (isRider && current === accountStep) {
        document.getElementById(isGoogleForm ? 'googleRegForm' : 'buyerForm')?.requestSubmit();
        return;
    }
    setStep(current, current + 1);
}

function prevStep(current) {
    setStep(current, getPrevStep(current));
}

// ── Submit (manual form only) ──
if (document.getElementById('buyerForm')) {
    document.getElementById('buyerForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!validateStep(accountStep)) return;
        const form = this;
        const btn = form.querySelector('.btn-submit');
        btn.disabled = true; btn.textContent = 'Submitting…';
        const fd = new FormData(form);
        if (selfieBlob) fd.set('selfie_file', selfieBlob, 'selfie.jpg');
        if (idPhotoBlob) {
            const idExt = idPhotoBlob.type === 'application/pdf' ? 'pdf' : 'jpg';
            fd.set('id_file', idPhotoBlob, `id_photo.${idExt}`);
        }
        if (businessPermitBlob) {
            const bpExt = businessPermitBlob.type === 'application/pdf' ? 'pdf' : 'jpg';
            fd.set('business_permit_file', businessPermitBlob, `business_permit.${bpExt}`);
        }
        if (docBlobs.or)      fd.set('or_file',      docBlobs.or,      docBlobs.or.type === 'application/pdf'      ? 'or.pdf'      : 'or.jpg');
        if (docBlobs.cr)      fd.set('cr_file',      docBlobs.cr,      docBlobs.cr.type === 'application/pdf'      ? 'cr.pdf'      : 'cr.jpg');
        if (docBlobs.license) fd.set('license_file', docBlobs.license, docBlobs.license.type === 'application/pdf' ? 'license.pdf' : 'license.jpg');
        fetch('/register', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    form.style.display = 'none';
                    document.getElementById('stepIndicator').style.display = 'none';
                    document.getElementById('signinLink').style.display = 'none';
                    document.getElementById('successScreen').classList.add('active');
                } else {
                    btn.disabled = false; btn.textContent = 'Submit Registration';
                    const errMsg = data.errors ? Object.entries(data.errors).map(([k,v]) => `${k}: ${v}`).join('\n') : (data.message ?? 'Something went wrong.');
                    alert(errMsg);
                }
            })
            .catch(() => { btn.disabled = false; btn.textContent = 'Submit Registration'; alert('Network error. Please try again.'); });
    });
}
