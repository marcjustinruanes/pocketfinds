(() => {
 const root=document.querySelector('[data-promotions]');if(!root)return;
 const search=root.querySelector('[data-promo-search]'),cards=[...root.querySelectorAll('[data-promo-card]')],filters=[...root.querySelectorAll('[data-promo-filter]')];let status='all';
 function update(){let count=0;const query=search.value.trim().toLocaleLowerCase();cards.forEach(card=>{const match=(status==='all'||card.dataset.status===status||card.dataset.timing===status||(status==='finished'&&['expired','ended','exhausted'].includes(card.dataset.status)))&&card.textContent.toLocaleLowerCase().includes(query);card.hidden=!match;if(match)count++;});filters.forEach(button=>{const active=button.dataset.promoFilter===status;button.classList.toggle('active',active);button.setAttribute('aria-pressed',String(active));});root.querySelector('[data-promo-count]').textContent=count+' '+(count===1?'offer':'offers');root.querySelector('[data-promo-no-results]').hidden=count>0||cards.length===0;}
 search.addEventListener('input',update);filters.forEach(button=>button.addEventListener('click',()=>{status=button.dataset.promoFilter;update();}));root.querySelector('[data-promo-reset]').addEventListener('click',()=>{search.value='';status='all';update();search.focus();});
 root.querySelectorAll('.promo-donut-segment').forEach(segment=>segment.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();segment.dispatchEvent(new Event('click',{bubbles:true}));}}));
 document.querySelectorAll('[data-end-promotion]').forEach(form=>form.addEventListener('submit',event=>{if(!confirm('End this promotion? Buyers will no longer be able to use it.'))event.preventDefault();}));
 const modal=document.getElementById('createPromotionModal'),type=modal?.querySelector('[data-discount-type]');
 const startModes=[...(modal?.querySelectorAll('[name=start_mode]')||[])];
 if(startModes.length){const syncStart=()=>{const field=modal.querySelector('[data-promo-start-field]'),input=field.querySelector('input'),scheduled=modal.querySelector('[name=start_mode]:checked')?.value==='scheduled';field.hidden=!scheduled;input.disabled=!scheduled;input.required=scheduled;};startModes.forEach(input=>input.addEventListener('change',syncStart));syncStart();}
 if(type){const sync=()=>{modal.querySelector('[data-amount-label]').textContent=type.value==='percent'?'Discount percentage (%)':'Discount amount (₱)';const amount=modal.querySelector('[name=amount]');amount.max=type.value==='percent'?'99.99':'1000000';};type.addEventListener('change',sync);sync();}
 if(modal){
  let open=false,previousFocus,previousOverflow;
  const syncModal=()=>{const next=modal.classList.contains('open');if(next===open)return;open=next;if(open){previousFocus=document.activeElement;previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden';modal.querySelector('input:not([type=hidden]),select,button')?.focus();}else{document.body.style.overflow=previousOverflow||'';previousFocus?.focus();}};
  new MutationObserver(syncModal).observe(modal,{attributes:true,attributeFilter:['class']});syncModal();
  modal.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();modal.classList.remove('open');}if(event.key==='Tab'){const controls=[...modal.querySelectorAll('button,input:not([type=hidden]),select')].filter(input=>!input.disabled&&input.getClientRects().length),first=controls[0],last=controls[controls.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}}});
 }
 update();
})();
