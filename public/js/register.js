const PSGC = 'https://psgc.gitlab.io/api';
const isSeller = document.querySelector('input[name="account_type"]')?.value === 'seller';

// ── Seller setup: show category step, update step numbers ──
if (isSeller) {
    // Show category step indicator
    document.querySelector('.seller-only').style.display = '';
    // Update step circles & eyebrows to reflect 5-step flow
    document.getElementById('personalStepCircle').textContent = '2';
    document.getElementById('contactStepCircle').textContent = '3';
    document.getElementById('addressStepCircle').textContent = '4';
    document.getElementById('accountStepCircle').textContent = '5';
    document.getElementById('personalEyebrow').textContent = 'Step 2 of 5';
    document.getElementById('contactEyebrow').textContent = 'Step 3 of 5';
    document.getElementById('addressEyebrow').textContent = 'Step 4 of 5';
    document.getElementById('accountStepLabel').textContent = 'Step 5 of 5';

    // Start on step 1 (category) for sellers
    document.getElementById('panel-2').classList.remove('active');
    document.getElementById('panel-1').classList.add('active');
    document.querySelector('[data-step="2"]').classList.remove('active');
    document.querySelector('[data-step="1"]').classList.add('active');

    // Category icon map keyed by lowercase name fragments
    const categoryIcons = {
        pet:          `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="4" r="2"/><circle cx="18" cy="8" r="2"/><circle cx="20" cy="16" r="2"/><path d="M9 10a5 5 0 015 5v3.5a3.5 3.5 0 01-6.84 1.045Q6.52 17.48 4.46 16.84A3.5 3.5 0 018 10z"/></svg>`,
        drink:        `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8l1 7H7L8 2z"/><path d="M7 9c0 5 2 8 5 8s5-3 5-8"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/></svg>`,
        automotive:   `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>`,
        garden:       `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22V12"/><path d="M5 12C5 7 8 4 12 4c4 0 7 3 7 8"/><path d="M5 12c0-3 2-5 7-5"/></svg>`,
        music:        `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>`,
        art:          `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32"/></svg>`,
        craft:        `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32"/></svg>`,
        electronics:  `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>`,
        men:          `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="2"/><path d="M9 9h6l1 5h-2l-1 6h-2l-1-6H8l1-5z"/></svg>`,
        women:        `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="2"/><path d="M9 9h6l3 11H6l3-11z"/><line x1="12" y1="20" x2="12" y2="23"/></svg>`,
        kid:          `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4.5" r="2.5"/><path d="M9 10c0 0-3 .5-3 3.5h3v6h6v-6h3c0-3-3-3.5-3-3.5"/><path d="M9 10h6"/><circle cx="7" cy="14" r="1"/><circle cx="17" cy="14" r="1"/></svg>`,
        fashion:      `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.57a2 2 0 00-1.34-2.23z"/></svg>`,
        clothing:     `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.57a2 2 0 00-1.34-2.23z"/></svg>`,
        food:         `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>`,
        beverage:     `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8l1 7H7L8 2z"/><path d="M7 9c0 5 2 8 5 8s5-3 5-8"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/></svg>`,
        health:       `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>`,
        beauty:       `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>`,
        home:         `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>`,
        living:       `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>`,
        sports:       `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l4.24 4.24M14.83 9.17l4.24-4.24M14.83 14.83l4.24 4.24M9.17 14.83l-4.24 4.24"/><circle cx="12" cy="12" r="4"/></svg>`,
        outdoors:     `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l4-8 4 5 3-3 4 6H3z"/><circle cx="18" cy="5" r="2"/></svg>`,
        toys:         `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 10c-.83 0-1.5-.67-1.5-1.5v-5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5z"/><path d="M20.5 10H19V8.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/><path d="M9.5 14.5c.83 0 1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5S8 21.83 8 21v-5c0-.83.67-1.5 1.5-1.5z"/><path d="M3.5 14H5v1.5c0 .83-.67 1.5-1.5 1.5S2 16.33 2 15.5 2.67 14 3.5 14z"/><rect x="2" y="10" width="20" height="4" rx="2"/></svg>`,
        games:        `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4M8 10v4M15 11h.01M17 13h.01"/></svg>`,
        books:        `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>`,
        stationery:   `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><path d="M5 12l7-7 7 7"/></svg>`,
        default:      `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="7" height="7"/><rect x="15" y="3" width="7" height="7"/><rect x="15" y="14" width="7" height="7"/><rect x="2" y="14" width="7" height="7"/></svg>`,
    };

    function getCategoryIcon(name) {
        const lower = name.toLowerCase();
        for (const [key, svg] of Object.entries(categoryIcons)) {
            if (key !== 'default' && lower.includes(key)) return svg;
        }
        return categoryIcons.default;
    }

    // Load categories as cards
    fetch('/register/categories')
        .then(r => r.json())
        .then(data => {
            const grid = document.getElementById('categoryGrid');
            grid.innerHTML = '';
            data.forEach(cat => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'category-box';
                btn.dataset.categoryId = cat.id;
                btn.innerHTML = `
                    <span class="category-box-icon">${getCategoryIcon(cat.name)}</span>
                    <span class="category-box-name">${cat.name}</span>
                `;
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.category-box').forEach(c => c.classList.remove('selected'));
                    btn.classList.add('selected');
                    document.getElementById('category_id_input').value = cat.id;
                    document.getElementById('categoryNextBtn').disabled = false;
                });
                grid.appendChild(btn);
            });
        })
        .catch(() => {
            document.getElementById('categoryGrid').innerHTML = '<p style="color:var(--auth-danger);font-size:13px">Failed to load categories. Please refresh.</p>';
        });
}

// ── Email OTP ──
let emailVerified = false;
let otpCountdown  = null;

function sendOtp() {
    const emailEl = document.getElementById('email');
    const email   = emailEl.value.trim();

    if (!email || !/@gmail\.com$/i.test(email)) {
        showError(emailEl, 'Enter a valid Gmail address first.');
        return;
    }
    clearError(emailEl);

    const btn = document.getElementById('sendOtpBtn');
    btn.disabled    = true;
    btn.textContent = 'Sending…';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                   || document.querySelector('input[name="_token"]').value;

    fetch('/register/send-otp', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body:    JSON.stringify({ email }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.textContent  = 'Sent ✓';
            document.getElementById('emailHint').textContent = 'Code sent! Check your inbox.';
            document.getElementById('otpField').style.display = '';
            document.getElementById('otp_code').value = '';
            document.getElementById('otpHint').style.color = '';
            document.getElementById('otpHint').innerHTML = 'Enter the code sent to your email. <button type="button" class="btn-inline-link" id="resendOtpBtn" onclick="resendOtp()" disabled>Resend</button>';
            startOtpCountdown();
        } else {
            btn.disabled    = false;
            btn.textContent = 'Send Code';
            showError(emailEl, data.message ?? 'Could not send code.');
        }
    })
    .catch(() => {
        btn.disabled    = false;
        btn.textContent = 'Send Code';
        showError(emailEl, 'Network error. Try again.');
    });
}

function resendOtp() {
    const btn = document.getElementById('sendOtpBtn');
    btn.disabled    = false;
    btn.textContent = 'Send Code';
    sendOtp();
}

function startOtpCountdown() {
    let secs = 60;
    clearInterval(otpCountdown);
    otpCountdown = setInterval(() => {
        secs--;
        const resendBtn = document.getElementById('resendOtpBtn');
        if (!resendBtn) { clearInterval(otpCountdown); return; }
        if (secs <= 0) {
            clearInterval(otpCountdown);
            resendBtn.disabled    = false;
            resendBtn.textContent = 'Resend';
        } else {
            resendBtn.disabled    = true;
            resendBtn.textContent = `Resend (${secs}s)`;
        }
    }, 1000);
}

function verifyOtp() {
    const email    = document.getElementById('email').value.trim();
    const otp      = document.getElementById('otp_code').value.trim();
    const hintEl   = document.getElementById('otpHint');
    const verifyBtn = document.getElementById('verifyOtpBtn');

    if (otp.length !== 6) {
        hintEl.style.color = 'var(--auth-danger)';
        hintEl.textContent = 'Please enter the full 6-digit code.';
        return;
    }

    verifyBtn.disabled    = true;
    verifyBtn.textContent = 'Verifying…';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                   || document.querySelector('input[name="_token"]').value;

    fetch('/register/verify-otp', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body:    JSON.stringify({ email, otp }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            emailVerified = true;
            clearInterval(otpCountdown);
            document.getElementById('otpField').style.display     = 'none';
            document.getElementById('verifiedBadge').style.display = '';
            document.getElementById('sendOtpBtn').style.display    = 'none';
            document.getElementById('emailHint').textContent       = '';
        } else {
            verifyBtn.disabled    = false;
            verifyBtn.textContent = 'Verify';
            hintEl.style.color    = 'var(--auth-danger)';
            hintEl.textContent    = data.message ?? 'Invalid or expired code.';
        }
    })
    .catch(() => {
        verifyBtn.disabled    = false;
        verifyBtn.textContent = 'Verify';
        hintEl.style.color    = 'var(--auth-danger)';
        hintEl.textContent    = 'Network error. Try again.';
    });
}

// Reset OTP state on any email change
let lastEmailValue = '';
document.getElementById('email')?.addEventListener('input', function () {
    if (this.value === lastEmailValue) return;
    lastEmailValue = this.value;

    // Reset everything as soon as email changes
    emailVerified = false;
    clearInterval(otpCountdown);
    document.getElementById('otpField').style.display      = 'none';
    document.getElementById('verifiedBadge').style.display = 'none';
    document.getElementById('emailHint').textContent       = '';
    const btn = document.getElementById('sendOtpBtn');
    btn.disabled    = false;
    btn.textContent = 'Send Code';
    btn.style.display = '';
});

// ── Age auto-generate from birthday ──
// For non-sellers, ensure step 2 indicator is active on load
if (!isSeller) {
    document.getElementById('personalStepItem').classList.add('active');
}

document.getElementById('birthday').addEventListener('change', function () {
    const dob = new Date(this.value);
    if (isNaN(dob)) return;
    const age = Math.floor((Date.now() - dob) / 31557600000);
    document.getElementById('age').value = age >= 0 ? age : '';
});

// ── File upload label update ──
document.getElementById('id_file').addEventListener('change', function () {
    const file = this.files[0];
    const nameEl = document.getElementById('uploadName');
    const textEl = document.querySelector('.upload-text');
    if (file) {
        nameEl.textContent = '✓ ' + file.name;
        nameEl.style.display = 'block';
        textEl.style.display = 'none';
    } else {
        nameEl.style.display = 'none';
        textEl.style.display = 'block';
    }
});

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
    [...items]
        .sort((a, b) => a[labelKey].localeCompare(b[labelKey]))
        .forEach(item => {
            const o = document.createElement('option');
            o.value = item[valueKey];
            o.textContent = item[labelKey];
            sel.appendChild(o);
        });
    sel.disabled = false;
}

// Load provinces on page load
fetchJSON(`${PSGC}/provinces/`)
    .then(data => populateSelect(
        document.getElementById('province'), data, 'code', 'name', 'Select province'
    ))
    .catch(() => {
        const sel = document.getElementById('province');
        sel.innerHTML = '<option value="" disabled selected>Failed to load provinces</option>';
        sel.disabled = false;
    });

document.getElementById('province').addEventListener('change', function () {
    const munSel = document.getElementById('municipality');
    const barSel = document.getElementById('barangay');

    setLoading(munSel, 'Loading cities / municipalities…');
    setLoading(barSel, 'Select municipality first');

    fetchJSON(`${PSGC}/provinces/${this.value}/cities-municipalities/`)
        .then(data => populateSelect(munSel, data, 'code', 'name', 'Select city / municipality'))
        .catch(() => setLoading(munSel, 'Failed to load'));
});

document.getElementById('municipality').addEventListener('change', function () {
    const barSel = document.getElementById('barangay');
    setLoading(barSel, 'Loading barangays…');

    fetchJSON(`${PSGC}/cities-municipalities/${this.value}/barangays/`)
        .then(data => populateSelect(barSel, data, 'code', 'name', 'Select barangay'))
        .catch(() => setLoading(barSel, 'Failed to load'));
});

// ── Field rules ──
const rules = {
    middle_initial: (v) => {
        if (!v) return null; // optional
        if (!/^[A-Za-z]$/.test(v)) return 'Middle initial must be 1 letter only, no numbers.';
        return null;
    },
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
};

function showError(el, msg) {
    el.classList.add('error');
    if (el.id === 'email') {
        const hint = document.getElementById('emailHint');
        hint.style.color = 'var(--auth-danger)';
        hint.textContent = msg;
        return;
    }
    if (el.id === 'contact_no') {
        const hint = document.getElementById('contactHint');
        hint.style.color = 'var(--auth-danger)';
        hint.textContent = msg;
        return;
    }
    const existing = el.parentElement.querySelector('.field-error');
    if (existing) existing.remove();
    const err = document.createElement('span');
    err.className = 'field-error';
    err.textContent = msg;
    const wrap = el.closest('.auth-input-wrap') ?? el;
    wrap.after(err);
}

function clearError(el) {
    el.classList.remove('error');
    if (el.id === 'email') {
        const hint = document.getElementById('emailHint');
        hint.style.color = '';
        hint.textContent = '';
        return;
    }
    if (el.id === 'contact_no') {
        const hint = document.getElementById('contactHint');
        hint.style.color = '';
        hint.textContent = '';
        return;
    }
    const existing = el.parentElement.querySelector('.field-error');
    if (existing) existing.remove();
}

// ── Validation ──
function validateStep(step) {
    // Category step: validated by card selection, not form fields
    if (step === 1) {
        const val = document.getElementById('category_id_input').value;
        if (!val) {
            document.getElementById('categoryGrid').closest('.category-scroll-wrap').style.outline = '2px solid var(--auth-danger)';
            return false;
        }
        document.getElementById('categoryGrid').closest('.category-scroll-wrap').style.outline = '';
        return true;
    }

    // Contact step: require email verification
    if (step === 3 && !emailVerified) {
        const emailEl = document.getElementById('email');
        showError(emailEl, 'Please verify your email address first.');
        return false;
    }

    const panel = document.getElementById(`panel-${step}`);
    let valid = true;

    panel.querySelectorAll('input, select').forEach(el => {
        clearError(el);
        const id = el.id;
        const val = el.value.trim();

        // custom rules first
        if (rules[id]) {
            const msg = rules[id](val);
            if (msg) { showError(el, msg); valid = false; return; }
        }

        // required check
        if (el.hasAttribute('required') && !val) {
            showError(el, 'This field is required.');
            valid = false;
        }
    });

    if (!valid) {
        const first = panel.querySelector('.error');
        first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        first?.focus();
    }
    return valid;
}

// ── Step switching (seller-aware) ──
function getNextStep(current) {
    return current + 1;
}

function getPrevStep(current) {
    if (current === 2 && isSeller) return 1;
    if (current === 2) return 2; // non-sellers can't go before step 2
    return current - 1;
}

function setStep(current, target) {
    document.getElementById(`panel-${current}`).classList.remove('active');
    document.getElementById(`panel-${target}`).classList.add('active');

    document.querySelectorAll('.step-item').forEach(item => {
        const s = parseInt(item.dataset.step);
        item.classList.remove('active', 'done');
        if (s === target) item.classList.add('active');
        if (s < target) item.classList.add('done');
    });

    document.querySelector('.auth-form-panel').scrollTop = 0;
}

function nextStep(current) {
    if (!validateStep(current)) return;
    setStep(current, getNextStep(current));
}

function prevStep(current) {
    setStep(current, getPrevStep(current));
}

// ── Submit → POST to backend ──
document.getElementById('buyerForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validateStep(5)) return;

    const form = this;
    const btn = form.querySelector('.btn-submit');
    btn.disabled = true;
    btn.textContent = 'Submitting…';

    fetch('/register', {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            form.style.display = 'none';
            document.getElementById('stepIndicator').style.display = 'none';
            document.querySelector('.auth-back').style.display = 'none';
            document.getElementById('signinLink').style.display = 'none';
            document.getElementById('successScreen').classList.add('active');
        } else {
            btn.disabled = false;
            btn.textContent = 'Submit Registration';
            alert(data.message ?? 'Something went wrong. Please try again.');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = 'Submit Registration';
        alert('Network error. Please try again.');
    });
});
