(() => {
 const workspace=document.querySelector('[data-seller-finance]');
 if(!workspace)return;
 let active=null,opener=null,previousOverflow='';
 const close=(restore=true)=>{
  if(!active)return;
  active.hidden=true;active=null;document.body.style.overflow=previousOverflow;
  if(restore && opener?.getClientRects().length)opener.focus();
 };
 const open=(id,trigger)=>{
  const modal=document.getElementById(id);
  if(!modal?.matches('[data-finance-modal]'))return;
  close(false);opener=trigger||workspace.querySelector(`[data-finance-open="${id}"]`);
  previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden';
  active=modal;modal.hidden=false;
  modal.querySelector('.finance-dialog-body').scrollTop=0;
  modal.querySelector('[data-finance-close]').focus();
 };
 workspace.addEventListener('click',event=>{
  const button=event.target.closest('[data-finance-open]');
  if(button){open(button.dataset.financeOpen,button);return;}
  if(event.target.closest('[data-finance-close]')||event.target===active){close();return;}
  const link=event.target.closest('a[href]');
  if(!link)return;
  if(link.getAttribute('href')==='#commission-payment-recovery'){
   event.preventDefault();open('finance-recovery');return;
  }
  if(active){
   const url=new URL(link.href,location.href);
   if(url.origin===location.origin && url.pathname===location.pathname && url.search){url.searchParams.set('finance_view',active.id);link.href=url.href;}
  }
 });
 workspace.querySelectorAll('[data-finance-modal] form').forEach(form=>{
  const id=form.closest('[data-finance-modal]').id;
  const hidden=(name,value)=>{if(form.querySelector(`[name="${name}"]`))return;const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.append(input);};
  if((form.getAttribute('method')||'get').toLowerCase()==='post')hidden('_finance_modal',id);
  else if(id!=='finance-filters'){
   hidden('finance_view',id);
   const params=new URLSearchParams(location.search);
   ['method','date_basis','from','to','balance','page'].forEach(key=>{if(params.has(key))hidden(key,params.get(key));});
  }
 });
 document.addEventListener('keydown',event=>{
  if(!active || document.querySelector('#sellerConfirmModal.open'))return;
  if(event.key==='Escape'){event.preventDefault();close();return;}
  if(event.key!=='Tab')return;
  const items=[...active.querySelectorAll('button,a[href],input,select,textarea,summary,[tabindex="0"]')].filter(el=>!el.disabled&&el.getClientRects().length);
  if(!items.length)return;
  if(event.shiftKey&&document.activeElement===items[0]){event.preventDefault();items.at(-1).focus();}
  else if(!event.shiftKey&&document.activeElement===items.at(-1)){event.preventDefault();items[0].focus();}
 },true);
 const params=new URLSearchParams(location.search);
 let initial=workspace.dataset.errorModal||params.get('finance_view');
 if(!initial){
  if([...params.keys()].some(key=>key.startsWith('payment_')||key==='payments_page'))initial='finance-history-modal';
  else if([...params.keys()].some(key=>key.startsWith('credit_')||key==='credits_page'))initial='finance-credit-modal';
  else if([...params.keys()].some(key=>key.startsWith('recovery_')||key==='recoveries_page'))initial='finance-recovery';
 }
 if(location.hash==='#commission-payment-recovery')initial='finance-recovery';
 if(initial)open(initial);
 const focusId=params.get('payment_id')||params.get('recovery_id');
 if(active&&focusId){const record=document.getElementById((params.has('payment_id')?'commission-payment-':'commission-recovery-')+focusId);record?.scrollIntoView({block:'nearest'});}
})();
