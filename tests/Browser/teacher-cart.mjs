import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
assert.equal(origin, 'http://127.0.0.1:8020');
const fixture = action => JSON.parse(execFileSync('php', ['tests/Browser/cart-fixture.php', action], { encoding: 'utf8' }));
const initial = fixture('prepare'), browser = await chromium.launch({ channel: 'chrome', headless: true });
const seller = await browser.newPage(), buyer = await (await browser.newContext()).newPage(), results = [], errors = [];
for (const page of [seller, buyer]) { page.setDefaultTimeout(30000); page.on('pageerror', error => errors.push(error.message)); }
const target = initial.products.find(product => product.name === 'Rattan Fruit Basket');
const deleted = initial.products.find(product => product.name === 'Cart Audit Deleted Basket');
const available = initial.products.find(product => product.name === 'Cart Audit Available Lamp');
const line = item => buyer.locator('.cart-item').filter({ has: buyer.locator(`.cart-item-name[href$="/product/${item.id}"]`) });
async function login(page, role) {
  await page.goto(`${origin}/login`); await page.locator('#email').fill(`cart.browser.${role}@example.com`); await page.locator('#password').fill('CartAudit@2026');
  await Promise.all([page.waitForURL(`**/${role}/dashboard`), page.locator('form.auth-form button[type=submit]').click()]);
}
async function run(cases, task, evidence) {
  try { await task(); results.push({ cases, status: 'PASS', evidence }); console.log(`${cases.join(', ')} PASS`); }
  catch (error) { results.push({ cases, status: 'FAIL', evidence: error.message }); process.exitCode = 1; console.log(`${cases.join(', ')} FAIL: ${error.message}`); await buyer.screenshot({ path: `docs/teacher-test-audit/failure-${cases[0]}.png`, fullPage: true }); }
}
async function submit(form) { await Promise.all([seller.waitForEvent('domcontentloaded'), form.locator('button[type=submit]').click()]); }
async function checkout(item) {
  await buyer.goto(`${origin}/buyer/cart`);
  for (const checkbox of await buyer.locator('.cart-select:not(:disabled)').all()) await checkbox.uncheck();
  await line(item).locator('.cart-select').check();
  await buyer.locator('#checkoutButton').click();
  await Promise.all([buyer.waitForEvent('domcontentloaded'), buyer.locator('#orderConfirmButton').click()]);
}
try {
  await login(seller, 'seller'); await login(buyer, 'buyer');
  await run(['SEL-PROD-011'], async () => {
    await seller.goto(`${origin}/seller/inventory`);
    const before = fixture('inspect').products.find(product => product.id === target.id);
    await Promise.all([seller.waitForEvent('domcontentloaded'), seller.locator(`[form="archiveForm-${target.id}"]`).click()]);
    if (await seller.locator('#productResultModal').count()) await seller.locator('#productResultModal button').click();
    await buyer.goto(`${origin}/buyer/browse?q=Rattan%20Fruit%20Basket`); assert.equal(await buyer.locator(`.product-card[onclick*="${target.id}"]`).count(), 0);
    await buyer.goto(`${origin}/buyer/browse?category=1`); assert.equal(await buyer.locator(`.product-card[onclick*="${target.id}"]`).count(), 0);
    await buyer.goto(`${origin}/buyer/shop/cart_browser_seller`); assert.equal(await buyer.locator(`.product-card[onclick*="${target.id}"]`).count(), 0);
    assert.equal((await buyer.goto(`${origin}/buyer/product/${target.id}`)).status(), 404);
    await buyer.goto(`${origin}/buyer/cart`); assert.equal(await line(target).locator('.cart-select').isDisabled(), true); assert.match(await line(target).innerText(), /Unavailable/);
    await Promise.all([seller.waitForEvent('domcontentloaded'), seller.locator(`[form="unarchiveForm-${target.id}"]`).click()]);
    const after = fixture('inspect').products.find(product => product.id === target.id);
    for (const key of ['price', 'stock', 'image', 'images']) assert.deepEqual(after[key], before[key]);
    for (const path of [`/buyer/browse?q=Rattan%20Fruit%20Basket`, '/buyer/browse?category=1', '/buyer/shop/cart_browser_seller']) {
      await buyer.goto(origin + path); assert.ok(await buyer.locator(`.product-card[onclick*="${target.id}"]`).count());
    }
    assert.equal((await buyer.goto(`${origin}/buyer/product/${target.id}`)).status(), 200);
    await buyer.goto(`${origin}/buyer/cart`); assert.equal(await line(target).locator('.cart-select').isDisabled(), false);
  }, 'Chrome/MySQL: seller archived/reactivated P-02; inactive search/category/store and URL blocked, cart disabled; reactivation restored every surface with unchanged price/stock/images.');
  await run(['BUY-CART-006', 'SEL-PROD-013'], async () => {
    await seller.goto(`${origin}/seller/inventory`); await seller.locator(`[data-modal="viewProductModal-${target.id}"]`).click();
    await seller.locator(`#viewProductModal-${target.id} [aria-label="Edit product"]`).click();
    const form = seller.locator(`#editProductModal-${target.id} form`); await form.locator('[name=price]').fill('420.00');
    // Existing imported listings can lack photos; supply a real image before publishing the edit.
    await form.locator('[name="images[]"]').setInputFiles({ name: 'basket.jpg', mimeType: 'image/jpeg', buffer: await seller.screenshot({ type: 'jpeg' }) });
    await Promise.all([seller.waitForEvent('domcontentloaded'), form.locator('button[value=publish]').click()]);
    await buyer.goto(`${origin}/buyer/cart`); assert.match(await line(target).innerText(), /420\.00 each/); assert.doesNotMatch(await line(target).innerText(), /450\.00/);
    for (const checkbox of await buyer.locator('.cart-select').all()) await checkbox.uncheck(); await line(target).locator('.cart-select').check();
    assert.match(await buyer.locator('#cartSubtotal').innerText(), /420\.00/);
    await checkout(target);
    const orders = fixture('inspect').orders.filter(order => !initial.orders.some(old => old.id === order.id)); assert.equal(orders.length, 1);
    assert.equal(Number(orders[0].items[0].price), 420); assert.equal(Number(orders[0].subtotal), 420); assert.equal(Number(orders[0].total), 420);
  }, 'Chrome/MySQL: seller changed P-02 from 450 to 420 through product edit; refreshed cart line/subtotal and confirmed COD order all use 420, with exactly one order.');
  await run(['BUY-CART-007'], async () => {
    fixture('restore-cart');
    await seller.goto(`${origin}/seller/inventory`); await seller.locator(`[data-modal="addStockModal-${target.id}"]`).click();
    const stockForm = seller.locator(`#addStockModal-${target.id} form[action$="/stock"]`); await stockForm.locator('[name=stock]').fill('0'); await submit(stockForm);
    const token = await seller.locator('input[name=_token]').first().inputValue();
    const response = await seller.request.post(`${origin}/seller/inventory/${deleted.id}`, { form: { _token: token, _method: 'DELETE' } }); assert.equal(response.status(), 200);
    await buyer.goto(`${origin}/buyer/cart`);
    assert.equal(await line(target).locator('.cart-select').isDisabled(), true); assert.match(await line(target).innerText(), /Out of stock/);
    assert.equal(await line(deleted).locator('.cart-select').isDisabled(), true); assert.match(await line(deleted).innerText(), /Unavailable/);
    await checkout(available);
    const orders = fixture('inspect').orders.filter(order => !initial.orders.some(old => old.id === order.id));
    assert.ok(orders.some(order => order.items.some(item => item.product_id === available.id)));
  }, 'Chrome/MySQL: zero-stock product and deleted product saved in the cart are both visibly unavailable and disabled; deleted saved cart line is disabled; available lamp completed COD checkout.');
  await run(['SEL-INV-009'], async () => {
    for (const value of ['-1', '0', '99999', '100000', '2.5', 'abc']) {
      await seller.goto(`${origin}/seller/inventory`); await seller.locator(`[data-modal="addStockModal-${target.id}"]`).click();
      const form = seller.locator(`#addStockModal-${target.id} form[action$="/stock"]`), field = form.locator('[name=stock]');
      const before = fixture('inspect').products.find(product => product.id === target.id).stock;
      if (value === 'abc') { await field.fill(''); await field.pressSequentially(value); } else await field.fill(value);
      if (['0', '99999'].includes(value)) {
        await submit(form); assert.equal(fixture('inspect').products.find(product => product.id === target.id).stock, Number(value));
      } else {
        await form.locator('button[type=submit]').click(); assert.equal(await field.evaluate(input => input.checkValidity()), false);
        assert.ok(await field.evaluate(input => input.validationMessage)); assert.equal(fixture('inspect').products.find(product => product.id === target.id).stock, before);
        const token = await form.locator('[name=_token]').inputValue();
        const rejected = await seller.request.patch(`${origin}/seller/inventory/${target.id}/stock`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, data: { stock: value } });
        assert.equal(rejected.status(), 422); assert.ok((await rejected.json()).errors.stock); assert.equal(fixture('inspect').products.find(product => product.id === target.id).stock, before);
      }
    }
  }, 'Chrome/MySQL: exactly 0 and 99,999 saved via stock form; -1, 100,000, 2.5 and alphabetic typing show native validation and preserve stock; direct requests for each invalid value also return field-specific 422 without changes.');
  await run(['SEL-INV-010'], async () => {
    fixture('quantity-five');
    await seller.goto(`${origin}/seller/inventory`); await seller.locator(`[data-modal="addStockModal-${target.id}"]`).click();
    const form = seller.locator(`#addStockModal-${target.id} form[action$="/stock"]`); await form.locator('[name=stock]').fill('3'); await submit(form);
    const before = fixture('inspect').orders.length;
    await buyer.goto(`${origin}/buyer/cart`); assert.match(await line(target).innerText(), /Only 3 left/); assert.equal(await line(target).locator('.cart-select').isDisabled(), true); assert.equal(await buyer.locator('#checkoutButton').isDisabled(), true);
    const token = await buyer.locator('meta[name=csrf-token]').getAttribute('content'), cart = fixture('inspect').cart.find(item => item.product_id === target.id);
    const payment = await buyer.locator('[name=cart_payment_choice][data-kind=cod]').first().inputValue();
    await buyer.request.post(`${origin}/buyer/checkout`, { form: { _token: token, 'items[]': cart.id, payment_method: payment } });
    assert.equal(fixture('inspect').orders.length, before); assert.equal(fixture('inspect').products.find(product => product.id === target.id).stock, 3);
    await buyer.reload(); await line(target).locator('[data-edit-key]').click(); await buyer.locator('#editQty').fill('3');
    await Promise.all([buyer.waitForEvent('domcontentloaded'), buyer.locator('#itemEditForm button[type=submit]').click()]);
    assert.equal(await line(target).locator('.cart-select').isDisabled(), false); assert.equal(await buyer.locator('#checkoutButton').isDisabled(), false);
  }, 'Chrome/MySQL: stock 3 against saved quantity 5 shows Only 3 left and blocks selection/checkout; forged checkout creates no order or stock movement; editing quantity down to 3 enables selection and checkout.');

} finally {
  if (errors.length) { results.push({ status: 'FAIL', evidence: errors.join('; ') }); process.exitCode = 1; }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/cart-browser.json', JSON.stringify(results, null, 2)); await browser.close();
}
