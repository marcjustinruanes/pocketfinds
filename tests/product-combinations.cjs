const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function loadScript(name) {
  const context = { module: { exports: {} } };
  vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../public/js/', name), 'utf8'), context);
  return context.module.exports;
}
const matrix = loadScript('variant-matrix.js');
const { resolveCombination } = loadScript('product-combinations.js');

const axes = [
  { name: 'Color', options: [{ value: 'Pink' }, { value: 'Blue' }] },
  { name: 'Size', options: [{ value: 'S' }, { value: 'M' }] },
];
const group = { axes, options: matrix.rows(axes).map((row, index) => ({ ...row, stock: [3, 5, 0, 2][index] })) };

test('one group keeps option stock and any two groups enable combination stock', () => {
  assert.equal(matrix.usesCombinations([]), false);
  assert.equal(matrix.usesCombinations([axes[0]]), false);
  assert.equal(matrix.usesCombinations(axes), true);
  const genericAxes = [axes[0], { name: 'Grams', options: [{ value: '100g' }, { value: '250g' }] }];
  assert.equal(matrix.usesCombinations(genericAxes), true);
  const generic = { axes: genericAxes, options: matrix.rows(genericAxes).map((row, index) => ({ ...row, stock: [3, 7, 2, 0][index] })) };
  assert.equal(resolveCombination(generic, { Color: 'Pink' }), null);
  assert.equal(resolveCombination(generic, { Color: 'Pink', Grams: '100g' }).stock, 3);
  assert.equal(resolveCombination(generic, { Color: 'Pink', Grams: '250g' }).stock, 7);
  assert.equal(matrix.usesCombinations([{ name: 'Material' }, { name: 'Flavor' }]), true);
});

test('stock is unresolved until all available attributes are chosen', () => {
  assert.equal(resolveCombination(group, {}), null);
  assert.equal(resolveCombination(group, { Color: 'Pink' }), null);
  assert.equal(resolveCombination(group, { Size: 'M' }), null);
});
test('each color and size pair resolves its own inventory', () => {
  assert.equal(resolveCombination(group, { Color: 'Pink', Size: 'S' }).stock, 3);
  assert.equal(resolveCombination(group, { Color: 'Pink', Size: 'M' }).stock, 5);
  assert.equal(resolveCombination(group, { Color: 'Blue', Size: 'S' }).stock, 0);
  assert.equal(resolveCombination(group, { Color: 'Blue', Size: 'M' }).stock, 2);
  assert.equal(resolveCombination(group, { Color: 'Unknown', Size: 'M' }), null);
});
test('matrix identities survive reordering and preserve each pair independently', () => {
  assert.equal(matrix.key({ Color: 'Pink', Size: 'S' }), matrix.key({ Size: 'S', Color: 'Pink' }));
  assert.equal(matrix.rows(axes).length, 4);
  assert.equal(matrix.rows([{ name: 'Color', options: [] }, axes[1]]).length, 0);
  assert.throws(() => matrix.rows(Array.from({ length: 4 }, (_, index) => ({ name: String(index), options: Array.from({ length: 10 }, (_, n) => ({ value: String(n) })) }))), /500/);
});

test('selecting size retains color and both product pages show the resolved stock outside the buttons', () => {
  for (const page of ['guest', 'buyer']) {
    const label = { hidden: true, textContent: '' };
    const buttons = axes.flatMap(axis => axis.options.map(option => ({
      dataset: { group: axis.name, value: option.value }, disabled: false,
      classList: { toggle() {} }, setAttribute() {},
      closest() { return { querySelectorAll: () => buttons.filter(button => button.dataset.group === axis.name) }; },
    })));
    const config = { ...group, name: 'Options' };
    const document = {
      getElementById(id) {
        if (id === 'pdCombinationData') return { textContent: JSON.stringify(config) };
        if (id === 'pdPrice') return { dataset: { basePrice: '100' } };
        if (id === 'pdSelectedStock') return label;
        return {};
      },
    };
    const window = { changeQty() {}, showProductImage() {}, syncThumbForImage() {} };
    const template = fs.readFileSync(require('node:path').join(__dirname, '../resources/views/', page, 'product.blade.php'), 'utf8');
    const body = template.match(/function updateSelectedStock\(\) \{([\s\S]*?)\n\}/)[1];
    const display = new Function('document', 'selectedPurchaseOption', body);
    window.updatePriceDisplay = () => display(document, () => window.productCombinations.selected());
    vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../public/js/product-combinations.js'), 'utf8'), { window, document });
    const api = window.productCombinations;
    api.select(buttons[0]); // Pink only
    assert.equal(label.hidden, true);
    api.select(buttons[2]); // Pink / S
    assert.equal(label.hidden, false);
    assert.equal(label.textContent, '3 available');
    assert.equal(api.selected().dataset.value, 'Pink / S');
    api.select(buttons[3]); // Pink / M
    assert.equal(label.textContent, '5 available');
    api.select(buttons[1]); // Blue / M
    assert.equal(label.textContent, '2 available');
    api.select(buttons[2]); // Blue / S
    assert.equal(label.textContent, 'Out of stock');
    assert.equal(api.selected().disabled, true);
  }
});
