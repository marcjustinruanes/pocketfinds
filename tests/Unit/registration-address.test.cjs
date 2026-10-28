const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/register.js'), 'utf8');
const addressCode = source.slice(source.indexOf('let psgcProvinces = [];'), source.indexOf('// ── Field rules'));
const tick = () => new Promise(resolve => setImmediate(resolve));

function setup(fetchJSON) {
    const fields = Object.fromEntries(['region', 'province', 'municipality', 'barangay'].map(id => [id, {
        value: '', options: [], selectedIndex: 0, disabled: true, listeners: {},
        get selectedOptions() { return this.options.length ? [this.options[this.selectedIndex]] : []; },
        addEventListener(event, callback) { this.listeners[event] = callback; },
        replaceChildren(...options) { this.options = options; this.value = options[0]?.value ?? ''; },
    }]));
    const context = vm.createContext({
        document: { getElementById: id => fields[id] }, PSGC: 'api', fetchJSON,
        Option: function (label, value) { this.label = label; this.value = value; this.dataset = {}; },
        setLoading(field) { field.value = ''; field.options = []; field.disabled = true; },
        populateSelect(field, items) {
            field.options = [{ value: '', dataset: {} }, ...items.map(item => ({ value: item.name, dataset: { code: item.code } }))];
            field.value = ''; field.selectedIndex = 0; field.disabled = false;
        },
    });
    vm.runInContext(addressCode, context);
    return { fields, context, async select(code, name = code) {
        fields.region.options = [{ value: name, dataset: { code } }];
        fields.region.value = name; fields.region.selectedIndex = 0;
        return context.loadRegionAddress(code);
    } };
}

test('each region displays only provinces with a matching API regionCode and resets descendants', async () => {
    const provinces = [
        { code: 'p1', name: 'Province One', regionCode: 'r1' },
        { code: 'p2', name: 'Province Two', regionCode: 'r2' },
        { code: 'p3', name: 'Province Three', regionCode: 'r1' },
    ];
    const app = setup(async url => url === 'api/provinces/' ? provinces : []);
    await tick();
    await app.select('r1');
    assert.deepEqual(Array.from(app.fields.province.options, o => o.value), ['', 'Province One', 'Province Three']);
    app.fields.municipality.value = 'Old City'; app.fields.barangay.value = 'Old Barangay';
    await app.select('r2');
    assert.deepEqual(Array.from(app.fields.province.options, o => o.value), ['', 'Province Two']);
    assert.equal(app.fields.municipality.value, '');
    assert.equal(app.fields.barangay.value, '');
    assert.equal(app.fields.municipality.disabled, true);
});

test('switching regions while loading cannot restore an earlier province list', async () => {
    let complete;
    const app = setup(url => url === 'api/provinces/' ? new Promise(resolve => { complete = resolve; }) : Promise.resolve([]));
    await tick();
    const first = app.select('r1');
    const second = app.select('r2');
    complete([{ code: 'p1', name: 'First', regionCode: 'r1' }, { code: 'p2', name: 'Second', regionCode: 'r2' }]);
    await Promise.all([first, second]);
    assert.deepEqual(Array.from(app.fields.province.options, o => o.value), ['', 'Second']);
});

test('regions without provinces load their cities directly', async () => {
    const urls = [];
    const app = setup(async url => {
        urls.push(url);
        return url.endsWith('/cities-municipalities/') ? [{ code: 'c1', name: 'City One' }] : [];
    });
    await tick();
    await app.select('capital', 'Capital Region');
    assert.equal(app.fields.province.value, 'Capital Region');
    assert.equal(app.fields.province.options[0].dataset.isRegion, '1');
    assert.deepEqual(Array.from(app.fields.municipality.options, o => o.value), ['', 'City One']);
    assert.ok(urls.includes('api/regions/capital/cities-municipalities/'));
});

test('a failed province request can be retried by selecting the region again', async () => {
    let attempts = 0;
    const app = setup(async url => {
        if (url !== 'api/provinces/') return [];
        if (++attempts === 1) throw new Error('offline');
        return [{ code: 'p1', name: 'Province', regionCode: 'r1' }];
    });
    await tick();
    await app.select('r1');
    assert.deepEqual(Array.from(app.fields.province.options, o => o.value), ['', 'Province']);
});

test('service area region selection filters provinces and clears the municipality picker', async () => {
    const app = setup(async url => url === 'api/provinces/' ? [
        { code: 'p1', name: 'Province One', regionCode: 'r1' },
        { code: 'p2', name: 'Province Two', regionCode: 'r2' },
    ] : []);
    await tick();
    app.context.document.getElementById = id => id === 'hub_region_input' ? app.fields.region : app.fields[id];
    Object.assign(app.context, {
        hubProvinceSelect: app.fields.province, hubMuniRequestId: 0,
        hubMuniPanel: { style: {} }, hubMuniBtn: { disabled: false },
        hubMuniCheckboxes: { replaceChildren() { this.cleared = true; } },
        hubMuniSelectAll: { checked: true }, hubMuniLabel: { textContent: 'Old City' },
    });
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/js/logistics-service-regions.js'), 'utf8'), app.context);
    await tick();
    app.fields.region.options = [{ value: 'Second Region', dataset: { code: 'r2' } }];
    app.fields.region.selectedIndex = 0;
    app.fields.region.value = 'Second Region';
    await app.fields.region.listeners.change.call(app.fields.region);
    assert.deepEqual(Array.from(app.fields.province.options, option => option.value), ['', 'Province Two']);
    assert.equal(app.context.hubMuniCheckboxes.cleared, true);
    assert.equal(app.context.hubMuniBtn.disabled, true);
    assert.equal(app.context.hubMuniSelectAll.checked, false);
});
