const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
const chrome=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);


let html=fs.readFileSync('storage/framework/testing/combined-assignments.html','utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
html=html.replace(/<link\b[^>]*href="([^"]+)"[^>]*>/gi,(_,url)=>{const file='public'+new URL(url,'http://localhost').pathname;return file.startsWith('public/css/')&&fs.existsSync(file)?'<style>'+fs.readFileSync(file,'utf8').replace(/@import[^;]+;/g,'')+'</style>':'';});
const checks=`setTimeout(()=>{try{const expect=(v,m)=>{if(!v)throw Error(m)};expect(document.documentElement.scrollWidth<=innerWidth+1,'Page fits');expect(document.querySelectorAll('.delivery-assignment-card table').length===1,'One assignment table');const rows=[...document.querySelectorAll('.delivery-assignment-card tbody tr')];expect(rows.length===2,'Mixed rows shown together');expect(rows.some(r=>r.textContent.includes('Hub transfer'))&&rows.some(r=>r.textContent.includes('Delivery')),'Both types in table');const action=document.querySelector('[data-transfer-details-url]');expect(!!action,'Transfer actions retained');expect(!document.querySelector('#assignment-view'),'No type filter');document.querySelector('#result').textContent='PASS';if(parent!==window)parent.postMessage({result:'PASS'},'*');}catch(e){document.querySelector('#result').textContent=e.stack;if(parent!==window)parent.postMessage({result:e.stack},'*');}},150);`;
html=html.replace('</body>','<pre id="result" hidden></pre><script>'+fs.readFileSync('public/js/admin.js','utf8')+'</script><script>'+checks+'</script></body>');
for(const width of [1440,390]){
 const fixture=path.resolve('storage/framework/testing/combined-assignments-browser.html');
 const content=width<500?'<!doctype html><pre id="result" hidden></pre><script>addEventListener("message",e=>{if(e.data.result)document.querySelector("#result").textContent=e.data.result});</script><iframe width="390" height="1000" style="border:0" srcdoc="'+html.replaceAll('&','&amp;').replaceAll('"','&quot;')+'"></iframe>':html;
 fs.writeFileSync(fixture,content);
 const output=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--disable-background-networking','--window-size='+width+',1000','--virtual-time-budget=2500','--dump-dom','file:///'+fixture.replaceAll('\\','/')],{encoding:'utf8',timeout:45000,maxBuffer:6*1024*1024});
 const result=output.stdout?.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[1];
 assert.equal(result,'PASS',width+': '+output.stderr.slice(-300));console.log('PASS combined assignments at '+width+'px');
}
