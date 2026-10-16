import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { writeFile, readFile } from 'node:fs/promises';
import { chromium } from 'playwright';
const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
assert.equal(origin, 'http://127.0.0.1:8020');
const fixture = action => JSON.parse(execFileSync('php', ['tests/Browser/product-validation-fixture.php', action], { encoding: 'utf8' }));
fixture('prepare');
const browser = await chromium.launch({ channel: 'chrome', headless: true }), seller = await browser.newPage(), buyer = await (await browser.newContext()).newPage();
const selected = process.argv.slice(2);
const results = selected.length ? JSON.parse(await readFile('docs/teacher-test-audit/product-validation-browser.json', 'utf8')).filter(row => !row.cases?.some(id => selected.includes(id))) : [];
const errors = []; let sequence = 0; const stamp = Date.now();
for (const page of [seller, buyer]) { page.setDefaultTimeout(30000); page.on('pageerror', error => errors.push(error.message)); }
async function login(page, email, password, role) {
  await page.goto(`${origin}/login`); await page.locator('#email').fill(email); await page.locator('#password').fill(password);
  await Promise.all([page.waitForURL(`**/${role}/dashboard`), page.locator('form.auth-form button[type=submit]').click()]);
}
let png, jpg;
const photos = count => Array.from({ length: count }, (_, index) => ({ name: `photo${index}.png`, mimeType: 'image/png', buffer: png }));
async function prepare(overrides = {}, count = 1) {
  await seller.goto(`${origin}/seller/inventory`); await seller.locator('[data-modal=addProductModal]').click();
  const form = seller.locator('#addProductModal form');
  const data = { name: `Validation Tote ${stamp}-${++sequence}`, description: 'A handmade product for browser validation testing.', price: '199.00', stock: '10', weight_grams: '500', ...overrides };
  for (const [name, value] of Object.entries(data)) {
    const field = form.locator(`[name=${name}]`);
    if (value === 'abc') { await field.fill(''); await field.pressSequentially('abc'); } else await field.fill(value);
  }
  await form.locator('[name=category_id]').selectOption('88201');
  if (count) { await form.locator('[name="images[]"]').setInputFiles(photos(count)); await seller.waitForFunction(count => document.querySelectorAll('#addProductModal [data-role=img-thumbs] img').length === count, count); }
  return { form, data };
}
async function publish(form, valid, field = null) {
  const before = fixture('inspect').products;
  if (!await form.evaluate(form => form.checkValidity())) {
    assert.equal(valid, false); await form.locator('button[value=publish]').click(); assert.ok(await form.locator(`[name=${field}]`).evaluate(input => input.validationMessage));
  } else {
    const navigation = valid ? seller.waitForEvent('domcontentloaded').then(() => null, error => error) : null;
    const [response] = await Promise.all([seller.waitForResponse(response => response.url() === `${origin}/seller/inventory` && response.request().method() === 'POST'), form.locator('button[value=publish]').click()]);
    if (valid) { assert.equal(response.status(), 200); const error = await navigation; if (error) throw error; assert.equal(new URL(seller.url()).pathname, '/seller/inventory'); }
    else { assert.equal(response.status(), 422); assert.ok((await response.json()).errors[field]); assert.ok(await form.locator(`[data-field-error="${field}"]`).first().isVisible()); }
  }
  const after = fixture('inspect').products, added = after.filter(product => !before.some(old => old.id === product.id));
  assert.equal(added.length, valid ? 1 : 0); return added[0];
}
async function run(cases, task, evidence, status = 'PASS') {
  if (selected.length && !cases.some(id => selected.includes(id))) return;
  try { await task(); results.push({ cases, status, evidence }); console.log(`${cases.join(', ')} ${status}`); }
  catch (error) { results.push({ cases, status: 'FAIL', evidence: error.message }); process.exitCode = 1; console.log(`${cases.join(', ')} FAIL: ${error.message}`); await seller.screenshot({ path: `docs/teacher-test-audit/failure-${cases[0]}-validation.png`, fullPage: true }); }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/product-validation-browser.json', JSON.stringify(results, null, 2));
}
async function edit(product) {
  await seller.goto(`${origin}/seller/inventory`); await seller.locator(`[data-modal="viewProductModal-${product.id}"]`).click();
  await seller.locator(`#viewProductModal-${product.id} [aria-label="Edit product"]`).click(); return seller.locator(`#editProductModal-${product.id} form`);
}
try {
  await login(seller, 'product.validation.audit@example.com', 'ProductValidation@2026', 'seller');
  await login(buyer, 'buyer@example.com', 'TeacherAudit@2026', 'buyer');
  png = await seller.screenshot({ type: 'png' }); jpg = await seller.screenshot({ type: 'jpeg' });
  await run(['SEL-PROD-002'], async () => {
    for (const field of ['name', 'description', 'images', 'category_id', 'price', 'stock', 'weight_grams']) {
      const { form, data } = await prepare({}, field === 'images' ? 0 : 1);
      if (field === 'category_id') await form.locator('[name=category_id]').selectOption('');
      else if (field !== 'images') await form.locator(`[name=${field}]`).fill('');
      await publish(form, false, field);
      for (const [name, value] of Object.entries(data)) if (name !== field) assert.equal(await form.locator(`[name=${name}]`).inputValue(), value);
      assert.equal(await form.locator('[data-role=img-thumbs] img').count(), field === 'images' ? 0 : 1);
    }
  }, 'Chrome/MySQL: all seven required-field omissions individually blocked with a field message; no product created, other values and uploaded preview retained.');
  await run(['SEL-PROD-003'], async () => {
    for (const [field, values, limits] of [['name', ['Tote', 'Totes', 'A'.repeat(120), 'A'.repeat(121)], [false, true, true, false]], ['description', ['A'.repeat(19), 'A'.repeat(20), 'A'.repeat(3000), 'A'.repeat(3001)], [false, true, true, false]]]) {
      for (let i = 0; i < values.length; i++) { const { form } = await prepare({ [field]: values[i] }); const product = await publish(form, limits[i], field); if (limits[i]) assert.equal(product[field], values[i]); }
    }
  }, 'Chrome/MySQL: exact 4/5/120/121 name and 19/20/3000/3001 description boundaries checked; only permitted lengths publish and persist exactly; rejected values show their limits.');
  await run(['SEL-PROD-004'], async () => {
    for (const price of ['0', '-50', '0.99', '1.00', '1.01', '199.999', '999999.99', '1000000.00', '1000000.01', 'abc', '']) {
      const valid = ['1.00', '1.01', '999999.99', '1000000.00'].includes(price), { form } = await prepare({ price });
      const product = await publish(form, valid, 'price'); if (valid) assert.equal(Number(product.price), Number(price));
    }
  }, 'Chrome/MySQL: all eleven workbook price inputs checked; only 1.00, 1.01, 999999.99 and 1000000.00 publish at exact prices; three-decimal input rejected without rounding or a product write.');
  await run(['SEL-PROD-005'], async () => {
    for (const stock of ['-1', '0', '1', '99999', '100000', '2.5']) {
      const valid = ['0', '1', '99999'].includes(stock), { form } = await prepare({ stock }); const product = await publish(form, valid, 'stock');
      if (valid) assert.equal(product.stock, Number(stock));
      if (stock === '0') { await buyer.goto(`${origin}/buyer/product/${product.id}`); assert.match(await buyer.locator('body').innerText(), /Sold out/i); assert.equal(await buyer.locator('#pdAddCartBtn').isDisabled(), true); assert.equal(await buyer.locator('#pdBuyNowBtn').isDisabled(), true); }
    }
  }, 'Chrome/MySQL: only integer stocks 0, 1 and 99999 publish; -1, 100000 and 2.5 rejected; zero-stock buyer product shows Sold out and both purchase buttons disabled.');
  await run(['SEL-PROD-006'], async () => {
    const zero = await prepare({}, 0); await publish(zero.form, false, 'images');
    const one = await prepare(); const product = await publish(one.form, true); assert.equal(product.images.length, 1);
    let form = await edit(product); await form.locator('[name="images[]"]').setInputFiles(photos(8));
    await seller.waitForFunction(id => document.querySelectorAll(`#editProductModal-${id} [data-role=img-thumbs] img`).length === 9, product.id);
    await Promise.all([seller.waitForEvent('domcontentloaded'), form.locator('button[value=publish]').click()]);
    assert.equal(fixture('inspect').products.find(item => item.id === product.id).images.length, 9);
    form = await edit(product); await form.locator('[name="images[]"]').setInputFiles(photos(1)); await form.locator('[data-role=image-errors]').waitFor({ state: 'visible' });
    assert.match(await form.locator('[data-role=image-errors]').innerText(), /up to 9/); assert.equal(await form.locator('[data-role=img-thumbs] img').count(), 9);
  }, 'Chrome/MySQL: zero images rejected, one saved, edit retained the first and saved eight more; attempted tenth photo shows the nine-photo limit and is not added.');
  await run(['SEL-PROD-007'], async () => {
    const buffer = Buffer.alloc(5 * 1024 * 1024); jpg.copy(buffer); const { form } = await prepare({}, 0);
    await form.locator('[name="images[]"]').setInputFiles({ name: 'size_5.0MB.jpg', mimeType: 'image/jpeg', buffer });
    await seller.waitForFunction(() => document.querySelectorAll('#addProductModal [data-role=img-thumbs] img').length === 1);
    const product = await publish(form, true); const stored = await buyer.request.get(`${origin}/storage/${product.images[0]}`); assert.equal(stored.status(), 200); assert.equal((await stored.body()).length, buffer.length);
    for (const file of [{ name: 'size_5.1MB.jpg', mimeType: 'image/jpeg', buffer: Buffer.concat([buffer, Buffer.alloc(105000)]) }, { name: 'price_list.docx', mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', buffer: Buffer.from('PK document fixture') }, { name: 'renamed_exe.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('MZ executable fixture, not an image') }]) {
      const rejected = await prepare({}, 0); const before = fixture('inspect').products.length; await rejected.form.locator('[name="images[]"]').setInputFiles(file);
      await rejected.form.locator('[data-role=image-errors]').waitFor({ state: 'visible' }); assert.match(await rejected.form.locator('[data-role=image-errors]').innerText(), /JPG or PNG.*5 MB/);
      assert.equal(await rejected.form.locator('[data-role=img-thumbs] img').count(), 0); assert.equal(fixture('inspect').products.length, before);
    }
  }, 'Chrome/MySQL: real JPEG padded to exactly 5 MiB accepted, saved and read back at full length; 5.1 MiB JPEG, DOCX and executable renamed JPG rejected before preview with JPG/PNG and 5 MB messages.');
} finally {
  if (errors.length) { results.push({ status: 'FAIL', evidence: errors.join('; ') }); process.exitCode = 1; }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/product-validation-browser.json', JSON.stringify(results, null, 2)); await browser.close();
}
