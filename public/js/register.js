const PSGC = 'https://psgc.gitlab.io/api';

// ── Age auto-generate from birthday ──
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
        if (!v) return 'This field is required.';
        if (!/@gmail\.com$/i.test(v)) return 'Email must be a Gmail address (@gmail.com).';
        return null;
    },
    contact_no: (v) => {
        if (!v) return 'This field is required.';
        if (!/^09\d{9}$/.test(v)) return 'Contact number must be 11 digits starting with 09.';
        return null;
    },
};

function showError(el, msg) {
    el.classList.add('error');
    // remove existing error first
    const existing = el.parentElement.querySelector('.field-error');
    if (existing) existing.remove();
    const err = document.createElement('span');
    err.className = 'field-error';
    err.textContent = msg;
    // insert after input-wrap if inside one, else after el
    const wrap = el.closest('.auth-input-wrap') ?? el;
    wrap.after(err);
}

function clearError(el) {
    el.classList.remove('error');
    const existing = el.parentElement.querySelector('.field-error');
    if (existing) existing.remove();
}

// ── Validation ──
function validateStep(step) {
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

// ── Step switching ──
function setStep(current, target) {
    document.getElementById(`panel-${current}`).classList.remove('active');
    document.getElementById(`panel-${target}`).classList.add('active');

    document.querySelectorAll('.step-item').forEach(item => {
        const s = parseInt(item.dataset.step);
        item.classList.remove('active', 'done');
        if (s === target) item.classList.add('active');
        if (s < target) item.classList.add('done');
    });

    // Scroll form panel to top on step change
    document.querySelector('.auth-form-panel').scrollTop = 0;
}

function nextStep(current) {
    if (!validateStep(current)) return;
    setStep(current, current + 1);
}

function prevStep(current) {
    setStep(current, current - 1);
}

// ── Submit → POST to backend ──
document.getElementById('buyerForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validateStep(4)) return;

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
