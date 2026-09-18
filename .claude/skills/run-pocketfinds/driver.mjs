// Driver for the run-pocketfinds skill.
// Launches headless Chromium against an already-running `php artisan serve`,
// drives one real page, and writes a screenshot + a small JSON fact-sheet.
//
// Usage:
//   node driver.mjs <url> <outPrefix> [--scroll]
//
// Examples:
//   node driver.mjs http://127.0.0.1:8000/ home
//   node driver.mjs http://127.0.0.1:8000/ home --scroll
//
// Output (written to the current working directory):
//   <outPrefix>-desktop.png   1440x1000 viewport
//   <outPrefix>-mobile.png    390x844 viewport
//   <outPrefix>-facts.json    title, H1 text, console errors, broken <img> count

import { chromium } from 'playwright';
import { writeFileSync } from 'node:fs';

const [, , url, outPrefix, ...flags] = process.argv;
if (!url || !outPrefix) {
  console.error('Usage: node driver.mjs <url> <outPrefix> [--scroll]');
  process.exit(1);
}
const doScroll = flags.includes('--scroll');

async function capture(browser, viewport, suffix) {
  const page = await browser.newPage({ viewport });
  const consoleErrors = [];
  page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
  page.on('pageerror', err => consoleErrors.push('pageerror: ' + err.message));

  const response = await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(500);

  if (doScroll) {
    await page.evaluate(async () => {
      for (let y = 0; y < document.body.scrollHeight; y += 300) {
        window.scrollTo(0, y);
        await new Promise(r => setTimeout(r, 40));
      }
      window.scrollTo(0, 0);
      await new Promise(r => setTimeout(r, 100));
    });
    await page.waitForTimeout(300);
  }

  const outFile = `${outPrefix}-${suffix}.png`;
  await page.screenshot({ path: outFile, fullPage: true });

  const facts = await page.evaluate(() => ({
    title: document.title,
    h1: document.querySelector('h1')?.textContent?.trim() || null,
    brokenImages: [...document.images].filter(img => !img.complete || img.naturalWidth === 0).length,
    totalImages: document.images.length,
  }));

  return {
    viewport,
    httpStatus: response?.status() ?? null,
    file: outFile,
    consoleErrors,
    ...facts,
  };
}

const browser = await chromium.launch();
const desktop = await capture(browser, { width: 1440, height: 1000 }, 'desktop');
const mobile = await capture(browser, { width: 390, height: 844 }, 'mobile');
await browser.close();

const report = { url, desktop, mobile };
writeFileSync(`${outPrefix}-facts.json`, JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));

if (desktop.httpStatus !== 200 || mobile.httpStatus !== 200) {
  console.error('Non-200 response detected.');
  process.exit(1);
}
if (desktop.consoleErrors.length || mobile.consoleErrors.length) {
  console.error('Console errors detected — see facts JSON.');
  process.exit(1);
}
