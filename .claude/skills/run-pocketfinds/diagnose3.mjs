import { chromium } from 'playwright';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const errors = [];
page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
page.on('pageerror', err => errors.push('pageerror: ' + err.message));

await page.goto('http://127.0.0.1:8142/', { waitUntil: 'networkidle' });

// Click the "Health and Beauty" pill (real nav link) and confirm it lands correctly
const pill = page.locator('.pfx-cat-pill', { hasText: 'Health and Beauty' });
await pill.click();
await page.waitForLoadState('networkidle');
const afterClick = {
  url: page.url(),
  h2: await page.locator('#products .pfx-h2').first().textContent(),
  activePill: await page.locator('.pfx-cat-pill[aria-current="page"]').textContent(),
  showcaseCount: await page.locator('[data-showcase-item]').count(),
};

// Test in-stock checkbox + sort select interactivity
const instock = page.locator('[data-instock-toggle]');
await instock.check();
const visibleAfterToggle = await page.locator('[data-visible-count]').textContent();
await instock.uncheck();
const visibleAfterUntoggle = await page.locator('[data-visible-count]').textContent();

const sortSelect = page.locator('[data-sort-select]');
await sortSelect.selectOption('price_asc');
const firstPriceAfterSort = await page.locator('[data-hero-slot] .pfx-showcase-price').first().textContent();

console.log(JSON.stringify({ afterClick, visibleAfterToggle, visibleAfterUntoggle, firstPriceAfterSort, errors }, null, 2));
await browser.close();
