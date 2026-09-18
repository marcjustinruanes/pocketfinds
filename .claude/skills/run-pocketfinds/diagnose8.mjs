import { chromium } from 'playwright';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
await page.goto('http://127.0.0.1:8142/', { waitUntil: 'networkidle' });

await page.locator('[data-hero-slot] .pfx-showcase-item').click({ timeout: 5000, position: { x: 300, y: 300 } });
await page.waitForLoadState('networkidle');
console.log('hero card click -> url=', page.url());
console.log('h1=', await page.locator('h1').first().textContent().catch(() => '(none)'));

await page.goto('http://127.0.0.1:8142/', { waitUntil: 'networkidle' });
// wish button (in media, z-index 2) must NOT navigate
await page.locator('[data-hero-slot] .pfx-showcase-wish').click({ timeout: 5000 });
await page.waitForTimeout(400);
console.log('wish click -> url=', page.url(), 'modalOpen=', await page.locator('#authModal.open').count());

await page.goto('http://127.0.0.1:8142/', { waitUntil: 'networkidle' });
// list row bag button must NOT navigate, must open modal
await page.locator('[data-list-slot] .pfx-showcase-bag').first().click({ timeout: 5000 });
await page.waitForTimeout(400);
console.log('bag click -> url=', page.url(), 'modalOpen=', await page.locator('#authModal.open').count());

// list row card click (not on buttons) must navigate
await page.reload({ waitUntil: 'networkidle' });
await page.locator('[data-list-slot] .pfx-showcase-item').first().click({ timeout: 5000, position: { x: 150, y: 20 } });
await page.waitForLoadState('networkidle');
console.log('list row click -> url=', page.url());

await browser.close();
