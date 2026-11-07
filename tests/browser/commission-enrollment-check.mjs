// Synthetic HTML only. This server never connects to Laravel or a database.
import http from 'node:http';
import fs from 'node:fs/promises';
import path from 'node:path';
import net from 'node:net';
import { spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';

const root = process.cwd();
const privateRoot = path.resolve(root, 'storage/app/private');
const previews = path.join(privateRoot, 'commission-enrollment-ui');
const publicRoot = path.resolve(root, 'public');
const profile = path.join(privateRoot, `commission-browser-${randomBytes(6).toString('hex')}`);
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let posts = 0;
const mime = { '.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml', '.ico': 'image/x-icon' };
const server = http.createServer(async (req, res) => {
  if (!['GET', 'HEAD'].includes(req.method)) { posts++; res.writeHead(405).end(); return; }
  try {
    const url = new URL(req.url, 'http://localhost');
    if (/^\/preview\/(inactive|active|history)$/.test(url.pathname)) {
      const html = await fs.readFile(path.join(previews, `${path.basename(url.pathname)}.html`), 'utf8');
      res.setHeader('Content-Type', 'text/html; charset=utf-8');
      res.end(html.replace(/https?:\/\/[^/"'\s<>]+/g, `http://127.0.0.1:${server.address().port}`));
      return;
    }
    const file = path.resolve(publicRoot, `.${decodeURIComponent(url.pathname)}`);
    if (!file.startsWith(publicRoot + path.sep)) { res.writeHead(403).end(); return; }
    res.setHeader('Content-Type', mime[path.extname(file)] || 'application/octet-stream');
    res.end(await fs.readFile(file));
  } catch { res.writeHead(404).end(); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const probe = net.createServer();
await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
const debugPort = probe.address().port;
await new Promise(resolve => probe.close(resolve));
const browser = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', '--no-sandbox', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--remote-debugging-port=${debugPort}`, `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: ['ignore','ignore','pipe'] });
let browserErrors = '';
browser.stderr.on('data', chunk => { browserErrors += chunk.toString(); });
let ws;
const results = [];
try {
  let targets;
  for (let i = 0; i < 250; i++) {
    try { targets = await (await fetch(`http://127.0.0.1:${debugPort}/json`)).json(); if (targets.some(t => t.type === 'page')) break; } catch {}
    await delay(100);
  }
  const target = targets?.find(t => t.type === 'page');
  if (!target) { await fs.writeFile(path.join(previews,'chrome-errors.txt'),browserErrors); throw new Error('Chrome did not start; inspect private chrome-errors.txt'); }
  ws = new WebSocket(target.webSocketDebuggerUrl);
  const pending = new Map(); let seq = 0;
  ws.onmessage = e => { const v = JSON.parse(e.data); const task = pending.get(v.id); if (!task) return; pending.delete(v.id); v.error ? task.reject(new Error(v.error.message)) : task.resolve(v.result); };
  await new Promise((resolve, reject) => { ws.onopen = resolve; ws.onerror = reject; });
  const send = (method, params = {}) => new Promise((resolve, reject) => { const id = ++seq; pending.set(id, { resolve, reject }); ws.send(JSON.stringify({ id, method, params })); });
  const evaluate = async expression => { const r = await send('Runtime.evaluate', { expression, returnByValue: true }); if (r.exceptionDetails) throw new Error(r.exceptionDetails.text); return r.result.value; };
  await send('Page.enable');
  for (const role of ['inactive', 'active', 'history']) for (const width of [1440, 390]) for (const theme of ['light', 'dark']) {
    await send('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: width === 390 });
    await send('Page.navigate', { url: `http://127.0.0.1:${server.address().port}/preview/${role}` });
    await delay(900);
    await evaluate(`document.documentElement.dataset.theme = "${theme}"`);
    const result = await evaluate(`(() => {
      const scope=document.querySelector('.commission-enrollment');
      const inputs=[...scope.querySelectorAll('input:not([type=hidden]),select,textarea')].filter(e=>e.getClientRects().length);
      const missing=inputs.filter(e=>!e.closest('label')&&!e.labels?.length&&!e.getAttribute('aria-label')).length;
      const form=scope.querySelector('form'); let cancelled=true;
      if(form){window.confirm=()=>false;cancelled=!form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))&&!form.dataset.busy;}
      return {title:document.title,overflow:document.documentElement.scrollWidth>innerWidth,unlabelled:missing,cancelled};
    })()`);
    const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    await fs.writeFile(path.join(previews, `${role}-${width}-${theme}.png`), Buffer.from(shot.data, 'base64'));
    results.push({ role, width, theme, ...result });
  }
  await fs.writeFile(path.join(previews, 'browser.json'), JSON.stringify({ results, posts }, null, 2));
  if (results.some(r => r.overflow || r.unlabelled || !r.cancelled) || posts) throw new Error('Synthetic UI check failed; inspect private browser.json');
  console.log(JSON.stringify({ pages: results.length, overflow: 0, unlabelled: 0, cancelledConfirmations: results.length, posts }));
  await send('Browser.close');
} finally {
  ws?.close();
  await delay(500);
  if (browser.exitCode === null) browser.kill();
  await new Promise(resolve => server.close(resolve));
  if (profile.startsWith(privateRoot + path.sep) && /^commission-browser-[a-f0-9]{12}$/.test(path.basename(profile))) await fs.rm(profile, { recursive: true, force: true, maxRetries: 10, retryDelay: 100 }).catch(() => {});
}
