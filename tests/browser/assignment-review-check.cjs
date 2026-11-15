const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
const chrome=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);

let html=fs.readFileSync('storage/framework/testing/assignment-review.html','utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
html=html.replace(/<link\b[^>]*href="([^"]+)"[^>]*>/gi,(_,url)=>{const file='public'+new URL(url,'http://localhost').pathname;return file.startsWith('public/css/')&&fs.existsSync(file)?'<style>'+fs.readFileSync(file,'utf8').replace(/@import[^;]+;/g,'')+'</style>':'';});
const checks=`setTimeout(()=>{try{
const expect=(v,m)=>{if(!v)throw Error(m)};
expect(document.documentElement.scrollWidth<=innerWidth+1,'Page fits viewport');
expect(!document.querySelector('dialog[open]'),'Review forms hidden initially');
const trigger=document.querySelector('[data-assignment-review]');trigger.focus();trigger.click();
const dialog=document.querySelector('dialog[open]');expect(!!dialog,'Review modal opens');
expect(document.querySelectorAll('[data-assignment-review]').length===1,'One details action for parcel');
expect(dialog.querySelectorAll('.assignment-step').length===3,'Three transport steps inside modal');
expect([...dialog.querySelectorAll('.assignment-step-heading h3')].map(item=>item.textContent.trim()).join(',')==='Pickup,Hub Transfer,Delivery','Steps follow parcel journey');
const review=dialog.querySelector('.assignment-step-review');expect(!review.open,'Step actions collapsed initially');review.querySelector('summary').click();expect(review.open,'Step review expands');
expect(dialog.querySelector('form[action*="reassign"]'),'Reassignment form available in modal');
expect(dialog.querySelector('.assignment-review-history'),'History available in modal');
const box=dialog.getBoundingClientRect();expect(box.left>=0&&box.right<=innerWidth,'Modal fits viewport');expect(box.top>=0&&box.bottom<=innerHeight,'Modal fits height');
expect(document.documentElement.classList.contains('assignment-review-modal-open'),'Background scrolling locked');
dialog.querySelector('[data-assignment-close]').click();expect(!dialog.open,'Close action works');
trigger.click();expect(dialog.open,'Review can reopen');dialog.click();expect(!dialog.open,'Backdrop closes review');
document.querySelector('#result').textContent='PASS';if(parent!==window)parent.postMessage({result:'PASS'},'*');
}catch(error){document.querySelector('#result').textContent=error.stack;if(parent!==window)parent.postMessage({result:error.stack},'*');}},150);`;
html=html.replace('</body>','<pre id="result" hidden></pre><script>'+fs.readFileSync('public/js/assignment-review.js','utf8')+'</script><script>'+checks+'</script></body>');
for(const width of [1440,390]){
 const fixture=path.resolve('storage/framework/testing/assignment-review-browser.html');
 const content=width<500?'<!doctype html><pre id="result" hidden></pre><script>addEventListener("message",e=>{if(e.data.result)document.querySelector("#result").textContent=e.data.result});</script><iframe width="390" height="1000" style="border:0" srcdoc="'+html.replaceAll('&','&amp;').replaceAll('"','&quot;')+'"></iframe>':html;
 fs.writeFileSync(fixture,content);
 const output=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--disable-background-networking','--window-size='+width+',1000','--virtual-time-budget=1500','--dump-dom','file:///'+fixture.replaceAll('\\','/')],{encoding:'utf8',timeout:45000,maxBuffer:6*1024*1024});
 const result=output.stdout?.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[1];
 assert.equal(result,'PASS',width+': '+output.stderr.slice(-300));console.log('PASS assignment review layout and modal at '+width+'px');
}
