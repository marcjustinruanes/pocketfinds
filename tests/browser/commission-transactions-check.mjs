// Real forms/sessions on the loopback-only synthetic Laravel instance.
import fs from 'node:fs/promises';
import path from 'node:path';
import net from 'node:net';
import { spawn, execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
const root=process.cwd(), dir=path.join(root,'storage/app/private/commission-website-test');
const fixture=JSON.parse(await fs.readFile(path.join(dir,'fixture.json'),'utf8'));
const server=JSON.parse((await fs.readFile(path.join(dir,'server.json'),'utf8')).replace(/^\uFEFF/,''));
if(!fixture.synthetic_only)throw Error('Synthetic fixture required');
const origin=`http://127.0.0.1:${server.port}`;
const delay=ms=>new Promise(r=>setTimeout(r,ms));
const check=(condition,message)=>{if(!condition)throw Error(message);};
const snapshot=()=>JSON.parse(execFileSync('C:/php/php.exe',[path.join(root,'storage/app/private/commission-website-inspect.php')],{encoding:'utf8',windowsHide:true}));
const collected=(data,id)=>data.commission_allocations.filter(a=>a.obligation_id===id).reduce((sum,a)=>sum+Number(a.allocated_cents),0);
const transit=data=>data.logistics_treasury_movements.filter(m=>m.bucket==='comm_transit').reduce((sum,m)=>sum+Number(m.delta_cents),0);
const probe=net.createServer();await new Promise(r=>probe.listen(0,'127.0.0.1',r));const port=probe.address().port;await new Promise(r=>probe.close(r));
const profile=path.join(dir,`browser-${randomBytes(6).toString('hex')}`);
const chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--no-sandbox','--disable-gpu','--no-first-run',`--remote-debugging-port=${port}`,`--user-data-dir=${profile}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
const connections=[],results=[];
let main;
async function connect(url){
 const ws=new WebSocket(url);const pending=new Map();let seq=0;
 const api={ws,dialogs:0,send(method,params={}){return new Promise((resolve,reject)=>{const id=++seq;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});}};
 ws.onmessage=e=>{const value=JSON.parse(e.data);if(value.method==='Page.javascriptDialogOpening'){api.dialogs++;api.send('Page.handleJavaScriptDialog',{accept:true}).catch(()=>{});}const p=pending.get(value.id);if(p){pending.delete(value.id);value.error?p.reject(Error(value.error.message)):p.resolve(value.result);}};
 await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});connections.push(api);
 api.eval=async expression=>{const v=await api.send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(v.exceptionDetails)throw Error(v.exceptionDetails.exception?.description||v.exceptionDetails.text);return v.result.value;};
 return api;
}
async function settled(page,marker){for(let n=0;n<160;n++){await delay(100);try{if(await page.eval(`document.readyState==='complete' && window.__websiteMarker!==${JSON.stringify(marker)}`))return;}catch{}}throw Error('Website navigation timeout');}
async function go(page,url){check(url.startsWith('/')&&!url.startsWith('//'),'Local path required');const marker=randomBytes(5).toString('hex');await page.eval(`window.__websiteMarker=${JSON.stringify(marker)}`);await page.send('Page.navigate',{url:origin+url});await settled(page,marker);}
async function form(page,selector,values={},upload=false){
 await page.eval(`(()=>{const f=document.querySelector(${JSON.stringify(selector)});if(!f)throw Error('Form not found: '+${JSON.stringify(selector)});for(let p=f.parentElement;p;p=p.parentElement)if(p.tagName==='DETAILS')p.open=true;for(const [k,v] of Object.entries(${JSON.stringify(values)})){const e=f.elements.namedItem(k);if(!e)throw Error('Field not found: '+k);if(e.type==='checkbox')e.checked=!!v;else e.value=v;e.dispatchEvent(new Event('input',{bubbles:true}));}f.scrollIntoView({block:'center'});})()`);
 if(upload){const uploadName='simulated-'+randomBytes(8).toString('hex')+'.pdf';await fs.writeFile(path.join(dir,uploadName),'%PDF-1.4\nSIMULATED EVIDENCE - NO MONEY TRANSFERRED\n'+uploadName+'\n%%EOF\n');const doc=await page.send('DOM.getDocument');const node=await page.send('DOM.querySelector',{nodeId:doc.root.nodeId,selector:selector+' input[type=file]'});check(node.nodeId,'File input missing');await page.send('DOM.setFileInputFiles',{nodeId:node.nodeId,files:[path.join(dir,uploadName)]});}
 check(await page.eval(`document.querySelector(${JSON.stringify(selector)}).checkValidity()`),'Form validation failed: '+selector);
 const marker=randomBytes(5).toString('hex');await page.eval(`window.__websiteMarker=${JSON.stringify(marker)};document.querySelector(${JSON.stringify(selector)}).requestSubmit()`);await settled(page,marker);
 const errors=await page.eval(`[...document.querySelectorAll('.commission-feedback.is-error')].map(e=>e.innerText).join(' | ')`);check(!errors,'Website validation: '+errors);
 if(!selector.includes('/login'))check(await page.eval(`!!document.querySelector('.commission-workspace')`),'Submission did not return a commission page: '+await page.eval('document.body.innerText.slice(0,350)'));
}
async function screen(page,name){const shot=await page.send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});await fs.writeFile(path.join(dir,name+'.png'),Buffer.from(shot.data,'base64'));}
async function rolePage(role){const context=await main.send('Target.createBrowserContext');const target=await main.send('Target.createTarget',{url:'about:blank',browserContextId:context.browserContextId});const targets=await(await fetch(`http://127.0.0.1:${port}/json`)).json();const page=await connect(targets.find(t=>t.id===target.targetId).webSocketDebuggerUrl);await page.send('Page.enable');await page.send('Network.enable');await page.send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*accounts.google.com*']});await page.send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});await go(page,'/login');await form(page,'form[action$="/login"]',{email:`website-${role}@example.invalid`,password:fixture.password});await go(page,'/commissions');check(await page.eval(`!!document.querySelector('.commission-workspace')`),'Authenticated commission page missing for '+role);results.push({check:'normal_password_login',role,pass:true});return page;}
try{
 let targets;for(let n=0;n<150;n++){try{targets=await(await fetch(`http://127.0.0.1:${port}/json`)).json();if(targets.some(t=>t.type==='page'))break;}catch{}await delay(100);}
 main=await connect((await(await fetch(`http://127.0.0.1:${port}/json/version`)).json()).webSocketDebuggerUrl);
 const seller=await rolePage('seller'),admin=await rolePage('platform'),logistics=await rolePage('logistics');
 await form(seller,'form[action$="/commissions/payments"]',{[`allocations[${fixture.gcash_obligation}]`]:'100',amount:'100',reference:'SIMSELL001',sent:true,message:'SIMULATED Seller payment submitted for review.'},true);
 let data=snapshot();const sellerPayment=data.commission_payments.find(p=>p.method==='gcash');check(sellerPayment&&collected(data,fixture.gcash_obligation)===0,'Seller proof incorrectly collected commission');
 const sellerConversation='/commissions/conversations/payment/'+sellerPayment.id;
 await go(admin,sellerConversation);await form(admin,'form[action$="/review"]',{decision:'correction',reason:'SIMULATED Admin request: clarify payment reference.'});
 await go(seller,sellerConversation);check(await seller.eval(`document.body.innerText.includes('clarify payment reference')`),'Seller did not see correction request');
 await form(seller,`form[action$="${sellerConversation}"]`,{message:'SIMULATED corrected reference SIMSELL001; replacement evidence attached.'},true);
 await go(admin,sellerConversation);check(await admin.eval(`document.body.innerText.includes('corrected reference SIMSELL001')`),'Admin did not see Seller reply');
 await form(admin,`form[action$="${sellerConversation}"]`,{message:'SIMULATED Admin reply: correction received, checking receipt.'});
 check(collected(snapshot(),fixture.gcash_obligation)===0,'Conversation incorrectly collected Seller commission');
 await form(admin,'form[action$="/review"]',{decision:'verify',amount:'100',reference:'SIMSELL001',received:true,reason:'SIMULATED actual platform receipt verification; no money transferred.'});
 check(collected(snapshot(),fixture.gcash_obligation)===10000,'Seller verified receipt balance incorrect');await screen(admin,'seller-platform-verified');
 results.push({check:'seller_submission_correction_private_evidence_reply_and_verified_receipt',pass:true});
 await form(logistics,'form[action$="/commissions/remittances"]',{[`allocations[${fixture.cod_obligation}]`]:'100'});
 data=snapshot();const codPayment=data.commission_payments.find(p=>p.method==='cod');check(codPayment?.status==='draft'&&transit(data)===0,'Draft remittance moved money');
 await form(logistics,`form[action$="/remittances/${codPayment.id}"][data-confirm^="Confirm"]`,{amount:'100',reference:'SIMCOD001',sent:true,reason:'SIMULATED manual remittance; no money transferred.',message:'SIMULATED Logistics remittance for review.'},true);
 data=snapshot();check(transit(data)===10000&&collected(data,fixture.cod_obligation)===0,'Sent COD remittance not held in transit');
 const codConversation='/commissions/conversations/payment/'+codPayment.id;
 await go(admin,codConversation);await form(admin,'form[action$="/review"]',{decision:'reject',reason:'SIMULATED screenshot rejected; provide clearer evidence.'});
 data=snapshot();check(transit(data)===10000&&data.commission_payments.find(p=>p.id===codPayment.id).status==='review','COD proof rejection lost transit/reservation');
 await go(logistics,codConversation);await form(logistics,`form[action$="${codConversation}"]`,{message:'SIMULATED Logistics correction: clearer receipt attached for SIMCOD001.'},true);
 await go(admin,codConversation);check(await admin.eval(`document.body.innerText.includes('clearer receipt attached')`),'Admin did not see Logistics correction');
 await form(admin,`form[action$="${codConversation}"]`,{message:'SIMULATED Admin reply: matching the remittance to actual receipt.'});
 await form(admin,'form[action$="/review"]',{decision:'verify',amount:'100',reference:'SIMCOD001',received:true,reason:'SIMULATED verified COD platform receipt; no external transfer.'});
 data=snapshot();check(transit(data)===0&&collected(data,fixture.cod_obligation)===10000,'COD verified receipt/transit balance incorrect');await screen(admin,'logistics-platform-verified');results.push({check:'logistics_draft_send_rejection_transit_correction_reply_verified_receipt',pass:true});
 await go(seller,sellerConversation);check(await seller.eval(`document.body.innerText.includes('Verified')`),'Seller cannot see verified status');
 await go(logistics,codConversation);check(await logistics.eval(`document.body.innerText.includes('Verified')`),'Logistics cannot see verified status');await screen(logistics,'logistics-own-verified');
 const denied=await seller.eval(`fetch(${JSON.stringify(codConversation)},{headers:{Accept:'application/json'}}).then(r=>r.status)`);check([403,404].includes(denied),'Seller accessed Logistics conversation');
 check(data.commission_enrollment_settings[0].enabled===0 && data.commission_enrollment_settings[0].activation_at===null,'Synthetic enrollment was activated');
 results.push({check:'cross_role_conversation_denied',status:denied,pass:true},{check:'enrollment_stayed_disabled',pass:true},{check:'notifications_recorded',count:data.notifications.filter(n=>n.notification_type==='commission_payment').length,pass:data.notifications.some(n=>n.notification_type==='commission_payment')});
 await fs.writeFile(path.join(dir,'browser-results.json'),JSON.stringify({pass:true,synthetic_only:true,origin,results,dialogs:connections.reduce((s,c)=>s+c.dialogs,0)},null,2));
 console.log(JSON.stringify({pass:true,checks:results.length,synthetic_only:true,dialogs:connections.reduce((s,c)=>s+c.dialogs,0)}));
}catch(error){await fs.writeFile(path.join(dir,'browser-results.json'),JSON.stringify({pass:false,synthetic_only:true,results,error:error.message},null,2));throw error;}
finally{if(main)await main.send('Browser.close').catch(()=>{});for(const page of connections)page.ws.close();await delay(300);if(chrome.exitCode===null)chrome.kill();if(profile.startsWith(dir+path.sep)&&/^browser-[a-f0-9]{12}$/.test(path.basename(profile)))await fs.rm(profile,{recursive:true,force:true,maxRetries:5,retryDelay:100}).catch(()=>{});}
