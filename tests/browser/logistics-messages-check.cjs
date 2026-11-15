const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
const chrome=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);

let html=fs.readFileSync('storage/framework/testing/logistics-messages.html','utf8');
html=html.replace(/<link\b[^>]*href="([^"]+)"[^>]*>/gi,(_,url)=>{const file='public'+new URL(url,'http://localhost').pathname;return file.startsWith('public/css/')&&fs.existsSync(file)?'<style>'+fs.readFileSync(file,'utf8').replace(/@import[^;]+;/g,'')+'</style>':'';});
html=html.replace('<head>','<head><script>window.fetch=async(url,options)=>({ok:true,text:async()=>JSON.stringify({ok:true,messages:[{id:999,sender_id:1,body:"Test reply",attachment_path:"/sample.pdf",attachment_type:"document",attachment_name:"QUESTIONNAIRE NILA REYMER.pdf",read:false,created_at:"10:00 AM"}]}),json:async()=>({messages:[]})});</script>');
const checks=`setTimeout(async()=>{try{
const expect=(v,m)=>{if(!v)throw Error(m)};
expect(document.documentElement.scrollWidth<=innerWidth+1,'No horizontal overflow');
expect(document.querySelectorAll('.chat-list-item').length===1,'Contact available');
expect(getComputedStyle(document.querySelector('.chat-main')).display!=='none','Conversation visible');
const search=document.querySelector('#conversationSearch');search.value='missing';search.dispatchEvent(new Event('input'));expect(document.querySelector('.chat-list-item').hidden,'Search hides unmatched contact');search.value='';search.dispatchEvent(new Event('input'));
const reply=document.querySelector('[title="Reply"]');reply.click();expect(document.querySelector('#chatReplyBox').classList.contains('open'),'Reply context visible');
document.querySelector('#chatInput').value='Test reply';await sendMessage();
expect(document.querySelector('[data-message-id="999"]'),'Message appended');expect(document.querySelector('#chatInput').value==='','Composer cleared');
expect(!document.querySelector('[title="Products"]'),'No seller-only controls');
const documents=[...document.querySelectorAll('.chat-doc-bubble')];expect(documents.length===2,'Initial and live PDF cards');documents.forEach(doc=>{expect(doc.querySelector('.chat-doc-badge').textContent==='PDF','PDF badge displayed');expect(doc.querySelector('.chat-doc-name').textContent.includes('QUESTIONNAIRE'),'Filename displayed');expect(doc.scrollWidth<=doc.clientWidth+1,'Document fits its card');});
document.querySelector('#result').textContent='PASS';if(parent!==window)parent.postMessage({result:'PASS'},'*');
}catch(error){document.querySelector('#result').textContent=error.stack;if(parent!==window)parent.postMessage({result:error.stack},'*');}},150);`;
html=html.replace('</body>','<pre id="result" hidden></pre><script>'+checks+'</script></body>');
for(const width of [1440,390]){
 const fixture=path.resolve('storage/framework/testing/logistics-messages-browser.html');
 const content=width<500?'<!doctype html><pre id="result" hidden></pre><script>addEventListener("message",e=>{if(e.data.result)document.querySelector("#result").textContent=e.data.result});</script><iframe width="390" height="1000" style="border:0" srcdoc="'+html.replaceAll('&','&amp;').replaceAll('"','&quot;')+'"></iframe>':html;
 fs.writeFileSync(fixture,content);
 const output=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--disable-background-networking','--window-size='+width+',1000','--virtual-time-budget=1500','--dump-dom','file:///'+fixture.replaceAll('\\','/')],{encoding:'utf8',timeout:45000,maxBuffer:6*1024*1024});
 const result=output.stdout?.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[1];
 assert.equal(result,'PASS',width+': '+output.stderr.slice(-300));console.log('PASS logistics embedded search, reply, composer, and layout at '+width+'px');
}
