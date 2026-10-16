import assert from 'node:assert/strict';
import { readFile, writeFile, stat } from 'node:fs/promises';
import { chromium } from 'playwright';

const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const results = [];
const page = await browser.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
try {
  await page.goto(`${origin}/register/buyer`);
  assert.equal(await page.locator('#password').getAttribute('type'), 'password');
  assert.equal(await page.locator('#password_confirmation').getAttribute('type'), 'password');
  results.push({ case: 'TC-BUY-020', status: 'PASS', evidence: 'Both password controls have type=password in Chrome.' });
  await page.screenshot({ path: 'docs/teacher-test-audit/buyer-registration-browser.png', fullPage: true });
  await page.locator('#given_names').fill('Niño Angelo');
  await page.locator('#last_name').fill("Peñaflor-O'Hara");
  const email = `browser.${Date.now()}@example.com`;
  await page.locator('#email').fill(email);
  await page.locator('#contact_no').fill('+63917' + String(Date.now()).slice(-7));
  await page.locator('#birthday').fill('1998-05-14');
  await page.locator('#password').fill('Mamimili@2026');
  await page.locator('#password_confirmation').fill('Mamimili@2026');
  // The browser must prevent registration until terms are accepted.
  assert.equal(await page.locator('#registrationForm').evaluate(form => form.checkValidity()), false);
  await page.locator('[name=terms]').check();
  assert.equal(await page.locator('#registrationForm').evaluate(form => form.checkValidity()), true);
  const offset = await stat('storage/logs/laravel.log').then(s => s.size).catch(() => 0);
  await Promise.all([page.waitForURL('**/register/verify'), page.locator('#registerSubmit').click()]);
  assert.match(await page.textContent('body'), new RegExp(email.replaceAll('.', '\\.')));
  const log = (await readFile('storage/logs/laravel.log')).subarray(offset).toString();
  const code = log.match(/Your PocketFinds verification code is: ([0-9]{6})/)?.[1];
  assert.ok(code, 'A verification code must be present in the local mail log.');
  await page.locator('#otp').fill(code);
  await Promise.all([page.waitForURL('**/login'), page.getByRole('button', { name: 'Verify', exact: true }).click()]);
  await page.locator('#email').fill(email);
  await page.locator('#password').fill('Mamimili@2026');
  await Promise.all([page.waitForURL('**/buyer/dashboard'), page.locator('form.auth-form button[type=submit]').click()]);
  results.push({ cases: ['TC-BUY-001', 'TC-BUY-005', 'TC-BUY-021', 'TC-BUY-023', 'TC-BUY-029'], status: 'BROWSER WORKFLOW VERIFIED',
    evidence: 'Registered a fictional buyer, captured OTP in local mail log, verified and signed in. External email delivery unverified.' });
  for (const role of ['seller', 'logistics']) {
    await page.goto(`${origin}/register/${role}`);
    assert.equal(await page.locator('#password').getAttribute('type'), 'password');
    assert.equal(await page.locator('#password_confirmation').getAttribute('type'), 'password');
    results.push({ case: role === 'seller' ? 'TC-SEL-018' : 'TC-LOG-018', status: 'PASS', evidence: 'Password controls have type=password in Chrome.' });
    if (role === 'seller') {
      for (const type of ['individual', 'sole_proprietorship', 'corporation']) {
        await page.locator('#seller_type').selectOption(type);
        assert.equal(await page.locator('#dti_file').isVisible(), type === 'sole_proprietorship');
        assert.equal(await page.locator('#sec_file').isVisible(), type === 'corporation');
        assert.equal(await page.locator('#bir_file').isVisible(), type !== 'individual');
      }
      results.push({ case: 'TC-SEL-023', status: 'BROWSER CONDITIONAL FIELDS VERIFIED', evidence: 'Individual, sole-proprietorship and corporation document controls change correctly.' });
    }
  }
  assert.deepEqual(errors, []);
} catch (error) {
  results.push({ status: 'FAIL', error: error.message });
  process.exitCode = 1;
} finally {
  results.forEach(row => { row.origin = origin; });
  await writeFile('docs/teacher-test-audit/registration-browser.json', JSON.stringify(results, null, 2));
  console.log(JSON.stringify(results));
  await browser.close();
}
