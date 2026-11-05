const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const tinModule = { exports: {} };
vm.runInNewContext(fs.readFileSync(require.resolve('../public/js/tin-input.js'), 'utf8'), { module: tinModule });
const { format } = tinModule.exports;

test('TIN formats typing and pasted values into four groups of three digits', () => {
    for (const [input, expected] of [['', ''], ['123', '123'], ['1234', '123-4'], ['123456789000', '123-456-789-000'], ['123 456 789 000', '123-456-789-000'], ['123-456-789-000', '123-456-789-000'], ['1234567890009', '123-456-789-000']]) {
        assert.equal(format(input), expected);
    }
});

test('TIN editing preserves cursor position and backspace crosses separators', () => {
    const events = {};
    const field = {
        value: '', selectionStart: 0, selectionEnd: 0,
        setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; },
        addEventListener(name, handler) { events[name] = handler; },
        dispatchEvent(event) { events[event.type](event); },
    };
    vm.runInNewContext(fs.readFileSync(require.resolve('../public/js/tin-input.js'), 'utf8'), {
        document: { querySelectorAll: () => [field] },
        Event: class { constructor(type) { this.type = type; } },
    });
    field.value = '1234'; field.setSelectionRange(4, 4); events.input();
    assert.equal(field.value, '123-4');
    assert.equal(field.selectionStart, 5);
    field.value = '123-456'; field.setSelectionRange(4, 4);
    let prevented = false;
    events.keydown({ key: 'Backspace', preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(field.value, '124-56');
    assert.equal(field.selectionStart, 2);
});
