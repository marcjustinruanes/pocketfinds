import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
assert.equal(origin, 'http://127.0.0.1:8020');
const fixture = action => JSON.parse(execFileSync('php', ['tests/Browser/wishlist-fixture.php', action], { encoding: 'utf8' }));
const initial = fixture('prepare'), browser = await chromium.launch({ channel: 'chrome', headless: true });
const buyer = await browser.newPage(), seller = await (await browser.newContext()).newPage(), guest = await (await browser.newContext()).newPage();
const otherSeller = await (await browser.newContext()).newPage();
const results = [], errors = [];
for (const page of [buyer, seller, otherSeller, guest]) { page.setDefaultTimeout(30000); page.on('pageerror', error => errors.push(error.message)); }
const basket = initial.products.find(product => product.name === 'Rattan Fruit Basket'), shirt = initial.products.find(product => product.seller_id !== initial.seller_id);
const heart = () => buyer.locator('[data-wishlist-product]');
async function login(page, role) {
  await page.goto(`${origin}/login`); await page.locator('#email').fill(`wishlist.audit.${role}@example.com`); await page.locator('#password').fill('WishlistAudit@2026');
  await Promise.all([page.waitForURL(`**/${role.startsWith('seller') ? 'seller' : 'buyer'}/dashboard`), page.locator('form.auth-form button[type=submit]').click()]);
}
async function clickHeart(button) { await Promise.all([buyer.waitForResponse(response => response.url().includes('/wishlist/') && response.request().method() === 'PUT'), button.click()]); }
async function run(cases, task, evidence) {
  try { await task(); results.push({ cases, status: 'PASS', evidence }); console.log(`${cases.join(', ')} PASS`); }
  catch (error) { results.push({ cases, status: 'FAIL', evidence: error.message }); process.exitCode = 1; console.log(`${cases.join(', ')} FAIL: ${error.message}`); await buyer.screenshot({ path: `docs/teacher-test-audit/failure-${cases[0]}.png`, fullPage: true }); }
}
try {
  await login(buyer, 'buyer'); await login(seller, 'seller'); await login(otherSeller, 'seller_other');
  await run(['BUY-WISH-001'], async () => {
    await buyer.goto(`${origin}/buyer/product/${basket.id}`); assert.equal(await heart().getAttribute('aria-pressed'), 'false');
    await clickHeart(heart()); await buyer.waitForFunction(() => document.querySelector('[data-wishlist-product]').getAttribute('aria-pressed') === 'true');
    await buyer.goto(`${origin}/buyer/wishlist`); assert.equal(await buyer.locator(`[data-wishlist-entry="${basket.id}"]`).count(), 1);
    await clickHeart(heart()); await buyer.reload(); assert.equal(await buyer.locator(`[data-wishlist-entry="${basket.id}"]`).count(), 0);
    await buyer.goto(`${origin}/buyer/product/${basket.id}`); assert.equal(await heart().getAttribute('aria-pressed'), 'false');
  }, 'Chrome/MySQL: saved P-02 once, wishlist showed one entry; removal persisted after reload and product heart returned to unsaved.');
  await run(['BUY-WISH-002'], async () => {
    await Promise.all([buyer.waitForResponse(response => response.url().includes('/wishlist/') && response.request().method() === 'PUT'), heart().evaluate(button => { button.click(); button.click(); })]);
    await buyer.reload(); const saved = await heart().getAttribute('aria-pressed') === 'true';
    const entries = fixture('inspect').wishlist.filter(entry => entry.product_id === basket.id); assert.ok(entries.length <= 1); assert.equal(entries.length === 1, saved);
    await buyer.goto(`${origin}/buyer/wishlist`); assert.equal(await buyer.locator(`[data-wishlist-entry="${basket.id}"]`).count(), entries.length);
  }, 'Chrome/MySQL: two immediate heart clicks created at most one entry; refreshed product heart, persisted row count and wishlist state agree.');
  await run(['BUY-WISH-003'], async () => {
    await buyer.goto(`${origin}/buyer/product/${shirt.id}`); await clickHeart(heart());
    await seller.goto(`${origin}/seller/inventory`); await seller.locator(`[data-modal="addStockModal-${basket.id}"]`).click();
    const stockForm = seller.locator(`#addStockModal-${basket.id} form[action$="/stock"]`); await stockForm.locator('[name=stock]').fill('0');
    await Promise.all([seller.waitForEvent('domcontentloaded'), stockForm.locator('button[type=submit]').click()]);
    await otherSeller.goto(`${origin}/seller/inventory`);
    await otherSeller.locator(`#removeForm-${shirt.id}`).locator('..').getByRole('button', { name: 'Remove', exact: true }).click();
    await Promise.all([otherSeller.waitForEvent('domcontentloaded'), otherSeller.locator('#removeConfirmBtn').click()]);
    await buyer.goto(`${origin}/buyer/wishlist`);
    const sold = buyer.locator(`[data-wishlist-entry="${basket.id}"]`), removed = buyer.locator(`[data-wishlist-entry="${shirt.id}"]`);
    assert.match(await sold.innerText(), /Sold out/); assert.equal(await sold.getByRole('button', { name: 'Add to Cart', exact: true }).isDisabled(), true);
    assert.match(await removed.innerText(), /Unavailable/); assert.equal(await removed.getByRole('button', { name: 'Add to Cart', exact: true }).count(), 0); assert.equal(await removed.getByRole('link', { name: /Add to Cart/ }).count(), 0);
    assert.equal(fixture('inspect').wishlist.length, 2); assert.equal(errors.length, 0);
  }, 'Chrome/MySQL: seller set saved P-02 stock to zero and deleted another saved listing through actual routes; wishlist retained both snapshots, showed Sold out / Unavailable and disabled purchase without a page error.');
  await run(['BUY-WISH-004'], async () => {
    await buyer.goto(`${origin}/buyer/shop/wishlist_audit_seller`); const button = buyer.locator('#shopFollowBtn');
    await Promise.all([buyer.waitForResponse(response => response.url().endsWith('/follow')), button.click()]);
    await buyer.waitForFunction(() => document.querySelector('#shopFollowBtn').textContent.includes('Following'));
    assert.equal(Number(await buyer.locator('#shopFollowersCount').innerText()), initial.followers + 1);
    await buyer.goto(`${origin}/buyer/following`); assert.equal(await buyer.locator(`[data-following-seller="${initial.seller_id}"]`).count(), 1);
    await buyer.goto(`${origin}/buyer/shop/wishlist_audit_seller`);
    await Promise.all([buyer.waitForResponse(response => response.url().endsWith('/follow')), buyer.locator('#shopFollowBtn').click()]);
    await buyer.waitForFunction(() => !document.querySelector('#shopFollowBtn').classList.contains('is-following'));
    assert.equal(Number(await buyer.locator('#shopFollowersCount').innerText()), initial.followers);
    await buyer.goto(`${origin}/buyer/following`); assert.equal(await buyer.locator(`[data-following-seller="${initial.seller_id}"]`).count(), 0);
  }, 'Chrome/MySQL: Follow changed count N→N+1 and added store to Following; Unfollow restored N and removed the store from Following.');
  await run(['BUY-WISH-005'], async () => {
    const before = fixture('inspect');
    await guest.goto(`${origin}/product/${basket.id}`);
    await Promise.all([guest.waitForURL('**/login'), guest.getByRole('link', { name: 'Add to wishlist', exact: true }).click()]);
    await guest.goto(`${origin}/shop/wishlist_audit_seller`);
    await Promise.all([guest.waitForURL('**/login'), guest.getByRole('link', { name: 'Follow', exact: true }).click()]);
    const after = fixture('inspect'); assert.deepEqual(after.wishlist, before.wishlist); assert.deepEqual(after.following, before.following); assert.equal(after.followers, before.followers);
    for (const path of ['/buyer/wishlist', '/buyer/following']) { const response = await guest.request.get(origin + path); assert.ok(response.url().endsWith('/login')); }
  }, 'Chrome/MySQL: guest wishlist and Follow both navigated to login; direct saved-page access also required login; persisted wishlist/follow rows and counts unchanged.');
} finally {
  if (errors.length) { results.push({ status: 'FAIL', evidence: errors.join('; ') }); process.exitCode = 1; }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/wishlist-browser.json', JSON.stringify(results, null, 2)); await browser.close();
}
