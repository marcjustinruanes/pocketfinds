// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const shell   = document.getElementById('appShell');
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
document.querySelectorAll('[data-sidebar-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        shell.classList.toggle('collapsed');
    });
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
