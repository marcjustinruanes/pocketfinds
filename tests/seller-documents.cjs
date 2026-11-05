const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function fixture() {
    const listeners = {};
    const select = { value: 'individual', form: { addEventListener() {} }, addEventListener(name, fn) { listeners[name] = fn; } };
    const sections = ['sole_proprietorship', 'corporation', 'sole_proprietorship,corporation'].map(types => {
        const input = { files: [], required: false, disabled: false, error: '', setCustomValidity(message) { this.error = message; }, checkValidity() { return !this.error && (!this.required || this.files.length > 0); }, reportValidity() {} };
        return { dataset: { sellerDocument: types }, hidden: true, input, querySelector: selector => selector === 'input[type="file"]' ? input : null, querySelectorAll: () => [input], addEventListener() {} };
    });
    const context = { window: {}, document: { querySelector: () => select, querySelectorAll: () => sections } };
    vm.runInNewContext(fs.readFileSync(require.resolve('../public/js/seller-documents.js'), 'utf8'), context);
    return { select, sections, change: () => listeners.change(), validate: () => context.window.sellerDocumentInputs.validate() };
}

test('individual, sole proprietor and corporation require only the applicable certificates', () => {
    const f = fixture();
    assert.deepEqual(f.sections.map(section => section.hidden), [true, true, true]);
    assert.equal(f.validate(), true);
    f.select.value = 'sole_proprietorship'; f.change();
    assert.deepEqual(f.sections.map(section => section.input.required), [true, false, true]);
    assert.equal(f.validate(), false);
    f.select.value = 'corporation'; f.change();
    assert.deepEqual(f.sections.map(section => section.input.required), [false, true, true]);
    f.sections[1].input.files = [{ name: 'sec.pdf', size: 1000 }];
    f.sections[2].input.files = [{ name: 'bir.png', size: 1000 }];
    assert.equal(f.validate(), true);
});

test('invalid files and oversized certificates are rejected, existing certificates can be retained', () => {
    const f = fixture();
    f.sections[0].dataset.documentOnFile = 'true';
    f.sections[2].dataset.documentOnFile = 'true';
    f.select.value = 'sole_proprietorship'; f.change();
    assert.equal(f.validate(), true);
    f.sections[0].input.files = [{ name: 'dti.exe', size: 1000 }];
    assert.equal(f.validate(), false);
    f.sections[0].input.files = [{ name: 'dti.pdf', size: 5 * 1024 * 1024 }];
    assert.equal(f.validate(), true);
    f.sections[0].input.files[0].size++;
    assert.equal(f.validate(), false);
});
