import { chromium } from 'playwright';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
await page.goto('http://127.0.0.1:8142/', { waitUntil: 'networkidle' });

const result = await page.evaluate(() => {
  const pills = document.querySelector('.pfx-cat-pills');
  const toolbar = document.querySelector('.pfx-cat-toolbar');
  const cs = getComputedStyle(pills);
  const ct = getComputedStyle(toolbar);
  return {
    pillsRect: pills.getBoundingClientRect(),
    pills: { overflowX: cs.overflowX, flexWrap: cs.flexWrap, minWidth: cs.minWidth, width: cs.width, display: cs.display },
    toolbarRect: toolbar.getBoundingClientRect(),
    toolbar: { display: ct.display, flexDirection: ct.flexDirection, alignItems: ct.alignItems, width: ct.width },
    matchMedia640: window.matchMedia('(max-width: 640px)').matches,
  };
});

console.log(JSON.stringify(result, null, 2));
await browser.close();
