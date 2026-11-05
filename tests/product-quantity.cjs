const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../public/js/product-quantity.js'), 'utf8');
const context = { module: { exports: {} } };
vm.runInNewContext(source, context);
const { quantityState } = context.module.exports;

test('quantities follow stock, minimum one, and the cart limit of 99', () => {
  assert.equal(quantityState(1, 3, true, -1).value, 1);
  assert.equal(quantityState(3, 3, true, 1).value, 3);
  assert.equal(quantityState(12, 2, true).value, 2);
  assert.equal(quantityState(99, 200, true, 1).value, 99);
  assert.equal(quantityState(5, 0, true).value, 1);
  assert.equal(quantityState(5, 10, false).max, 0);
});

test('guest and buyer controls synchronize for plain stock, single options, and combinations', () => {
  for (const page of ['guest', 'buyer']) {
    const element = dataset => ({ dataset, textContent: '', disabled: false, lastChild: { textContent: '' }, setAttribute() {} });
    const elements = {
      pdPrice: element({ baseStock: '4', onVacation: 'false' }), pdQty: element(),
      pdQtyDecrease: element(), pdQtyIncrease: element(), pdAddCartBtn: element(), pdBuyNowBtn: element(),
    };
    let selected = null;
    let quantity = 1;
    let requiresSelection = false;
    const document = { getElementById: id => id === 'pdSelectedStock' ? (requiresSelection ? {} : null) : elements[id] };
    const window = { selectedPurchaseOption: () => selected };
    const template = fs.readFileSync(path.join(__dirname, '../resources/views/', page, 'product.blade.php'), 'utf8');
    const change = template.match(/function changeQty\(d\) \{([\s\S]*?)\n\}/)[1];
    const body = new Function('window', 'updateQtyTotal', 'qty', 'd', change + '\nreturn qty;');
    window.changeQty = delta => { quantity = body(window, () => {}, quantity, delta); };
    vm.runInNewContext(source, { window, document });
    assert.equal(elements.pdQtyDecrease.disabled, true);
    for (let i = 0; i < 10; i++) window.changeQty(1);
    assert.equal(quantity, 4);
    assert.equal(elements.pdQtyIncrease.disabled, true);

    requiresSelection = true;
    window.changeQty(0);
    assert.equal(quantity, 1);
    assert.equal(elements.pdQtyIncrease.disabled, true);
    assert.equal(elements.pdAddCartBtn.disabled, false);
    assert.match(elements.pdAddCartBtn.lastChild.textContent, /Add to Cart/);
    elements.pdPrice.dataset.baseStock = '0';
    window.changeQty(0);
    assert.equal(elements.pdAddCartBtn.disabled, true);
    assert.match(elements.pdAddCartBtn.lastChild.textContent, /Out of Stock/);
    elements.pdPrice.dataset.baseStock = '4';

    selected = { dataset: { stock: '3' } }; // a color-only option or a complete combination
    window.changeQty(0);
    assert.equal(elements.pdAddCartBtn.disabled, false);
    window.changeQty(1); window.changeQty(1);
    assert.equal(quantity, 3);
    selected = { dataset: { stock: '1' } }; // switch to a lower-stock variant, including via image
    window.changeQty(0);
    assert.equal(quantity, 1);
    assert.equal(elements.pdQtyIncrease.disabled, true);
    selected = { dataset: { stock: '0' } };
    window.changeQty(0);
    assert.equal(elements.pdAddCartBtn.disabled, true);
    assert.equal(elements.pdQtyDecrease.disabled, true);
    assert.match(elements.pdAddCartBtn.lastChild.textContent, /Out of Stock/);
    elements.pdPrice.dataset.onVacation = 'true';
    selected = { dataset: { stock: '20' } };
    window.changeQty(0);
    assert.equal(elements.pdAddCartBtn.disabled, true);
    assert.equal(elements.pdQtyIncrease.disabled, true);
  }
});
