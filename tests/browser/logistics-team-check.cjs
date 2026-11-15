const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
const chrome=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);


let html=fs.readFileSync('storage/framework/testing/team-editor.html','utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
html=html.replace(/<link\b[^>]*href="([^"]+)"[^>]*>/gi,(_,url)=>{const file='public'+new URL(url,'http://localhost').pathname;return file.startsWith('public/css/')&&fs.existsSync(file)?'<style>'+fs.readFileSync(file,'utf8').replace(/@import[^;]+;/g,'')+'</style>':'';});
const checks=`setTimeout(()=>{try{
const expect=(v,m)=>{if(!v)throw Error(m)};
expect(document.documentElement.scrollWidth<=innerWidth+1,'Page fits viewport');
for(const id of ['team-edit-account','team-assignments','team-application-history']){
const button=document.querySelector('[data-modal-open="'+id+'"]'),modal=document.getElementById(id);
expect(!modal.classList.contains('open'),'Dialog initially hidden');button.click();expect(modal.classList.contains('open'),'Dialog opens');
const box=modal.querySelector('.modal').getBoundingClientRect();expect(box.width<=innerWidth&&box.left>=0,'Dialog fits viewport');
modal.querySelector('[data-modal-close]').click();expect(!modal.classList.contains('open'),'Dialog closes');}
expect(document.querySelector('#team-edit-account form [name=logistics_hub_id]'),'Hub assignment retained');
document.querySelector('#result').textContent='PASS';if(parent!==window)parent.postMessage({result:'PASS'},'*');
}catch(error){document.querySelector('#result').textContent=error.stack;if(parent!==window)parent.postMessage({result:error.stack},'*');}},150);`;
html=html.replace('</body>','<pre id="result" hidden></pre><script>'+fs.readFileSync('public/js/admin.js','utf8')+'</script><script>'+checks+'</script></body>');
for(const width of [1440,390]){
 const fixture=path.resolve('storage/framework/testing/team-editor-browser.html');
 const content=width<500?'<!doctype html><pre id="result" hidden></pre><script>addEventListener("message",e=>{if(e.data.result)document.querySelector("#result").textContent=e.data.result});</script><iframe width="390" height="1000" style="border:0" srcdoc="'+html.replaceAll('&','&amp;').replaceAll('"','&quot;')+'"></iframe>':html;
 fs.writeFileSync(fixture,content);
 const output=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--disable-background-networking','--window-size='+width+',1000','--virtual-time-budget=2500','--dump-dom','file:///'+fixture.replaceAll('\\','/')],{encoding:'utf8',timeout:45000,maxBuffer:6*1024*1024});
 const result=output.stdout?.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[1];
 assert.equal(result,'PASS',width+': '+output.stderr.slice(-300));console.log('PASS team account dialogs at '+width+'px');
}
