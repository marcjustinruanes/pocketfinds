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
    if(shell.hasAttribute('data-sidebar-hover')){
        const brand=sidebar.querySelector('[data-sidebar-toggle]');
        const expand=value=>{shell.classList.toggle('collapsed',!value);sidebar.inert=false;brand?.setAttribute('aria-expanded',String(value));brand?.setAttribute('aria-controls','sidebar');};
        expand(false);
        sidebar.addEventListener('pointerenter',event=>{if(event.pointerType!=='touch')expand(true);});
        sidebar.addEventListener('pointerleave',()=>{if(!sidebar.contains(document.activeElement))expand(false);});
        sidebar.addEventListener('focusin',event=>{if(event.target!==brand)expand(true);});
        sidebar.addEventListener('focusout',event=>{if(!sidebar.contains(event.relatedTarget)&&!sidebar.matches(':hover'))expand(false);});
        brand?.addEventListener('click',()=>expand(shell.classList.contains('collapsed')));
        brand?.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();expand(shell.classList.contains('collapsed'));}});
        document.addEventListener('pointerdown',event=>{if(!sidebar.contains(event.target))expand(false);});
        sidebar.addEventListener('keydown',event=>{if(event.key==='Escape'){brand?.focus();expand(false);}});
        return;
    }
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

// Reading notifications stays in the open panel instead of navigating away.
document.addEventListener('DOMContentLoaded', () => {
    const notificationMenus = [...document.querySelectorAll('.pf-notification-menu')];
    const closeNotifications = () => notificationMenus.forEach(menu => { menu.open = false; });
    notificationMenus.forEach(menu => {
        const trigger = menu.querySelector('summary');
        const positionPanel = () => menu.style.setProperty('--notification-panel-top', `${Math.round(trigger.getBoundingClientRect().bottom + 12)}px`);
        menu.addEventListener('toggle', () => {
            trigger.setAttribute('aria-expanded', String(menu.open));
            if (!menu.open) return;
            positionPanel();
            document.querySelectorAll('.pf-buyer-menu[open]').forEach(other => { if (other !== menu) other.open = false; });
            document.querySelectorAll('.dropdown-panel.open').forEach(other => other.classList.remove('open'));
        });
        trigger.setAttribute('aria-expanded', String(menu.open));
        window.addEventListener('resize', () => { if (menu.open) positionPanel(); });
    });
    document.addEventListener('click', event => {
        notificationMenus.forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const openMenu = notificationMenus.find(menu => menu.open);
        if (!openMenu) return;
        closeNotifications();
        openMenu.querySelector('summary').focus();
    });
    document.querySelectorAll('.dropdown-panel.pf-buyer-notifications').forEach(panel => {
        // Keep header dropdown handlers from closing the panel when using its controls.
        panel.addEventListener('click', event => event.stopPropagation());
        const toggle = [...document.querySelectorAll('[data-dropdown-toggle]')].find(button => button.dataset.dropdownToggle === panel.id);
        if (!toggle) return;
        toggle.setAttribute('aria-controls', panel.id);
        const syncExpanded = () => toggle.setAttribute('aria-expanded', String(panel.classList.contains('open')));
        syncExpanded();
        new MutationObserver(syncExpanded).observe(panel, {attributes:true, attributeFilter:['class']});
        panel.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            event.preventDefault();
            panel.classList.remove('open');
            toggle.focus();
        });
    });
});

document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const path = new URL(form.action, window.location.href).pathname;
    if (!['/account/notifications/read-all', '/buyer/commerce/notifications/read-all', '/seller/notifications/read'].includes(path)) return;
    event.preventDefault();
    if (form.dataset.readPending) return;
    form.dataset.readPending = 'true';
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (button) button.disabled = true;
    form.querySelector('[data-read-error]')?.remove();
    try {
        const response = await fetch(form.action, {
            method: 'POST', body: new FormData(form), credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) throw new Error('Unable to mark notifications as read.');
        document.querySelectorAll('.notif-item').forEach(item => {
            item.classList.add('read');
            item.querySelectorAll('.dot').forEach(dot => dot.remove());
        });
        document.querySelectorAll('.notification-entry').forEach(item => {
            item.classList.remove('is-unread');
            item.classList.add('is-read');
            item.querySelectorAll('.notification-tag').forEach(tag => tag.remove());
        });
        document.querySelectorAll('.pf-buyer-notifications .notif-dropdown-head > span, .dropdown-panel .dropdown-head > span').forEach(count => count.remove());
        document.querySelectorAll('[aria-label="Notifications"] .dot-badge, [aria-label="Notifications"] .pf-buyer-dot').forEach(dot => dot.remove());
        document.querySelectorAll('form').forEach(other => {
            if (['/account/notifications/read-all', '/buyer/commerce/notifications/read-all', '/seller/notifications/read'].includes(new URL(other.action, window.location.href).pathname)) other.hidden = true;
        });
    } catch {
        const error = document.createElement('span');
        error.dataset.readError = '';
        error.setAttribute('role', 'alert');
        error.textContent = 'Could not mark notifications as read. Please try again.';
        form.append(error);
    } finally {
        delete form.dataset.readPending;
        if (button) button.disabled = false;
    }
});
