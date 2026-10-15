(() => {
    const requests = new Map();
    const load = (level, code = '') => {
        const url = `/logistics/hub-locations/${level}${code ? '/' + encodeURIComponent(code) : ''}`;
        if (!requests.has(url)) {
            requests.set(url, fetch(url, { headers: { Accept: 'application/json' } }).then(async response => {
                if (!response.ok) throw new Error('Address choices are unavailable.');
                const rows = await response.json();
                if (!Array.isArray(rows)) throw new Error('Invalid address choices.');
                return rows;
            }).catch(error => { requests.delete(url); throw error; }));
        }
        return requests.get(url);
    };
    document.querySelectorAll('[data-hub-contact]').forEach(input => {
        const full = input.closest('label').querySelector('[data-hub-contact-full]');
        const sync = () => {
            input.setCustomValidity('');
            input.value = input.value.replace(/[^0-9]/g, '').slice(0, 9);
            full.value = '09' + input.value;
        };
        input.addEventListener('input', sync);
        input.addEventListener('paste', event => {
            const digits = (event.clipboardData?.getData('text') ?? '').replace(/[^0-9]/g, '');
            if (/^09[0-9]{9}$/.test(digits)) {
                event.preventDefault();
                input.value = digits.slice(2);
                sync();
            } else if (digits.length > 9) {
                event.preventDefault();
                input.setCustomValidity('Paste a number starting with 09, or enter the remaining nine digits.');
                input.reportValidity();
            }
        });
        input.closest('form').addEventListener('submit', sync);
        sync();
    });
    document.querySelectorAll('[data-hub-address]').forEach(root => {
        const form = root.closest('form');
        const save = form.querySelector('[data-hub-save]');
        const province = root.querySelector('[data-province-select]');
        const municipality = root.querySelector('[data-municipality-select]');
        const barangay = root.querySelector('[data-barangay-select]');
        const status = root.querySelector('[data-address-status]');
        const retry = root.querySelector('[data-address-retry]');
        const fixed = root.dataset.fixed === '1';
        let revision = 0;
        const reset = (select, message) => { select.replaceChildren(new Option(message, '')); select.disabled = true; };
        const fill = (select, rows, placeholder, code, name) => {
            select.replaceChildren(new Option(placeholder, ''));
            [...rows].sort((a, b) => a.name.localeCompare(b.name)).forEach(row => {
                select.add(new Option(row.name, row.code, false, row.code === code || (!!name && row.name.toLowerCase() === name.toLowerCase())));
            });
            select.disabled = false;
        };
        const ready = () => {
            save.disabled = !province.value || !municipality.value || !barangay.value || barangay.disabled;
            if (fixed) {
                root.querySelector('[data-province-code]').value = province.value;
                root.querySelector('[data-municipality-code]').value = municipality.value;
            }
        };
        const fail = () => { status.textContent = 'Unable to load address choices. Retry before saving.'; retry.hidden = false; save.disabled = true; };
        const cities = async (ticket, restore = false) => {
            reset(municipality, 'Loading municipalities...'); reset(barangay, 'Select a municipality first'); ready();
            if (!province.value) { status.textContent = 'Select a province.'; return; }
            const rows = await load('municipalities', province.value);
            if (ticket !== revision) return;
            fill(municipality, rows, 'Select municipality / city', restore ? root.dataset.oldMunicipality : '', fixed ? root.dataset.municipality : '');
            municipality.disabled = fixed;
            if (fixed && !municipality.value) throw new Error('Existing municipality not found.');
            if (municipality.value) await villages(ticket, restore);
            else status.textContent = 'Select a municipality / city.';
            ready();
        };
        const villages = async (ticket, restore = false) => {
            reset(barangay, 'Loading barangays...'); ready();
            if (!municipality.value) { status.textContent = 'Select a municipality / city.'; return; }
            const rows = await load('barangays', municipality.value);
            if (ticket !== revision) return;
            fill(barangay, rows, 'Select barangay', restore ? root.dataset.oldBarangay : '', restore ? root.dataset.barangay : '');
            status.textContent = 'Select a barangay and complete all required fields.'; ready();
        };
        const start = async () => {
            const ticket = ++revision;
            retry.hidden = true; save.disabled = true;
            reset(province, 'Loading provinces...'); reset(municipality, 'Select a province first'); reset(barangay, 'Select a municipality first');
            status.textContent = 'Loading address choices...';
            try {
                const rows = await load('provinces');
                if (ticket !== revision) return;
                fill(province, rows, 'Select province', root.dataset.oldProvince, fixed ? root.dataset.province : '');
                province.disabled = fixed;
                if (fixed && !province.value) throw new Error('Existing province not found.');
                if (province.value) await cities(ticket, true);
                else status.textContent = 'Select a province.';
                ready();
            } catch { if (ticket === revision) fail(); }
        };
        province.addEventListener('change', async () => { const ticket = ++revision; retry.hidden = true; try { await cities(ticket); } catch { if (ticket === revision) fail(); } });
        municipality.addEventListener('change', async () => { const ticket = ++revision; retry.hidden = true; try { await villages(ticket); } catch { if (ticket === revision) fail(); } });
        barangay.addEventListener('change', ready);
        form.addEventListener('submit', event => { if (save.disabled) { event.preventDefault(); status.textContent = 'Load and select a complete address before saving.'; } });
        retry.addEventListener('click', start);
        if (fixed && root.closest('details')) root.closest('details').addEventListener('toggle', () => { if (root.closest('details').open && !province.options[1]) start(); });
        else start();
    });
})();
