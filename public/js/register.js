const PSGC = 'https://psgc.gitlab.io/api';
const isGoogleForm = !!document.getElementById('googleRegForm');
const accountTypeVal = document.querySelector('input[name="account_type"]')?.value;
const isSeller    = accountTypeVal === 'seller';
const isRider     = accountTypeVal === 'rider';
const isLogistics = accountTypeVal === 'logistics';

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

// Account is the final step for every account type, including riders (the
// rider view's own inline script owns steps 2/7 and just falls through to
// this file's generic panel-by-panel flow for the rest).
document.getElementById('stepIndicator')?.classList.add('account-is-last');

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
        women:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="2"/><path d="M9 8h6l2 7h-4v7h-2v-7H7l2-7z"/></svg>`,
        men:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="2"/><path d="M9 8h6v7h-2v7h-2v-7H9V8z"/></svg>`,
        kid:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="3.2" ry="2.8"/><ellipse cx="12" cy="10.2" rx="5.5" ry="2.3"/><ellipse cx="12" cy="15" rx="2.6" ry="3.2"/></svg>`,
        fashion:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.57a2 2 0 00-1.34-2.23z"/></svg>`,
        clothing:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.57a2 2 0 00-1.34-2.23z"/></svg>`,
        food:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>`,
        beverage:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8l1 7H7L8 2z"/><path d="M7 9c0 5 2 8 5 8s5-3 5-8"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/></svg>`,
        health:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>`,
        beauty:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>`,
        home:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16v-3"/><path d="M12 13c-3 0-5-2-5-5 3 0 5 2 5 5z"/><path d="M12 13c3 0 5-2 5-5-3 0-5 2-5 5z"/><path d="M7 21h10l-1-5H8l-1 5z"/></svg>`,
        garden:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22V12"/><path d="M5 12C5 7 8 4 12 4c4 0 7 3 7 8"/><path d="M5 12c0-3 2-5 7-5"/></svg>`,
        living:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>`,
        sports:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 01-10 0V4z"/><path d="M7 5H4a3 3 0 003 3"/><path d="M17 5h3a3 3 0 01-3 3"/></svg>`,
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
            // One message, right under the email field — showError already writes to
            // #emailHint and marks the input red; no need for a second copy below it.
            // The buyer can just correct the email and click Send Code again.
            showError(emailEl, msg);
            document.getElementById('otpField').style.display = 'none';
        }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Send Code'; showError(emailEl, 'Network error. Try again.'); });
}

function resendOtp() {
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

// Any verification failure (bad code, expired, network hiccup) leaves the buyer stuck
// unless they can immediately ask for a new one — so the error message always carries
// its own Resend button right next to it, not just plain text.
function showOtpError(msg) {
    const hintEl = document.getElementById('otpHint');
    hintEl.style.color = 'var(--auth-danger)';
    hintEl.innerHTML = `${msg} <button type="button" class="btn-inline-link" id="resendOtpBtn" onclick="resendOtp()">Resend code</button>`;
}

function verifyOtp() {
    const email = document.getElementById('email').value.trim();
    const otp   = document.getElementById('otp_code').value.trim();
    const verifyBtn = document.getElementById('verifyOtpBtn');
    if (otp.length !== 6) { showOtpError('Please enter the full 6-digit code.'); return; }
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
            showOtpError(data.message ?? 'Invalid or expired code.');
        }
    })
    .catch(() => { verifyBtn.disabled = false; verifyBtn.textContent = 'Verify'; showOtpError('Network error. Try again.'); });
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
    clearError(this);
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

document.getElementById('id_file')?.addEventListener('change', async function () {
    if (!this.files[0]) return;
    let file = this.files[0];
    if (file.size > MAX_DOC_UPLOAD_BYTES && file.type.startsWith('image/')) {
        try { file = await compressImageFile(file, MAX_DOC_UPLOAD_BYTES); } catch (e) { /* falls through to the size check below */ }
    }
    if (file.size > MAX_DOC_UPLOAD_BYTES) {
        const idErrEl = document.getElementById('idPhotoError');
        if (idErrEl) { idErrEl.textContent = 'This file is too large (max 5MB) — please choose a smaller one.'; idErrEl.style.display = 'block'; }
        this.value = '';
        return;
    }
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
        if (enlargeBtn) enlargeBtn.onclick = (e) => { e.stopPropagation(); showPdfLightbox(idPhotoUrl); };
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
// Everything previewed here — photos and PDFs alike — stays inside this modal.
// PDFs used to open via window.open() into a new browser tab/native viewer;
// they now render in an <iframe> right here instead, same as an image would.
function openLightbox(imgId) {
    const imgEl = document.getElementById(imgId);
    const lightboxImg = document.getElementById('lightboxImg');
    const lightboxPdf = document.getElementById('lightboxPdf');
    lightboxImg.style.display = 'block';
    lightboxPdf.style.display = 'none';
    lightboxPdf.innerHTML = '';
    lightboxImg.src = imgEl.src;
    document.getElementById('imgLightbox').classList.add('open');
}
function showPdfLightbox(url) {
    const lightboxImg = document.getElementById('lightboxImg');
    const lightboxPdf = document.getElementById('lightboxPdf');
    lightboxImg.style.display = 'none';
    lightboxImg.src = '';
    lightboxPdf.style.display = 'flex';
    lightboxPdf.innerHTML = `<iframe src="${url}" style="width:min(800px,90vw);height:85vh;border:0;border-radius:8px;background:#fff"></iframe>`;
    document.getElementById('imgLightbox').classList.add('open');
}
function openDocLightbox(key) {
    const url  = docUrls[key];
    const file = docBlobs[key];
    if (!url || !file) return;
    if (file.type === 'application/pdf') {
        showPdfLightbox(url);
        return;
    }
    const lightboxImg = document.getElementById('lightboxImg');
    const lightboxPdf = document.getElementById('lightboxPdf');
    lightboxImg.style.display = 'block';
    lightboxPdf.style.display = 'none';
    lightboxPdf.innerHTML = '';
    lightboxImg.src = url;
    document.getElementById('imgLightbox').classList.add('open');
}
function closeLightbox() {
    document.getElementById('imgLightbox').classList.remove('open');
    document.getElementById('lightboxImg').src = '';
    document.getElementById('lightboxPdf').innerHTML = '';
}

let businessPermitBlob = null;
let businessPermitUrl  = null;

// Prevent the box onclick from firing when clicking preview buttons
document.getElementById('businessPermitBox')?.addEventListener('click', function (e) {
    if (businessPermitBlob) return; // preview is showing — don't open file picker
    document.getElementById('business_permit_file').click();
});

document.getElementById('business_permit_file')?.addEventListener('change', async function () {
    if (!this.files[0]) return;
    let file = this.files[0];
    if (file.size > MAX_DOC_UPLOAD_BYTES && file.type.startsWith('image/')) {
        try { file = await compressImageFile(file, MAX_DOC_UPLOAD_BYTES); } catch (e) { /* falls through to the size check below */ }
    }
    if (file.size > MAX_DOC_UPLOAD_BYTES) {
        const permitErrEl = document.getElementById('businessPermitError');
        if (permitErrEl) { permitErrEl.textContent = 'This file is too large (max 5MB) — please choose a smaller one.'; permitErrEl.style.display = 'block'; }
        this.value = '';
        return;
    }
    businessPermitBlob = file;
    if (businessPermitUrl) URL.revokeObjectURL(businessPermitUrl);
    businessPermitUrl = URL.createObjectURL(file);
    const isPdf = file.type === 'application/pdf';
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
        pdfCard.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d9468f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><span style="font-size:10px;color:#555;word-break:break-all;max-width:100%">${file.name}</span><span style="font-size:10px;font-weight:700;color:var(--auth-primary);background:var(--auth-primary-soft);padding:2px 8px;border-radius:4px">PDF</span>`;
        pdfCard.style.display = 'flex';
        const enlargeBtn = prevEl.querySelector('.enlarge-btn');
        if (enlargeBtn) enlargeBtn.onclick = (e) => { e.stopPropagation(); showPdfLightbox(businessPermitUrl); };
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
if (businessNameInput && !isLogistics) {
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

// ── Rider live checks: license number & plate number ──
// A driver's license (and, for a self-owned vehicle, its plate) can only ever
// belong to one account — same "type it, get a live yes/no" pattern as
// username/business name above, wired into a single helper since the two
// fields behave identically.
function attachDuplicateFieldCheck(inputId, checkUrl, paramName, minLen, onResult) {
    let timer = null;
    const input = document.getElementById(inputId);
    if (!input) return;

    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:relative';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    const status = document.createElement('span');
    status.style.cssText = 'position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:12px;pointer-events:none';
    wrap.appendChild(status);

    input.addEventListener('input', function () {
        const value = this.value.trim();
        onResult?.(null);
        status.innerHTML = '';
        clearTimeout(timer);
        if (value.length < minLen) return;
        status.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;color:#94a3b8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
        timer = setTimeout(() => {
            const checkedVal = value;
            fetch(`${checkUrl}?${paramName}=${encodeURIComponent(value)}`)
                .then(r => r.json())
                .then(data => {
                    if (input.value.trim() !== checkedVal) return; // stale — value changed since this request went out
                    onResult?.(data.available);
                    if (data.available) {
                        status.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>';
                        status.style.color = '#16a34a';
                    } else {
                        status.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
                        status.style.color = '#dc2626';
                    }
                })
                .catch(() => {
                    if (input.value.trim() !== checkedVal) return;
                    onResult?.(null);
                    status.innerHTML = '';
                });
        }, 500);
    });
}

let licenseNumberAvailable = null;
attachDuplicateFieldCheck('license_number', '/register/check-license', 'license_number', 3, (result) => { licenseNumberAvailable = result; });

let plateNumberAvailable = null;
attachDuplicateFieldCheck('plate_number', '/register/check-plate', 'plate_number', 2, (result) => { plateNumberAvailable = result; });

// Terms & Conditions: the actual document text comes from the admin-managed Policy
// record (rendered server-side into #tcContent — see register-*.blade.php), not from
// anything hardcoded here. Reading it is still a real gate: the "I agree" checkbox
// stays disabled until the buyer has scrolled the document to the bottom.
let tcRead = false;

function openTc() {
    document.getElementById('tcModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    checkTcScrolled();
}

function closeTc() {
    document.getElementById('tcModal').style.display = 'none';
    document.body.style.overflow = '';
}

function checkTcScrolled() {
    const content = document.getElementById('tcContent');
    if (!content || tcRead) return;
    const atBottom = content.scrollTop + content.clientHeight >= content.scrollHeight - 8;
    if (!atBottom) return;
    tcRead = true;
    const cb = document.getElementById('tcCheckbox');
    if (cb) cb.disabled = false;
    const scrollHint = document.getElementById('tcScrollHint');
    if (scrollHint) scrollHint.textContent = 'You can now check "I agree" below.';
}
document.getElementById('tcContent')?.addEventListener('scroll', checkTcScrolled);

document.getElementById('tcCheckbox')?.addEventListener('change', function () {
    const badge = document.getElementById('tcBadge');
    if (badge) badge.style.display = this.checked ? 'inline' : 'none';
    if (this.checked) document.getElementById('tcError').style.display = 'none';
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
            // Guard against a slow/out-of-order response landing after the user has
            // already changed the field again — without this, an earlier request's
            // "available" could paint the green check for a username that isn't the
            // one currently in the box, letting a genuinely-taken one slip through.
            const checkedVal = val;
            fetch(`/register/check-username?username=${encodeURIComponent(val)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    if (usernameInput.value.trim() !== checkedVal) return;
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
                .catch(() => {
                    if (usernameInput.value.trim() !== checkedVal) return;
                    usernameStatus.textContent = ''; usernameAvailable = null;
                });
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

// Name fields: strip digits/symbols as the user types — only letters, spaces,
// and the punctuation real names actually use (hyphen, apostrophe, period for
// suffixes like "Jr.") survive.
['last_name', 'given_names', 'middle_name'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', function () {
        this.value = this.value.replace(/[^\p{L}\s'.-]/gu, '');
    });
});

// ── Auto-clear field errors on the next interaction ──
// Photo/vehicle/ownership errors already hide themselves the moment their
// own control changes (see handleDocUpload/onVehicleTypeChange/onOwnershipChange);
// this covers every plain text input and <select> driven by showError/clearError,
// so a red field stops looking broken the instant the user starts fixing it.
['input', 'change'].forEach(evt => {
    document.addEventListener(evt, function (e) {
        const el = e.target;
        if (el?.classList?.contains('error') && (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA')) {
            clearError(el);
        }
    });
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

// ── Registration error modal ──
// Replaces the old plain alert() for errors caught only at final submission
// (duplicate name/username/business name, or anything else the server rejects) —
// built and injected once here so every registration page gets it for free.
function showRegisterErrorModal(message) {
    let overlay = document.getElementById('registerErrorModal');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'registerErrorModal';
        overlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(27,22,32,.55);z-index:999;align-items:center;justify-content:center;padding:20px';
        overlay.innerHTML = `
            <div style="background:#fff;border-radius:16px;width:min(420px,100%);padding:28px 26px;box-shadow:0 24px 60px rgba(27,22,32,.3);text-align:center">
                <div style="width:52px;height:52px;border-radius:50%;background:#fef2f2;color:#dc2626;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <h3 id="registerErrorModalTitle" style="margin:0 0 8px;font-size:17px;font-weight:800;color:#1b1620">Registration Error</h3>
                <p id="registerErrorModalMsg" style="margin:0 0 20px;font-size:13.5px;line-height:1.5;color:#6b6470"></p>
                <button type="button" id="registerErrorModalClose" style="width:100%;padding:11px;border:0;border-radius:10px;background:var(--auth-primary,#d9468f);color:#fff;font-weight:700;font-size:14px;cursor:pointer">Okay</button>
            </div>`;
        document.body.appendChild(overlay);
        const close = () => { overlay.style.display = 'none'; };
        overlay.querySelector('#registerErrorModalClose').addEventListener('click', close);
        overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
    }
    const isDuplicate = /already registered|already taken/i.test(message);
    overlay.querySelector('#registerErrorModalTitle').textContent = isDuplicate ? 'Already Registered' : 'Registration Error';
    overlay.querySelector('#registerErrorModalMsg').textContent = message;
    overlay.style.display = 'flex';
}

// ── Vehicle type change ──
function onVehicleTypeChange() {
    const checked = document.querySelector('input[name="vehicle_type"]:checked');
    // Driven by the vehicle_types table's requires_documents flag (see register-rider.blade.php),
    // not a hardcoded vehicle-type name — a new vehicle type added in the database just works.
    const requiresDocs = checked?.dataset.requiresDocuments === '1';
    // #plateField doesn't exist in the current markup (register-rider.blade.php's plate
    // number field has no such wrapper) — this used to throw here uncaught on every vehicle
    // type selection, which skipped everything below it: the OR/CR upload section never
    // showed, the card never highlighted, and the error message never cleared. Guarded so a
    // future page CAN add that wrapper back without this breaking either way.
    const plateField = document.getElementById('plateField');
    if (plateField) plateField.style.display = requiresDocs ? '' : 'none';
    document.getElementById('vehicleDocsSection').style.display = requiresDocs ? '' : 'none';
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

// Matches the server-side 'max:5120' (5MB) rule on these fields. A phone camera photo
// routinely comes out well over this — so instead of just rejecting it, compress it
// down first and only fall back to an error if it genuinely can't be gotten under the cap.
const MAX_DOC_UPLOAD_BYTES = 5 * 1024 * 1024;

/** Re-encodes an oversized image as JPEG, stepping quality down and then dimensions down until it fits, or gives up after a few tries. */
function compressImageFile(file, maxBytes) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = async () => {
            URL.revokeObjectURL(url);
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            let w = img.naturalWidth, h = img.naturalHeight, quality = 0.85;

            const encode = (width, height, q) => new Promise((res) => {
                canvas.width = width;
                canvas.height = height;
                ctx.clearRect(0, 0, width, height);
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob(res, 'image/jpeg', q);
            });

            let blob = await encode(w, h, quality);
            for (let attempt = 0; blob && blob.size > maxBytes && attempt < 8; attempt++) {
                if (quality > 0.4) quality -= 0.15;
                else { w = Math.round(w * 0.8); h = Math.round(h * 0.8); }
                blob = await encode(w, h, quality);
            }

            if (!blob) { reject(new Error('Could not compress image.')); return; }
            resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
        };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Could not read image.')); };
        img.src = url;
    });
}

async function handleDocUpload(key, input) {
    if (!input.files[0]) return;
    let file = input.files[0];
    const errEl = document.getElementById(`${key}Error`);

    if (file.size > MAX_DOC_UPLOAD_BYTES && file.type.startsWith('image/')) {
        try {
            file = await compressImageFile(file, MAX_DOC_UPLOAD_BYTES);
        } catch (e) {
            // Compression failed outright — fall through to the size check below,
            // which will show a clear error rather than silently keeping the oversized original.
        }
    }

    if (file.size > MAX_DOC_UPLOAD_BYTES) {
        if (errEl) { errEl.textContent = 'This file is too large (max 5MB) — please choose a smaller one.'; errEl.style.display = 'block'; }
        input.value = '';
        return;
    }

    docBlobs[key] = file;
    if (docUrls[key]) URL.revokeObjectURL(docUrls[key]);
    docUrls[key] = URL.createObjectURL(file);
    const isPdf = file.type === 'application/pdf';
    const idleEl   = document.getElementById(`${key}Idle`);
    const prevEl   = document.getElementById(`${key}Preview`);
    const imgEl    = document.getElementById(`${key}Img`);
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
// Rider flow is entirely different (License+Selfie=panel-2 is step 1, Vehicle=panel-7 is step 2,
// then personal/contact/address/account follow in their normal relative order) and is fully
// self-contained in register-rider.blade.php's own inline script, which wraps nextStep/prevStep/
// setStep below — this file only needs to supply the correct fallback for panels 3-6.
const idSelfieStep = isGoogleForm ? 1 : 2;
const contactStep  = isGoogleForm ? 3 : 4;
const accountStep  = isGoogleForm ? 5 : 6;

function indicatorStepFor(panelStep) {
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
            // usernameAvailable === null (check still pending, or its fetch never resolved —
            // this DB connection can take several seconds per round trip) is deliberately NOT
            // blocking: a stale live check used to strand the user here indefinitely with no
            // way forward. Uniqueness is still fully enforced server-side at final submit.
            if (!uEl.value.trim()) { showError(uEl, 'Username is required.'); valid = false; }
            else if (uEl.value.trim().length < 8) { showError(uEl, 'Username must be at least 8 characters.'); valid = false; }
            else if (!/^[a-zA-Z0-9_-]+$/.test(uEl.value.trim())) { showError(uEl, 'Only letters, numbers, underscores and dashes.'); valid = false; }
            else if (usernameAvailable === false) { showError(uEl, 'Username is already taken.'); valid = false; }
        }
        const businessName = document.getElementById('business_name');
        if (businessName) {
            if (!businessName.value.trim()) { showError(businessName, 'Business name is required.'); valid = false; }
            else if (businessNameAvailable === false) { showError(businessName, 'Business name is already registered.'); valid = false; }
        }
        if ((isSeller || isLogistics) && !businessPermitBlob) {
            document.getElementById('businessPermitError').style.display = 'block';
            valid = false;
        } else if (isSeller || isLogistics) {
            document.getElementById('businessPermitError').style.display = 'none';
        }
        // password confirmation
        const pw = document.getElementById('password');
        const pc = document.getElementById('password_confirmation');
        if (pw && pc && pc.value !== pw.value) { showError(pc, 'Passwords do not match.'); valid = false; }
    }

    if (!valid) { const first = panel.querySelector('.error'); first?.scrollIntoView({ behavior: 'smooth', block: 'center' }); first?.focus(); }
    return valid;
}

// ── Step switching ──
// Rider panels 2 (License+Selfie) and 7 (Vehicle) have their own validation/transition
// logic in register-rider.blade.php's inline script, which intercepts those steps before
// ever calling into getPrevStep/nextStep below — so no rider-specific branching is needed
// here for panels 3-6, which just move forward/back in their normal relative order.
function getPrevStep(current) {
    if (current === idSelfieStep && isSeller) return isGoogleForm ? 0 : 1;
    if (current === idSelfieStep) return idSelfieStep;
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
                    const errMsg = data.errors ? Object.values(data.errors).map(v => Array.isArray(v) ? v[0] : v).join('\n') : (data.message ?? 'Something went wrong.');
                    showRegisterErrorModal(errMsg);
                }
            })
            .catch(() => { btn.disabled = false; btn.textContent = 'Submit Registration'; showRegisterErrorModal('Network error. Please try again.'); });
    });
}
