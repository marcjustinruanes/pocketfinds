import assert from 'node:assert/strict';
import { readFile, writeFile, stat } from 'node:fs/promises';
import { chromium } from 'playwright';

const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const results = [];
const context = await browser.newContext();
const page = await context.newPage();
try {
  await page.goto(`${origin}/login`);
  await page.locator('#email').fill('logistics@example.com');
  await page.locator('#password').fill('TeacherAudit@2026');
  await Promise.all([page.waitForURL('**/logistics/dashboard'), page.locator('form.auth-form button[type=submit]').click()]);
  await page.goto(`${origin}/logistics/riders/create`);
  const serial = String(Date.now());
  const email = `rider.browser.${serial}@example.com`;
  for (const [field, value] of Object.entries({ given_names: 'Pedro Jose', last_name: 'Garcia', email,
    contact_no: '0945' + serial.slice(-7), license_number: 'N01-23-' + serial.slice(-6), plate_number: 'BR' + serial.slice(-6),
    birthday: '1995-03-22', license_expiry: '2030-03-22' })) await page.locator(`#${field}`).fill(value);
  await page.locator('#vehicle_type').selectOption('two_wheels');
  const hub = await page.locator('#logistics_hub_id option').nth(1).getAttribute('value');
  await page.locator('#logistics_hub_id').selectOption(hub);
  const image = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII=', 'base64');
  for (const field of ['license_file', 'or_cr_file']) await page.locator(`#${field}`).setInputFiles({ name: `${field}.png`, mimeType: 'image/png', buffer: image });
  const offset = await stat('storage/logs/laravel.log').then(s => s.size).catch(() => 0);
  await Promise.all([page.waitForURL('**/logistics/riders?status=pending_activation'), page.getByRole('button', { name: 'Save and send invitation' }).click()]);
  assert.match(await page.textContent('body'), new RegExp(email.replaceAll('.', '\\.')));
  assert.ok(await page.getByRole('button', { name: 'Resend invitation' }).count() > 0);
  const log = (await readFile('storage/logs/laravel.log')).subarray(offset).toString();
  const path = log.match(/\/rider\/activate\/[0-9]+\/[A-Za-z0-9]{64}/)?.[0];
  assert.ok(path, 'An activation invitation must have been written to the local mail log.');
  const riderContext = await browser.newContext();
  const rider = await riderContext.newPage();
  await rider.goto(origin + path);
  await rider.locator('#password').fill('Rider@2026');
  await rider.locator('#password_confirmation').fill('Rider@2026');
  await Promise.all([rider.waitForURL('**/login'), rider.getByRole('button', { name: 'Activate account' }).click()]);
  await rider.locator('#email').fill(email);
  await rider.locator('#password').fill('Rider@2026');
  await Promise.all([rider.waitForURL('**/rider/dashboard'), rider.locator('form.auth-form button[type=submit]').click()]);
  assert.equal((await rider.request.get(origin + path)).status(), 410);
  assert.equal((await rider.request.get(`${origin}/register/rider`)).status(), 403);
  results.push({ cases: ['TC-RID-001', 'TC-RID-020', 'TC-RID-025', 'TC-RID-027', 'TC-RID-029'], status: 'BROWSER WORKFLOW VERIFIED',
    evidence: 'Created rider from logistics form, read local logged invitation, activated, signed in, verified single-use link and denied public registration. External email delivery unverified.' });
  await rider.screenshot({ path: 'docs/teacher-test-audit/activated-rider-browser.png', fullPage: true });
} catch (error) {
  results.push({ status: 'FAIL', error: error.message });
  process.exitCode = 1;
} finally {
  results.forEach(row => { row.origin = origin; });
  await writeFile('docs/teacher-test-audit/rider-invitation-browser.json', JSON.stringify(results, null, 2));
  console.log(JSON.stringify(results));
  await browser.close();
}
