const fs=require('fs'),path=require('path'),assert=require('assert/strict'),{spawnSync}=require('child_process');
const source=path.resolve('storage/app/private/commission-ui/seller.html');
assert(fs.existsSync(source),'Generate CommissionTest fixtures with COMMISSION_UI_PREVIEW=1 first.');
let html=fs.readFileSync(source,'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
html=html.replace(/<link\b[^>]*href="([^"]+)"[^>]*>/gi,(_,url)=>{
 const file='public'+new URL(url,'http://localhost').pathname;
 return file.startsWith('public/css/')&&fs.existsSync(file)?'<style>'+fs.readFileSync(file,'utf8').replace(/@import[^;]+;/g,'')+'</style>':'';
});
const checks=`setTimeout(()=>{try{
 const expect=(v,m)=>{if(!v)throw Error(m);},root=document.querySelector('[data-seller-finance]');
 expect(!!root,'Seller-specific workspace');
 expect(!root.querySelector('.commission-policy'),'Repeated policy panel removed');
 expect(root.querySelectorAll('.finance-balances article').length===2,'Only two main balances');
 expect(document.documentElement.scrollWidth<=innerWidth+2,'No page overflow');
 if(new URLSearchParams(location.search).has('finance_view')){
  expect(!document.querySelector('#finance-history-modal').hidden,'History reopens after navigation');
  document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
 }
 const triggers=[...root.querySelectorAll('[data-finance-open]')].filter(el=>!el.closest('[data-finance-modal]'));
 for(const trigger of triggers){
  trigger.click();const modal=document.getElementById(trigger.dataset.financeOpen),dialog=modal.querySelector('.finance-dialog');
  expect(!modal.hidden,'Opens '+modal.id);expect(document.body.style.overflow==='hidden','Body scroll locked');
  expect(dialog.getBoundingClientRect().left>=0&&dialog.getBoundingClientRect().right<=innerWidth+1,'Dialog fits '+modal.id);
  expect(getComputedStyle(modal.querySelector('.finance-dialog-body')).overflowY==='auto','Dialog remains scrollable');
  const focusable=[...modal.querySelectorAll('button,a[href],input,select,textarea,summary,[tabindex="0"]')].filter(el=>!el.disabled&&el.getClientRects().length);
  focusable.at(-1).focus();document.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',bubbles:true,cancelable:true}));
  expect(document.activeElement===focusable[0],'Focus stays inside '+modal.id+'; last='+focusable.at(-1).outerHTML.slice(0,100)+'; focused='+document.activeElement.outerHTML.slice(0,100));
  modal.querySelector('[data-finance-close]').click();expect(modal.hidden,'Closes '+modal.id);expect(document.activeElement===trigger,'Focus returns to opener');
 }
 expect(document.querySelector('#finance-history-modal form input[name="finance_view"]').value==='finance-history-modal','History filters preserve modal context');
 expect([...root.querySelectorAll('[data-finance-modal] form[method="POST"]')].every(form=>form.querySelector('[name="_finance_modal"]')),'Validation returns to originating modal');
 document.querySelector('#result').textContent='PASS';
}catch(e){document.querySelector('#result').textContent=e.stack;}},100);`;
html=html.replace('</body>','<pre id="result"></pre><script>'+fs.readFileSync('public/js/seller-finance.js','utf8')+'</script><script>'+checks+'</script></body>');
const fixture=path.resolve('storage/framework/testing/seller-finance-browser.html');fs.writeFileSync(fixture,html);
const chrome=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
for(const [size,query] of [['1440,1000',''],['500,844','?finance_view=finance-history-modal']]){
 const result=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--virtual-time-budget=1000','--window-size='+size,'--dump-dom','file:///'+fixture.replaceAll('\\','/')+query],{encoding:'utf8',timeout:30000,maxBuffer:5*1024*1024});
 assert.equal(result.stdout?.match(/<pre id="result">([\s\S]*?)<\/pre>/)?.[1],'PASS',result.stderr.slice(-1000));
 console.log('PASS seller finance layout and modal navigation '+size);
}
