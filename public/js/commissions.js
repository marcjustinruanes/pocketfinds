document.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    const form = event.target;
    if (!form.closest('.commission-workspace') || !form.dataset.confirm) return;
    const usesSharedConfirmation = form.closest('[data-seller-finance]') && document.getElementById('sellerConfirmModal');
    if (form.dataset.busy || (!usesSharedConfirmation && !window.confirm(form.dataset.confirm))) { event.preventDefault(); return; }
    form.dataset.busy = '1';
    form.querySelectorAll('button[type="submit"],button:not([type])').forEach(button => { button.disabled = true; });
});
window.addEventListener('pageshow', () => document.querySelectorAll('.commission-workspace form[data-busy]').forEach(form => {
    delete form.dataset.busy;
    form.querySelectorAll('button').forEach(button => { button.disabled = false; });
}));
