// Run after rendering a synthetic receipt to storage/framework/testing/receipt-preview.html.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require('playwright');

(async () => {
  const directory = path.resolve('storage/framework/testing');
  const fixture = path.join(directory, 'receipt-preview.html');
  assert.ok(fs.existsSync(fixture), 'Render the synthetic receipt fixture first');
  const executablePath = ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
  const browser = await chromium.launch({ executablePath, headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(pathToFileURL(fixture).href);
    await page.locator('#print-receipt').waitFor();
    await page.waitForFunction(() => getComputedStyle(document.querySelector('.receipt')).maxWidth === '850px');
    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: 850 });
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Receipt overflows at ${width}px`);
      await page.screenshot({ path: path.join(directory, `receipt-${width}.png`), fullPage: true });
    }
    await page.evaluate(() => { window.printCalls = 0; window.print = () => window.printCalls++; });
    await page.getByRole('button', { name: 'Print / save PDF' }).click();
    assert.equal(await page.evaluate(() => window.printCalls), 1);
    await page.emulateMedia({ media: 'print' });
    assert.equal(await page.locator('#print-receipt').isVisible(), false);
    await page.pdf({ path: path.join(directory, 'receipt-preview.pdf'), format: 'A4' });
    assert.deepEqual(errors, []);
    console.log('PASS: receipt at desktop and mobile sizes, print action, print styles and PDF export');
    for (const role of ['buyer', 'seller']) {
      const orderFixture = path.join(directory, `${role}-orders-preview.html`);
      if (!fs.existsSync(orderFixture)) continue;
      const context = await browser.newContext();
      const modalPage = await context.newPage();
      await context.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/__order_receipt_qa__') return route.fulfill({ contentType: 'text/html', body: fs.readFileSync(orderFixture, 'utf8') });
        if (url.hostname === '127.0.0.1' && /^\/(css|js|build|images|fonts)\//.test(url.pathname) && route.request().method() === 'GET') return route.continue();
        // Synthetic previews never submit forms or access real account endpoints.
        return route.fulfill({ contentType: 'application/json', body: '{}' });
      });
      await modalPage.goto('http://127.0.0.1:8000/__order_receipt_qa__', { waitUntil: 'networkidle' });
      for (const width of [1280, 390]) {
        await modalPage.setViewportSize({ width, height: 850 });
        const opener = role === 'buyer'
          ? modalPage.getByRole('button', { name: 'Continue payment', exact: true }).first()
          : modalPage.locator('[data-modal^="sellerOrderDetails"]').first();
        await opener.click();
        const modal = modalPage.locator('[data-order-summary].open');
        await modal.waitFor({ state: 'visible' });
        const dialog = modal.locator('.order-details-dialog');
        assert.ok(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth + 2), `${role} dialog overflows at ${width}px`);
        assert.ok(await modal.getByRole('link', { name: 'Print / save PDF' }).isVisible());
        if (role === 'buyer') {
          const products = modal.locator('details').filter({ has: modalPage.locator('summary', { hasText: 'View products and order total' }) });
          if (!(await products.evaluate(el => el.open))) await products.locator('summary').click();
          assert.ok(await products.evaluate(el => el.open));
          assert.ok(await products.getByRole('cell').filter({ hasText: 'Receipt item' }).first().isVisible());
        } else {
          assert.ok(await modal.getByText('Seller earnings and settlement', { exact: true }).isVisible());
        }
        assert.ok(await modal.locator('.order-details-head h3').evaluate(el => el.getBoundingClientRect().height <= parseFloat(getComputedStyle(el).lineHeight) * 2 + 2), `${role} title is squeezed at ${width}px`);
        await modalPage.screenshot({ path: path.join(directory, `${role}-order-${width}.png`), fullPage: true });
        await modal.locator(role === 'buyer' ? '.order-details-close-icon' : '[data-modal-close]').first().click();
        await modal.waitFor({ state: 'hidden' });
      }
      await context.close();
      console.log(`PASS: ${role} order modal opens, shows the added details, fits desktop/mobile and closes`);
    }
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
