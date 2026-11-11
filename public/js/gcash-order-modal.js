(() => {
 const modal=document.getElementById('gcashPurchaseModal');
 if(!modal)return;
 const frame=modal.querySelector('iframe');
 const closeButton=modal.querySelector('[data-gcash-modal-close]');
 const platformModal=document.getElementById('platformGcashAccountModal');
 const platformOpen=document.querySelector('[data-platform-account-open]');
 const platformClose=platformModal?.querySelector('[data-platform-account-close]');
 let opener, previousOverflow;
 const closePlatform=()=>{if(!platformModal)return;platformModal.classList.remove('open');platformModal.setAttribute('aria-hidden','true');platformOpen?.focus();};
 platformOpen?.addEventListener('click',()=>{platformModal.classList.add('open');platformModal.setAttribute('aria-hidden','false');platformClose?.focus();});
 platformClose?.addEventListener('click',closePlatform);
 platformModal?.addEventListener('click',event=>{if(event.target===platformModal)closePlatform();});
 platformModal?.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();closePlatform();}});
 const close=()=>{
  modal.classList.remove('open');modal.setAttribute('aria-hidden','true');
  document.body.style.overflow=previousOverflow??'';
  frame.removeAttribute('src');opener?.focus();
 };
 document.querySelectorAll('a[data-gcash-payment], [data-purchase-reference] a[href]').forEach(link=>{
  if(!new URL(link.href,location.href).pathname.startsWith('/gcash-purchases/'))return;
  link.addEventListener('click',event=>{
   if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
   event.preventDefault();opener=link;previousOverflow=document.body.style.overflow;
   const card=link.closest('[data-purchase-reference]');
   const purchaseTitle=link.dataset.purchaseTitle||card?.dataset.purchaseTitle||'GCash payment';
   const purchaseReference=link.dataset.purchaseReference||card?.dataset.purchaseReference;
   modal.querySelector('h3').textContent=purchaseTitle;
   modal.querySelector('[data-gcash-modal-reference]').textContent=purchaseReference||'';
   const url=new URL(link.href,location.href);url.searchParams.set('embedded','1');
   frame.src=url.href;modal.classList.add('open');modal.setAttribute('aria-hidden','false');
   document.body.style.overflow='hidden';closeButton.focus();
  });
 });
 closeButton.addEventListener('click',close);
 modal.addEventListener('click',event=>{if(event.target===modal)close();});
 modal.addEventListener('keydown',event=>{
  if(event.key==='Escape'){event.preventDefault();close();}
  if(event.key==='Tab'&&event.shiftKey&&event.target===closeButton){event.preventDefault();frame.focus();}
 });
 window.addEventListener('message',event=>{
  if(event.origin!==location.origin||event.source!==frame.contentWindow)return;
  if(event.data?.type==='gcash-details-close')close();
  if(event.data?.type==='gcash-details-state'&&typeof event.data.state==='string'){
   const card=opener?.closest('[data-purchase-reference]');
   if((opener?.dataset.purchaseReference||card?.dataset.purchaseReference)!==event.data.id)return;
   const badge=card?.querySelector('.gcash-badge');
   if(badge){badge.textContent=event.data.state.replaceAll('_',' ');badge.classList.toggle('gcash-badge-success',['verified','order_placed'].includes(event.data.state));if(badge.classList.contains('admin-payment-status')){[...badge.classList].filter(name=>name.startsWith('status-')).forEach(name=>badge.classList.remove(name));badge.classList.add('status-'+event.data.state);}}
  }
 });
})();
