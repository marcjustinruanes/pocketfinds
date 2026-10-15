document.addEventListener('DOMContentLoaded', () => {
    let cancelling = false;
    document.getElementById('orderSearch')?.addEventListener('input', event => {
        const query = event.target.value.trim().toLowerCase();
        document.querySelectorAll('[data-order-search]').forEach(card => card.hidden = Boolean(query && !card.dataset.orderSearch.includes(query)));
    });
    document.querySelectorAll('[data-order-details]').forEach(button => {
        const modal = document.getElementById(button.dataset.orderDetails);
        if (!modal) return;
        let previousOverflow;
        const close = () => {
            if (cancelling) return;
            modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = previousOverflow ?? ''; button.focus();
        };
        button.addEventListener('click', () => {
            previousOverflow = document.body.style.overflow;
            modal.classList.add('open'); modal.setAttribute('aria-hidden', 'false');
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
        cancelOpen?.addEventListener('click', () => { panel.hidden = false; cancelOpen.hidden = true; panel.querySelector('select').focus(); });
        modal.querySelector('.order-cancel-back')?.addEventListener('click', () => { if (cancelling) return; panel.hidden = true; cancelOpen.hidden = false; cancelOpen.focus(); });
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
            dialog.querySelector('[data-cancel-reason]').textContent = 'Reason: ' + form.elements.cancellation_reason.value;
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
