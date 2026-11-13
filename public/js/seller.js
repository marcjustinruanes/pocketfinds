document.querySelectorAll('[data-dropdown-toggle]').forEach(button => {
    button.addEventListener('click', event => {
        event.stopPropagation();
        const panel = document.getElementById(button.dataset.dropdownToggle);
        document.querySelectorAll('.dropdown-panel.open').forEach(open => open !== panel && open.classList.remove('open'));
        panel?.classList.toggle('open');
    });
});
document.addEventListener('click', event => {
    if (!event.target.closest('.dropdown')) document.querySelectorAll('.dropdown-panel.open').forEach(panel => panel.classList.remove('open'));
});

// Logout modal
const logoutOverlay = document.getElementById('logoutOverlay');
document.querySelectorAll('[data-logout]').forEach(btn => {
    btn.addEventListener('click', () => logoutOverlay.classList.add('open'));
});
document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => logoutOverlay.classList.remove('open'));
});
logoutOverlay?.addEventListener('click', e => {
    if (e.target === logoutOverlay) logoutOverlay.classList.remove('open');
});

// Generic modal open/close
document.querySelectorAll('[data-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.modal);
        target?.classList.add('open');
    });
});
document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.modal-overlay')?.classList.remove('open');
    });
});
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});

// Accessible shared confirmation dialog for destructive Seller form actions.
const sellerConfirmModal = document.getElementById('sellerConfirmModal');
const sellerConfirmMessage = document.getElementById('sellerConfirmMessage');
const sellerConfirmSubmit = document.getElementById('sellerConfirmSubmit');
let pendingSellerForm = null;
let pendingSellerSubmitter = null;
let sellerConfirmOpener = null;
const closeSellerConfirm = () => {
    if (!sellerConfirmModal) return;
    sellerConfirmModal.classList.remove('open');
    sellerConfirmModal.setAttribute('aria-hidden', 'true');
    pendingSellerForm = null;
    pendingSellerSubmitter = null;
    sellerConfirmOpener?.focus();
};
document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;
            return;
        }
        event.preventDefault();
        if (!sellerConfirmModal) return;
        pendingSellerForm = form;
        pendingSellerSubmitter = event.submitter || null;
        sellerConfirmOpener = pendingSellerSubmitter || document.activeElement;
        sellerConfirmMessage.textContent = form.dataset.confirm || 'Are you sure you want to continue?';
        sellerConfirmSubmit.textContent = form.dataset.confirmAction || 'Continue';
        sellerConfirmModal.classList.add('open');
        sellerConfirmModal.setAttribute('aria-hidden', 'false');
        sellerConfirmSubmit.focus();
    });
});
document.querySelectorAll('[data-confirm-cancel]').forEach(button => button.addEventListener('click', closeSellerConfirm));
sellerConfirmModal?.addEventListener('click', event => { if (event.target === sellerConfirmModal) closeSellerConfirm(); });
sellerConfirmSubmit?.addEventListener('click', () => {
    const form = pendingSellerForm;
    const submitter = pendingSellerSubmitter;
    if (!form?.isConnected) return closeSellerConfirm();
    closeSellerConfirm();
    form.dataset.confirmed = 'true';
    if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter || undefined);
    else form.submit();
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && sellerConfirmModal?.classList.contains('open')) closeSellerConfirm();
});

// Tab switching
document.querySelectorAll('[data-tabs]').forEach(container => {
    const tabs   = container.querySelectorAll('.tab');
    const panels = document.querySelectorAll('[data-panel]');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const target = tab.dataset.tab;
            panels.forEach(p => p.style.display = p.dataset.panel === target ? 'block' : 'none');
        });
    });
});

// Toast helper
function showToast(msg, type = 'default') {
    const stack = document.getElementById('toastStack');
    if (!stack) return;
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    stack.appendChild(t);
    setTimeout(() => t.remove(), 3200);
}
