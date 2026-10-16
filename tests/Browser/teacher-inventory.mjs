import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020'; assert.equal(origin, 'http://127.0.0.1:8020');
const fixture = action => JSON.parse(execFileSync('php', ['tests/Browser/cart-basics-fixture.php', action], { encoding: 'utf8' }));
const initial = fixture('prepare'), browser = await chromium.launch({ channel: 'chrome', headless: true });
const buyer = await browser.newPage(), other = await (await browser.newContext()).newPage(), seller = await (await browser.newContext()).newPage(), seller2 = await (await browser.newContext()).newPage();
const results = [], errors = []; let firstOrder;
for (const page of [buyer, other, seller, seller2]) { page.setDefaultTimeout(30000); page.on('pageerror', error => errors.push(error.message)); }
const basket = initial.products.find(product => product.name === 'Rattan Fruit Basket'), last = initial.products.find(product => product.name === 'Cart Basics Last Unit');
const stock = product => fixture('inspect').products.find(item => item.id === product.id).stock;
async function login(page, identity, role) {
  await page.goto(`${origin}/login`); await page.locator('#email').fill(`cart.basics.${identity}@example.com`); await page.locator('#password').fill('CartBasics@2026');
  await Promise.all([page.waitForURL(`**/${role}/dashboard`), page.locator('form.auth-form button[type=submit]').click()]);
}
async function purchase(product, quantity) {
  const before = fixture('inspect').orders.map(order => order.id);
  await buyer.goto(`${origin}/buyer/product/${product.id}`); await buyer.locator('#pdQty').fill(String(quantity));
  await Promise.all([buyer.waitForURL('**/buyer/cart?checkout_item=*'), buyer.locator('#pdBuyNowBtn').click()]);
  await buyer.locator('#orderConfirmModal.open').waitFor();
  await Promise.all([buyer.waitForEvent('domcontentloaded'), buyer.locator('#orderConfirmButton').click()]);
  const added = fixture('inspect').orders.filter(order => !before.includes(order.id)); assert.equal(added.length, 1); assert.equal(added[0].status, 'placed'); return added[0];
}
async function run(cases, task, evidence) {
  try { await task(); results.push({ cases, status: 'PASS', evidence }); console.log(`${cases.join(', ')} PASS`); }
  catch (error) { results.push({ cases, status: 'FAIL', evidence: error.message }); process.exitCode = 1; console.log(`${cases.join(', ')} FAIL: ${error.message}`); await buyer.screenshot({ path: `docs/teacher-test-audit/failure-${cases[0]}-inventory.png`, fullPage: true }); }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/inventory-browser.json', JSON.stringify(results, null, 2));
}
try {
  await login(buyer, 'buyer', 'buyer'); await login(other, 'other', 'buyer'); await login(seller, 'seller1', 'seller'); await login(seller2, 'seller2', 'seller');
  await run(['SEL-INV-001'], async () => {
    assert.equal(stock(basket), 20); firstOrder = await purchase(basket, 2); assert.equal(stock(basket), 18);
    await seller.goto(`${origin}/seller/inventory`); assert.match(await seller.locator(`tr:has([name="ids[]"][value="${basket.id}"])`).innerText(), /\b18\b/);
  }, 'Chrome/MySQL: buyer explicitly placed a COD order for two units from stock 20; immediately committed stock and refreshed seller inventory both show exactly 18.');
  await run(['SEL-INV-003'], async () => {
    assert.ok(firstOrder); await buyer.goto(`${origin}/buyer/orders`); await buyer.locator(`[data-order-details="orderDetails${firstOrder.id}"]`).click();
    const detail = buyer.locator(`#orderDetails${firstOrder.id}`); await detail.locator('.order-cancel-open').click(); await detail.locator('[name=cancellation_reason]').selectOption({ label: 'Changed my mind' });
    await detail.locator('.order-cancel-form button[type=submit]').click();
    await Promise.all([buyer.waitForEvent('domcontentloaded'), buyer.locator(`#cancelConfirm${firstOrder.id} [data-confirm-cancellation]`).click()]);
    assert.equal(fixture('inspect').orders.find(order => order.id === firstOrder.id).status, 'cancelled'); assert.equal(stock(basket), 20);
  }, 'Chrome/MySQL: cancelled the actual preceding two-unit COD order through its reason/review/confirmation UI; order became Cancelled and stock returned exactly to 20.');
  await run(['SEL-INV-007'], async () => {
    assert.equal(stock(last), 1); await purchase(last, 1); assert.equal(stock(last), 0);
    await other.goto(`${origin}/buyer/product/${last.id}`); assert.match(await other.locator('body').innerText(), /Sold out/i); assert.equal(await other.locator('#pdAddCartBtn').isDisabled(), true); assert.equal(await other.locator('#pdBuyNowBtn').isDisabled(), true);
    await other.goto(`${origin}/buyer/browse?q=Cart%20Basics%20Last%20Unit`); const card = other.locator(`.product-card[onclick*="${last.id}"]`); assert.match(await card.innerText(), /Sold out/i);
    await seller2.goto(`${origin}/seller/inventory`); assert.match(await seller2.locator(`tr:has([name="ids[]"][value="${last.id}"])`).innerText(), /Out of Stock/);
  }, 'Chrome/MySQL: bought the only remaining unit; separate Buyer 2 saw Sold out in search and product, purchase buttons disabled; Seller 2 inventory showed Out of Stock.');
  await run(['SEL-INV-008'], async () => {
    await seller.goto(`${origin}/seller/inventory`); await seller.locator(`[data-modal="addStockModal-${basket.id}"]`).click();
    const form = seller.locator(`#addStockModal-${basket.id} form[action$="/stock"]`); await form.locator('[name=stock]').fill('7');
    await Promise.all([seller.waitForEvent('domcontentloaded'), form.locator('button[type=submit]').click()]); const baseline = fixture('inspect').low_stock_alerts;
    for (const [remaining, alerts] of [[6, baseline], [5, baseline + 1], [4, baseline + 1]]) { await purchase(basket, 1); assert.equal(stock(basket), remaining); assert.equal(fixture('inspect').low_stock_alerts, alerts); }
    await seller.goto(`${origin}/seller/notifications`); assert.match(await seller.locator('body').innerText(), /Low stock/);
  }, 'Chrome/MySQL: three real confirmed COD purchases moved stock 7→6→5→4; no new low-stock alert at 6, exactly one at 5, no duplicate at 4; seller notification page displayed the alert.');
  await run(['BUY-PDP-008'], async () => {
    const inactive = initial.products.find(product => product.status === 'archived'); const guest = await (await browser.newContext()).newPage();
    for (const id of [inactive.id, initial.deleted_id]) {
      for (const [page, prefix] of [[buyer, '/buyer'], [guest, '']]) {
        const response = await page.goto(`${origin}${prefix}/product/${id}`); assert.equal(response.status(), 404); assert.match(await page.locator('body').innerText(), /Product unavailable/); assert.equal(await page.locator('#pdAddCartBtn').count(), 0); assert.doesNotMatch(await page.locator('body').innerText(), /exception|stack trace|Server Error/i);
      }
    }
    await guest.close();
  }, 'Chrome/MySQL: inactive and actually deleted fixture product URLs show a clear Product unavailable page with no purchase button or exception; checked both buyer and guest URLs.');
} finally {
  if (errors.length) { results.push({ status: 'FAIL', evidence: errors.join('; ') }); process.exitCode = 1; }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/inventory-browser.json', JSON.stringify(results, null, 2)); await browser.close();
}
