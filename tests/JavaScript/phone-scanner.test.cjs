const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');
const { webcrypto } = require('node:crypto');
const flush = async () => { for (let i = 0; i < 12; i++) await new Promise(resolve => setImmediate(resolve)); };
const deferred = () => { let resolve; const promise = new Promise(r => resolve = r); return { promise, resolve }; };
const response = (data, status = 200) => ({ ok: status < 400, status, headers: { get: () => 'application/json' }, json: async () => data });

function fixture(file, fetch, secure = true) {
    const nodes = new Map(), requests = [], timers = [], windowEvents = {};
    function node(id) {
        if (nodes.has(id)) return nodes.get(id);
        const value = {
            id, children: [], style: {}, handlers: {}, value: '', disabled: false, hidden: true, isConnected: true, textContent: '', dataset: {},
            addEventListener(name, fn) { this.handlers[name] = fn; },
            appendChild(child) { this.children.push(child); child.isConnected = true; },
            replaceChildren(...children) { this.children.forEach(c => c.isConnected = false); this.children = children; },
            set innerHTML(text) { assert.equal(text, '', 'Untrusted text must never be assigned to innerHTML'); this.replaceChildren(); }
        };
        nodes.set(id, value);
        return value;
    }
    node('phoneScannerConnection').dataset = { lookupUrl: '/lookup', createUrl: '/pair', receiveBase: '/scan', assignBase: '/assignments', statusBase: '/status', loginUrl: 'https://example.test/login' };
    node('phoneScannerDevice').dataset = { sendUrl: '/codes', expiresAt: String(Math.floor(Date.now() / 1000) + 600) };
    node('trackingInput').value = 'TRACK';
    node('phoneCode').value = 'TRACK';
    let camera;
    class Camera {
        constructor() { camera = this; }
        async start(config, options, callback) { this.config = config; this.callback = callback; }
        async stop() { this.stopped = true; }
        clear() {}
    }
    const document = {
        getElementById: node, createElement: tag => node('generated-'+tag+'-'+nodes.size),
        querySelector: () => ({ content: 'test-csrf' }),
        querySelectorAll: () => node('statusActions').children.filter(child => child.handlers.click)
    };
    runInNewContext(readFileSync(file, 'utf8'), {
        document, fetch: async (url, options) => { requests.push({ url, options }); return fetch(url, options, requests.length); },
        window: { isSecureContext: secure, addEventListener: (name, fn) => windowEvents[name] = fn },
        navigator: { mediaDevices: { getUserMedia() {} } }, crypto: webcrypto, Html5Qrcode: Camera,
        URL, setTimeout: fn => { timers.push(fn); return timers.length; }, clearTimeout() {}, prompt: () => 'Fixture reason'
    });
    return { node, requests, timers, camera: () => camera, windowEvents };
}

function parcel(code, stage = 'none') {
    return { ok: true, shipment: { id: 'parcel', tracking_number: code, status: stage === 'receive' ? 'picked_up' : 'sorted', status_label: 'Fixture' }, stage, hub_transfer_riders: [] };
}

test('direct device camera prefers rear camera, suppresses repeated codes, and respects HTTPS', async () => {
    const f = fixture('public/js/parcel-scan.js', () => response(parcel('TRACK')));
    await f.node('startCamera').handlers.click();
    assert.equal(f.node('reader').hidden, false);
    assert.equal(f.camera().config.facingMode, 'environment');
    f.camera().callback('TRACK'); f.camera().callback('TRACK'); await flush();
    f.camera().callback('TRACK'); await flush();
    assert.equal(f.requests.length, 1);
    assert.equal(f.node('rTracking').textContent, 'TRACK');
    await f.node('stopCamera').handlers.click();
    assert.equal(f.camera().stopped, true);
    const insecure = fixture('public/js/parcel-scan.js', () => response(parcel('TRACK')), false);
    await insecure.node('startCamera').handlers.click();
    assert.equal(insecure.camera(), undefined);
    assert.match(insecure.node('scanMessage').children[0].textContent, /HTTPS/);
});

test('desktop ignores stale lookup responses and clears failed lookup actions safely', async () => {
    const first = deferred(), second = deferred();
    const f = fixture('public/js/parcel-scan.js', (url, options, count) => count === 1 ? first.promise : count === 2 ? second.promise : response({ message: '<img onerror=alert(1)>' }, 404));
    f.node('trackingForm').handlers.submit({ preventDefault() {} });
    f.node('trackingInput').value = 'NEW';
    f.node('trackingForm').handlers.submit({ preventDefault() {} });
    second.resolve(response(parcel('NEW', 'receive'))); await flush();
    first.resolve(response(parcel('OLD'))); await flush();
    assert.equal(f.node('rTracking').textContent, 'NEW');
    assert.equal(f.node('statusActions').children.length, 1);
    f.node('trackingForm').handlers.submit({ preventDefault() {} }); await flush();
    assert.equal(f.node('shipmentResult').style.display, 'none');
    assert.equal(f.node('statusActions').children.length, 0);
    assert.equal(f.node('scanMessage').children[0].textContent, '<img onerror=alert(1)>');
});

test('desktop action remains locked through its refreshed lookup', async () => {
    const refresh = deferred();
    const f = fixture('public/js/parcel-scan.js', (url, options, count) => count === 1 ? response(parcel('TRACK', 'receive')) : count === 2 ? response({ ok: true }) : refresh.promise);
    f.node('trackingForm').handlers.submit({ preventDefault() {} }); await flush();
    const button = f.node('statusActions').children[0];
    button.handlers.click(); button.handlers.click(); await flush();
    assert.equal(f.requests.length, 3);
    assert.equal(f.node('trackingInput').disabled, true);
    refresh.resolve(response(parcel('TRACK'))); await flush();
    assert.equal(f.node('trackingInput').disabled, false);
});

test('desktop consumes phone codes one at a time and never automatically writes parcel status', async () => {
    const pair = { url: 'https://example.test/phone', events_url: '/events', ack_url: '/ack', disconnect_url: '/disconnect', expires_at: Math.floor(Date.now() / 1000) + 600 };
    const f = fixture('public/js/parcel-scan.js', (url, options) => {
        if (url === '/pair') return response(pair);
        if (url === '/events') return response({ events: [{ sequence: 1, code: 'TRACK' }, { sequence: 2, code: 'NEXT' }] });
        if (url === '/lookup') return response(parcel(JSON.parse(options.body).code));
        return response({ ok: true });
    });
    await f.node('connectPhoneScanner').handlers.click(); await flush();
    assert.equal(f.node('rTracking').textContent, 'TRACK');
    assert.equal(f.node('phoneNextScan').hidden, false);
    assert.equal(f.requests.filter(r => r.url === '/lookup').length, 1);
    f.node('phoneNextScan').handlers.click(); await flush();
    assert.equal(f.node('rTracking').textContent, 'NEXT');
    assert.equal(f.requests.some(r => r.options.method === 'PATCH'), false);
});

 test('phone disconnect is displayed while desktop is reviewing a parcel', async () => {
    let closed = false;
    const pair = { url: 'https://example.test/phone', events_url: '/events', ack_url: '/ack', disconnect_url: '/disconnect', expires_at: Math.floor(Date.now() / 1000) + 600 };
    const f = fixture('public/js/parcel-scan.js', (url, options) => {
        if (url === '/pair') return response(pair);
        if (url === '/events') return response(closed ? { state: 'disconnected', disconnected_by: 'phone', events: [] } : { state: 'connected', events: [{ sequence: 1, code: 'TRACK' }] });
        if (url === '/lookup') return response(parcel('TRACK'));
        return response({ ok: true });
    });
    await f.node('connectPhoneScanner').handlers.click(); await flush();
    assert.equal(f.node('phoneNextScan').hidden, false);
    closed = true;
    await f.timers.at(-1)(); await flush();
    assert.match(f.node('phonePairStatus').textContent, /phone disconnected/);
    assert.equal(f.node('connectPhoneScanner').disabled, false);
    assert.equal(f.node('phoneNextScan').hidden, true);
    assert.equal(f.node('phonePairLink').value, '');
    assert.equal(f.requests.filter(r => r.url === '/lookup').length, 1);
    assert.equal(f.node('rTracking').textContent, 'TRACK');
 });
