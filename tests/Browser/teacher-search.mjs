import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
assert.equal(origin, 'http://127.0.0.1:8020');
const fixture = JSON.parse(execFileSync('php', ['tests/Browser/search-fixture.php', 'prepare'], { encoding: 'utf8' }));
const browser = await chromium.launch({ channel: 'chrome', headless: true }), page = await browser.newPage();
const results = [], errors = [], dialogs = [];
page.setDefaultTimeout(30000); page.on('pageerror', error => errors.push(error.message));
page.on('dialog', async dialog => { dialogs.push(dialog.message()); await dialog.dismiss(); });
const target = fixture.products.find(product => product.name === 'Rattan Fruit Basket');
const cards = () => page.locator('#browseGrid .product-card');
const ids = () => cards().evaluateAll(cards => cards.map(card => card.getAttribute('onclick').match(/product\/([^']+)/)[1]));
async function browse(query = {}) { const response = await page.goto(`${origin}/buyer/browse?${new URLSearchParams(query)}`); assert.equal(response.status(), 200); }
async function allPages(query) {
  await browse(query); let found = await ids();
  while (await page.getByRole('link', { name: 'Next', exact: true }).count()) {
    await Promise.all([page.waitForEvent('domcontentloaded'), page.getByRole('link', { name: 'Next', exact: true }).click()]); found.push(...await ids());
  }
  return found;
}
async function run(cases, task, evidence) {
  try { await task(); results.push({ cases, status: 'PASS', evidence }); console.log(`${cases.join(', ')} PASS`); }
  catch (error) { results.push({ cases, status: 'FAIL', evidence: error.message }); process.exitCode = 1; console.log(`${cases.join(', ')} FAIL: ${error.message}`); await page.screenshot({ path: `docs/teacher-test-audit/failure-${cases[0]}.png`, fullPage: true }); }
}
try {
  await page.goto(`${origin}/login`); await page.locator('#email').fill('buyer@example.com'); await page.locator('#password').fill('TeacherAudit@2026');
  await Promise.all([page.waitForURL('**/buyer/dashboard'), page.locator('form.auth-form button[type=submit]').click()]);
  await run(['BUY-SRCH-001'], async () => {
    const found = await allPages({ category: 88101 });
    assert.deepEqual(new Set(found), new Set(fixture.products.filter(product => product.status === 'active' && product.category_id === 88101).map(product => product.id)));
  }, 'Chrome/MySQL: browsed every page of Home & Living; visible IDs exactly match all active fixture products in that category, including P-02; other-category and inactive/draft items excluded.');
  await run(['BUY-SRCH-003'], async () => {
    for (const q of ['Rattan Fruit Basket', 'rattan', 'RATTAN', '  rattan  ']) { await browse({ q }); assert.ok((await ids()).includes(target.id)); }
  }, 'Chrome/MySQL: exact, partial, uppercase and padded workbook searches all returned Rattan Fruit Basket.');
  await run(['BUY-SRCH-004'], async () => {
    await browse({ q: 'Ratan baskit' }); assert.match(await page.locator('body').innerText(), /Did you mean/);
    const suggestion = page.getByRole('link', { name: /Rattan Fruit Basket/i }); assert.ok(await suggestion.count());
    await Promise.all([page.waitForEvent('domcontentloaded'), suggestion.first().click()]); assert.ok((await ids()).includes(target.id));
    await browse({ q: 'zzqxw' }); assert.equal(await cards().count(), 0); assert.match(await page.locator('body').innerText(), /No products found/);
  }, 'Chrome/MySQL: typo produced a usable Rattan Fruit Basket suggestion; following it found P-02; zzqxw returned a clear empty state with HTTP 200.');
  await run(['BUY-SRCH-005'], async () => {
    for (const q of ['%', "' OR '1'='1", '<script>alert(1)</script>', '"><img src=x onerror=alert(1)>']) {
      await browse({ q }); assert.equal(await cards().count(), 0); assert.equal(await page.locator('form.filter-bar input[name=q]').inputValue(), q);
    }
    assert.deepEqual(dialogs, []); assert.deepEqual(errors, []);
  }, 'Chrome/MySQL: all four workbook injection/special-character searches returned no products and escaped their literal input; no dialog, script execution or page error occurred.');
  await run(['BUY-SRCH-006'], async () => {
    for (const q of ['', '   ']) { await browse({ q }); assert.ok(await cards().count()); assert.doesNotMatch(await page.locator('body').innerText(), /Server Error/); }
  }, 'Chrome/MySQL: empty and spaces-only queries displayed normal listings without errors.');
  await run(['BUY-SRCH-007'], async () => {
    for (const [min, max, includes] of [[300, 450, true], [451, 1000, false], [450, 450, true]]) {
      const found = await allPages({ category: 88101, min_price: min, max_price: max });
      assert.equal(found.includes(target.id), includes);
      for (const id of found) { const price = Number(fixture.products.find(product => product.id === id).price); assert.ok(price >= min && price <= max); }
      if (min === 450) assert.deepEqual(found, [target.id]);
    }
    for (const data of [{ min_price: '1000', max_price: '100' }, { min_price: '-1' }]) {
      await browse({ category: 88101 });
      for (const [key, value] of Object.entries(data)) await page.locator(`[name=${key}]`).fill(value);
      const form = page.locator('form.filter-bar');
      if (await form.evaluate(form => form.checkValidity())) {
        await Promise.all([page.waitForEvent('domcontentloaded'), form.getByRole('button', { name: 'Apply filters' }).click()]);
        assert.ok(await page.getByRole('alert').count()); assert.ok((await ids()).includes(target.id) || await page.getByRole('link', { name: 'Next', exact: true }).count());
      } else assert.equal(await form.locator('[name=min_price]').evaluate(input => input.validity.rangeUnderflow), true);
    }
  }, 'Chrome/MySQL: all three workbook ranges use inclusive endpoints; equal 450 bounds return P-02 only; reversed range reports validation without applying it, negative min is blocked by the form.');
  await run(['BUY-SRCH-008'], async () => {
    const strict = await allPages({ category: 88101, min_rating: 4, location: 'Metro Manila' }); assert.deepEqual(strict, [target.id]);
    const noLocation = await allPages({ category: 88101, min_rating: 4 }); assert.equal(noLocation.length, 2); assert.ok(strict.every(id => noLocation.includes(id)));
    const noRating = await allPages({ category: 88101, location: 'Metro Manila' }); assert.ok(noRating.length > strict.length); assert.ok(strict.every(id => noRating.includes(id)));
    const noCategory = await allPages({ min_rating: 4, location: 'Metro Manila' }); assert.ok(noCategory.length > strict.length); assert.ok(strict.every(id => noCategory.includes(id)));
  }, 'Chrome/MySQL: rating 4+, Home & Living and Metro Manila intersect to P-02 only; independently clearing location, rating and category each widened results and retained P-02.');
  await run(['BUY-SRCH-009'], async () => {
    const categoryProducts = fixture.products.filter(product => product.category_id === 88101 && product.status === 'active');
    for (const sort of ['price_asc', 'price_desc', 'newest', 'best_selling']) {
      const found = await allPages({ category: 88101, sort }); assert.equal(new Set(found).size, found.length); assert.equal(found.length, categoryProducts.length);
      const values = found.map(id => { const product = categoryProducts.find(product => product.id === id); return sort === 'newest' ? Date.parse(product.created_at) : sort === 'best_selling' ? (product.name === 'Rattan Fruit Basket' || product.name === 'Search Audit Bulacan Decor' ? 5 : product.name === 'Search Audit Low Rating' ? 3 : 0) : Number(product.price); });
      for (let i = 1; i < values.length; i++) assert.ok(sort === 'price_asc' ? values[i] >= values[i - 1] : values[i] <= values[i - 1]);
      await page.getByRole('link', { name: 'Previous', exact: true }).click(); await page.waitForLoadState('load');
      assert.equal(new URL(page.url()).searchParams.get('sort'), sort); assert.equal(new URL(page.url()).searchParams.get('category'), '88101');
    }
  }, 'Chrome/MySQL: low/high price, latest and completed-unit best-selling order checked across two pages; all active category IDs occur exactly once; Previous retains category/sort.');
  await run(['BUY-SRCH-010'], async () => {
    const hidden = fixture.products.filter(product => ['archived', 'draft'].includes(product.status));
    hidden.push({ id: 'abcdef01-1234-4234-8234-123456789012', name: 'Search Audit Deleted' });
    for (const item of hidden) {
      await browse({ q: item.name }); assert.equal(await cards().count(), 0);
      assert.equal((await page.goto(`${origin}/buyer/product/${item.id}`)).status(), 404);
    }
    const category = await allPages({ category: 88101 }); for (const item of hidden) assert.equal(category.includes(item.id), false);
    await page.goto(`${origin}/buyer/shop/search_browser_manila`);
    for (const item of hidden) assert.equal(await page.locator(`.product-card[onclick*="${item.id}"]`).count(), 0);
  }, 'Chrome/MySQL: inactive, draft and actually deleted products absent from exact search, category and store; all three direct product URLs return 404.');
} finally {
  if (errors.length || dialogs.length) { results.push({ status: 'FAIL', evidence: JSON.stringify({ errors, dialogs }) }); process.exitCode = 1; }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/search-browser.json', JSON.stringify(results, null, 2)); await browser.close();
}
