import { chromium } from 'playwright';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
await page.goto('http://127.0.0.1:8142/', { waitUntil: 'networkidle' });

const img = page.locator('[data-hero-slot] .pfx-showcase-media img');
await img.scrollIntoViewIfNeeded();
const box = await img.boundingBox();
const cx = box.x + box.width / 2, cy = box.y + box.height / 2;
const info = await page.evaluate(([cx, cy]) => {
  const el = document.elementFromPoint(cx, cy);
  if (!el) return null;
  const path = [];
  let cur = el;
  while (cur && cur !== document.body) { path.push(cur.tagName + '.' + (cur.className || '')); cur = cur.parentElement; }
  return { tag: el.tagName, cls: el.className, path };
}, [cx, cy]);
console.log('box=', box);
console.log('elementFromPoint at hero image center:', JSON.stringify(info, null, 2));

await browser.close();
