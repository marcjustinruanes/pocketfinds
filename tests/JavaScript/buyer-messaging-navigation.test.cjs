const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');
const source = readFileSync('public/js/buyer.js', 'utf8');

function fixture(pathname) {
    const handlers = [], requests = [], navigations = [], classes = new Set();
    const widget = { classList: { add: x => classes.add(x) }, dataset: {}, setAttribute() {} };
    const panel = { innerHTML: '', querySelectorAll: () => [], replaceChildren: (...frames) => frames.forEach(frame => requests.push(frame.src)) };
    const context = {
        document: {
            addEventListener: (type, handler) => { if (type === 'click') handlers.push(handler); },
            querySelectorAll: () => [],
            querySelector: () => null,
            createElement: () => ({}),
            getElementById: id => id === 'messagesModal' ? widget : id === 'messagesModalBody' ? panel : null,
        },
        window: { location: { pathname, origin:'https://example.test', assign: url => navigations.push(url) } },
        URL,
        fetch: url => { requests.push(url); return Promise.resolve({ text: async () => '' }); },
    };
    runInNewContext(source, context);
    function click(inWidget, modifiers = {}) {
        let prevented = false;
        const link = { closest: () => inWidget ? widget : null, getAttribute: () => '/buyer/messages?seller=fictional' };
        const target = { classList: { contains: () => false }, closest: selector => selector === 'a.chat-list-item, a.chat-mobile-back' ? link : null };
        const event = { target, button: 0, preventDefault: () => { prevented = true; }, ...modifiers };
        handlers.forEach(handler => handler(event));
        return prevented;
    }
    return { click, requests, navigations, classes, context };
}

test('full-page conversation clicks navigate normally without opening a duplicate widget', () => {
    const f = fixture('/buyer/messages');
    assert.equal(f.click(false), false);
    assert.equal(f.classes.has('open'), false);
    assert.deepEqual(f.requests, []);
});

test('message openers on the dedicated page navigate to the selected conversation', () => {
    const f = fixture('/buyer/messages/');
    f.context.openMessagesModal('/buyer/messages?seller=fictional&purchase=review');
    assert.deepEqual(f.navigations, ['/buyer/messages?seller=fictional&purchase=review']);
    assert.equal(f.classes.has('open'), false);
    assert.deepEqual(f.requests, []);
});

test('conversation switches inside the widget retain in-place loading', () => {
    const f = fixture('/buyer/orders');
    assert.equal(f.click(true), true);
    assert.equal(f.classes.has('open'), true);
    assert.deepEqual(f.requests, ['https://example.test/buyer/messages?seller=fictional&embedded=1']);
});

test('modified widget clicks preserve new-tab browser behavior', () => {
    const f = fixture('/buyer/orders');
    assert.equal(f.click(true, { ctrlKey: true }), false);
    assert.deepEqual(f.requests, []);
});

test('other pages can still open the floating conversation widget', () => {
    const f = fixture('/buyer/orders');
    f.context.openMessagesModal('/buyer/messages?seller=fictional');
    assert.equal(f.classes.has('open'), true);
    assert.deepEqual(f.requests, ['https://example.test/buyer/messages?seller=fictional&embedded=1']);
});
