import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
assert.equal(origin, 'http://127.0.0.1:8020');
const fixture = action => JSON.parse(execFileSync('php', ['tests/Browser/store-category-fixture.php', action], { encoding: 'utf8' }));
const initial = fixture('vacation-prepare');
const product = initial.products.find(product => product.name === 'Rattan Fruit Basket');
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const sellerContext = await browser.newContext(), buyerContext = await browser.newContext();
const seller = await sellerContext.newPage(), buyer = await buyerContext.newPage();
const results = [], errors = [];
for (const page of [seller, buyer]) { page.setDefaultTimeout(10000); page.on('pageerror', error => errors.push(error.message)); }
async function login(page, email, password, role) {
  await page.goto(`${origin}/login`); await page.locator('#email').fill(email); await page.locator('#password').fill(password);
  await Promise.all([page.waitForURL(`**/${role}/dashboard`), page.locator('form.auth-form button[type=submit]').click()]);
}
async function toggle(value) {
  await seller.goto(`${origin}/seller/account`);
  const form = seller.locator('form[action$="/account/vacation"]');
  await form.locator('[type=checkbox]').setChecked(value);
  await Promise.all([seller.waitForEvent('load'), form.locator('button').click()]);
  assert.equal(fixture('inspect').vacation, value);
}
async function checkout() {
  await buyer.goto(`${origin}/buyer/cart`);
  await buyer.locator('#checkoutButton').click();
  await Promise.all([buyer.waitForEvent('load'), buyer.locator('#orderConfirmButton').click()]);
}
try {
  await login(seller, 'category.audit1@example.com', 'CategoryAudit@2026', 'seller');
  await login(buyer, 'vacation.audit.buyer@example.com', 'VacationAudit@2026', 'buyer');
  await toggle(true);
  await buyer.goto(`${origin}/buyer/shop/store_category_audit_1`);
  assert.match(await buyer.textContent('body'), /Rattan Fruit Basket/); assert.match(await buyer.textContent('body'), /On vacation/);
  await buyer.goto(`${origin}/buyer/product/${product.id}`);
  assert.match(await buyer.textContent('body'), /On vacation/);
  assert.equal(await buyer.locator('#pdAddCartBtn').isDisabled(), true); assert.equal(await buyer.locator('#pdBuyNowBtn').isDisabled(), true);
  const token = await buyer.locator('meta[name=csrf-token]').getAttribute('content');
  const denied = await buyer.request.post(`${origin}/buyer/cart/add`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, data: { product_id: product.id, qty: 1 } });
  assert.equal(denied.status(), 422); assert.match(await denied.text(), /vacation/);
  await buyer.goto(`${origin}/buyer/cart`);
  assert.equal(await buyer.locator('.cart-select').isDisabled(), true);
  assert.equal(await buyer.locator('#checkoutButton').isDisabled(), true);
  assert.match(await buyer.locator('body').innerText(), /on vacation/);
  assert.equal(fixture('inspect').vacation_orders, initial.vacation_orders);
  assert.equal(fixture('inspect').products.find(item => item.id === product.id).stock, 20);
  const search = await browser.newPage();
  await search.goto(`${origin}/?q=Rattan%20Fruit%20Basket`);
  assert.match(await search.textContent('body'), /Rattan Fruit Basket/); assert.match(await search.textContent('body'), /On vacation/); await search.close();
  results.push({ cases: ['SEL-PROF-009', 'BUY-SRCH-011'], status: 'PASS', evidence: 'Chrome/MySQL: enabled vacation in seller settings; store, search and product remained visible with vacation label; Add to Cart/Buy Now disabled; direct add and existing-cart COD checkout blocked; stock/order count unchanged. Separate PHP checks also block GCash reservation and placement.' });
  await toggle(false); await buyer.goto(`${origin}/buyer/product/${product.id}`);
  assert.doesNotMatch(await buyer.locator('body').innerText(), /On vacation/);
  assert.equal(await buyer.locator('#pdAddCartBtn').isDisabled(), false); assert.equal(await buyer.locator('#pdBuyNowBtn').isDisabled(), false);
  await checkout();
  assert.equal(fixture('inspect').vacation_orders, initial.vacation_orders + 1);
  assert.equal(fixture('inspect').products.find(item => item.id === product.id).stock, 19);
  results.push({ cases: ['SEL-PROF-010'], status: 'PASS', evidence: 'Chrome/MySQL: disabled vacation; label disappeared and purchase buttons enabled; confirmed COD checkout created exactly one order and deducted one unit.' });
} catch (error) {
  results.push({ cases: ['SEL-PROF-009', 'SEL-PROF-010', 'BUY-SRCH-011'], status: 'FAIL', evidence: error.message }); process.exitCode = 1;
  await buyer.screenshot({ path: 'docs/teacher-test-audit/failure-vacation.png', fullPage: true }).catch(() => {});
} finally {
  if (errors.length) { results.push({ status: 'FAIL', evidence: errors.join('; ') }); process.exitCode = 1; }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/vacation-browser.json', JSON.stringify(results, null, 2));
  console.log(JSON.stringify(results, null, 2)); await browser.close();
}
