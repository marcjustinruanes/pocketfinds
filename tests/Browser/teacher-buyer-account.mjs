import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile, writeFile, stat } from 'node:fs/promises';
import { chromium } from 'playwright';

const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
assert.match(origin, /^http:\/\/127\.0\.0\.1:8020$/);
const fixture = action => JSON.parse(execFileSync('php', ['tests/Browser/buyer-account-fixture.php', action], { encoding: 'utf8' }));
fixture('prepare');
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const page = await browser.newPage();
page.setDefaultTimeout(10000);
const results = [], errors = [];
page.on('pageerror', error => errors.push(error.message));
const account = () => page.goto(`${origin}/buyer/account`);
const profile = () => page.locator('form[action$="/account/profile"]');
async function fillAddress(label = 'Extra address') {
  await page.locator('#addrRegion option[value="NCR"]').waitFor({ state: 'attached' });
  await page.locator('#addrRegion').selectOption('NCR');
  await page.locator('#addrMunicipality option').filter({ hasText: /Makati/ }).waitFor({ state: 'attached' });
  const makati = await page.locator('#addrMunicipality option').filter({ hasText: /Makati/ }).textContent();
  await page.locator('#addrMunicipality').selectOption({ label: makati });
  await page.locator('#addrBarangay option[value="Bel-Air"]').waitFor({ state: 'attached' });
  await page.locator('#addrBarangay').selectOption('Bel-Air');
  await page.locator('#addrLabel').fill(label); await page.locator('#addrRecipientName').fill('Juan Miguel Dela Cruz');
  await page.locator('#addrContactNo').fill('09171234567'); await page.locator('#addrHouseNo').fill('14F'); await page.locator('#addrStreet').fill('Pacific Tower');
}
const csrf = () => page.locator('meta[name=csrf-token]').getAttribute('content');
const login = async (email = 'buyer.account.audit@example.com', password = 'Mamimili@2026') => {
  await page.goto(`${origin}/login`);
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await Promise.all([page.waitForURL('**/buyer/dashboard'), page.locator('form.auth-form button[type=submit]').click()]);
};
async function logout() {
  const token = await page.locator('meta[name=csrf-token]').getAttribute('content');
  await page.request.post(`${origin}/logout`, { form: { _token: token } });
}
async function submit(form) {
  await Promise.all([page.waitForEvent('load'), form.locator('button[type=submit]').click()]);
}
async function codeSince(offset) {
  const log = (await readFile('storage/logs/laravel.log')).subarray(offset).toString();
  const matches = [...log.matchAll(/Your PocketFinds verification code is: ([0-9]{6})/g)];
  assert.ok(matches.length, 'Mail double must actually receive a code');
  return matches.at(-1)[1];
}
async function run(ids, task, evidence) {
  try { await task(); results.push({ cases: ids, status: 'PASS', evidence }); console.log(`${ids.join(', ')} PASS`); }
  catch (error) {
    results.push({ cases: ids, status: 'FAIL', evidence: error.message });
    process.exitCode = 1;
    console.log(`${ids.join(', ')} FAIL: ${error.message}`);
    await page.screenshot({ path: `docs/teacher-test-audit/failure-${ids[0]}.png`, fullPage: true }).catch(() => {});
  }
}
try {
  await login();
  await run(['BUY-ACCT-001'], async () => {
    await account();
    await profile().locator('[name=given_names]').fill('Juan Miguel Jr.');
    const image = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jE1sAAAAASUVORK5CYII=', 'base64');
    await profile().locator('[name=profile_picture]').setInputFiles({ name: 'review_photo.jpg', mimeType: 'image/jpeg', buffer: image });
    await submit(profile());
    assert.match(await page.textContent('body'), /Profile updated/);
    const saved = fixture('inspect').buyer;
    assert.equal(saved.given_names, 'Juan Miguel Jr.'); assert.ok(saved.profile_picture);
    assert.equal(saved.email, 'buyer.account.audit@example.com'); assert.equal(saved.contact_no, '09170000011');
    await page.reload(); await logout(); await login(); await account();
    assert.match(await page.locator('.profile-summary').textContent(), /Juan Miguel Jr\./);
    assert.ok(await page.locator(`img[src$="${saved.profile_picture}"]`).count() >= 2, 'Photo appears in profile and header');
  }, 'Chrome/MySQL: saved Juan Miguel Jr. and photo; refresh/logout/login retained profile and header photo; email and phone unchanged.');
  await run(['BUY-ACCT-002'], async () => {
    await account(); const before = fixture('inspect').buyer;
    for (const name of ['', 'Juan123', 'J']) {
      await profile().locator('[name=given_names]').fill(name);
      // Invalid length is blocked in the browser; invalid characters rejected by server.
      const valid = await profile().evaluate(form => form.checkValidity());
      if (valid) { await submit(profile()); assert.match(await page.textContent('body'), /format is invalid/i); }
      assert.deepEqual(fixture('inspect').buyer, before);
    }
    for (const [name, buffer] of [['size_5.1MB.jpg', Buffer.alloc(Math.ceil(5.1 * 1024 * 1024), 65)], ['renamed_exe.jpg', Buffer.from('MZ executable, not image')]]) {
      await profile().locator('[name=given_names]').fill('Juan Miguel Jr.');
      await profile().locator('[name=profile_picture]').setInputFiles({ name, mimeType: 'image/jpeg', buffer });
      await submit(profile());
      assert.match(await page.textContent('body'), /profile picture/i);
      assert.deepEqual(fixture('inspect').buyer, before);
    }
    await page.reload(); assert.deepEqual(fixture('inspect').buyer, before);
  }, 'Chrome/MySQL: empty, Juan123, J, 5.1 MB upload and renamed executable rejected; name and previous photo unchanged after refresh.');
  await run(['BUY-ACCT-006'], async () => {
    await account();
    const offset = await stat('storage/logs/laravel.log').then(s => s.size);
    const send = page.locator('form[action$="/contact/email/send"]');
    await send.locator('[name=value]').fill('buyer.test01.accountaudit@example.com'); await submit(send);
    const code = await codeSince(offset);
    assert.equal(fixture('inspect').buyer.email, 'buyer.account.audit@example.com');
    await logout(); await login(); await account();
    assert.match(await page.locator('.profile-summary').textContent(), /buyer.account.audit@example.com/);
    const verify = page.locator('form[action$="/contact/email/verify"]');
    await verify.locator('[name=code]').fill(code); await submit(verify);
    assert.equal(fixture('inspect').buyer.email, 'buyer.test01.accountaudit@example.com');
    await logout(); await page.goto(`${origin}/login`);
    await page.locator('#email').fill('buyer.account.audit@example.com'); await page.locator('#password').fill('Mamimili@2026');
    await submit(page.locator('form.auth-form'));
    assert.match(await page.textContent('body'), /credentials|incorrect|invalid/i);
    await login('buyer.test01.accountaudit@example.com');
  }, 'Chrome/MySQL: old email logged in while change pending; logged code verified new email; old login rejected, new login succeeded. Local mail transport only; external delivery unverified.');
  await run(['BUY-ACCT-007'], async () => {
    await account(); const before = fixture('inspect').buyer.email;
    const offset = await stat('storage/logs/laravel.log').then(s => s.size);
    const send = page.locator('form[action$="/contact/email/send"]');
    await send.locator('[name=value]').fill('expired.account.audit@example.com'); await submit(send);
    const code = await codeSince(offset);
    const verify = page.locator('form[action$="/contact/email/verify"]');
    await verify.locator('[name=code]').fill(code === '000000' ? '111111' : '000000'); await submit(verify);
    assert.match(await page.textContent('body'), /Invalid or expired verification code/);
    fixture('expire-email'); // Expire only this fictional token; PHP suite also tests the exact five-minute boundary with a controlled clock.
    await verify.locator('[name=code]').fill(code); await submit(verify);
    assert.match(await page.textContent('body'), /Invalid or expired verification code/);
    assert.equal(fixture('inspect').buyer.email, before);
  }, 'Chrome/MySQL: wrong code and correct-but-expired token rejected without changing email. Audit fixture expired the token; PHP controlled-clock test verifies five-minute expiry.');
  await run(['BUY-ACCT-008', 'BUY-ACCT-009'], async () => {
    fixture('duplicates'); await account();
    for (const email of ['anamarie.reyes@example.com', 'MARIACLARA.SANTOS@example.com']) {
      const offset = await stat('storage/logs/laravel.log').then(s => s.size);
      const send = page.locator('form[action$="/contact/email/send"]');
      await send.locator('[name=value]').fill(email); await submit(send);
      assert.match(await page.textContent('body'), /Email already in use/);
      assert.doesNotMatch((await readFile('storage/logs/laravel.log')).subarray(offset).toString(), /Your PocketFinds verification code is:/);
    }
    const send = page.locator('form[action$="/contact/contact_no/send"]');
    for (const phone of ['0917123', '09173456789']) {
      const offset = await stat('storage/logs/laravel.log').then(s => s.size);
      await send.locator('[name=value]').fill(phone); await submit(send);
      assert.match(await page.textContent('body'), /format is invalid|Phone number already in use/i);
      assert.doesNotMatch((await readFile('storage/logs/laravel.log')).subarray(offset).toString(), /Your PocketFinds verification code is:/);
    }
    const before = fixture('inspect').buyer.contact_no;
    const offset = await stat('storage/logs/laravel.log').then(s => s.size);
    await send.locator('[name=value]').fill('09170000001'); await submit(send);
    const code = await codeSince(offset);
    assert.equal(fixture('inspect').buyer.contact_no, before);
    const verify = page.locator('form[action$="/contact/contact_no/verify"]');
    await verify.locator('[name=code]').fill(code); await submit(verify);
    assert.equal(fixture('inspect').buyer.contact_no, '09170000001');
  }, 'Chrome/MySQL: both duplicate emails (cross-role/case) and invalid/duplicate phones rejected without sending code; valid phone saved only after logged email OTP. External email delivery unverified.');
  await run(['BUY-ADDR-001', 'BUY-ADDR-002'], async () => {
    await account();
    await page.locator('[data-modal-open="addAddressModal"]').click();
    await page.locator('#addrRegion option').filter({ hasText: /NCR|National Capital Region/ }).waitFor({ state: 'attached' });
    const ncr = await page.locator('#addrRegion option').filter({ hasText: /NCR|National Capital Region/ }).textContent();
    await page.locator('#addrRegion').selectOption({ label: ncr });
    await page.locator('#addrMunicipality option').filter({ hasText: /Makati/ }).waitFor({ state: 'attached' });
    assert.equal(await page.locator('#addrMunicipality option').filter({ hasText: /Cebu/ }).count(), 0);
    const makati = await page.locator('#addrMunicipality option').filter({ hasText: /Makati/ }).textContent();
    await page.locator('#addrMunicipality').selectOption({ label: makati });
    await page.locator('#addrBarangay option[value="Bel-Air"]').waitFor({ state: 'attached' });
    await page.locator('#addrBarangay').selectOption('Bel-Air');
    const vii = await page.locator('#addrRegion option').filter({ hasText: /Central Visayas/ }).textContent();
    await page.locator('#addrRegion').selectOption({ label: vii });
    assert.equal(await page.locator('#addrMunicipality').inputValue(), '');
    assert.equal(await page.locator('#addrBarangay').inputValue(), '');
    await page.locator('#addrRegion').selectOption({ label: ncr });
    await page.locator('#addrMunicipality option').filter({ hasText: /Makati/ }).waitFor({ state: 'attached' });
    await page.locator('#addrMunicipality').selectOption({ label: makati });
    await page.locator('#addrBarangay option[value="Bel-Air"]').waitFor({ state: 'attached' });
    await page.locator('#addrBarangay').selectOption('Bel-Air');
    await page.locator('#addrLabel').fill('Makati office'); await page.locator('#addrRecipientName').fill('Juan Miguel Dela Cruz');
    await page.locator('#addrContactNo').fill('09171234567'); await page.locator('#addrHouseNo').fill('14F'); await page.locator('#addrStreet').fill('Pacific Tower');
    await Promise.all([page.waitForEvent('load'), page.locator('#saveAddressBtn').click()]);
    const state = fixture('inspect');
    assert.equal(state.addresses.length, 2);
    const office = state.addresses.find(address => address.label === 'Makati office');
    assert.ok(office); assert.equal(office.is_default, false);
    for (const [key, value] of Object.entries({ recipient_name: 'Juan Miguel Dela Cruz', contact_no: '09171234567', region: ncr, province: 'Metro Manila', municipality: makati, barangay: 'Bel-Air', house_no: '14F', street: 'Pacific Tower' })) assert.equal(office[key], value);
    assert.equal(state.addresses.filter(address => address.is_default).length, 1);
    assert.match(await page.locator('#addressList').textContent(), /Makati office/);
  }, 'Chrome/MySQL with bundled PSGC data: NCR contains Makati and no Cebu; Makati contains Bel-Air; changing to Region VII clears city/barangay. Saved office with all exact fields; original home remains sole default.');
  await run(['BUY-ADDR-003', 'BUY-ADDR-004'], async () => {
    await page.locator('[data-modal-open="addAddressModal"]').click(); await fillAddress();
    const before = fixture('inspect').addresses.length;
    for (const id of ['addrRecipientName', 'addrContactNo', 'addrStreet']) {
      const control = page.locator('#' + id), value = await control.inputValue();
      await control.fill(''); assert.equal(await control.evaluate(el => el.checkValidity()), false);
      await page.locator('#saveAddressBtn').click(); assert.equal(fixture('inspect').addresses.length, before);
      await control.fill(value);
    }
    for (const id of ['addrRegion', 'addrMunicipality', 'addrBarangay']) {
      assert.equal(await page.locator('#' + id).evaluate(el => { const saved = el.value; el.value = ''; const valid = el.checkValidity(); el.value = saved; return valid; }), false);
    }
    for (const phone of ['0917123', '091712345678']) {
      await page.locator('#addrContactNo').fill(phone); assert.equal(await page.locator('#addrContactNo').evaluate(el => el.checkValidity()), false);
      await page.locator('#saveAddressBtn').click(); assert.equal(fixture('inspect').addresses.length, before);
    }
    for (const phone of ['09171234567', '+639171234567']) {
      await page.locator('#addrContactNo').fill(phone);
      await Promise.all([page.waitForEvent('load'), page.locator('#saveAddressBtn').click()]);
      await page.locator('[data-modal-open="addAddressModal"]').click(); await fillAddress();
    }
    assert.equal(fixture('inspect').addresses.length, before + 2);
    await page.locator('#addAddressModal [data-modal-close]').first().click();
  }, 'Chrome/MySQL: each of six required controls rejects blank values; invalid short/long phones cannot submit; both local and +63 formats saved. PHP additionally submits all blank fields directly and confirms zero writes.');
  await run(['BUY-ADDR-008'], async () => {
    const snapshot = fixture('snapshot');
    const office = fixture('inspect').addresses.find(address => address.label === 'Makati office');
    await page.locator(`[data-edit-address="${office.id}"]`).click();
    await page.locator('#addrBarangay option[value="Bel-Air"]').waitFor({ state: 'attached' });
    await page.locator('#addrStreet').fill('14F Pacific Tower');
    await Promise.all([page.waitForEvent('load'), page.locator('#saveAddressBtn').click()]);
    assert.equal(fixture('inspect').addresses.find(address => address.id === office.id).street, '14F Pacific Tower');
    await page.locator(`[data-delete-address="${office.id}"]`).click();
    await Promise.all([page.waitForEvent('load'), page.locator('#confirmRemoveAddressBtn').click()]);
    const state = fixture('inspect');
    assert.ok(!state.addresses.some(address => address.id === office.id));
    assert.deepEqual(state.snapshots.find(order => order.id === snapshot.order_id).address, snapshot.snapshot);
    await page.goto(`${origin}/buyer/orders`);
    const buyerHtml = await page.content();
    assert.match(buyerHtml, /Pacific Tower/); assert.doesNotMatch(buyerHtml, /14F Pacific Tower/);
    const sellerContext = await browser.newContext(); const seller = await sellerContext.newPage();
    try {
      await seller.goto(`${origin}/login`); await seller.locator('#email').fill('seller@example.com'); await seller.locator('#password').fill('TeacherAudit@2026');
      await Promise.all([seller.waitForURL('**/seller/dashboard'), seller.locator('form.auth-form button[type=submit]').click()]);
      await seller.goto(`${origin}/seller/orders`);
      assert.match(await seller.content(), /Pacific Tower/); assert.doesNotMatch(await seller.content(), /14F Pacific Tower/);
      await seller.goto(`${origin}/seller/orders/${snapshot.order_id}/waybill`);
      assert.match(await seller.textContent('body'), /Pacific Tower/); assert.doesNotMatch(await seller.textContent('body'), /14F Pacific Tower/);
      assert.match(await seller.textContent('body'), /09171234567/);
      await seller.emulateMedia({ media: 'print' });
      await seller.pdf({ path: 'docs/teacher-test-audit/address-snapshot-waybill.pdf' });
    } finally { await sellerContext.close(); }
    await account();
  }, 'Chrome/MySQL: saved address edited then deleted; existing order snapshot, buyer view, seller view and printed waybill retained original street and recipient phone. Printed PDF retained.');
  await run(['BUY-ADDR-006', 'BUY-ADDR-007'], async () => {
    const state = fixture('inspect'), first = state.addresses.find(address => address.is_default), second = state.addresses.find(address => !address.is_default);
    const rejected = await page.request.delete(`${origin}/buyer/addresses/${first.id}`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': await csrf() } });
    assert.equal(rejected.status(), 422); assert.match((await rejected.json()).message, /another address as default/);
    await page.locator(`form[action$="/addresses/${second.id}/default"] button`).click();
    await page.waitForLoadState('load');
    const after = fixture('inspect'); assert.equal(after.addresses.filter(address => address.is_default).length, 1);
    assert.equal(after.addresses.find(address => address.id === second.id).is_default, true);
    fixture('cart'); await page.goto(`${origin}/buyer/cart`);
    assert.equal(await page.locator(`input[name=delivery_address_radio][value="${second.id}"]`).isChecked(), true);
    await account(); await page.locator(`[data-delete-address="${first.id}"]`).click();
    await Promise.all([page.waitForEvent('load'), page.locator('#confirmRemoveAddressBtn').click()]);
    assert.ok(!fixture('inspect').addresses.some(address => address.id === first.id));
    assert.equal(fixture('inspect').addresses.filter(address => address.is_default).length, 1);
  }, 'Chrome/MySQL: deleting default returns choose-another-default message; changed default, checked checkout radio preselection and sole-default invariant; previous default then deleted successfully.');
  await run(['BUY-ADDR-009'], async () => {
    const foreign = fixture('duplicates').foreign_address_id;
    const response = await page.request.get(`${origin}/buyer/addresses/${foreign}`);
    assert.equal(response.status(), 403); assert.doesNotMatch(await response.text(), /Private Foreign Street/);
  }, 'Authenticated browser requested another buyer address ID: HTTP 403 with no foreign address text. HTTP suite also verifies writes are denied.');
  await run(['BUY-ADDR-005'], async () => {
    fixture('fill-nine'); await account();
    for (const expected of [10, 10]) {
      await page.locator('[data-modal-open="addAddressModal"]').click(); await fillAddress('Limit boundary');
      if (fixture('inspect').addresses.length === 9) await Promise.all([page.waitForEvent('load'), page.locator('#saveAddressBtn').click()]);
      else {
        await page.locator('#saveAddressBtn').click();
        await page.locator('#addAddressError').filter({ hasText: /up to 10 addresses/ }).waitFor();
      }
      assert.equal(fixture('inspect').addresses.length, expected);
    }
    await page.locator('#addAddressModal [data-modal-close]').first().click();
  }, 'Chrome/MySQL: prepared nine fictional addresses; tenth saved using form; eleventh blocked with explicit limit message, count remained ten.');
  await run(['BUY-ACCT-004', 'BUY-ACCT-005', 'BUY-ACCT-003'], async () => {
    await account();
    const passwordForm = page.locator('form[action$="/account/password"]');
    await passwordForm.locator('[name=current_password]').fill('Wrong@2026');
    await passwordForm.locator('[name=password]').fill('Mamimili@2027'); await passwordForm.locator('[name=password_confirmation]').fill('Mamimili@2027');
    await submit(passwordForm); assert.match(await page.textContent('body'), /Current password is incorrect/);
    await logout(); await login('buyer.test01.accountaudit@example.com'); await account();
    for (const [password, confirm] of [['Mami@26', 'Mami@26'], ['mamimili@2027', 'mamimili@2027'], ['MAMIMILI@2027', 'MAMIMILI@2027'], ['Mamimili@abcd', 'Mamimili@abcd'], ['Mamimili2027', 'Mamimili2027'], ['Mamimili@2027', 'Mamimili@2028'], ['Mamimili@2026', 'Mamimili@2026']]) {
      await passwordForm.locator('[name=current_password]').fill('Mamimili@2026'); await passwordForm.locator('[name=password]').fill(password); await passwordForm.locator('[name=password_confirmation]').fill(confirm);
      await submit(passwordForm); assert.ok(await page.locator('[role=alert]').count(), 'Invalid password must have a visible validation message');
    }
    await logout(); await login('buyer.test01.accountaudit@example.com'); await account();
    await passwordForm.locator('[name=current_password]').fill('Mamimili@2026'); await passwordForm.locator('[name=password]').fill('Mamimili@2027'); await passwordForm.locator('[name=password_confirmation]').fill('Mamimili@2027');
    await submit(passwordForm); assert.match(await page.textContent('body'), /Password updated/);
    await logout(); await page.goto(`${origin}/login`);
    await page.locator('#email').fill('buyer.test01.accountaudit@example.com'); await page.locator('#password').fill('Mamimili@2026'); await submit(page.locator('form.auth-form'));
    assert.match(await page.textContent('body'), /credentials|incorrect|invalid/i);
    await login('buyer.test01.accountaudit@example.com', 'Mamimili@2027');
  }, 'Chrome/MySQL: wrong old password rejected and old login retained; all seven specified invalid new passwords rejected with visible messages, old login retained; valid change succeeded, old login rejected and new login succeeded.');
  await page.screenshot({ path: 'docs/teacher-test-audit/buyer-account-browser.png', fullPage: true });
} finally {
  for (const row of results) {
    row.origin = origin; row.executed_at = new Date().toISOString();
  }
  if (errors.length) { results.push({ status: 'FAIL', evidence: `Browser errors: ${errors.join('; ')}` }); process.exitCode = 1; }
  await writeFile('docs/teacher-test-audit/buyer-account-browser.json', JSON.stringify(results, null, 2));
  console.log(JSON.stringify(results, null, 2));
  await browser.close();
}
