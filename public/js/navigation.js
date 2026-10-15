(() => {
    document.querySelectorAll('[data-navigation-group]').forEach(button => {
        const panel = document.getElementById(button.getAttribute('aria-controls'));
        if (!panel) return;
        button.addEventListener('click', () => {
            const expanded = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', String(expanded));
            panel.hidden = !expanded;
        });
    });

    const shell = document.querySelector('[data-navigation-shell]');
    const sidebar = shell?.querySelector('#sidebar');
    if (!shell || !sidebar) return;
    const key = shell.dataset.collapseKey;
    const toggles = [...document.querySelectorAll('[data-sidebar-toggle]')];
    const readCollapsed = () => {
        try { return localStorage.getItem(key) === '1'; } catch { return false; }
    };
    const saveCollapsed = value => {
        try { localStorage.setItem(key, value ? '1' : '0'); } catch { /* Storage may be unavailable. */ }
    };
    shell.classList.toggle('collapsed', readCollapsed());
    const sync = () => {
        const expanded = window.innerWidth <= 860 ? sidebar.classList.contains('open') : !shell.classList.contains('collapsed');
        sidebar.inert = !expanded;
        toggles.forEach(button => {
            button.setAttribute('aria-controls', 'sidebar');
            button.setAttribute('aria-expanded', String(expanded));
        });
    };
    const closeMobile = () => { sidebar.classList.remove('open'); sync(); };
    toggles.forEach(button => button.addEventListener('click', () => {
        if (window.innerWidth <= 860) sidebar.classList.toggle('open');
        else saveCollapsed(shell.classList.toggle('collapsed'));
        sync();
        if (window.innerWidth <= 860 && sidebar.classList.contains('open')) sidebar.querySelector('a, button')?.focus();
    }));
    document.addEventListener('click', event => {
        if (window.innerWidth <= 860 && sidebar.classList.contains('open') && !sidebar.contains(event.target) && !event.target.closest('[data-sidebar-toggle]')) closeMobile();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && window.innerWidth <= 860 && sidebar.classList.contains('open')) {
            closeMobile();
            toggles[0]?.focus();
        }
    });
    window.addEventListener('resize', () => { if (window.innerWidth > 860) sidebar.classList.remove('open'); sync(); });
    sync();
})();
