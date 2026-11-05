(() => {
    const dialog = document.getElementById('pocketfinds-contact');
    if (!dialog) return;
    const form = dialog.querySelector('[data-contact-form]');
    const status = dialog.querySelector('[data-contact-status]');
    const submit = form.querySelector('[type="submit"]');
    let opener;
    document.querySelectorAll('[data-open-contact]').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            opener = link;
            dialog.showModal();
        });
    });
    dialog.querySelector('[data-close-contact]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => opener?.focus());
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submit.disabled || !form.reportValidity()) return;
        submit.disabled = true;
        status.textContent = 'Sending…';
        status.dataset.error = 'false';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(form),
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                const validation = Object.values(result.errors || {}).flat()[0];
                throw new Error(validation || result.message || 'Could not send your message. Please try again.');
            }
            form.elements.message.value = '';
            status.textContent = 'Your message has been sent to PocketFinds.';
        } catch (error) {
            status.dataset.error = 'true';
            status.textContent = error instanceof SyntaxError ? 'Could not send your message. Please refresh the page and try again.' : error.message;
        } finally {
            submit.disabled = false;
        }
    });
})();
