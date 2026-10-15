const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');
const source = readFileSync('public/js/navigation.js', 'utf8');

function element() {
    const attributes = new Map();
    const classes = new Set();
    const handlers = {};
    return {
        attributes, handlers, focused: false, hidden: false,
        setAttribute: (key, value) => attributes.set(key, value),
        getAttribute: key => attributes.get(key),
        addEventListener: (event, handler) => { handlers[event] = handler; },
        focus() { this.focused = true; },
        classList: {
            contains: value => classes.has(value),
            remove: value => classes.delete(value),
            toggle(value, force = !classes.has(value)) { force ? classes.add(value) : classes.delete(value); return force; }
        }
    };
}

function fixture(key, width = 1200, storage = new Map(), blocked = false) {
    const shell = element(), sidebar = element(), toggle = element(), group = element(), panel = element(), firstLink = element();
    const menu = element(), summary = element();
    group.setAttribute('aria-controls', 'group');
    group.setAttribute('aria-expanded', 'false');
    panel.hidden = true;
    shell.dataset = { collapseKey: key };
    shell.querySelector = () => sidebar;
    sidebar.querySelector = () => firstLink;
    sidebar.contains = target => target === firstLink;
    menu.querySelector = () => summary;
    menu.open = true;
    const doc = element(), win = element();
    win.innerWidth = width;
    doc.querySelectorAll = selector => ({ '[data-navigation-group]': [group], '[data-sidebar-toggle]': [toggle], '.pf-buyer-menu': [menu] })[selector] || [];
    doc.querySelector = () => shell;
    doc.getElementById = () => panel;
    runInNewContext(source, { document: doc, window: win, localStorage: {
        getItem(k) { if (blocked) throw Error('blocked'); return storage.get(k); },
        setItem(k, value) { if (blocked) throw Error('blocked'); storage.set(k, value); }
    }});
    return { shell, sidebar, toggle, group, panel, doc, win, firstLink, menu, summary };
}

test('group controls update expanded state and panel visibility', () => {
    const f = fixture('sidebar_collapsed');
    f.group.handlers.click();
    assert.equal(f.group.getAttribute('aria-expanded'), 'true');
    assert.equal(f.panel.hidden, false);
    f.group.handlers.click();
    assert.equal(f.panel.hidden, true);
});

test('each desktop portal writes only its own collapse key and restores it', () => {
    for (const key of ['sidebar_collapsed', 'logistics_sidebar_collapsed', 'rider_sidebar_collapsed', 'seller_sidebar_collapsed']) {
        const storage = new Map([['unrelated_portal', '1']]);
        const f = fixture(key, 1200, storage);
        f.toggle.handlers.click();
        assert.equal(f.sidebar.inert, true);
        assert.deepEqual([...storage], [['unrelated_portal', '1'], [key, '1']]);
        assert.equal(fixture(key, 1200, storage).toggle.getAttribute('aria-expanded'), 'false');
    }
});

test('mobile toggling, Escape, outside click and resize preserve desktop preference', () => {
    const storage = new Map([['rider_sidebar_collapsed', '1']]);
    const f = fixture('rider_sidebar_collapsed', 390, storage);
    assert.equal(f.sidebar.inert, true);
    f.toggle.handlers.click();
    assert.equal(f.sidebar.inert, false);
    assert.equal(f.firstLink.focused, true);
    f.doc.handlers.keydown({ key: 'Escape' });
    assert.equal(f.sidebar.inert, true);
    assert.equal(f.toggle.focused, true);
    f.toggle.handlers.click();
    f.doc.handlers.click({ target: { closest: () => null } });
    assert.equal(f.sidebar.classList.contains('open'), false);
    f.toggle.handlers.click();
    f.win.innerWidth = 1200;
    f.win.handlers.resize();
    assert.equal(f.sidebar.inert, true);
    assert.equal(storage.get('rider_sidebar_collapsed'), '1');
});

test('blocked storage does not disable navigation', () => {
    const f = fixture('seller_sidebar_collapsed', 1200, new Map(), true);
    f.toggle.handlers.click();
    assert.equal(f.sidebar.inert, true);
});
