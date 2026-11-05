(() => {
    const form = document.querySelector('form[data-registration-form]');
    if (!form) return;
    const ownership = form.querySelector('[name="vehicle_ownership"]');
    const sync = () => form.querySelectorAll('[data-own-vehicle-correction]').forEach(section => {
        const own = ownership?.selectedOptions[0]?.dataset.ownVehicle === '1';
        section.hidden = !own;
        section.querySelectorAll('input').forEach(input => { input.disabled = !own; input.required = own && input.dataset.required === 'true'; });
    });
    ownership?.addEventListener('change', sync);
    sync();
    form.querySelectorAll('input[type="file"]').forEach(input => input.addEventListener('change', () => {
        const file = input.files[0];
        const maxKb = Number(input.dataset.maxKb || 5120);
        input.setCustomValidity(file && file.size > maxKb * 1024 ? `Maximum file size is ${maxKb / 1024} MB.` : '');
        input.reportValidity();
    }));
})();
