const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const viewportWidth=Number(process.argv[2]??1280);
assert.ok(viewportWidth>=320 && viewportWidth<=2560,'Viewport must be between 320 and 2560 pixels');

const chrome = ['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
if (!chrome) throw new Error('Chrome or Edge is required for notification style checks.');
const read = file => fs.readFileSync(file,'utf8');
const cssFrom = file => {
    if (!fs.existsSync(file)) return [];
    return [...read(file).matchAll(/(?:css\/)[\w-]+\.css/g)].map(match=>`public/${match[0]}`);
};
const scenarios = [
    ...['orders','dashboard','cart','account','browse','product','wishlist','shop','messages','announcements'].map(page=>['buyer',page]),
    ...['orders','dashboard','inventory','promotions','account'].map(page=>['seller',page]),
    ['admin','dashboard'],['admin','account'],['admin','gcash'],['logistics','dashboard'],['rider','dashboard'],
];
const sample = `<details class="pf-buyer-menu pf-notification-menu" open><summary class="pf-acct-icon notification-trigger"><svg width="18" height="18" viewBox="0 0 24 24"></svg><span class="pf-buyer-dot"></span></summary><div class="pf-buyer-dropdown pf-buyer-notifications pf-notification-panel"><div class="notif-dropdown-head"><div><strong>Notifications</strong><small>Recent account activity</small></div><span>1 new</span></div><form class="notif-mark-read"><button>Mark all as read</button></form><div class="notif-list"><a href="#" class="notif-item"><span class="notif-entry-icon is-order"><svg width="16" height="16"></svg></span><span class="notif-item-copy"><strong>Order update</strong><span>Your order is preparing.</span><small class="notif-item-time">2 minutes ago</small></span><span class="dot"></span></a></div><div class="notif-footer"><a href="#">View notifications</a></div></div></details>`;
const frames = scenarios.map(([role,page],index)=>{
    const wrapperFile=`resources/views/${role}/${page}.blade.php`;
    const wrapperSource=fs.existsSync(wrapperFile)?read(wrapperFile):'';
    const standaloneStorefront=wrapperSource.includes("@include('guest.home')");
    const file=standaloneStorefront?'resources/views/guest/home.blade.php':wrapperFile;
    const source=fs.existsSync(file)?read(file):'';
    const pageClass=source.match(/@section\('body-class',\s*'([^']+)'\)/)?.[1]??'';
    const roleClass=standaloneStorefront?'marketplace storefront-home buyer-storefront':role==='buyer'?'buyer marketplace buyer-storefront buyer-pages':role==='seller'?'seller':role==='logistics'?'admin logistics-ui':'admin';
    const layoutSource=standaloneStorefront?source:read(`resources/views/${role}/layout.blade.php`);
    assert.ok(layoutSource.includes("@include('account.partials.notification-assets')"),`${role}/${page} must load the shared notification assets`);
    const files=[...(standaloneStorefront?[]:cssFrom(`resources/views/${role}/layout.blade.php`)),...cssFrom(file),'public/css/notifications.css','public/css/notification-menu.css'];
    if(role==='admin'&&page==='gcash') files.splice(-2,0,...cssFrom('resources/views/gcash/partials/admin-purchases.blade.php'));
    const css=[...new Set(files)].filter(fs.existsSync).map(file=>read(file).replace(/@import[^;]+;/g,'')).join('\n');
    const content=`<!doctype html><html><head><meta charset="utf-8"><style>${css}</style></head><body class="${roleClass} ${pageClass}"><header class="${role==='buyer'?'market-header':'topbar'}" style="position:static;padding:20px;display:flex;justify-content:flex-end">${sample}</header><script>const fields={bell:'.notification-trigger',panel:'.pf-notification-panel',heading:'.notif-dropdown-head strong',read:'.notif-mark-read button',row:'.notif-item',icon:'.notif-entry-icon',title:'.notif-item-copy strong',message:'.notif-item-copy>span',time:'.notif-item-time'};const properties=['width','height','fontFamily','fontSize','fontWeight','lineHeight','padding','borderRadius','borderWidth','backgroundColor','color','display','gridTemplateColumns'];const metrics={};for(const [key,selector] of Object.entries(fields)){const style=getComputedStyle(document.querySelector(selector));metrics[key]=Object.fromEntries(properties.map(property=>[property,style[property]]));}parent.postMessage({index:${index},name:${JSON.stringify(role+'/'+page)},metrics},'*');</script></body></html>`;
    return `<iframe width="${viewportWidth}" height="800" style="position:absolute;left:-3000px" srcdoc="${content.replace(/&/g,'&amp;').replace(/"/g,'&quot;')}"></iframe>`;
});
const fixture=path.resolve('tests/browser/.notification-styles-fixture.html');
fs.writeFileSync(fixture,`<!doctype html><meta charset="utf-8"><pre id="results"></pre><script>const results=[];addEventListener('message',event=>{if(!event.data.metrics)return;results[event.data.index]=event.data;document.querySelector('#results').textContent=JSON.stringify(results);});</script>${frames.join('')}`);
try {
    const result=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--disable-software-rasterizer','--in-process-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--virtual-time-budget=5000','--dump-dom',new URL('file:///'+fixture.replace(/\\/g,'/')).href],{encoding:'utf8',timeout:45000,maxBuffer:30*1024*1024});
    if(result.error) throw result.error;
    const output=result.stdout.match(/<pre id="results">([^<]*)<\/pre>/)?.[1];
    if(!output) throw new Error('Browser did not return style measurements. '+result.stderr.slice(-500));
    const results=JSON.parse(output.replace(/&quot;/g,'"').replace(/&amp;/g,'&'));
    assert.equal(results.filter(Boolean).length,scenarios.length,'Every account/page must return measurements');
    const reference=results[0].metrics;
    for(const result of results) assert.deepEqual(result.metrics,reference,`Notification styles differ on ${result.name}`);
    console.log(`Notification styles match buyer/orders across ${results.length} account/page stylesheets at ${viewportWidth}px.`);
} finally {
    fs.unlinkSync(fixture);
}
