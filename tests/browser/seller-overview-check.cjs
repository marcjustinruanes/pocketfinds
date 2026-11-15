const fs=require('fs'),path=require('path'),assert=require('assert/strict'),{spawnSync}=require('child_process');
const chrome=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
const checks=`setTimeout(()=>{try{
 const expect=(v,m)=>{if(!v)throw Error(m);};
 expect(document.documentElement.scrollWidth<=innerWidth+2,'Page fits viewport');
 expect(document.querySelectorAll('.seller-page-name').length===1,'One page heading');
 const trigger=document.querySelector('.notification-trigger');expect(getComputedStyle(trigger).borderRadius==='50%','Circular notification button');
 expect(document.querySelector('[data-seller-clock]').nextElementSibling.classList.contains('pf-notification-menu'),'Clock before notifications');
 if(document.body.classList.contains('seller-dashboard-page')){
  expect(!document.querySelector('.seller-dashboard-intro'),'Repeated overview heading removed');
  expect(!document.querySelector('.kpi .delta'),'Metric subtitles removed');
  expect(document.querySelector('.seller-page-name').textContent.trim()==='Shop overview','Shop overview title');
  expect(!!document.querySelector('.seller-page-welcome'),'Welcome above heading');
  expect(parseFloat(getComputedStyle(document.querySelector('.kpi .label')).fontSize)<=9,'Smaller card labels');
  scrollTo(0,60);
  expect(document.querySelector('.seller-utility-row').getBoundingClientRect().bottom<=document.querySelector('.kpi-grid').getBoundingClientRect().top,'Heading does not overlap cards while scrolling');
  scrollTo(0,0);
  expect(getComputedStyle(document.querySelector('.kpi .label')).order==='2','Card labels below values');
  expect(document.querySelector('.kpi').getBoundingClientRect().height<=130,'Compact statistic cards');
  const orderTable=document.querySelector('.dashboard-recent-orders .table-wrap');
  expect(orderTable.scrollWidth<=orderTable.clientWidth+1,'Recent orders fits without horizontal scrolling: '+JSON.stringify({wrapper:orderTable.clientWidth,scroll:orderTable.scrollWidth,table:orderTable.querySelector('table').getBoundingClientRect().width,cells:[...orderTable.querySelectorAll('tbody tr:first-child td')].map(td=>({width:td.clientWidth,scroll:td.scrollWidth,text:td.textContent.trim()}))}));
  expect(document.querySelectorAll('.dashboard-chart-column').length>=28,'Full month chart');
  expect(!!document.querySelector('input[type="month"]'),'Month selector');
  const month=document.querySelector('input[type="month"]');let submitted=false;month.form.requestSubmit=()=>{submitted=true;};month.dispatchEvent(new Event('change',{bubbles:true}));expect(submitted,'Changing month applies filter');
  const flash=document.querySelector('.dashboard-flash-sales'),activity=document.querySelector('.dashboard-pipeline');
  expect(flash.closest('.dash-grid')!==null,'Flash sales beside sales chart');
  expect(activity.closest('.dashboard-promotions-grid')!==null,'Shop activity beside vouchers');
  const gallery=document.querySelector('[data-dashboard-deals]');
  if(gallery){expect(gallery.getBoundingClientRect().width<=flash.getBoundingClientRect().width,'Deals gallery fits card');expect(gallery.querySelectorAll('[data-dashboard-deal]:not([hidden])').length===1,'One visible deal');}
 }else{
  const modal=document.querySelector('#inventoryCategoriesModal');modal.classList.add('open');
  const dialog=modal.querySelector('.inventory-categories-dialog'),rect=dialog.getBoundingClientRect();
  expect(rect.left>=0&&rect.right<=innerWidth+1,'Category dialog fits');
  const category=modal.querySelector('.category-manager-item');expect(!category.open,'Existing categories start compact');
  category.querySelector('.category-manager-item-heading').click();expect(category.open,'Category editing expands');
  const picker=category.querySelector('.category-product-picker');picker.querySelector('summary').click();
  const search=picker.querySelector('[data-category-product-search]');search.value='no product matches this';search.dispatchEvent(new Event('input',{bubbles:true}));
  expect([...picker.querySelectorAll('.category-product-option')].every(e=>e.hidden),'Product search filters choices');
  search.value='';search.dispatchEvent(new Event('input',{bubbles:true}));
  const box=picker.querySelector('input[type="checkbox"]');box.checked=!box.checked;box.dispatchEvent(new Event('change',{bubbles:true}));
  expect(picker.querySelector('[data-category-selection-count]').textContent===picker.querySelectorAll('input[type="checkbox"]:checked').length+' selected','Live selection count');
 }
 document.querySelector('#result').textContent='PASS';
}catch(e){document.querySelector('#result').textContent=e.stack;}},100);`;
for(const [kind,source] of [['dashboard',process.env.SELLER_DASHBOARD_FIXTURE||'seller-dashboard.html'],['categories','product-modals.html']]){
 let html=fs.readFileSync('storage/framework/testing/'+source,'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace(/<link\b[^>]*href="([^"]+)"[^>]*>/gi,(_,url)=>{
  const file='public'+new URL(url,'http://localhost').pathname;return file.startsWith('public/css/')&&fs.existsSync(file)?'<style>'+fs.readFileSync(file,'utf8').replace(/@import[^;]+;/g,'')+'</style>':'';
 });
 html=html.replace('</body>','<pre id="result"></pre><script>'+fs.readFileSync('public/js/navigation.js','utf8')+'</script><script>'+fs.readFileSync('public/js/seller-products.js','utf8')+'</script><script>'+checks+'</script></body>');
 const fixture=path.resolve('storage/framework/testing/'+kind+'-overview-browser.html');fs.writeFileSync(fixture,html);
 for(const size of ['1440,1000','500,844']){
  const result=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--virtual-time-budget=1000','--window-size='+size,'--dump-dom','file:///'+fixture.replaceAll('\\','/')],{encoding:'utf8',timeout:30000,maxBuffer:5*1024*1024});
  assert.equal(result.stdout?.match(/<pre id="result">([\s\S]*?)<\/pre>/)?.[1],'PASS',result.stderr.slice(-500));console.log('PASS '+kind+' '+size);
 }
}
