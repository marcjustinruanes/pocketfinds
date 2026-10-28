const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function gallery({ mobile = false, reduced = false } = {}) {
    const node = () => ({
        hidden: false, dataset: {}, attributes: {}, events: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(name, callback) { this.events[name] = callback; },
    });
    const previous = node(), next = node(), counter = node();
    const section = {
        querySelector(selector) {
            return ({ '[data-deal-prev]': previous, '[data-deal-next]': next, '[data-deal-position]': counter })[selector] || null;
        },
        querySelectorAll() { return []; },
    };
    const frame = node();
    const panels = Array.from({ length: 8 }, (_, index) => {
        const panel = node();
        panel.dataset.dealPanel = String(index);
        const classes = new Set(index === 0 ? ['is-active'] : []);
        panel.classList = {
            contains: name => classes.has(name),
            remove: name => classes.delete(name),
            toggle(name, enabled) { if (enabled) classes.add(name); else classes.delete(name); },
        };
        const properties = {};
        panel.style = { setProperty(name, value) { properties[name] = value; } };
        panel.properties = properties;
        panel.details = node();
        panel.querySelector = () => panel.details;
        panel.animations = [];
        panel.getAnimations = () => panel.animations.filter(animation => !animation.cancelled);
        panel.animate = (frames, options) => {
            const animation = { frames, options, cancel() { this.cancelled = true; } };
            panel.animations.push(animation);
            return animation;
        };
        return panel;
    });
    const options = panels.map(panel => {
        const option = node();
        option.dataset.dealSelect = panel.dataset.dealPanel;
        return option;
    });
    const showcase = {
        clientWidth: 800,
        closest: selector => selector === '#deals' ? section : frame,
        querySelectorAll: selector => selector === '[data-deal-panel]' ? panels : options,
    };
    const listeners = {};
    const timers = new Map();
    let timerId = 0;
    const context = {
        document: { hidden: false, querySelectorAll: () => [showcase], addEventListener() {} },
        window: {
            location: { search: '' },
            matchMedia: query => ({ matches: query.includes('reduced-motion') ? reduced : mobile, addEventListener() {} }),
            addEventListener(name, callback) { listeners[name] = callback; },
            clearTimeout(id) { timers.delete(id); },
            setTimeout(callback, delay) { const id = ++timerId; timers.set(id, { callback, delay }); return id; },
        },
        URLSearchParams,
        getComputedStyle(panel) {
            return {
                left: panel.properties['--deal-position'] || '50%',
                transform: 'matrix(1,0,0,1,0,0)',
                opacity: panel.classList.contains('is-outside') ? '0' : '1',
                visibility: panel.classList.contains('is-outside') ? 'hidden' : 'visible',
            };
        },
    };
    const source = fs.readFileSync('public/js/marketplace.js', 'utf8');
    const start = source.indexOf('    document.querySelectorAll("[data-deal-showcase]")');
    const end = source.indexOf('    const root = document.documentElement;', start);
    vm.runInNewContext(source.slice(start, end), context);
    return {
        panels, next: () => next.events.click(), previous: () => previous.events.click(), resize: () => listeners.resize(),
        finishTransition() {
            const entry = [...timers].find(([, timer]) => timer.delay === 620);
            if (entry) { timers.delete(entry[0]); entry[1].callback(); }
        },
    };
}

test('only the selected card shows its name and stacked prices', () => {
    const { panels, next } = gallery();
    next();
    panels.forEach((panel, index) => {
        assert.equal(panel.details.hidden, index !== 1);
    });
});

test('recycled cards enter at the perimeter', () => {
    const { panels, next } = gallery();
    next();
    const incoming = panels[3].animations.at(-1);
    assert.ok(incoming);
    assert.equal(incoming.frames[0].left, '960px');
    assert.equal(incoming.frames[0].opacity, '0');
});

test('rapid navigation waits for the current movement before reversing', () => {
    const { panels, next, previous, finishTransition } = gallery();
    next();
    const first = panels[0].animations.at(-1);
    previous();
    assert.equal(first.cancelled, undefined);
    assert.equal(panels[1].classList.contains('is-active'), true);
    assert.equal(panels[0].getAnimations().length, 1);
    finishTransition();
    assert.equal(panels[0].classList.contains('is-active'), true);
});

test('a burst of clicks produces one pending movement instead of a backlog', () => {
    const { panels, next, finishTransition } = gallery();
    next();
    for (let click = 0; click < 50; click++) next();
    assert.equal(panels[0].animations.length, 1);
    finishTransition();
    assert.equal(panels[2].classList.contains('is-active'), true);
    assert.equal(panels[0].animations.length, 2);
    finishTransition();
    finishTransition();
    assert.equal(panels[0].animations.length, 2);
});

test('resizing discards pending navigation', () => {
    const { panels, next, resize, finishTransition } = gallery();
    next();
    next();
    resize();
    finishTransition();
    assert.equal(panels[1].classList.contains('is-active'), true);
});

test('mobile animates incoming and outgoing cards; resizing skips animation', () => {
    const { panels, next, resize } = gallery({ mobile: true });
    next();
    assert.equal(panels[0].animations.length, 1);
    assert.equal(panels[1].animations.length, 1);
    assert.equal(panels[2].animations.length, 0);
    resize();
    assert.ok(panels.every(panel => panel.getAnimations().length === 0));
});

test('reduced motion switches cards without animation', () => {
    const { panels, next } = gallery({ reduced: true });
    next();
    assert.ok(panels.every(panel => panel.animations.length === 0));
    assert.equal(panels[1].details.hidden, false);
});
