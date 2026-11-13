document.addEventListener('DOMContentLoaded', () => {

  /* ---- Dropdowns (notifications, etc.) ---- */
  document.querySelectorAll('[data-dropdown-toggle]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const panel = document.getElementById(btn.dataset.dropdownToggle);
      const isOpen = panel.classList.contains('open');
      document.querySelectorAll('.dropdown-panel.open').forEach(p => p.classList.remove('open'));
      if (!isOpen) panel.classList.add('open');
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-panel.open').forEach(p => p.classList.remove('open'));
  });

  /* ---- Modals ---- */
  let pendingConfirmForm = null;
  let pendingConfirmSubmitter = null;
  let lastModalOpener = null;
  const confirmOverlay = document.getElementById('confirmActionModal');
  const confirmTitle = document.getElementById('confirmActionTitle');
  const confirmMessage = document.getElementById('confirmActionMessage');
  const confirmButton = confirmOverlay?.querySelector('[data-confirm-submit]');

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('form[data-confirm]')) return;
    if (form.dataset.confirmed === '1') {
      delete form.dataset.confirmed;
      return;
    }
    e.preventDefault();
    pendingConfirmForm = form;
    pendingConfirmSubmitter = e.submitter instanceof HTMLElement ? e.submitter : null;
    if (pendingConfirmSubmitter) lastModalOpener = pendingConfirmSubmitter;
    if (confirmTitle) confirmTitle.textContent = form.dataset.confirmTitle || 'Confirm this action?';
    if (confirmMessage) confirmMessage.textContent = form.dataset.confirmMessage || 'Please review this change before continuing.';
    if (confirmButton) {
      confirmButton.textContent = form.dataset.confirmAction || 'Continue';
      confirmButton.className = `btn ${form.dataset.confirmTone === 'danger' ? 'btn-danger' : form.dataset.confirmTone === 'success' ? 'btn-success' : 'btn-primary'}`;
    }
    confirmOverlay?.classList.add('open');
    confirmOverlay?.querySelector('[data-modal-close]')?.focus();
  });

  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-modal-open]');
    if (opener) {
      const modal = document.getElementById(opener.dataset.modalOpen);
      modal?.classList.add('open');
      if (modal) {
        lastModalOpener = opener;
        const dialog = modal.querySelector('.modal');
        if (dialog) {
          dialog.setAttribute('role', 'dialog');
          dialog.setAttribute('aria-modal', 'true');
          requestAnimationFrame(() => (dialog.querySelector('[data-modal-close],button,input,select,textarea,a[href]') || dialog).focus());
        }
      }
    }
    const closer = e.target.closest('[data-modal-close]');
    if (closer) {
      const overlay = closer.closest('.modal-overlay');
      overlay?.classList.remove('open');
      if (overlay === confirmOverlay) {
        pendingConfirmForm = null;
        pendingConfirmSubmitter = null;
      }
      if (!document.querySelector('.modal-overlay.open') && lastModalOpener?.isConnected) {
        lastModalOpener.focus();
        lastModalOpener = null;
      }
    }
    // click outside modal content closes it
    if (e.target.classList.contains('modal-overlay')) {
      e.target.classList.remove('open');
      if (e.target === confirmOverlay) {
        pendingConfirmForm = null;
        pendingConfirmSubmitter = null;
      }
      if (!document.querySelector('.modal-overlay.open') && lastModalOpener?.isConnected) {
        lastModalOpener.focus();
        lastModalOpener = null;
      }
    }
    if (e.target.closest('[data-confirm-submit]') && pendingConfirmForm) {
      const form = pendingConfirmForm;
      const submitter = pendingConfirmSubmitter;
      pendingConfirmForm = null;
      pendingConfirmSubmitter = null;
      confirmOverlay?.classList.remove('open');
      form.dataset.confirmed = '1';
      if (submitter && submitter.form === form) form.requestSubmit(submitter);
      else form.requestSubmit();
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
      pendingConfirmForm = null;
      pendingConfirmSubmitter = null;
      if (lastModalOpener?.isConnected) lastModalOpener.focus();
      lastModalOpener = null;
      return;
    }
    if (e.key === 'Tab') {
      const topOverlay = [...document.querySelectorAll('.modal-overlay.open')].at(-1);
      const dialog = topOverlay?.querySelector('.modal');
      if (!dialog) return;
      const focusable = [...dialog.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')]
        .filter(el => el.getClientRects().length > 0);
      if (!focusable.length) {
        e.preventDefault();
        dialog.tabIndex = -1;
        dialog.focus();
        return;
      }
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  /* ---- Logout confirmation ---- */
  document.querySelectorAll('[data-logout]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('logoutOverlay')?.classList.add('open');
    });
  });

  /* ---- Tabs ---- */
  document.querySelectorAll('[data-tabs]').forEach(group => {
    group.querySelectorAll('.tab').forEach(tab => {
      tab.addEventListener('click', () => {
        group.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const filter = tab.dataset.tab;
        const table = group.parentElement.querySelector('table[id]') || document.querySelector('table.dtable');
        if (!table) return;
        table.querySelectorAll('tbody tr').forEach(row => {
          if (filter === 'all' || !row.dataset.type) { row.hidden = false; return; }
          row.hidden = row.dataset.type !== filter;
        });
      });
    });
  });

  /* ---- Table search ---- */
  document.querySelectorAll('[data-table-search]').forEach(input => {
    const table = document.getElementById(input.dataset.tableSearch);
    if (!table) return;
    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      table.querySelectorAll('tbody tr').forEach(row => {
        row.hidden = q && !(row.textContent + ' ' + (row.dataset.search || '')).toLowerCase().includes(q);
      });
    });
  });

  /* ---- Toasts ---- */
  window.showToast = (msg) => {
    const stack = document.getElementById('toastStack');
    if (!stack) return;
    const t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = `<span class="ic" aria-hidden="true"><svg class="svg-icon" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="4"></circle></svg></span><span>${msg}</span>`;
    stack.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = '.25s'; setTimeout(() => t.remove(), 250); }, 3200);
  };
  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-toast]');
    if (el) showToast(el.dataset.toast);
  });

});
