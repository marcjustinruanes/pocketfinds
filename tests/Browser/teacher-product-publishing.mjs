import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
assert.equal(origin, 'http://127.0.0.1:8020');
const fixture = action => JSON.parse(execFileSync('php', ['tests/Browser/product-publishing-fixture.php', action], { encoding: 'utf8' }));
const initial = fixture('prepare'), browser = await chromium.launch({ channel: 'chrome', headless: true });
const seller = await browser.newPage(), buyer = await (await browser.newContext()).newPage();
const results = [], errors = [];
for (const page of [seller, buyer]) { page.setDefaultTimeout(30000); page.on('pageerror', error => errors.push(error.message)); }
async function login(page, email, password, role) {
  await page.goto(`${origin}/login`); await page.locator('#email').fill(email); await page.locator('#password').fill(password);
  await Promise.all([page.waitForURL(`**/${role}/dashboard`), page.locator('form.auth-form button[type=submit]').click()]);
}
async function run(cases, task, evidence) {
  try { await task(); results.push({ cases, status: 'PASS', evidence }); console.log(`${cases.join(', ')} PASS`); }
  catch (error) { results.push({ cases, status: 'FAIL', evidence: error.message }); process.exitCode = 1; console.log(`${cases.join(', ')} FAIL: ${error.message}`); await seller.screenshot({ path: `docs/teacher-test-audit/failure-${cases[0]}.png`, fullPage: true }).catch(() => {}); }
}
const inventory = () => seller.goto(`${origin}/seller/inventory`);
const product = name => fixture('inspect').products.find(product => product.name === name);
async function openAdd() { await inventory(); await seller.locator('[data-modal=addProductModal]').click(); return seller.locator('#addProductModal form'); }
async function save(form, intent) { await Promise.all([seller.waitForEvent('domcontentloaded'), form.locator(`button[value=${intent}]`).click()]); await seller.waitForLoadState('load'); }
async function edit(item) {
  await inventory(); await seller.locator(`[data-modal="viewProductModal-${item.id}"]`).click();
  await seller.locator(`#viewProductModal-${item.id} button[aria-label="Edit product"]`).click();
  return seller.locator(`#editProductModal-${item.id} form`);
}
try {
  await login(seller, 'publishing.browser.audit@example.com', 'PublishingAudit@2026', 'seller');
  await login(buyer, 'buyer@example.com', 'TeacherAudit@2026', 'buyer');
  await run(['SEL-PROD-009'], async () => {
    const form = await openAdd(); await form.locator('[name=name]').fill('Draft Woven Fan'); await save(form, 'draft');
    const item = product('Draft Woven Fan'); assert.equal(item.status, 'draft'); assert.equal(item.category_id, initial.category_id);
    await buyer.goto(`${origin}/buyer/browse?q=Draft%20Woven%20Fan`);
    assert.equal(await buyer.locator('.product-card[onclick*="' + item.id + '"]').count(), 0);
    assert.equal((await buyer.goto(`${origin}/buyer/product/${item.id}`)).status(), 404);
  }, 'Chrome/MySQL: name-only Save as Draft succeeded; saved draft is absent from buyer search and direct buyer URL returns 404.');
  await run(['SEL-PROD-010'], async () => {
    const item = product('Draft Woven Fan'), form = await edit(item);
    await form.locator('button[value=publish]').click();
    assert.equal(await form.evaluate(form => form.checkValidity()), false);
    assert.equal(product('Draft Woven Fan').status, 'draft');
  }, 'Chrome/MySQL: publishing incomplete draft is blocked by required-field validation; database status remains draft. Direct HTTP regression also checks server required-field responses.');
  await run(['SEL-PROD-001'], async () => {
    const form = await openAdd();
    for (const [name, value] of Object.entries({ name: 'Buri Placemat Set', description: 'Set of 6 placemats, handwoven buri palm.', price: '299.00', stock: '30', weight_grams: '600', length_cm: '35', width_cm: '25', height_cm: '3' })) await form.locator(`[name=${name}]`).fill(value);
    await form.locator('[name=category_id]').selectOption(String(initial.category_id));
    await form.locator('[name="store_category_ids[]"]').selectOption(String(initial.store_category_id));
    await form.locator('[data-role=add-detail-btn]').click(); await form.locator('.detail-label').fill('Brand'); await form.locator('.detail-value').fill('No brand');
    const jpg = await seller.screenshot({ type: 'jpeg' }), png = await seller.screenshot({ type: 'png' });
    await form.locator('[name="images[]"]').setInputFiles([{ name: 'product_front.jpg', mimeType: 'image/jpeg', buffer: jpg }, { name: 'product_side.png', mimeType: 'image/png', buffer: png }]);
    await save(form, 'publish');
    const item = product('Buri Placemat Set'); assert.ok(item); assert.equal(item.status, 'active');
    for (const [key, value] of Object.entries({ price: 299, stock: 30, weight_grams: 600, length_cm: 35, width_cm: 25, height_cm: 3, category_id: initial.category_id })) assert.equal(Number(item[key]), value);
    assert.equal(item.description, 'Set of 6 placemats, handwoven buri palm.'); assert.equal(item.condition, 'new');
    assert.deepEqual(item.details, [{ label: 'Brand', value: 'No brand' }]); assert.equal(item.images.length, 2); assert.equal(item.image, item.images[0]);
    assert.deepEqual(item.store_categories.map(category => category.id), [initial.store_category_id]);
    for (const query of ['q=Buri%20Placemat%20Set', `category=${initial.category_id}`]) {
      await buyer.goto(`${origin}/buyer/browse?${query}`); assert.ok(await buyer.locator(` .product-card[onclick*="${item.id}"]`).count());
    }
    await buyer.goto(`${origin}/buyer/shop/publishing_browser_audit?store_category=${initial.store_category_id}`); assert.ok(await buyer.locator(` .product-card[onclick*="${item.id}"]`).count());
    await buyer.goto(`${origin}/buyer/product/${item.id}`); assert.match(await buyer.locator('body').innerText(), /299\.00/); assert.match(await buyer.locator('body').innerText(), /30/);
    const displayed = await buyer.locator('img').evaluateAll(images => images.map(image => image.src));
    const thumbnails = await buyer.locator('#pdThumbTrack img').evaluateAll(images => images.map(image => image.src));
    assert.deepEqual(thumbnails.map(src => src.split('/storage/')[1]), item.images);
    for (const [index, path] of item.images.entries()) { assert.ok(displayed.some(src => src.endsWith(path))); const response = await buyer.request.get(`${origin}/storage/${path}`); assert.equal(response.status(), 200); assert.deepEqual(await response.body(), index === 0 ? jpg : png); }
  }, 'Chrome/MySQL: published exact workbook values including brand, dimensions, category and two ordered images; active record retained after reload and buyer found product via search, platform/store category and product page.');
  await run(['SEL-PROD-017'], async () => {
    const form = await openAdd();
    for (const [name, value] of Object.entries({ name: 'Double Click Test Tote', description: 'A tote used to verify duplicate publish protection.', price: '299.00', stock: '30', weight_grams: '600' })) await form.locator(`[name=${name}]`).fill(value);
    await form.locator('[name=category_id]').selectOption(String(initial.category_id));
    await form.locator('[name="images[]"]').setInputFiles({ name: 'tote.jpg', mimeType: 'image/jpeg', buffer: await seller.screenshot({ type: 'jpeg' }) });
    await Promise.all([seller.waitForEvent('domcontentloaded'), form.locator('button[value=publish]').evaluate(button => { button.click(); button.click(); })]);
    const matches = fixture('inspect').products.filter(product => product.name === 'Double Click Test Tote');
    assert.equal(matches.length, 1); assert.equal(matches[0].status, 'active');
  }, 'Chrome/MySQL: two immediate Publish button clicks created exactly one active Double Click Test Tote. Separate HTTP regression replays the same creation key and also verifies one record and one set of uploads.');

} finally {
  if (errors.length) { results.push({ status: 'FAIL', evidence: errors.join('; ') }); process.exitCode = 1; }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/product-publishing-browser.json', JSON.stringify(results, null, 2));
  await browser.close();
}
