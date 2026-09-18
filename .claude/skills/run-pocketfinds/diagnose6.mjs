import { chromium } from 'playwright';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
await page.goto('http://127.0.0.1:8142/', { waitUntil: 'networkidle' });

// Test an EXISTING, unmodified product card (Latest Listings) as a control
await page.locator('#products .pfx-product-card').first().click({ timeout: 5000 });
await page.waitForTimeout(1000);
console.log('control (Latest Listings card) click -> url=', page.url());

await page.goto('http://127.0.0.1:8142/', { waitUntil: 'networkidle' });

// Inspect what's actually at the hero image's click point
const box = await page.locator('[data-hero-slot] .pfx-showcase-media img').boundingBox();
const cx = box.x + box.width / 2, cy = box.y + box.height / 2;
const info = await page.evaluate(([cx, cy]) => {
  const el = document.elementFromPoint(cx, cy);
  const path = [];
  let cur = el;
  while (cur && cur !== document.body) { path.push(cur.tagName + '.' + (cur.className || '')); cur = cur.parentElement; }
  return { tag: el.tagName, cls: el.className, path };
}, [cx, cy]);
console.log('elementFromPoint at hero image center:', JSON.stringify(info, null, 2));

await browser.close();
