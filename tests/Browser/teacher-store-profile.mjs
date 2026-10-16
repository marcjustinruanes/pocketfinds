import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
assert.equal(origin, 'http://127.0.0.1:8020');
const fixture = action => JSON.parse(execFileSync('php', ['tests/Browser/store-profile-fixture.php', action], { encoding: 'utf8' }));
const initial = fixture('prepare');
assert.equal(initial.serve_command, 'App\\Console\\Commands\\ServeCommand');
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const seller = await browser.newPage(), context = await browser.newContext(), buyer = await context.newPage();
for (const page of [seller, buyer]) { page.setDefaultTimeout(30000); page.setDefaultNavigationTimeout(30000); }
const results = [], errors = [];
for (const page of [seller, buyer]) page.on('pageerror', error => errors.push(error.message));
const form = () => seller.locator('#storeSettingsForm');
const state = () => fixture('inspect');
const account = () => seller.goto(`${origin}/seller/account`, { waitUntil: 'domcontentloaded' });
async function login(page, email, password, role) {
  await page.goto(`${origin}/login`); await page.locator('#email').fill(email); await page.locator('#password').fill(password);
  await Promise.all([page.waitForURL(`**/${role}/dashboard`), page.locator('form.auth-form button[type=submit]').click()]);
}
async function submit() { await Promise.all([seller.waitForEvent('domcontentloaded'), form().locator('button[type=submit]').click()]); }
async function run(ids, task, evidence) {
  try { await task(); results.push({ cases: ids, status: 'PASS', evidence }); console.log(`${ids.join(', ')} PASS`); }
  catch (error) { results.push({ cases: ids, status: 'FAIL', evidence: error.message.slice(0, 2500) }); process.exitCode = 1; console.log(`${ids.join(', ')} FAIL: ${error.message.slice(0, 400)}`); await seller.screenshot({ path: `docs/teacher-test-audit/failure-${ids[0]}.png`, fullPage: true }).catch(() => {}); }
}
const data = { business_name: 'Maria Clara Handicrafts PH', store_description: 'Handwoven bags and home decor from Bulacan artisans.', contact_no: '09189876543',
  region: 'Central Luzon', province: 'Bulacan', municipality: 'City of Malolos', barangay: 'Anilao', street: 'Artisan Street' };
async function restore() { for (const [name, value] of Object.entries(data)) await form().locator(`[name="${name}"]`).fill(value); }
try {
  await login(seller, 'store_profile_audit@example.com', 'StoreProfile@2026', 'seller');
  await login(buyer, 'buyer@example.com', 'TeacherAudit@2026', 'buyer');
  await account();
  const png = await seller.screenshot({ type: 'png' }), jpeg = await seller.screenshot({ type: 'jpeg', quality: 85 });
  function image(name, bytes, type = 'image/jpeg') {
    const source = type === 'image/png' ? png : jpeg, buffer = Buffer.alloc(bytes); assert.ok(source.length <= bytes); source.copy(buffer);
    return { name, mimeType: type, buffer };
  }
  await run(['SEL-PROF-001'], async () => {
    await restore();
    await form().locator('[name=logo]').setInputFiles(image('store_logo.png', 300 * 1024, 'image/png'));
    await form().locator('[name=banner]').setInputFiles(image('store_banner.jpg', Math.ceil(1.8 * 1024 * 1024)));
    await submit(); assert.match(await seller.locator('body').innerText(), /Store settings updated/);
    await seller.reload();
    const saved = state();
    for (const [name, value] of Object.entries(data)) { assert.equal(await form().locator(`[name="${name}"]`).inputValue(), value); assert.equal(saved.seller[name], value); }
    assert.deepEqual(saved.other, initial.other);
    await buyer.goto(`${origin}/buyer/shop/store_profile_audit`);
    assert.match(await buyer.locator('body').innerText(), /Maria Clara Handicrafts PH/); assert.match(await buyer.locator('body').innerText(), /Handwoven bags and home decor from Bulacan artisans\./);
    assert.ok(await buyer.locator(`img[src$="${saved.seller.profile_picture}"]`).count()); assert.ok(await buyer.locator(`img[src$="${saved.seller.store_banner}"]`).count());
    for (const path of [saved.seller.profile_picture, saved.seller.store_banner]) assert.equal((await buyer.request.get(`${origin}/storage/profiles/${path}`)).status(), 200);
    await buyer.goto(`${origin}/buyer/browse?q=Maria%20Clara%20Handicrafts%20PH`);
    assert.match(await buyer.locator('.pf-shop-name').filter({ hasText: /^Maria Clara Handicrafts PH$/ }).textContent(), /^Maria Clara Handicrafts PH$/);
    assert.equal(await buyer.locator('.pf-shop-name').filter({ hasText: /^Maria Clara Handicrafts$/ }).count(), 0);
  }, 'Chrome/MySQL: saved specified store name/description/phone, 300 KB PNG logo and 1.8 MB JPG banner; refresh retained fields; separate buyer saw text/images and found new store name by search; other store unchanged.');
  await run(['SEL-PROF-002', 'SEL-PROF-003'], async () => {
    await account(); const before = state().seller;
    for (const name of ['business_name', 'contact_no', 'region', 'municipality', 'barangay', 'street']) {
      await restore(); await form().locator(`[name="${name}"]`).fill('');
      assert.equal(await form().locator(`[name="${name}"]`).evaluate(el => el.checkValidity()), false);
      await form().locator('button[type=submit]').click(); assert.deepEqual(state().seller, before);
      await seller.reload();
    }
    await restore(); await form().locator('[name=business_name]').fill('     '); await submit();
    assert.match(await seller.locator('[role=alert]').first().innerText(), /business name field is required/i); assert.deepEqual(state().seller, before);
  }, 'Chrome/MySQL: all six specified required fields block blank submission without writes; spaces-only store name rejected by server with required message. PHP direct requests additionally verify one field error per attempt and unchanged fields.');
  await run(['SEL-PROF-004', 'SEL-PROF-005'], async () => {
    for (const name of ['RV Gadget Hub', 'rv gadget hub', '  RV Gadget Hub  ']) {
      await restore(); await form().locator('[name=business_name]').fill(name); await submit();
      assert.match(await seller.locator('[role=alert]').first().innerText(), /already taken/); assert.equal(state().seller.business_name, data.business_name);
    }
    for (const name of ['MC', 'Maria Clara Handicrafts Bulacan']) {
      await restore(); await form().locator('[name=business_name]').fill(name);
      if (await form().evaluate(form => form.checkValidity())) { await submit(); assert.match(await seller.locator('[role=alert]').first().innerText(), /30/); }
      assert.equal(state().seller.business_name, data.business_name);
    }
    for (const name of ['MCH', 'Maria Clara Handicrafts Bulaca']) {
      await restore(); await form().locator('[name=business_name]').fill(name); await submit(); assert.equal(state().seller.business_name, name);
    }
    await restore(); await submit();
  }, 'Chrome/MySQL: three case/space variants of other store name rejected; 2/31-character names blocked and 3/30-character names saved exactly; original store unchanged on rejection.');
  await run(['SEL-PROF-006'], async () => {
    await restore(); await form().locator('[name=store_description]').fill('A'.repeat(500)); await submit();
    assert.equal(state().seller.store_description, 'A'.repeat(500));
    await buyer.goto(`${origin}/buyer/shop/store_profile_audit`); assert.equal(await buyer.locator('.sp-desc').innerText(), 'A'.repeat(500));
    await form().locator('[name=store_description]').fill('B'.repeat(501)); await submit();
    assert.match(await seller.locator('[role=alert]').first().innerText(), /500/); assert.equal(state().seller.store_description, 'A'.repeat(500));
    await restore(); await submit();
  }, 'Chrome/MySQL: 500-character description saved and displayed in full to buyer; 501 rejected with limit message and prior text preserved.');
  await run(['SEL-PROF-007'], async () => {
    for (const phone of ['09189876543', '+639189876543']) {
      await restore(); await form().locator('[name=contact_no]').fill(phone); await submit(); assert.equal(state().seller.contact_no, '09189876543');
    }
    for (const phone of ['0918987654', '091898765432', '0918-ABC-6543', '08189876543']) {
      await restore(); await form().locator('[name=contact_no]').fill(phone); assert.equal(await form().locator('[name=contact_no]').evaluate(el => el.checkValidity()), false);
      await form().locator('button[type=submit]').click(); assert.equal(state().seller.contact_no, '09189876543');
    }
    await restore();
  }, 'Chrome/MySQL: both Philippine phone formats saved canonically; all four invalid workbook inputs blocked without updates. PHP also verifies server validation.');
  await run(['SEL-PROF-008'], async () => {
    for (const [field, column] of [['logo', 'profile_picture'], ['banner', 'store_banner']]) {
      for (const bytes of [Math.ceil(4.9 * 1024 * 1024), 5 * 1024 * 1024]) {
        await restore(); await form().locator(`[name=${field}]`).setInputFiles(image('size.jpg', bytes)); await submit();
        assert.match(await seller.locator('body').innerText(), /Store settings updated/);
        const saved = state().seller[column]; assert.ok(saved);
        assert.equal((await seller.request.get(`${origin}/storage/profiles/${saved}`)).status(), 200);
      }
      const previous = state().seller[column];
      const files = [image('size_5.1MB.jpg', Math.ceil(5.1 * 1024 * 1024)),
        { name: 'price_list.docx', mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', buffer: Buffer.from('PK document') },
        { name: 'installer.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('MZ executable') },
        { name: 'renamed_exe.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('MZ executable') }];
      for (const file of files) {
        await restore(); await form().locator(`[name=${field}]`).setInputFiles(file); await submit();
        assert.match(await seller.locator('[role=alert]').first().innerText(), /JPG or PNG.*5 MB/); assert.equal(state().seller[column], previous);
        await seller.reload(); assert.equal(state().seller[column], previous);
      }
    }
  }, 'Chrome/MySQL using actual multipart bodies: both logo/banner accepted valid 4.9 MB and 5.0 MB JPGs; rejected valid 5.1 MB JPG, DOCX, EXE and renamed executable with JPG/PNG and 5 MB message. Prior image remained accessible after every rejection/refresh.');
  await run(['SEL-PROF-012', 'SEL-PROF-013'], async () => {
    let dialogs = 0; buyer.on('dialog', async dialog => { dialogs++; await dialog.dismiss(); });
    for (const text of ["<b>Sale!</b><script>alert('x')</script>", '\"><img src=x onerror=alert(1)>']) {
      await restore(); await form().locator('[name=store_description]').fill(text); await submit();
      await buyer.goto(`${origin}/buyer/shop/store_profile_audit`); assert.equal(await buyer.locator('.sp-desc').innerText(), text);
      assert.equal(await buyer.locator('.sp-desc script,.sp-desc img').count(), 0);
    }
    assert.equal(dialogs, 0);
    const before = state(), token = await seller.locator('input[name=_token]').first().inputValue();
    const response = await seller.request.post(`${origin}/seller/account/shop`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, data: { ...data, seller_id: initial.other.id } });
    assert.equal(response.status(), 403); assert.deepEqual(state(), before);
    const denied = await seller.request.get(`${origin}/seller/account?store_id=${initial.other.id}`); assert.equal(denied.status(), 403); assert.doesNotMatch(await denied.text(), /RV Gadget Hub/);
  }, 'Chrome/MySQL: both script/HTML payloads displayed as literal text with no executable elements/dialogs. Tampered foreign store ID denied with 403 for read and save; both stores remained unchanged.');
  await seller.screenshot({ path: 'docs/teacher-test-audit/store-profile-browser.png', fullPage: true });
} finally {
  if (errors.length) { results.push({ status: 'FAIL', evidence: errors.join('; ') }); process.exitCode = 1; }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/store-profile-browser.json', JSON.stringify(results, null, 2)); console.log(JSON.stringify(results, null, 2)); await browser.close();
}
