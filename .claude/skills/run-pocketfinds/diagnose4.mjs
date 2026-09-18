import { chromium } from 'playwright';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const errors = [];
page.on('pageerror', err => errors.push('pageerror: ' + err.message));
await page.goto('http://127.0.0.1:8142/', { waitUntil: 'networkidle' });

// Click the hero card's media image (not the wish/bag buttons) — should navigate via the stretched-link
await page.locator('[data-hero-slot] .pfx-showcase-media img').click();
await page.waitForLoadState('networkidle');
const heroClickUrl = page.url();

await page.goBack();
await page.waitForLoadState('networkidle');

// Click a list row's bag button — should open the auth modal, NOT navigate
const bagBtn = page.locator('[data-list-slot] .pfx-showcase-bag').first();
await bagBtn.click();
const modalOpen = await page.locator('#authModal.open').count();
const urlAfterBagClick = page.url();

console.log(JSON.stringify({ heroClickUrl, modalOpen, urlAfterBagClick, errors }, null, 2));
await browser.close();
