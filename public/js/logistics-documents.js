(() => {
    const section = document.querySelector('.logistics-document-fields');
    if (!section) return;
    const inputs = [...section.querySelectorAll('input[type="file"]')];
    const check = input => {
        const file = input.files[0];
        input.setCustomValidity(!file ? 'Upload this required company certificate.' : file.size > 5 * 1024 * 1024 ? 'Maximum file size is 5 MB.' : !['image/jpeg','image/png','application/pdf'].includes(file.type) ? 'Upload a JPG, PNG or PDF file.' : '');
        return input.checkValidity();
    };
    const validate = () => {
        const invalid = inputs.filter(input => !check(input));
        if (invalid[0]) { invalid[0].scrollIntoView({block:'center'}); invalid[0].reportValidity(); }
        return !invalid.length;
    };
    inputs.forEach(input => input.addEventListener('change', () => { check(input); input.reportValidity(); }));
    section.closest('form')?.addEventListener('submit', event => {
        if (!validate()) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    window.logisticsDocumentInputs = {validate};
})();
