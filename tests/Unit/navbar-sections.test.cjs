const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function navigation() {
    const positions = { products: [1400, 2600], deals: [600, 1300], about: [2700, 3000] };
    const links = ['products', 'deals', 'about'].map(id => ({
        href: `https://example.test/#${id}`, attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
    }));
    const events = {};
    let scheduled;
    let frames = 0;
    const window = {
        location: { pathname: '/', search: '' }, scrollY: 0, innerHeight: 800,
        addEventListener(name, callback) { events[name] = callback; },
        requestAnimationFrame(callback) { scheduled = callback; return ++frames; },
    };
    const source = fs.readFileSync('public/js/marketplace.js', 'utf8');
    const start = source.indexOf('        const sectionLinks = links.map');
    const end = source.indexOf('        links.forEach((link) => link.addEventListener', start);
    vm.runInNewContext(source.slice(start, end), {
        links, window, URL,
        navbar: { getBoundingClientRect: () => ({ bottom: 76 }) },
        document: {
            body: {}, documentElement: { scrollHeight: 3000 },
            getElementById(id) {
                return { getBoundingClientRect: () => ({ top: positions[id][0] - window.scrollY, bottom: positions[id][1] - window.scrollY }) };
            },
        },
    });
    return {
        scroll(top) { window.scrollY = top; events.scroll(); scheduled(); },
        selected: () => links.find(link => link.attributes['aria-current'])?.href.split('#')[1],
        events, flush: () => scheduled(), frames: () => frames,
    };
}

test('scrolling selects sections in page order rather than navigation link order', () => {
    const nav = navigation();
    nav.scroll(0);
    assert.equal(nav.selected(), undefined);
    nav.scroll(600);
    assert.equal(nav.selected(), 'deals');
    nav.scroll(1450);
    assert.equal(nav.selected(), 'products');
    nav.scroll(650);
    assert.equal(nav.selected(), 'deals');
    nav.scroll(0);
    assert.equal(nav.selected(), undefined);
});

test('the short About footer becomes selected at the bottom of the page', () => {
    const nav = navigation();
    nav.scroll(2200);
    assert.equal(nav.selected(), 'about');
});

test('rapid scroll events share one animation frame', () => {
    const nav = navigation();
    nav.flush();
    const before = nav.frames();
    for (let i = 0; i < 50; i++) nav.events.scroll();
    assert.equal(nav.frames(), before + 1);
});
