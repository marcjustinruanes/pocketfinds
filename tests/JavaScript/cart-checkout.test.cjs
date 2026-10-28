const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');

const source = readFileSync('resources/views/buyer/cart.blade.php', 'utf8');
const checkout = source.slice(source.indexOf("  checkoutForm.addEventListener('submit'"), source.indexOf('  // ---- Add Note modal ----'));

function fixture(gcash = true) {
    const elements = new Map();
    const document = {
        activeElement: null,
        getElementById: id => elements.get(id),
        querySelector: () => ({ value: 'fictional-address' }),
        createElement: () => ({}),
    };
    function element(id) {
        const classes = new Set();
        const node = { disabled: false, handlers: {},
            addEventListener: (type, fn) => { node.handlers[type] = fn; },
            classList: { add: x => classes.add(x), remove: x => classes.delete(x), contains: x => classes.has(x) },
            setAttribute: () => {}, focus: () => { document.activeElement = node; },
        };
        elements.set(id, node);
        return node;
    }
    const modal = element('orderConfirmModal'), cancel = element('orderCancelButton'), confirm = element('orderConfirmButton');
    const button = element('checkoutButton'), dock = element('cartDockCheckout');
    for (const id of ['orderConfirmTitle', 'orderConfirmMessage', 'checkoutPayment', 'checkoutVoucher', 'checkoutNote', 'checkoutAddress', 'cartTotal']) element(id);
    const fields = [], windowEvents = {};
    const form = element('checkoutForm');
    form.dataset = {};
    let submits = 0;
    form.appendChild = field => fields.push(field);
    form.submit = () => { submits++; };
    const context = { document, window: { addEventListener: (type, fn) => { windowEvents[type] = fn; } },
        checkoutForm: form, checkoutButton: button, orderConfirmModal: modal, orderCancelButton: cancel, orderConfirmButton: confirm,
        itemChecks: [{ checked: true, closest: () => ({ querySelector: () => ({ dataset: { editKey: 'fictional-line' } }) }) }],
        submittingOrder: false, checkoutReturnFocus: null, isGcashSelection: () => gcash,
        paymentNameSelect: { value: 'fictional-method' }, appliedVoucherCode: '', orderNoteValue: '',
        refreshSummary: () => { button.disabled = false; dock.disabled = false; },
    };
    runInNewContext(checkout, context);
    const submit = async () => { let prevented = false; await form.handlers.submit({ preventDefault: () => { prevented = true; } }); return prevented; };
    return { submit, modal, cancel, confirm, button, dock, document, fields, windowEvents, context, submits: () => submits };
}

test('GCash confirmation never creates an order before explicit confirmation', async () => {
    const f = fixture();
    assert.equal(await f.submit(), true);
    assert.equal(f.submits(), 0);
    assert.equal(f.modal.classList.contains('open'), true);
    assert.equal(f.confirm.textContent, 'Reserve & message seller');
    assert.equal(f.document.activeElement, f.cancel);
    f.cancel.handlers.click();
    assert.equal(f.submits(), 0);
    assert.equal(f.modal.classList.contains('open'), false);
});

test('repeated confirmation and form submissions produce only one native submission', async () => {
    const f = fixture();
    await f.submit();
    f.confirm.handlers.click();
    f.confirm.handlers.click();
    assert.equal(await f.submit(), true);
    assert.equal(f.submits(), 1);
    assert.equal(f.fields.length, 1);
    assert.equal(f.confirm.disabled && f.button.disabled && f.dock.disabled, true);
});

test('COD uses the explicit Place Order confirmation', async () => {
    const f = fixture(false);
    await f.submit();
    assert.equal(f.confirm.textContent, 'Place Order');
    assert.equal(f.submits(), 0);
    f.confirm.handlers.click();
    assert.equal(f.submits(), 1);
});

test('confirmation traps keyboard focus and Escape restores its opener', async () => {
    const f = fixture();
    f.document.activeElement = f.dock;
    await f.submit();
    f.modal.handlers.keydown({ key: 'Tab', shiftKey: true, preventDefault() {} });
    assert.equal(f.document.activeElement, f.confirm);
    f.modal.handlers.keydown({ key: 'Tab', shiftKey: false, preventDefault() {} });
    assert.equal(f.document.activeElement, f.cancel);
    f.modal.handlers.keydown({ key: 'Escape', preventDefault() {} });
    assert.equal(f.document.activeElement, f.dock);
    assert.equal(f.modal.classList.contains('open'), false);
});

test('back-forward cache restores usable checkout controls without resubmitting', async () => {
    const f = fixture();
    await f.submit();
    f.confirm.handlers.click();
    f.windowEvents.pageshow({ persisted: true });
    assert.equal(f.context.submittingOrder, false);
    assert.equal(f.confirm.disabled || f.button.disabled || f.dock.disabled, false);
    assert.equal(f.submits(), 1);
});
