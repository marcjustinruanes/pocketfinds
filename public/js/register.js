const PSGC = 'https://psgc.gitlab.io/api';

const form = document.getElementById('registrationForm');

function byId(id) {
    return document.getElementById(id);
}

function setFieldValue(id, value, overwrite = false) {
    const field = byId(id);
    if (!field || !value) return;
    if (!overwrite && field.value.trim()) return;
    field.value = value;
    field.dispatchEvent(new Event('change', { bubbles: true }));
}

function calculateAge(value) {
    const dob = new Date(value);
    if (Number.isNaN(dob.getTime())) return '';

    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age -= 1;
    }

    return age >= 0 ? String(age) : '';
}

byId('birthday')?.addEventListener('change', function () {
    byId('age').value = calculateAge(this.value);
});

byId('id_file')?.addEventListener('change', function () {
    const file = this.files[0];
    const nameEl = byId('uploadName');
    const textEl = document.querySelector('.upload-text');

    if (file) {
        nameEl.textContent = 'Selected: ' + file.name;
        nameEl.style.display = 'block';
        textEl.style.display = 'none';
        runIdAutofill(file);
    } else {
        nameEl.style.display = 'none';
        textEl.style.display = 'block';
    }
});

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
            const option = document.createElement('option');
            option.value = item[valueKey];
            option.textContent = item[labelKey];
            sel.appendChild(option);
        });
    sel.disabled = false;
}

fetchJSON(`${PSGC}/provinces/`)
    .then(data => populateSelect(byId('province'), data, 'code', 'name', 'Select province'))
    .catch(() => {
        const sel = byId('province');
        sel.innerHTML = '<option value="" disabled selected>Failed to load provinces</option>';
        sel.disabled = false;
    });

byId('province')?.addEventListener('change', function () {
    const munSel = byId('municipality');
    const barSel = byId('barangay');

    setLoading(munSel, 'Loading cities / municipalities...');
    setLoading(barSel, 'Select municipality first');

    fetchJSON(`${PSGC}/provinces/${this.value}/cities-municipalities/`)
        .then(data => populateSelect(munSel, data, 'code', 'name', 'Select city / municipality'))
        .catch(() => setLoading(munSel, 'Failed to load'));
});

byId('municipality')?.addEventListener('change', function () {
    const barSel = byId('barangay');
    setLoading(barSel, 'Loading barangays...');

    fetchJSON(`${PSGC}/cities-municipalities/${this.value}/barangays/`)
        .then(data => populateSelect(barSel, data, 'code', 'name', 'Select barangay'))
        .catch(() => setLoading(barSel, 'Failed to load'));
});

const rules = {
    middle_initial: (v) => {
        if (!v) return null;
        if (!/^[A-Za-z]$/.test(v)) return 'Middle initial must be 1 letter only.';
        return null;
    },
    email: (v) => {
        if (!v) return 'This field is required.';
        if (!/@gmail\.com$/i.test(v)) return 'Email must be a Gmail address.';
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
    const existing = el.parentElement.querySelector('.field-error');
    if (existing) existing.remove();
}

function validateStep(step) {
    const panel = byId(`panel-${step}`);
    let valid = true;

    panel.querySelectorAll('input, select').forEach(el => {
        if (el.disabled || el.type === 'hidden') return;

        clearError(el);
        const id = el.id;
        const val = el.value.trim();

        if (rules[id]) {
            const msg = rules[id](val);
            if (msg) {
                showError(el, msg);
                valid = false;
                return;
            }
        }

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

function setStep(current, target) {
    byId(`panel-${current}`).classList.remove('active');
    byId(`panel-${target}`).classList.add('active');

    document.querySelectorAll('.step-item').forEach(item => {
        const s = parseInt(item.dataset.step, 10);
        item.classList.remove('active', 'done');
        if (s === target) item.classList.add('active');
        if (s < target) item.classList.add('done');
    });

    document.querySelector('.auth-form-panel').scrollTop = 0;
}

function nextStep(current) {
    if (!validateStep(current)) return;
    setStep(current, current + 1);
}

function prevStep(current) {
    setStep(current, current - 1);
}

window.nextStep = nextStep;
window.prevStep = prevStep;
window.validateStep = validateStep;

async function runIdAutofill(file) {
    const status = byId('idAutofillStatus');
    if (!status) return;

    if (file.type === 'application/pdf') {
        status.textContent = 'PDF uploaded. Automatic reading works best with clear JPG or PNG ID photos.';
        return;
    }

    if (!file.type.startsWith('image/')) {
        status.textContent = 'Automatic reading only supports image uploads.';
        return;
    }

    if (!window.Tesseract) {
        status.textContent = 'ID reader is still loading. You can continue filling the form manually.';
        return;
    }

    status.textContent = 'Reading ID photo...';

    try {
        const result = await window.Tesseract.recognize(file, 'eng');
        const text = result?.data?.text ?? '';
        const parsed = parseIdText(text);

        applyParsedId(parsed);
        status.textContent = parsed.any
            ? 'Some details were filled from the ID. Please review them before submitting.'
            : 'ID uploaded, but no clear details were detected. Please fill the fields manually.';
    } catch (error) {
        status.textContent = 'Could not read the ID clearly. Please fill the fields manually.';
    }
}

function parseIdText(text) {
    const normalized = text
        .replace(/\r/g, '\n')
        .replace(/[^\S\n]+/g, ' ')
        .trim();

    const lines = normalized
        .split('\n')
        .map(line => line.trim())
        .filter(Boolean);

    const parsed = { any: false };
    const joined = lines.join(' ');

    const birthday = joined.match(/\b(19|20)\d{2}[-/.](0?[1-9]|1[0-2])[-/.](0?[1-9]|[12]\d|3[01])\b/)
        ?? joined.match(/\b(0?[1-9]|1[0-2])[-/.](0?[1-9]|[12]\d|3[01])[-/.]((19|20)\d{2})\b/);

    if (birthday) {
        parsed.birthday = normalizeDate(birthday[0]);
        parsed.any = true;
    }

    const sex = joined.match(/\b(FEMALE|MALE|F|M)\b/i);
    if (sex) {
        const value = sex[1].toLowerCase();
        parsed.sex = value.startsWith('f') ? 'female' : 'male';
        parsed.any = true;
    }

    const namedLine = findAfterLabel(lines, ['name', 'full name']);
    const fallbackName = namedLine || lines.find(line => isLikelyName(line));
    if (fallbackName) {
        Object.assign(parsed, parseName(fallbackName));
        parsed.any = true;
    }

    const addressLine = findAfterLabel(lines, ['address', 'residence', 'home address']);
    if (addressLine) {
        parsed.street = addressLine;
        parsed.any = true;
    }

    return parsed;
}

function findAfterLabel(lines, labels) {
    for (let index = 0; index < lines.length; index += 1) {
        const line = lines[index];
        const lower = line.toLowerCase();

        for (const label of labels) {
            if (lower.startsWith(label + ':')) return line.slice(label.length + 1).trim();
            if (lower === label) return lines[index + 1] ?? '';
        }
    }

    return '';
}

function isLikelyName(line) {
    if (/\d/.test(line)) return false;
    if (line.length < 5 || line.length > 60) return false;
    if (/(republic|philippines|license|identification|address|birth|date|sex|signature)/i.test(line)) return false;
    return /^[A-Z ,.'-]+$/i.test(line) && line.trim().split(/\s+/).length >= 2;
}

function parseName(value) {
    const clean = value.replace(/^name\s*:/i, '').replace(/\s+/g, ' ').trim();
    const tokens = clean.includes(',')
        ? clean.split(',').map(part => part.trim()).filter(Boolean).reverse().join(' ').split(' ')
        : clean.split(' ');

    const firstName = tokens.shift() ?? '';
    const lastName = tokens.pop() ?? '';
    const middle = tokens.length ? tokens[0].charAt(0).toUpperCase() : '';

    return {
        first_name: titleCase(firstName),
        last_name: titleCase(lastName),
        middle_initial: middle,
    };
}

function titleCase(value) {
    return value.toLowerCase().replace(/\b[a-z]/g, char => char.toUpperCase());
}

function normalizeDate(value) {
    const parts = value.replace(/[/.]/g, '-').split('-').map(part => part.padStart(2, '0'));
    if (parts[0].length === 4) return `${parts[0]}-${parts[1]}-${parts[2]}`;
    return `${parts[2]}-${parts[0]}-${parts[1]}`;
}

function applyParsedId(parsed) {
    setFieldValue('first_name', parsed.first_name);
    setFieldValue('last_name', parsed.last_name);
    setFieldValue('middle_initial', parsed.middle_initial);
    setFieldValue('birthday', parsed.birthday);
    setFieldValue('sex', parsed.sex);
    setFieldValue('street', parsed.street);

    if (parsed.birthday) {
        setFieldValue('age', calculateAge(parsed.birthday), true);
    }
}

form?.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validateStep(4)) return;

    const btn = form.querySelector('.btn-submit');
    btn.disabled = true;
    btn.textContent = 'Submitting...';

    fetch('/register', {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw data;
            return data;
        })
        .then(data => {
            if (!data.success) throw data;

            form.style.display = 'none';
            byId('stepIndicator').style.display = 'none';
            document.querySelector('.auth-back').style.display = 'none';
            byId('signinLink').style.display = 'none';
            byId('successScreen').classList.add('active');
        })
        .catch(data => {
            btn.disabled = false;
            btn.textContent = 'Submit Registration';
            const firstError = data?.errors ? Object.values(data.errors).flat()[0] : null;
            alert(firstError ?? data?.message ?? 'Something went wrong. Please try again.');
        });
});
