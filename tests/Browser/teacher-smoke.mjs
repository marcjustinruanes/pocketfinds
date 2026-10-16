import { chromium } from 'playwright';
import { writeFile } from 'node:fs/promises';

const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const results = [];
try {
  for (const role of ['buyer', 'seller', 'rider', 'logistics']) {
    const context = await browser.newContext();
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${origin}/login`);
    await page.locator('#email').fill(`${role}@example.com`);
    await page.locator('#password').fill('TeacherAudit@2026');
    await Promise.all([page.waitForURL(`**/${role}/dashboard`), page.locator('form.auth-form button[type=submit]').click()]);
    for (const route of role === 'buyer' ? ['dashboard', 'account', 'cart', 'orders']
      : role === 'seller' ? ['dashboard', 'inventory', 'vouchers', 'orders', 'account']
      : ['dashboard', 'account']) {
      const response = await page.goto(`${origin}/${role}/${route}`);
      const result = { role, route, status: response.status(), url: page.url(), errors: errors.splice(0) };
      results.push(result);
      console.log(JSON.stringify(result));
    }
    await page.screenshot({ path: `docs/teacher-test-audit/${role}-browser.png`, fullPage: true });
    await context.close();
  }
} finally {
  await browser.close();
  await writeFile('docs/teacher-test-audit/browser-smoke.json', JSON.stringify(results, null, 2));
}
if (results.some(row => row.status !== 200 || row.errors.length)) process.exitCode = 1;
