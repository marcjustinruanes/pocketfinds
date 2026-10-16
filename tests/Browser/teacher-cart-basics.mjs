import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
assert.equal(origin, 'http://127.0.0.1:8020');
const fixture = action => JSON.parse(execFileSync('php', ['tests/Browser/cart-basics-fixture.php', action], { encoding: 'utf8' }));
const initial = fixture('prepare'), browser = await chromium.launch({ channel: 'chrome', headless: true });
const buyer = await browser.newPage(), results = [], errors = [];
buyer.setDefaultTimeout(30000); buyer.on('pageerror', error => errors.push(error.message));
const basket = initial.products.find(product => product.name === 'Rattan Fruit Basket'), shirt = initial.products.find(product => product.name === 'Cart Basics Graphic T-shirt');
const line = (page, product) => page.locator('.cart-item').filter({ has: page.locator(`.cart-item-name[href$="/product/${product.id}"]`) });
async function login(page) {
  await page.goto(`${origin}/login`); await page.locator('#email').fill('cart.basics.buyer@example.com'); await page.locator('#password').fill('CartBasics@2026');
  await Promise.all([page.waitForURL('**/buyer/dashboard'), page.locator('form.auth-form button[type=submit]').click()]);
}
async function add(product, qty) {
  await buyer.goto(`${origin}/buyer/product/${product.id}`); await buyer.locator('#pdQty').fill(String(qty));
  const [response] = await Promise.all([buyer.waitForResponse(response => response.url().endsWith('/cart/add')), buyer.locator('#pdAddCartBtn').click()]); assert.equal(response.status(), 200);
}
async function remove(product) {
  await buyer.goto(`${origin}/buyer/cart`); await line(buyer, product).locator('.cart-remove-form button').click();
  await Promise.all([buyer.waitForEvent('domcontentloaded'), buyer.locator('#removeItemConfirmButton').click()]);
}
async function run(cases, task, evidence) {
  try { await task(); results.push({ cases, status: 'PASS', evidence }); console.log(`${cases.join(', ')} PASS`); }
  catch (error) { results.push({ cases, status: 'FAIL', evidence: error.message }); process.exitCode = 1; console.log(`${cases.join(', ')} FAIL: ${error.message}`); await buyer.screenshot({ path: `docs/teacher-test-audit/failure-${cases[0]}.png`, fullPage: true }); }
}
try {
  await login(buyer);
  await run(['BUY-PDP-004'], async () => {
    for (const value of ['0', '-1', '1', '20', '21', '1.5', 'abc']) {
      await buyer.goto(`${origin}/buyer/product/${basket.id}`); const input = buyer.locator('#pdQty');
      if (value === 'abc') { await input.fill(''); await input.pressSequentially('abc'); } else await input.fill(value);
      const before = fixture('inspect').cart;
      if (['1', '20'].includes(value)) {
        const [response] = await Promise.all([buyer.waitForResponse(response => response.url().endsWith('/cart/add')), buyer.locator('#pdAddCartBtn').click()]); assert.equal(response.status(), 200);
        assert.equal(fixture('inspect').cart.find(item => item.product_id === basket.id).qty, Number(value));
        if (value === '1') await remove(basket);
      } else {
        await buyer.locator('#pdAddCartBtn').click(); assert.equal(await input.evaluate(input => input.checkValidity()), false); assert.ok(await input.evaluate(input => input.validationMessage));
        assert.deepEqual(fixture('inspect').cart, before);
      }
    }
    await buyer.locator('#pdQty').fill('20'); await buyer.locator('[onclick="changeQty(1)"]').click(); assert.equal(await buyer.locator('#pdQty').inputValue(), '20');
  }, 'Chrome/MySQL: quantities 0, -1, 21, 1.5 and alphabetic typing show validation and do not change cart; 1 and 20 each add exactly that quantity in separate attempts; plus stops at available 20.');
  await run(['BUY-CART-003'], async () => {
    await add(shirt, 1); await buyer.goto(`${origin}/buyer/cart`);
    assert.equal(await buyer.locator('.cart-shop').count(), 2);
    for (const product of [basket, shirt]) {
      const shop = line(buyer, product).locator('xpath=ancestor::section[contains(@class,"cart-shop")]');
      assert.equal(await shop.locator('.cart-item').count(), 1); assert.match(await shop.locator('.cart-shop-head').innerText(), /Cart Basics Store/);
    }
  }, 'Chrome/MySQL: products from two sellers render in exactly two named store groups, with only the correct item in each group.');
  await run(['BUY-CART-005'], async () => {
    for (const value of ['0', '1', '20', '21']) {
      await buyer.goto(`${origin}/buyer/cart`); await line(buyer, basket).locator('[data-edit-key]').click(); await buyer.locator('#editQty').fill(value);
      const before = fixture('inspect').cart.find(item => item.product_id === basket.id).qty;
      if (['1', '20'].includes(value)) {
        await Promise.all([buyer.waitForEvent('domcontentloaded'), buyer.locator('#itemEditForm button[type=submit]').click()]);
        assert.equal(fixture('inspect').cart.find(item => item.product_id === basket.id).qty, Number(value));
      } else {
        await buyer.locator('#itemEditForm button[type=submit]').click(); assert.equal(await buyer.locator('#editQty').evaluate(input => input.checkValidity()), false);
        assert.equal(fixture('inspect').cart.find(item => item.product_id === basket.id).qty, before);
        if (value === '21') assert.match(await buyer.locator('#editQtyError').innerText(), /Only 20/i);
      }
    }
  }, 'Chrome/MySQL: cart editor rejects 0 and 21, saves exactly 1 and 20, and displays Only 20 for an overstock quantity while preserving the previous value.');
  await run(['BUY-CART-008'], async () => {
    const expected = fixture('inspect').cart.map(item => ({ id: item.product_id, qty: item.qty })); assert.equal(expected.length, 2);
    await buyer.goto(`${origin}/buyer/cart`); const token = await buyer.locator('meta[name=csrf-token]').getAttribute('content');
    await buyer.request.post(`${origin}/buyer/logout`, { form: { _token: token } }); await login(buyer); await buyer.goto(`${origin}/buyer/cart`);
    const second = await (await browser.newContext()).newPage(); second.setDefaultTimeout(30000); await login(second); await second.goto(`${origin}/buyer/cart`);
    for (const page of [buyer, second]) {
      assert.equal(await page.locator('.cart-item').count(), 2);
      for (const item of expected) assert.match(await line(page, item).innerText(), new RegExp(`Qty ${item.qty}\\b`));
    }
    await second.close();
  }, 'Chrome/MySQL: same two saved items/quantities survived logout/login and appeared unchanged in a separate browser context.');
  await run(['BUY-CART-010'], async () => {
    const before = fixture('inspect').other_cart, token = await buyer.locator('meta[name=csrf-token]').getAttribute('content');
    for (const suffix of ['', '/edit']) {
      const response = await buyer.request.patch(`${origin}/buyer/cart/${before[0].id}${suffix}`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, data: { qty: 5 } }); assert.equal(response.status(), 404);
    }
    assert.deepEqual(fixture('inspect').other_cart, before);
  }, 'Chrome/MySQL: forged update and edit requests using Buyer 2 cart ID each return 404 and preserve all Buyer 2 cart rows.');
  await run(['BUY-CART-009'], async () => {
    await remove(basket); await buyer.reload(); assert.equal(await line(buyer, basket).count(), 0); assert.equal(await buyer.locator('.cart-item').count(), 1); assert.match(await buyer.locator('#cartSubtotal').innerText(), /399\.00/);
    assert.equal(fixture('inspect').cart.some(item => item.product_id === basket.id), false);
  }, 'Chrome/MySQL: confirmed deletion removed the line persistently and recalculated the remaining subtotal to 399.00.');
  // The matrix-specific Buy Now workbook case remains separate; this checks the shared path with a plain product.
  await run([], async () => {
    await buyer.goto(`${origin}/buyer/product/${basket.id}`); await buyer.locator('#pdQty').fill('1');
    await Promise.all([buyer.waitForURL('**/buyer/cart?checkout_item=*'), buyer.locator('#pdBuyNowBtn').click()]);
    assert.equal(await line(buyer, basket).locator('.cart-select').isChecked(), true); assert.equal(await line(buyer, shirt).locator('.cart-select').isChecked(), false);
    assert.ok(await buyer.locator('#orderConfirmModal.open').count()); assert.equal(fixture('inspect').orders.length, initial.orders.length);
  }, 'Supporting browser check only: plain-product Buy Now selects that line, leaves unrelated item saved/unselected and opens explicit confirmation without creating an order. Matrix-specific BUY-PDP-007 is not certified by this check.');
} finally {
  if (errors.length) { results.push({ status: 'FAIL', evidence: errors.join('; ') }); process.exitCode = 1; }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/cart-basics-browser.json', JSON.stringify(results, null, 2)); await browser.close();
}
