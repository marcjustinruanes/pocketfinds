(() => {
    // Early feedback only. Laravel remains authoritative for application/identity checks.
    document.querySelectorAll('[data-personnel-address]').forEach((root, formIndex) => {
        const form = root.closest('form');
        const name = value => !value || ([...value].length >= 2 && [...value].length <= 50 && /^[\p{L}-]+(\s[\p{L}-]+)*$/u.test(value));
        const fields = {
            given_names: value => name(value) ? '' : 'Use 2 to 50 Unicode letters, hyphens, and single spaces between words.',
            last_name: value => name(value) ? '' : 'Use 2 to 50 Unicode letters, hyphens, and single spaces between words.',
            middle_name: value => name(value) ? '' : 'Use 2 to 50 Unicode letters, hyphens, and single spaces between words.',
            license_number: value => !value || /^[A-Z]\d{2}-\d{2}-\d{6}$/u.test(value) ? '' : 'Use the licence format N01-23-456789, with an uppercase letter.',
            password: value => !value || ([...value].length >= 12 && [...value].length <= 128 && /\p{Ll}/u.test(value) && /\p{Lu}/u.test(value) && /\p{N}/u.test(value) && /[\p{Z}\p{S}\p{P}]/u.test(value)) ? '' : 'Use 12 to 128 characters with uppercase, lowercase, a number and a symbol.',
            password_confirmation: value => !value || value === form.querySelector('[name="password"]')?.value ? '' : 'Passwords must match.',
        };
        const validators = [];
        for (const [field, validate] of Object.entries(fields)) {
            const input = form.querySelector(`[name="${field}"]`);
            if (!input) continue;
            const message = document.createElement('small'); message.id = `personnel-${formIndex}-${field}-feedback`;
            message.setAttribute('aria-live', 'polite');input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), message.id].filter(Boolean).join(' '));
            input.after(message);
            const check = () => {const error = validate(input.value);input.setCustomValidity(error);message.textContent = error;};
            input.addEventListener('input', check);input.addEventListener('blur', check);validators.push(check);
            if (field === 'password_confirmation') form.querySelector('[name="password"]')?.addEventListener('input', check);
        }
        validators.forEach(check => check());
    });
    const requests = new Map();
    const load = (level, code = '') => {
        const url = `/logistics/hub-locations/${level}${code ? '/' + encodeURIComponent(code) : ''}`;
        if (!requests.has(url)) {
            requests.set(url, fetch(url, {
                credentials: 'same-origin', headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(12000),
            }).then(async response => {
                if (!response.ok) throw new Error('Location lookup failed.');
                const rows = await response.json();
                if (!Array.isArray(rows) || rows.some(row => typeof row.name !== 'string' || !/^\d{9}$/.test(row.code))) {
                    throw new Error('Invalid location response.');
                }
                return rows;
            }).catch(error => { requests.delete(url); throw error; }));
        }
        return requests.get(url);
    };
    const match = (rows, name) => rows.find(row => row.name.toLowerCase() === (name || '').trim().toLowerCase());
    document.querySelectorAll('[data-personnel-address]').forEach(root => {
        const form = root.closest('form');
        const hub = root.querySelector('[data-personnel-hub]');
        const barangay = root.querySelector('[data-personnel-barangay]');
        const status = root.querySelector('[data-personnel-address-status]');
        const retry = root.querySelector('[data-personnel-address-retry]');
        let revision = 0;
        const start = async () => {
            const ticket = ++revision;
            barangay.disabled = true;
            barangay.replaceChildren(new Option(hub.value ? 'Loading barangays...' : 'Select a hub first', ''));
            retry.hidden = true;
            if (!hub.value) { status.textContent = 'Select the assigned hub to load its barangays.'; return; }
            const selected = hub.selectedOptions[0];
            status.textContent = 'Loading barangays for the assigned hub...';
            try {
                const province = match(await load('provinces'), selected.dataset.province);
                if (!province) throw new Error('Province unavailable.');
                const city = match(await load('municipalities', province.code), selected.dataset.municipality);
                if (!city) throw new Error('Municipality unavailable.');
                const rows = await load('barangays', city.code);
                if (ticket !== revision) return;
                barangay.replaceChildren(new Option('Select barangay', ''));
                [...rows].sort((a, b) => a.name.localeCompare(b.name)).forEach(row => {
                    barangay.add(new Option(row.name, row.name, false, row.name.toLowerCase() === barangay.dataset.current.toLowerCase()));
                });
                barangay.disabled = false;
                status.textContent = `Select a barangay in ${selected.dataset.municipality}, ${selected.dataset.province}.`;
            } catch {
                if (ticket !== revision) return;
                barangay.replaceChildren(new Option('Address unavailable', ''));
                status.textContent = 'Unable to load barangays. Retry before saving.';
                retry.hidden = false;
            }
        };
        hub.addEventListener('change', () => { barangay.dataset.current = ''; start(); });
        retry.addEventListener('click', start);
        form.addEventListener('submit', event => {
            if (barangay.disabled || !barangay.value) {
                event.preventDefault();
                status.textContent = 'Load and select a valid barangay before saving.';
            }
        });
        start();
    });
})();
