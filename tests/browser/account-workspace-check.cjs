const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
const chrome=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
const roles=process.argv[2]?[process.argv[2]]:['seller','admin','rider','logistics','hub_staff'];
const scripts=['role-account-details.js','role-account-workspace.js','buyer-account-form.js','buyer-account-panels.js','account-profile-photo.js','account-verification.js'];
const setup=`window.accountErrors=[];addEventListener('error',e=>accountErrors.push(e.message));window.fetch=async url=>({ok:true,json:async()=>{
 const p=new URL(url,location.href).pathname;
 if(p.endsWith('/regions/'))return[{code:'040000000',name:'CALABARZON'}];
 if(p.endsWith('/provinces/'))return[{code:'043400000',regionCode:'040000000',name:'Laguna'}];
 if(p.includes('barangays'))return[{code:'043426001',name:'Central'}];
 return[{code:'043426000',name:'Pila'}];
}});`;
const checks=`setTimeout(async()=>{try{
 const expect=(v,m)=>{if(!v)throw Error(m);};
 const root=document.querySelector('#accountLayout'),main=root.querySelector('.account-main'),card=root.querySelector('[data-account-panel="profile-information"]');
 expect(accountErrors.length===0,'Script errors: '+accountErrors.join('; '));
 expect(document.querySelectorAll('#accountLayout').length===1,'One account workspace');
 expect(!!card.querySelector('#profileGivenNames')?.closest('.account-floating-field'),'Personal fields use buyer outlines');
 expect(root.querySelector('#profileContact').closest('.account-floating-field').querySelector('.account-mobile-prefix')?.textContent==='+63','Contact retains +63 prefix');
 expect(document.documentElement.scrollWidth<=innerWidth+2,'Page fits viewport: '+document.documentElement.scrollWidth+'/'+innerWidth);
 expect(main.scrollWidth<=main.clientWidth+2,'Account fields fit panel: '+main.scrollWidth+'/'+main.clientWidth);
 const links=[...root.querySelectorAll('[data-account-section]')];
 for(const link of links){
  link.click();
  expect(link.getAttribute('aria-current')==='page','Selected section announced');
  const visible=[...root.querySelectorAll('[data-account-panel]')].filter(c=>!c.hidden);
  expect(visible.length>0,'Section has content: '+link.dataset.accountSection);
  expect(visible.every(c=>c.dataset.accountPanel===link.dataset.accountSection),'Only selected settings visible');
 }
 if(root.dataset.accountRole==='seller'){
  expect(!root.querySelector('#shipping-rates'),'Shipping rates moved out of account');
  expect(root.querySelector('[data-account-section="shipping-rates"]').textContent.includes('Shop availability'),'Availability section renamed');
  root.querySelector('[data-account-section="account-security"]').click();
  expect(!!root.querySelector('#accountEmailCodeForm'),'Buyer email verification form');
  expect(!!root.querySelector('form[action*="password-link"]'),'Buyer password-link flow');
  expect(!root.querySelector('[name="current_password"]'),'No old seller password form');
  root.querySelector('[data-account-section="account-documents"]').click();
  const id=document.getElementById('id_file'),transfer=new DataTransfer();
  transfer.items.add(new File(['%PDF-1.4 sample'],'sample.pdf',{type:'application/pdf'}));id.files=transfer.files;id.dispatchEvent(new Event('change'));
  expect(document.getElementById('idPhotoPreview').style.display==='block','ID document preview shown');
  expect(document.getElementById('accountIdPdfName').textContent==='sample.pdf','PDF name displayed');
  expect(typeof startCamera==='function'&&typeof snapSelfie==='function'&&typeof retakeSelfie==='function','Selfie capture actions ready');
  const form=id.form,event=new Event('submit',{cancelable:true});form.dispatchEvent(event);
  expect(event.defaultPrevented&&document.getElementById('selfieError').textContent.includes('selfie'),'New ID requires selfie');
  if(parent===window){
   const canvas=document.createElement('canvas');canvas.width=160;canvas.height=120;const context=canvas.getContext('2d');
   const draw=()=>{const gradient=context.createLinearGradient(0,0,160,120);gradient.addColorStop(0,'red');gradient.addColorStop(1,'blue');context.fillStyle=gradient;context.fillRect(0,0,160,120);};draw();
   const stream=canvas.captureStream(10),timer=setInterval(draw,50);
   Object.defineProperty(navigator.mediaDevices,'getUserMedia',{configurable:true,value:async()=>stream});
   await startCamera();expect(document.getElementById('selfieVideo').srcObject===stream,'Camera started: '+document.getElementById('selfieError').textContent);await snapSelfie();clearInterval(timer);
   expect(document.getElementById('accountSelfieFile').files[0]?.type==='image/jpeg','Captured selfie attaches as JPEG: '+document.getElementById('selfieError').textContent+'; video '+document.getElementById('selfieVideo').readyState+'; tracks '+stream.getTracks().map(t=>t.readyState+'/'+t.muted).join(',')+'; panel '+form.closest('.card').dataset.accountPanel+'/'+form.closest('.card').hidden+'; id '+id.name);
   expect(stream.getTracks().every(track=>track.readyState==='ended'),'Camera stops after capture');
   expect(document.getElementById('selfiePreview').style.display==='block','Selfie preview shown');
  }
  root.querySelector('[data-account-section="address-information"]').click();
  expect(getComputedStyle(root.querySelector('[data-role-address-error]')).display==='none','Empty address error creates no gap');
 }
 links[0].click();
 document.querySelector('#openBuyerProfilePhotoModal').click();
 expect(document.querySelector('#profilePhotoCropModal').classList.contains('open'),'Shared photo dialog opens');
 expect(document.querySelector('#profilePhotoCropModal').parentElement===document.body,'Photo dialog outside account scroller');
 document.querySelector('[data-photo-modal-close]').click();
 expect(!document.querySelector('#profilePhotoCropModal').classList.contains('open'),'Photo dialog closes');
 document.querySelector('#result').textContent='PASS';if(parent!==window)parent.postMessage({accountResult:'PASS'},'*');
}catch(e){document.querySelector('#result').textContent=e.stack;if(parent!==window)parent.postMessage({accountResult:e.stack},'*');}},150);`;
for(const role of roles){
 const source=path.resolve('storage/framework/testing/account-'+role+'.html');
 if(!fs.existsSync(source))throw Error('Generate fixtures with ACCOUNT_BROWSER_FIXTURES=1 and AccountWorkspaceTest first.');
 let html=fs.readFileSync(source,'utf8').replace(/<script\b([^>]*)>[\s\S]*?<\/script>/gi,(all,attrs)=>attrs.includes('application/json')?all:'');
 html=html.replace(/<link\b[^>]*href="([^"]+)"[^>]*>/gi,(all,url)=>{
  const pathname=new URL(url,'http://localhost').pathname;if(!pathname.startsWith('/css/'))return'';
  const file=path.join('public',pathname);return fs.existsSync(file)?'<style>'+fs.readFileSync(file,'utf8').replace(/@import[^;]+;/g,'')+'</style>':'';
 });
 html=html.replace(/src="https?:[^"<>]+\/images\/([^"?]+)(?:\?[^"<>]*)?"/g,(_,name)=>'src="file:///'+path.resolve('public/images/'+name).replaceAll('\\','/')+'"');
 const injected='<pre id="result" hidden></pre><script>'+setup+'</script>'+scripts.map(s=>'<script>'+fs.readFileSync('public/js/'+s,'utf8')+'</script>').join('')+'<script>'+checks+'</script>';
 html=html.replace('</body>',injected+'</body>');
 for(const width of [1280,390]){
  const fixture=path.resolve('storage/framework/testing/.account-browser-'+role+'.html');
  const content=width<500?`<!doctype html><pre id="result" hidden></pre><script>addEventListener('message',e=>{if(e.data.accountResult)document.querySelector('#result').textContent=e.data.accountResult;});</script><iframe width="${width}" height="1000" style="border:0" srcdoc="${html.replaceAll('&','&amp;').replaceAll('"','&quot;')}"></iframe>`:html;
  fs.writeFileSync(fixture,content);
  try{
   const args=['--headless','--no-sandbox','--disable-gpu','--allow-file-access-from-files','--autoplay-policy=no-user-gesture-required','--disable-background-networking','--window-size='+width+',1000','--virtual-time-budget=2500','--dump-dom'];
   if(process.argv[3]&&width===1280)args.push('--screenshot='+path.resolve(process.argv[3]));
   const out=spawnSync(chrome,[...args,'file:///'+fixture.replaceAll('\\','/')],{encoding:'utf8',timeout:45000,maxBuffer:8*1024*1024});
   const result=out.stdout?.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[1];
   assert.equal(result,'PASS',role+' '+width+': '+out.stderr.slice(-350));
   console.log('PASS '+role+' account panels, photo manager, and layout at '+width+'px');
  }finally{fs.unlinkSync(fixture);}
 }
}
