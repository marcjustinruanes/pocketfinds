const fs=require('fs'),path=require('path'),assert=require('assert/strict'),{spawnSync}=require('child_process');
const chrome=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync),fixture=path.resolve('.seller-hover-check.html');
const checks=`document.addEventListener('DOMContentLoaded',()=>{try{
 const shell=document.querySelector('.shell'),sidebar=document.querySelector('.sidebar'),brand=document.querySelector('.sidebar-brand'),topbar=document.querySelector('.seller-utility-row');
 const expect=(v,m)=>{if(!v)throw Error(m);};
 expect(shell.classList.contains('collapsed')&&!sidebar.inert,'Rail starts collapsed and accessible');
 const signout=sidebar.querySelector('.logout-btn svg').getBoundingClientRect();
 expect(Math.abs(brand.getBoundingClientRect().bottom-topbar.getBoundingClientRect().bottom)<=1,'Header dividers align');
 sidebar.dispatchEvent(new PointerEvent('pointerenter',{pointerType:'mouse'}));
 expect(shell.classList.contains('collapsed'),'Stationary pointer after navigation must not expand rail');
 sidebar.dispatchEvent(new PointerEvent('pointermove',{pointerType:'mouse',movementX:1,bubbles:true}));
 expect(!shell.classList.contains('collapsed'),'Intentional hover expands');
 const expandedSignout=sidebar.querySelector('.logout-btn svg').getBoundingClientRect();
 expect(Math.abs(signout.x-expandedSignout.x)<1&&Math.abs(signout.y-expandedSignout.y)<1,'Sign-out icon stays in place across rail states');
 expect(getComputedStyle(topbar).backgroundColor==='rgba(0, 0, 0, 0)','Utility controls have no header background');
 sidebar.dispatchEvent(new PointerEvent('pointerleave',{pointerType:'mouse'}));expect(shell.classList.contains('collapsed'),'Leaving collapses');
 brand.click();expect(!shell.classList.contains('collapsed'),'Brand tap expands');
 document.querySelector('main').dispatchEvent(new PointerEvent('pointerdown',{bubbles:true}));expect(shell.classList.contains('collapsed'),'Outside tap collapses');
 sidebar.querySelector('a').focus();expect(!shell.classList.contains('collapsed'),'Keyboard focus expands');
 window.dispatchEvent(new PageTransitionEvent('pagehide'));window.dispatchEvent(new PageTransitionEvent('pageshow'));
 sidebar.dispatchEvent(new PointerEvent('pointerenter',{pointerType:'mouse'}));expect(shell.classList.contains('collapsed'),'History navigation leaves rail collapsed');
 expect(getComputedStyle(sidebar).backgroundImage==='none'&&getComputedStyle(sidebar).backgroundColor==='rgb(255, 255, 255)','Rail uses white workspace theme');
 document.querySelector('#result').textContent='PASS';
}catch(e){document.querySelector('#result').textContent=e.stack;}});`;
try{
 const css=['seller.css','navigation.css','seller-shell.css','seller-modern.css','seller-navigation.css'].map(n=>fs.readFileSync('public/css/'+n,'utf8').replace(/@import[^;]+;/g,'')).join('\n');
 fs.writeFileSync(fixture,`<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body class="seller"><div class="shell collapsed" data-navigation-shell data-sidebar-hover><nav class="sidebar" id="sidebar"><div class="sidebar-brand" role="button" tabindex="0" data-sidebar-toggle><div class="mark"></div><div><div class="name">PocketFinds</div></div></div><div class="nav-groups"><a class="nav-item" href="#" aria-label="Products"><span class="ic">P</span>Products</a></div><div class="sidebar-foot"><div class="sidebar-user"><div class="avatar">S</div><div class="who"><strong>Seller</strong></div></div><button class="logout-btn" aria-label="Sign out"><svg width="20" height="20"></svg><span>Sign out</span></button></div></nav><div><div class="seller-utility-row"><div class="topbar-actions">Account</div></div><main>Content</main></div></div><pre id="result"></pre><script>${fs.readFileSync('public/js/navigation.js','utf8')}</script><script>${checks}</script>`);
 for(const size of ['1280,900','390,844']){
  const out=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--virtual-time-budget=1000','--window-size='+size,'--dump-dom','file:///'+fixture.replaceAll('\\','/')],{encoding:'utf8',timeout:30000,maxBuffer:2*1024*1024});
  assert.equal(out.stdout?.match(/<pre id="result">([\s\S]*?)<\/pre>/)?.[1],'PASS',out.stderr);console.log('PASS seller hover navigation '+size);
 }
}finally{if(fs.existsSync(fixture))fs.unlinkSync(fixture);}
