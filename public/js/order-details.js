document.addEventListener('DOMContentLoaded', () => {
    let cancelling = false;
    document.querySelectorAll('.refund-mobile-field input').forEach(input=>{
        const normalize=value=>{let digits=value.replace(/\D/g,'');if(digits.length===12&&digits.startsWith('63'))digits=digits.slice(2);else if(digits.length===11&&digits.startsWith('0'))digits=digits.slice(1);return digits.slice(0,10);};
        input.addEventListener('input',()=>{input.value=normalize(input.value);});
        input.addEventListener('paste',event=>{const value=event.clipboardData?.getData('text');if(value){event.preventDefault();input.value=normalize(value);input.dispatchEvent(new Event('input',{bubbles:true}));}});
    });
    document.getElementById('orderSearch')?.addEventListener('input', event => {
        const query = event.target.value.trim().toLowerCase();
        document.querySelectorAll('[data-order-search]').forEach(card => card.hidden = Boolean(query && !card.dataset.orderSearch.includes(query)));
    });
    document.querySelectorAll('[data-order-details]').forEach(button => {
        const modal = document.getElementById(button.dataset.orderDetails);
        if (!modal) return;
        const orderCard = button.closest('.order-card');
        if (modal.parentElement !== document.body) document.body.append(modal);
        let previousOverflow;
        const close = () => {
            if (cancelling) return;
            modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true');
            modal.classList.remove('order-cancelling');
            const cancelPanel = modal.querySelector('.order-cancel-panel');
            const cancelButton = modal.querySelector('.order-cancel-open');
            if (cancelPanel) cancelPanel.hidden = true;
            if (cancelButton) cancelButton.hidden = false;
            orderCard?.classList.remove('is-order-modal-open');
            document.body.style.overflow = previousOverflow ?? ''; button.focus();
        };
        button.addEventListener('click', () => {
            document.querySelectorAll('.order-details-modal.open').forEach(open=>{if(open!==modal)open.querySelector('.order-details-close')?.click();});
            previousOverflow = document.body.style.overflow;
            modal.classList.add('open'); modal.setAttribute('aria-hidden', 'false');
            const detailsButton = modal.querySelector('[data-continue-order-details]');
            if (detailsButton && !detailsButton.disabled) detailsButton.click();
            document.body.style.overflow = 'hidden'; modal.querySelector('.order-details-close').focus();
        });
        modal.querySelectorAll('.order-details-close').forEach(control => control.addEventListener('click', close));
        modal.addEventListener('click', event => { if (event.target === modal) close(); });
        modal.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); close(); }
            if (event.key !== 'Tab') return;
            const controls = [...modal.querySelectorAll('button,a[href],input,select,textarea,summary,[tabindex="0"]')].filter(el => !el.disabled && el.getClientRects().length);
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
        modal.querySelector('.order-copy-reference')?.addEventListener('click', async event => {
            const copy = event.currentTarget;
            try { await navigator.clipboard.writeText(modal.querySelector('[data-order-reference]').textContent.trim()); copy.textContent = 'Copied'; }
            catch { copy.textContent = 'Select reference to copy'; }
        });
        const cancelOpen = modal.querySelector('.order-cancel-open'), panel = modal.querySelector('.order-cancel-panel');
        cancelOpen?.addEventListener('click', () => { panel.hidden = false; cancelOpen.hidden = true; modal.classList.add('order-cancelling'); panel.scrollIntoView({block:'nearest', behavior:'smooth'}); panel.querySelector('select').focus(); });
        modal.querySelector('.order-cancel-back')?.addEventListener('click', () => { if (cancelling) return; panel.hidden = true; cancelOpen.hidden = false; modal.classList.remove('order-cancelling'); cancelOpen.focus(); });
        const form = modal.querySelector('.order-cancel-form');
        if (!form) return;
        const dialog = document.getElementById(form.dataset.confirmDialog);
        const feedback = form.querySelector('.order-cancel-feedback');
        let submitter;
        form.addEventListener('submit', event => {
            event.preventDefault();
            if (cancelling || !form.reportValidity()) return;
            if (!dialog?.showModal) { feedback.textContent = 'Use a current browser to review cancellation safely.'; feedback.focus(); return; }
            submitter = event.submitter;
            dialog.querySelector('[data-cancel-reason]').textContent = form.elements.cancellation_reason.value;
            dialog.showModal();
        });
        dialog.querySelector('[data-keep-order]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('cancel', event => { if (cancelling) event.preventDefault(); });
        dialog.addEventListener('close', () => { if (!cancelling) submitter?.focus(); });
        dialog.querySelector('[data-confirm-cancellation]').addEventListener('click', async () => {
            if (cancelling || !form.reportValidity()) return;
            const data = new FormData(form);
            cancelling = true; form.setAttribute('aria-busy', 'true');
            const controls = [...modal.querySelectorAll('button,select,textarea'), ...dialog.querySelectorAll('button')];
            controls.forEach(control => control.disabled = true);
            dialog.close(); feedback.textContent = 'Cancelling... Waiting for server confirmation.';
            try {
                const response = await fetch(form.action, {method:'POST', credentials:'same-origin', headers:{Accept:'application/json'}, body:data});
                let result;
                try { result = await response.json(); } catch { throw new Error('Unexpected server response. Refresh the order before retrying.'); }
                if (!response.ok || result.cancelled !== true) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Cancellation was not confirmed. Refresh the order before retrying.');
                feedback.textContent = result.message;
                const destination = new URL(result.redirect, location.href);
                if (destination.origin !== location.origin) throw new Error('Refresh to see the confirmed cancellation.');
                location.assign(destination.href);
            } catch (error) {
                cancelling = false; form.removeAttribute('aria-busy'); controls.forEach(control => control.disabled = false);
                feedback.textContent = error.message; feedback.focus();
            }
        });
    });
});

document.addEventListener('click', event => {
    const button = event.target.closest('[data-continue-order-details], [data-show-payment-progress]');
    if (!button || button.disabled) return;
    const modal = button.closest('.order-details-modal');
    if (!modal) return;
    const content = modal.querySelector('[data-approved-order-content]');
    const paymentSteps = modal.querySelector('.order-payment-steps');
    const paymentDetails = modal.querySelector('[data-payment-details-panel]');
    const orderButton = modal.querySelector('[data-continue-order-details]');
    const paymentButton = modal.querySelector('[data-show-payment-progress]');
    const showPayment = button.hasAttribute('data-show-payment-progress');
    if (paymentSteps) paymentSteps.hidden = !showPayment;
    if (paymentDetails) paymentDetails.hidden = !showPayment;
    if (content && orderButton && !orderButton.disabled) content.hidden = showPayment;
    const footer = modal.querySelector('[data-approved-order-footer]');
    if (footer) footer.hidden = !footer.querySelector('.order-cancel-open');
    for (const [control, active] of [[orderButton, !showPayment], [paymentButton, showPayment]]) {
        if (!control) continue;
        control.hidden = false;
        control.classList.toggle('is-active', active);
        control.setAttribute('aria-pressed', String(active));
    }
    const body = modal.querySelector('.order-details-body');
    body?.scrollTo({top: 0, behavior: 'smooth'});
});
