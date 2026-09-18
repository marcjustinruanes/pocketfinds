import { chromium } from 'playwright';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
await page.goto('http://127.0.0.1:8142/', { waitUntil: 'networkidle' });

const result = await page.evaluate(() => {
  const vw = window.innerWidth;
  const overflowing = [];
  document.querySelectorAll('body *').forEach(el => {
    const rect = el.getBoundingClientRect();
    if (rect.right > vw + 1 || rect.width > vw + 1) {
      overflowing.push({
        tag: el.tagName,
        cls: el.className && el.className.toString ? el.className.toString().slice(0, 80) : '',
        width: Math.round(rect.width),
        right: Math.round(rect.right),
        scrollWidth: el.scrollWidth,
      });
    }
  });
  return { vw, bodyScrollWidth: document.body.scrollWidth, overflowing: overflowing.slice(0, 25) };
});

console.log(JSON.stringify(result, null, 2));
await browser.close();
