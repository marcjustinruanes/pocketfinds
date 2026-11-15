const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
const chrome=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);


let html=fs.readFileSync('storage/framework/testing/transfer-filters.html','utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
html=html.replace(/<link\b[^>]*href="([^"]+)"[^>]*>/gi,(_,url)=>{const file='public'+new URL(url,'http://localhost').pathname;return file.startsWith('public/css/')&&fs.existsSync(file)?'<style>'+fs.readFileSync(file,'utf8').replace(/@import[^;]+;/g,'')+'</style>':'';});
const checks=`setTimeout(async()=>{try{
const expect=(v,m)=>{if(!v)throw Error(m)};
expect(document.documentElement.scrollWidth<=innerWidth+1,'Page fits viewport');
const queueTabs=document.querySelector('.hub-transfer-toolbar>.tabs');expect(getComputedStyle(queueTabs).backgroundColor==='rgba(0, 0, 0, 0)','Queue tabs have no large white container');expect(getComputedStyle(queueTabs).borderTopWidth==='0px','Queue container outline removed');expect(queueTabs.scrollWidth<=queueTabs.clientWidth+1,'Queue text fits without clipping');
const form=document.getElementById('hubTransferFilters'),status=document.querySelector('select[form="hubTransferFilters"]');
expect(getComputedStyle(form).backgroundColor==='rgba(0, 0, 0, 0)','Hub and date filters have no white card');
expect(!!status.closest('th'),'Status filter sits in table heading');expect(status.parentElement.querySelector('span').textContent==='Status','Status heading stays plain text');expect(getComputedStyle(status).opacity==='0','Native field has no visible box');
[...document.querySelectorAll('.hub-transfer-records th,.hub-transfer-records td')].forEach(cell=>expect(getComputedStyle(cell).textAlign==='center','Table headings and values centered'));
expect(getComputedStyle(status.closest('th')).textTransform==='uppercase','Uppercase table headings');expect(!form.querySelector('button'),'No Filter button');
const searchInput=form.elements.namedItem('search'),searchStyle=getComputedStyle(searchInput);expect(searchStyle.borderTopWidth==='0px'&&searchStyle.borderRadius==='0px','Search input has no nested outline');searchInput.focus();expect(getComputedStyle(searchInput).outlineStyle==='none','Focus stays on search wrapper');expect(getComputedStyle(searchInput.closest('.hub-transfer-search')).borderTopWidth==='1px','One outer search border');
const calls=[];form.requestSubmit=()=>calls.push(Object.fromEntries(new FormData(form)));
status.value='approved';status.dispatchEvent(new Event('change',{bubbles:true}));expect(calls.length===1&&calls[0].status==='approved'&&calls[0].hub_id,'Status auto-submit retains hub filter');
window.dispatchEvent(new Event('pageshow'));
const from=form.elements.namedItem('from'),to=form.elements.namedItem('to');from.value='2026-01-01';to.value='2026-12-31';to.dispatchEvent(new Event('change',{bubbles:true}));expect(calls.length===2&&calls[1].from==='2026-01-01','Dates auto-submit');
window.dispatchEvent(new Event('pageshow'));
const search=form.elements.namedItem('search');search.value='parcel';search.dispatchEvent(new Event('input',{bubbles:true}));await new Promise(resolve=>setTimeout(resolve,600));expect(calls.length===3&&calls[2].search==='parcel','Search auto-submits after typing pause');
if(innerWidth>760){const toolbar=document.querySelector('.hub-transfer-toolbar'),nav=toolbar.querySelector('nav').getBoundingClientRect(),button=toolbar.querySelector('.btn').getBoundingClientRect();const searchBox=toolbar.querySelector('.hub-transfer-search').getBoundingClientRect();expect(Math.abs(searchBox.top+searchBox.height/2-button.top-button.height/2)<2,'Search and transfer action share row');if(innerWidth>1000)expect(Math.abs(nav.top+nav.height/2-button.top-button.height/2)<2,'Queues share toolbar');
const hub=document.querySelector('.hub-transfer-hub-filter').getBoundingClientRect(),dates=document.querySelector('.hub-transfer-date-filter').getBoundingClientRect();expect(hub.right<=dates.left&&Math.abs(hub.bottom-dates.bottom)<2,'Hub left and dates right aligned');}
document.querySelector('#result').textContent='PASS';if(parent!==window)parent.postMessage({result:'PASS'},'*');
}catch(error){document.querySelector('#result').textContent=error.stack;if(parent!==window)parent.postMessage({result:error.stack},'*');}},150);`;
html=html.replace('</body>','<pre id="result" hidden></pre><script>'+fs.readFileSync('public/js/logistics-transfer-filters.js','utf8')+'</script><script>'+checks+'</script></body>');
for(const width of [1440,390]){
 const fixture=path.resolve('storage/framework/testing/transfer-filters-browser.html');
 const content=width<500?'<!doctype html><pre id="result" hidden></pre><script>addEventListener("message",e=>{if(e.data.result)document.querySelector("#result").textContent=e.data.result});</script><iframe width="390" height="1000" style="border:0" srcdoc="'+html.replaceAll('&','&amp;').replaceAll('"','&quot;')+'"></iframe>':html;
 fs.writeFileSync(fixture,content);
 const output=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--disable-background-networking','--window-size='+width+',1000','--virtual-time-budget=2500','--dump-dom','file:///'+fixture.replaceAll('\\','/')],{encoding:'utf8',timeout:45000,maxBuffer:6*1024*1024});
 const result=output.stdout?.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[1];
 assert.equal(result,'PASS',width+': '+output.stderr.slice(-300));console.log('PASS transfer filters and toolbar at '+width+'px');
}
