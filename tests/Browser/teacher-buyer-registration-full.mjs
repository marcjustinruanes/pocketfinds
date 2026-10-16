import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile, writeFile, stat } from 'node:fs/promises';
import { chromium } from 'playwright';
const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
assert.equal(origin, 'http://127.0.0.1:8020');
const fixture = email => JSON.parse(execFileSync('php', ['tests/Browser/buyer-registration-full-fixture.php', email.trim()], { encoding: 'utf8' }));
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const page = await browser.newPage(), primary = await (await browser.newContext()).newPage(), expiryPage = await (await browser.newContext()).newPage();
const selected = process.argv.includes('--affected-only') ? [9, 23, 27] : process.argv.includes('--email-only') ? [9] : null;
const resultPath = 'docs/teacher-test-audit/buyer-registration-full-browser.json';
const results = selected ? JSON.parse(await readFile(resultPath, 'utf8')).filter(row => !row.cases?.some(id => selected.includes(Number(id.split('-').at(-1))))) : [];
const errors = [], runId = Date.now(); let serial = runId % 10000000;
for (const p of [page, primary, expiryPage]) { p.setDefaultTimeout(20000); p.on('pageerror', error => errors.push(error.message)); }
const required = ['given_names', 'last_name', 'email', 'contact_no', 'birthday', 'password', 'password_confirmation'];
const next = () => ({ given_names: 'Juan Miguel', last_name: 'Dela Cruz', email: `buyer.full.audit.${runId}.${++serial}@example.com`, contact_no: '0992' + String(serial).padStart(7, '0'), birthday: '1998-05-14', password: 'Mamimili@2026', password_confirmation: 'Mamimili@2026' });
const logSize = () => stat('storage/logs/laravel.log').then(s => s.size).catch(() => 0);
async function mailSince(offset) { return (await readFile('storage/logs/laravel.log')).subarray(offset).toString(); }
async function fill(p, data = next()) {
  await p.goto(`${origin}/register/buyer`);
  for (const [name, value] of Object.entries(data)) await p.locator(`[name=${name}]`).fill(value);
  await p.locator('[name=terms]').check(); return data;
}
async function accepted(p, data, triple = false) {
  await fill(p, data); const before = fixture(data.email), offset = await logSize();
  await Promise.all([p.waitForURL('**/register/verify'), triple ? p.locator('#registerSubmit').evaluate(button => { button.click(); button.click(); button.click(); }) : p.locator('#registerSubmit').click()]);
  const log = await mailSince(offset), codes = [...log.matchAll(/Your PocketFinds verification code is: ([0-9]{6})/g)].map(match => match[1]);
  assert.equal(codes.length, 1); const after = fixture(data.email); assert.equal(after.account_count, before.account_count + 1); assert.equal(after.user.status, 'unverified');
  assert.match(await p.locator('body').innerText(), /Account saved|Check your email/i);
  return { data, code: codes[0], created: Date.now() };
}
async function rejected(data, fields) {
  await fill(page, data); const before = fixture(data.email.trim() || 'empty.audit@example.com'), offset = await logSize();
  const valid = await page.locator('#registrationForm').evaluate(form => form.checkValidity());
  if (valid) await Promise.all([page.waitForEvent('domcontentloaded'), page.locator('#registerSubmit').click()]);
  else await page.locator('#registerSubmit').click();
  for (const field of fields) assert.ok(await page.locator(`[name=${field}]`).locator('..').locator('.field-error').count(), `Missing field message: ${field}`);
  assert.equal(fixture(data.email.trim() || 'empty.audit@example.com').account_count, before.account_count);
  assert.equal((await mailSince(offset)).includes('Your PocketFinds verification code is:'), false);
}
async function run(numbers, task, evidence) {
  if (selected && !numbers.some(n => selected.includes(n))) return;
  const cases = numbers.map(n => `TC-BUY-${String(n).padStart(3, '0')}`);
  try { await task(); results.push({ cases, status: 'PASS', evidence }); console.log(`${cases.join(', ')} PASS`); }
  catch (error) { results.push({ cases, status: 'FAIL', evidence: error.message }); process.exitCode = 1; console.log(`${cases.join(', ')} FAIL: ${error.message}`); await page.screenshot({ path: `docs/teacher-test-audit/failure-${cases[0]}-full.png`, fullPage: true }).catch(() => {}); }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/buyer-registration-full-browser.json', JSON.stringify(results, null, 2));
}
const common = 'Chrome/MySQL/local mail log; isolated unique email/phone aliases preserve existing accounts. ';
let account, expiring;
try {
  // Start a real five-minute expiry while the remaining browser cases execute.
  if (!selected) expiring = await accepted(expiryPage, next());
  else if (selected.includes(23)) account = await accepted(primary, next());
  await run([1], async () => { account = await accepted(primary, next()); assert.equal(fixture(account.data.email).user.given_names, 'Juan Miguel'); }, common + 'All required valid values saved, exactly one six-digit OTP logged, success and verification page shown; account remains Unverified. External delivery is separate.');
  await run([2], async () => {
    await page.goto(`${origin}/register/buyer`); const before = fixture('empty.audit@example.com').account_count, offset = await logSize(); await page.locator('#registerSubmit').click();
    for (const name of [...required, 'terms']) { assert.equal(await page.locator(`[name=${name}]`).evaluate(field => field.validity.valueMissing), true); assert.ok(await page.locator(`[data-client-error="${name}"]`).isVisible()); }
    assert.equal(fixture('empty.audit@example.com').account_count, before); assert.equal((await mailSince(offset)).includes('Your PocketFinds verification code is:'), false);
  }, common + 'Empty submission shows visible required messages on all seven fields and terms, with no account or OTP.');
  await run([3], async () => {
    for (const field of required) {
      const data = next(); await fill(page, data); await page.locator(`[name=${field}]`).fill(''); const before = fixture(data.email).account_count;
      await page.locator('#registerSubmit').click(); assert.equal(await page.locator('[data-client-error]').count(), 1); assert.ok(await page.locator(`[data-client-error="${field}"]`).isVisible());
      assert.equal(fixture(data.email).account_count, before);
      for (const name of required.filter(name => name !== field)) assert.equal(await page.locator(`[name=${name}]`).inputValue(), data[name]);
    }
  }, common + 'Cleared each mandatory field separately: exactly that field shows a required message, other inputs retained, account count unchanged.');
  await run([4], async () => { await rejected({ ...next(), given_names: 'Juan Miguel123', last_name: 'Dela Cruz@#' }, ['given_names', 'last_name']); }, common + 'Both workbook names containing digits/symbols rejected with field messages; no account/mail.');
  await run([5], async () => { const data = { ...next(), given_names: 'Niño Angelo', last_name: "Peñaflor-O'Hara" }; await accepted(page, data); const user = fixture(data.email).user; assert.equal(user.given_names, data.given_names); assert.equal(user.last_name, data.last_name); }, common + 'Specified ñ, apostrophe and hyphen names saved byte-for-byte.');
  await run([6], async () => {
    for (const [name, valid] of [['J', false], ['Jo', true], ['Maria Cristina Angelica Bernadette Josefina Leonor', true], ['Maria Cristina Angelica Bernadette Josefina Leonora', false]]) {
      const data = { ...next(), given_names: name }; if (valid) { await accepted(page, data); assert.equal(fixture(data.email).user.given_names, name); } else await rejected(data, ['given_names']);
    }
  }, common + 'Exact workbook names of 1/2/50/51 characters checked; only 2 and 50 accepted and saved exactly.');
  await run([7], async () => {
    assert.equal(fixture(account.data.email).server_date, '2026-10-16');
    await rejected({ ...next(), birthday: '2008-10-17' }, ['birthday']); assert.match(await page.locator('[data-client-error=birthday]').innerText(), /at least 18/i);
    for (const birthday of ['2008-10-16', '2008-10-15']) await accepted(page, { ...next(), birthday });
  }, common + 'On the workbook test date 2026-10-16, tomorrow’s 18th birthday rejected with age message; today/yesterday accepted.');
  await run([8], async () => { await rejected({ ...next(), birthday: '2026-10-17' }, ['birthday']); assert.match(await page.locator('[data-client-error=birthday]').innerText(), /valid date|future/i); }, common + 'Tomorrow’s birth date rejected with a valid-date/future-date message.');
  await run([9], async () => { for (const email of ['juanmiguel.delacruzexample.com', 'juanmiguel.delacruz@', '@example.com', 'juanmiguel.delacruz@example', 'juanmiguel delacruz@example.com']) {
    const data = { ...next(), email }; await fill(page, data); const before = fixture('invalid.audit@example.com').account_count, offset = await logSize();
    if (await page.locator('#registrationForm').evaluate(form => form.checkValidity())) await Promise.all([page.waitForEvent('domcontentloaded'), page.locator('#registerSubmit').click()]); else await page.locator('#registerSubmit').click();
    assert.ok(await page.locator('[name=email]').locator('..').locator('.field-error').count()); assert.equal(fixture('invalid.audit@example.com').account_count, before); assert.equal((await mailSince(offset)).includes('Your PocketFinds verification code is:'), false);
  } }, common + 'All five literal malformed workbook emails rejected with field messages and no account/OTP.');
  await run([10, 11], async () => { for (const email of [account.data.email, account.data.email.toUpperCase()]) await rejected({ ...next(), email }, ['email']); }, common + 'Existing primary email and uppercase form both rejected as duplicate without another account or OTP.');
  await run([12], async () => { const data = next(); await accepted(page, { ...data, email: '  ' + data.email + '  ' }); assert.equal(fixture(data.email).user.email, data.email); }, common + 'Two outer email spaces removed and canonical email persisted.');
  await run([13], async () => { for (const contact_no of ['0917ABC4567', '0917-123-456', '12345678901', '02-8123-4567']) await rejected({ ...next(), contact_no }, ['contact_no']); }, common + 'All four literal workbook invalid phones rejected without account/mail.');
  await run([14], async () => { for (const contact_no of ['0917123456', '091712345678']) await rejected({ ...next(), contact_no }, ['contact_no']); const data = next(); await accepted(page, data); assert.equal(fixture(data.email).user.contact_no.length, 11); }, common + 'Ten/twelve-digit numbers rejected; unique eleven-digit Philippine number accepted.');
  await run([15, 16], async () => { for (const contact_no of [account.data.contact_no, '+63' + account.data.contact_no.slice(1)]) await rejected({ ...next(), contact_no }, ['contact_no']); }, common + 'Existing phone in 09 and equivalent +63 formats both rejected as the same registered number.');
  await run([17], async () => { await rejected({ ...next(), password: 'Bili@26', password_confirmation: 'Bili@26' }, ['password', 'password_confirmation']); await accepted(page, { ...next(), password: 'Bili@202', password_confirmation: 'Bili@202' }); }, common + 'Seven-character workbook password rejected; eight-character password accepted.');
  await run([18], async () => { for (const password of ['mamimili@2026', 'MAMIMILI@2026', 'Mamimili@abcd', 'Mamimili2026']) await rejected({ ...next(), password, password_confirmation: password }, ['password']); }, common + 'All four missing-character-class passwords rejected without account/mail.');
  await run([19], async () => { await rejected({ ...next(), password_confirmation: 'Mamimili@2027' }, ['password']); }, common + 'Mismatched confirmation rejected with password-confirmation message.');
  await run([21], async () => { const data = await fill(page); const before = fixture(data.email).account_count; await page.locator('[name=terms]').uncheck(); await page.locator('#registerSubmit').click(); assert.match(await page.locator('[data-client-error=terms]').innerText(), /Terms and Privacy Policy/); assert.equal(fixture(data.email).account_count, before); }, common + 'Unticked terms block registration with explicit terms message and no new account.');
  await run([22], async () => { await accepted(page, next(), true); }, common + 'Three immediate Register button clicks produced exactly one unverified account and exactly one OTP, without duplicate-email error.');
  await run([24], async () => {
    await primary.goto(`${origin}/register/verify`); await primary.locator('#otp').fill(account.code === '000000' ? '111111' : '000000');
    await Promise.all([primary.waitForEvent('domcontentloaded'), primary.getByRole('button', { name: 'Verify', exact: true }).click()]); assert.match(await primary.getByRole('alert').innerText(), /invalid/i); assert.equal(fixture(account.data.email).user.status, 'unverified');
  }, common + 'A code different from actual mail code was rejected and account stayed Unverified.');
  await run([25], async () => {
    for (const value of ['48291', '4829137', '48A91B', '']) { await primary.locator('#otp').fill(value); await primary.getByRole('button', { name: 'Verify', exact: true }).click(); assert.equal(await primary.locator('#otp').evaluate(input => input.checkValidity()), false); assert.equal(fixture(account.data.email).user.status, 'unverified'); }
  }, common + 'Five/seven-digit, alphabetic and blank OTPs rejected by visible native validation; account stayed Unverified.');
  await run([28], async () => {
    const login = await (await browser.newContext()).newPage(); await login.goto(`${origin}/login`); await login.locator('#email').fill(account.data.email); await login.locator('#password').fill(account.data.password);
    await Promise.all([login.waitForEvent('domcontentloaded'), login.locator('form.auth-form button[type=submit]').click()]); assert.ok(!login.url().includes('/buyer/dashboard')); assert.match(await login.locator('body').innerText(), /verify|verification|unverified/i); await login.close();
  }, common + 'Unverified buyer login denied portal access and displayed verification requirement.');
  await run([27, 23], async () => {
    await primary.goto(`${origin}/register/verify`); const offset = await logSize();
    await Promise.all([primary.waitForEvent('domcontentloaded'), primary.getByRole('button', { name: 'Resend code', exact: true }).click()]);
    const code = (await mailSince(offset)).match(/Your PocketFinds verification code is: ([0-9]{6})/)?.[1]; assert.ok(code); assert.notEqual(code, account.code);
    await primary.locator('#otp').fill(account.code); await Promise.all([primary.waitForEvent('domcontentloaded'), primary.getByRole('button', { name: 'Verify', exact: true }).click()]); assert.match(await primary.getByRole('alert').innerText(), /invalid/i); assert.equal(fixture(account.data.email).user.status, 'unverified');
    await primary.locator('#otp').fill(code); await Promise.all([primary.waitForURL('**/login'), primary.getByRole('button', { name: 'Verify', exact: true }).click()]); assert.equal(fixture(account.data.email).user.status, 'approved'); assert.match(await primary.locator('body').innerText(), /Email verified/i);
  }, common + 'Resend logged a different code; first code rejected while Unverified, replacement accepted within five minutes, status became Active/approved and login showed success.');
  await run([29, 30], async () => {
    await primary.goto(`${origin}/login`); await primary.locator('#email').fill(account.data.email); await primary.locator('#password').fill(account.data.password);
    await Promise.all([primary.waitForURL('**/buyer/dashboard'), primary.locator('form.auth-form button[type=submit]').click()]); assert.match(await primary.locator('body').innerText(), /Juan Miguel/);
    for (const role of ['seller', 'logistics', 'rider', 'admin']) { const response = await primary.goto(`${origin}/${role}/dashboard`); assert.ok(response.status() === 403 || !primary.url().includes(`/${role}/dashboard`)); }
  }, common + 'Verified credentials opened buyer dashboard with Juan Miguel; seller, logistics, rider and admin dashboards each denied access without exposing their data.');
  await run([26], async () => {
    const remaining = expiring.created + 302000 - Date.now();
    if (remaining > 0) { console.log(`Waiting ${Math.ceil(remaining/1000)} seconds for actual five-minute OTP expiry; no database clock/token manipulation.`); await new Promise(resolve => setTimeout(resolve, remaining)); }
    await expiryPage.locator('#otp').fill(expiring.code); await Promise.all([expiryPage.waitForEvent('domcontentloaded'), expiryPage.getByRole('button', { name: 'Verify', exact: true }).click()]);
    assert.match(await expiryPage.getByRole('alert').innerText(), /expired/i); assert.ok(await expiryPage.getByRole('button', { name: 'Resend code', exact: true }).isVisible()); assert.equal(fixture(expiring.data.email).user.status, 'unverified');
  }, common + 'Waited at least 5:01 in real elapsed time without token/clock edits; original code rejected as expired, Resend remained available, account stayed Unverified.');
} finally {
  if (errors.length) { results.push({ status: 'FAIL', evidence: errors.join('; ') }); process.exitCode = 1; }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/buyer-registration-full-browser.json', JSON.stringify(results, null, 2)); await browser.close();
}
