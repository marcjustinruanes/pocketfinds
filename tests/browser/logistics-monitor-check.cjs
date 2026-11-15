const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
const chrome=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);


let html=fs.readFileSync('storage/framework/testing/live-monitor.html','utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
html=html.replace(/<link\b[^>]*href="([^"]+)"[^>]*>/gi,(_,url)=>{const file='public'+new URL(url,'http://localhost').pathname;return file.startsWith('public/css/')&&fs.existsSync(file)?'<style>'+fs.readFileSync(file,'utf8').replace(/@import[^;]+;/g,'')+'</style>':'';});
const checks=`setTimeout(()=>{try{
const expect=(v,m)=>{if(!v)throw Error(m)};
expect(document.documentElement.scrollWidth<=innerWidth+1,'Page fits viewport');
expect(!document.querySelector('.monitor-lookup,.monitor-guidance'),'Search guidance cards removed');
const input=document.querySelector('#monitor-search');expect(getComputedStyle(input).borderTopWidth==='0px','Search has no nested border');
[...document.querySelectorAll('.live-monitor-table th,.live-monitor-table td')].forEach(cell=>expect(getComputedStyle(cell).textAlign==='center','Centered table'));
expect(getComputedStyle(document.querySelector('.live-monitor-table th')).textTransform==='uppercase','Uppercase headings');
const button=document.querySelector('[data-modal-open^="monitor-status-"]'),modal=document.getElementById(button.dataset.modalOpen);
expect(!modal.classList.contains('open'),'Status form initially hidden');button.click();expect(modal.classList.contains('open'),'Update action opens modal');
const form=modal.querySelector('form');expect(form.querySelector('[name=attempt_token]')&&form.querySelector('[name=reason]').required,'Update safeguards preserved');
modal.querySelector('[data-modal-close]').click();expect(!modal.classList.contains('open'),'Modal closes');
document.querySelector('#result').textContent='PASS';if(parent!==window)parent.postMessage({result:'PASS'},'*');
}catch(error){document.querySelector('#result').textContent=error.stack;if(parent!==window)parent.postMessage({result:error.stack},'*');}},150);`;
html=html.replace('</body>','<pre id="result" hidden></pre><script>'+fs.readFileSync('public/js/admin.js','utf8')+'</script><script>'+checks+'</script></body>');
for(const width of [1440,390]){
 const fixture=path.resolve('storage/framework/testing/live-monitor-browser.html');
 const content=width<500?'<!doctype html><pre id="result" hidden></pre><script>addEventListener("message",e=>{if(e.data.result)document.querySelector("#result").textContent=e.data.result});</script><iframe width="390" height="1000" style="border:0" srcdoc="'+html.replaceAll('&','&amp;').replaceAll('"','&quot;')+'"></iframe>':html;
 fs.writeFileSync(fixture,content);
 const output=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--disable-background-networking','--window-size='+width+',1000','--virtual-time-budget=2500','--dump-dom','file:///'+fixture.replaceAll('\\','/')],{encoding:'utf8',timeout:45000,maxBuffer:6*1024*1024});
 const result=output.stdout?.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[1];
 assert.equal(result,'PASS',width+': '+output.stderr.slice(-300));console.log('PASS monitor table and status modal at '+width+'px');
}
