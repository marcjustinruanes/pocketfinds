document.addEventListener('DOMContentLoaded', () => {

  /* ---- Sidebar toggle (mobile + desktop) ---- */
  const sidebar = document.getElementById('sidebar');
  const shell   = document.getElementById('appShell');
  const COLLAPSED_KEY = 'sidebar_collapsed';

  // restore state
  if (localStorage.getItem(COLLAPSED_KEY) === '1') {
    shell?.classList.add('collapsed');
  }

  document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
    if (window.innerWidth <= 860) {
      sidebar?.classList.toggle('open');
    } else {
      const isCollapsed = shell?.classList.toggle('collapsed');
      localStorage.setItem(COLLAPSED_KEY, isCollapsed ? '1' : '0');
    }
  });

  document.addEventListener('click', (e) => {
    if (window.innerWidth > 860) return;
    if (sidebar?.classList.contains('open') && !sidebar.contains(e.target) && !e.target.closest('[data-sidebar-toggle]')) {
      sidebar.classList.remove('open');
    }
  });

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
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-modal-open]');
    if (opener) {
      const modal = document.getElementById(opener.dataset.modalOpen);
      modal?.classList.add('open');
    }
    const closer = e.target.closest('[data-modal-close]');
    if (closer) {
      const overlay = closer.closest('.modal-overlay');
      overlay?.classList.remove('open');
    }
    // click outside modal content closes it
    if (e.target.classList.contains('modal-overlay')) {
      e.target.classList.remove('open');
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
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
        row.hidden = q && !row.textContent.toLowerCase().includes(q);
      });
    });
  });

  /* ---- Toasts ---- */
  window.showToast = (msg) => {
    const stack = document.getElementById('toastStack');
    if (!stack) return;
    const t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = `<span class="ic">●</span><span>${msg}</span>`;
    stack.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = '.25s'; setTimeout(() => t.remove(), 250); }, 3200);
  };
  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-toast]');
    if (el) showToast(el.dataset.toast);
  });

});
