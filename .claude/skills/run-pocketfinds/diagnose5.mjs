import { chromium } from 'playwright';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
await page.goto('http://127.0.0.1:8142/', { waitUntil: 'networkidle' });

console.log('step1: loaded home, url=', page.url());
await page.locator('[data-hero-slot] .pfx-showcase-media img').click({ timeout: 5000 });
await page.waitForTimeout(1500);
console.log('step2: after hero click, url=', page.url());

await page.goto('http://127.0.0.1:8142/', { waitUntil: 'networkidle' });
console.log('step3: back on home, url=', page.url());
const bagCount = await page.locator('[data-list-slot] .pfx-showcase-bag').count();
console.log('step4: bag button count=', bagCount);
await page.locator('[data-list-slot] .pfx-showcase-bag').first().click({ timeout: 5000 });
await page.waitForTimeout(500);
const modalOpen = await page.locator('#authModal.open').count();
console.log('step5: modalOpen=', modalOpen, 'url=', page.url());

await browser.close();
