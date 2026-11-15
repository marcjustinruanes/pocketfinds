const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
const chrome=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
let html=fs.readFileSync(process.env.LOGISTICS_WORKSPACE_SOURCE||'storage/framework/testing/logistics-workspace.html','utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
html=html.replace(/<link\b[^>]*href="([^"]+)"[^>]*>/gi,(_,url)=>{
 const file='public'+new URL(url,'http://localhost').pathname;
 return file.startsWith('public/css/')&&fs.existsSync(file)?'<style>'+fs.readFileSync(file,'utf8').replace(/@import[^;]+;/g,'')+'</style>':'';
});
const checks=`setTimeout(()=>{try{
 const expect=(value,message)=>{if(!value)throw Error(message);};
 const shell=document.querySelector('[data-sidebar-hover]'),sidebar=document.querySelector('#sidebar'),content=document.querySelector('.content');
 expect(shell.classList.contains('collapsed'),'Navigation begins collapsed');
 expect(sidebar.querySelectorAll('.nav-item').length===(document.body.classList.contains('hub-staff-ui')?4:6),'Compact role-specific sections');
 expect(!sidebar.querySelector('[data-navigation-group]'),'No sidebar dropdowns');
 expect(!document.querySelector('.menu-toggle,.topbar-search'),'No redundant header controls');
 expect(sidebar.getBoundingClientRect().width===64,'Compact rail');
 expect(getComputedStyle(content).backgroundColor==='rgba(0, 0, 0, 0)','Content blends with page background');
 const pageTabs=document.querySelector('.logistics-workspace-tabs');if(pageTabs)expect(getComputedStyle(pageTabs).backgroundColor===(pageTabs.classList.contains('logistics-operation-filters')?'rgb(243, 237, 241)':'rgba(0, 0, 0, 0)'),'Operations use a segmented container');
 expect(content.getBoundingClientRect().left>=64&&content.getBoundingClientRect().width>innerWidth*.65,'Content uses available width');
 expect(document.documentElement.scrollWidth<=innerWidth+1,'Page fits viewport');
 expect(getComputedStyle(document.querySelector('.notification-trigger')).borderRadius==='50%','Circular notifications');
 expect(!!document.querySelector('.logistics-profile-link'),'Account accessible from header');
 if(document.querySelector('.logistics-dashboard')){
  const metric=document.querySelector('.dashboard-metric');expect(getComputedStyle(metric).display==='flex','Summary figures use compact seller format');
  expect(parseFloat(getComputedStyle(metric.querySelector('.dashboard-metric-head>span')).fontSize)<=9,'Summary labels stay small');
  expect(getComputedStyle(metric.querySelector('p')).display==='none','Repeated metric descriptions removed');
 }else if(document.querySelector('.hub-dashboard-page')) expect(!document.querySelector('.hub-dashboard-action'),'Redundant hub assignment banner removed');
 else expect(document.querySelectorAll('.logistics-workspace-tabs a').length>=(document.body.classList.contains('hub-staff-ui')?6:7),'Operations queues and tools share one navigation row');
 const launch=document.querySelector('.pf-messages-launch'),widget=document.querySelector('#sellerMessagesWidget');
 expect(!!launch&&!!document.querySelector('[aria-label="Ask PocketFinds"]'),'Shared floating messages and assistant dock');
 expect(getComputedStyle(launch).borderRadius==='50%','Circular message button');
 // The mobile srcdoc fixture has an opaque origin; exercise navigation in the top-level fixture.
 if(location.protocol!=='about:'){
 launch.dataset.sellerMessages='file:///logistics/messages';launch.click();
 expect(widget.classList.contains('open'),'Floating inbox opens');
 expect(widget.querySelector('iframe').src.includes('embedded=1'),'Floating inbox uses embedded conversation');
 document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
 expect(!widget.classList.contains('open'),'Escape closes floating inbox');
 }
 if(content.classList.contains('logistics-operations-layout')){
  const filters=content.querySelector('.logistics-operation-filters'),queue=content.querySelector('.logistics-operations-content');
  expect(getComputedStyle(filters).display==='grid','Operations filters share available width');
  expect(filters.getBoundingClientRect().bottom<=queue.getBoundingClientRect().top,'Operations filters appear above content');
  expect(Math.abs(filters.getBoundingClientRect().width-queue.getBoundingClientRect().width)<2,'Operations filters span full content width');
  expect(queue.scrollWidth<=queue.clientWidth+2,'Operations content fits available width');
 }
 const left=content.getBoundingClientRect().left,logout=sidebar.querySelector('.logout-btn svg').getBoundingClientRect().left;
 sidebar.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,pointerType:'mouse',movementX:2}));
 expect(!shell.classList.contains('collapsed'),'Movement over rail expands navigation');
 expect(content.getBoundingClientRect().left===left,'Expansion does not move page');
 expect(sidebar.querySelector('.logout-btn svg').getBoundingClientRect().left===logout,'Sign out icon stays stable');
 sidebar.dispatchEvent(new PointerEvent('pointerleave',{pointerType:'mouse'}));
 expect(shell.classList.contains('collapsed'),'Leaving rail collapses navigation');
 const brand=sidebar.querySelector('[data-sidebar-toggle]');brand.click();expect(!shell.classList.contains('collapsed'),'Brand supports tap navigation');
 sidebar.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));expect(shell.classList.contains('collapsed'),'Escape closes navigation');
 window.dispatchEvent(new Event('pageshow'));expect(shell.classList.contains('collapsed'),'Returning to a page keeps rail collapsed');
 document.querySelector('#result').textContent='PASS';if(parent!==window)parent.postMessage({result:'PASS'},'*');
}catch(error){document.querySelector('#result').textContent=error.stack;if(parent!==window)parent.postMessage({result:error.stack},'*');}},150);`;
html=html.replace('</body>','<pre id="result" hidden></pre><script>'+fs.readFileSync('public/js/navigation.js','utf8')+fs.readFileSync('public/js/seller-messages-widget.js','utf8')+'</script><script>'+checks+'</script></body>');
for(const width of [1440,390]){
 const fixture=path.resolve('storage/framework/testing/logistics-workspace-browser.html');
 const content=width<500?'<!doctype html><pre id="result" hidden></pre><script>addEventListener("message",e=>{if(e.data.result)document.querySelector("#result").textContent=e.data.result});</script><iframe width="390" height="1000" style="border:0" srcdoc="'+html.replaceAll('&','&amp;').replaceAll('"','&quot;')+'"></iframe>':html;
 fs.writeFileSync(fixture,content);
 const output=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--disable-background-networking','--window-size='+width+',1000','--virtual-time-budget=1500','--dump-dom','file:///'+fixture.replaceAll('\\','/')],{encoding:'utf8',timeout:45000,maxBuffer:6*1024*1024});
 const result=output.stdout?.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[1];
 assert.equal(result,'PASS',width+': '+output.stderr.slice(-300));console.log('PASS logistics workspace navigation and layout at '+width+'px');
}
