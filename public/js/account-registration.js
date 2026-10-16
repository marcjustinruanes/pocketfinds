(() => {
    const form = document.getElementById('registrationForm');
    if (!form) return;
    const email = form.elements.namedItem('email');
    email.addEventListener('input', () => {
        email.setCustomValidity(email.value && !/^[^@\s]+@[^@\s.]+(?:\.[^@\s.]+)+$/.test(email.value.trim())
            ? 'Enter a valid email address with a complete domain.' : '');
    });
    const birthday = form.querySelector('[name=birthday]');
    birthday?.addEventListener('input', () => {
        birthday.setCustomValidity('');
        const today = new Date();
        const date = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
        if (birthday.value > date) birthday.setCustomValidity('Enter a valid date of birth; future dates are not allowed.');
        else if (birthday.value && birthday.value > birthday.max) birthday.setCustomValidity('You must be at least 18 years old.');
    });
    for (const name of ['given_names', 'last_name']) {
        const field = form.elements.namedItem(name);
        field.removeAttribute('maxlength');
        field.addEventListener('input', () => field.setCustomValidity(field.value.length > 50 ? 'Names may have at most 50 characters.' : ''));
    }
    form.addEventListener('invalid', event => {
        event.preventDefault();
        const field = event.target;
        let error = field.closest('.auth-field')?.querySelector('[data-client-error]');
        if (!error) {
            error = document.createElement('span'); error.className = 'field-error';
            error.dataset.clientError = field.name; error.setAttribute('role', 'alert');
            field.closest('.auth-field')?.append(error);
        }
        error.textContent = field.name === 'terms' ? 'Accept the Terms and Privacy Policy to continue.' : field.validationMessage;
    }, true);
    form.addEventListener('input', event => {
        if (event.target.validity.valid) event.target.closest('.auth-field')?.querySelector('[data-client-error]')?.remove();
    });
    let submitting = false;
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        submitting = true;
        document.getElementById('registerSubmit').disabled = true;
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        document.getElementById('registerSubmit').disabled = false;
    });
    for (const input of form.querySelectorAll('input[type=file]')) {
        input.addEventListener('change', () => {
            input.setCustomValidity(input.files[0]?.size > 5 * 1024 * 1024 ? 'The file must be 5 MB or smaller.' : '');
            input.reportValidity();
        });
    }
    const sellerType = document.getElementById('seller_type');
    function documents() {
        for (const field of ['dti_file', 'sec_file', 'bir_file']) {
            const input = document.getElementById(field);
            const required = field === 'dti_file' ? sellerType.value === 'sole_proprietorship'
                : field === 'sec_file' ? sellerType.value === 'corporation'
                : ['sole_proprietorship', 'corporation'].includes(sellerType.value);
            input.required = required;
            input.disabled = !required;
            input.closest('[data-document]').hidden = !required;
        }
    }
    if (sellerType) { sellerType.addEventListener('change', documents); documents(); }
    const region = document.getElementById('region');
    if (!region) return;
    const province = document.getElementById('province');
    const city = document.getElementById('municipality');
    const barangay = document.getElementById('barangay');
    const service = document.getElementById('service_areas');
    const api = '/locations';
    const sequence = new Map();
    function reset(select, message) {
        sequence.set(select, (sequence.get(select) || 0) + 1);
        select.replaceChildren(new Option(message, ''));
        select.disabled = true;
    }
    async function load(select, path, placeholder) {
        reset(select, 'Loading…');
        const version = sequence.get(select);
        try {
            const response = await fetch(`${api}/${path}/`);
            if (!response.ok) throw new Error('Location service unavailable');
            const rows = await response.json();
            if (sequence.get(select) !== version) return null;
            select.replaceChildren(new Option(placeholder, ''));
            for (const row of rows.sort((a, b) => a.name.localeCompare(b.name))) {
                const option = new Option(row.name, row.name);
                option.dataset.code = row.code;
                select.append(option);
            }
            select.disabled = false;
            if (select.dataset.initial && rows.some(row => row.name === select.dataset.initial)) {
                select.value = select.dataset.initial;
                delete select.dataset.initial;
                select.dispatchEvent(new Event('change'));
            }
            return rows;
        } catch {
            if (sequence.get(select) === version) reset(select, 'Could not load locations. Please refresh to retry.');
            return null;
        }
    }
    function code(select) { return select.selectedOptions[0]?.dataset.code; }
    region.addEventListener('change', async () => {
        reset(city, 'Select province first'); reset(barangay, 'Select city first');
        const regionCode = code(region);
        if (!regionCode) return;
        const rows = await load(province, `regions/${regionCode}/provinces`, 'Select province');
        if (code(region) !== regionCode) return;
        if (rows?.length === 0) {
            province.replaceChildren(new Option('Select province', ''), new Option('Metro Manila', 'Metro Manila'));
            province.options[1].dataset.region = regionCode;
            province.disabled = false;
        }
    });
    province.addEventListener('change', () => {
        reset(barangay, 'Select city first');
        const option = province.selectedOptions[0];
        if (!option?.value) { reset(city, 'Select province first'); return; }
        load(city, option.dataset.region ? `regions/${option.dataset.region}/cities-municipalities`
            : `provinces/${code(province)}/cities-municipalities`, 'Select city / municipality');
    });
    city.addEventListener('change', () => {
        if (code(city)) load(barangay, `cities-municipalities/${code(city)}/barangays`, 'Select barangay');
        else reset(barangay, 'Select city first');
    });
    load(region, 'regions', 'Select region').then(rows => {
        if (service && rows) {
            service.replaceChildren(...rows.map(row => new Option(row.name, row.name)));
        }
    });
})();
