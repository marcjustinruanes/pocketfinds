(() => {
  const all=document.querySelector('[data-wishlist-all]'), shops=[...document.querySelectorAll('.wishlist-shop')];
  const modal=document.getElementById('wishlistRemoveModal');if(!modal)return;
  document.body.append(modal);
  const cancel=modal.querySelector('[data-wishlist-cancel]'),confirm=modal.querySelector('[data-wishlist-confirm]');
  let pending=[],opener=null,busy=false;
  const checks=scope=>[...scope.querySelectorAll('.wishlist-select')];
  const syncCheck=(control,items)=>{if(!control)return;control.checked=items.length>0&&items.every(item=>item.checked);control.indeterminate=items.some(item=>item.checked)&&!control.checked;};
  const sync=()=>{syncCheck(all,checks(document));shops.forEach(shop=>syncCheck(shop.querySelector('[data-wishlist-shop]'),checks(shop)));document.querySelectorAll('[data-wishlist-delete]').forEach(button=>{const scope=button.dataset.wishlistDelete==='shop'?button.closest('.wishlist-shop'):document;button.disabled=!checks(scope).some(item=>item.checked);});};
  all?.addEventListener('change',()=>{checks(document).forEach(item=>item.checked=all.checked);sync();});
  shops.forEach(shop=>shop.querySelector('[data-wishlist-shop]').addEventListener('change',event=>{checks(shop).forEach(item=>item.checked=event.target.checked);sync();}));
  document.querySelectorAll('.wishlist-select').forEach(item=>item.addEventListener('change',sync));
  const close=()=>{if(busy)return;modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.classList.remove('wishlist-dialog-open');pending=[];opener?.focus();};
  const open=(forms,button)=>{if(!forms.length)return;pending=forms;opener=button;document.getElementById('wishlistRemoveMessage').textContent=`Remove ${forms.length} selected item${forms.length===1?'':'s'} from your wishlist?`;modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.classList.add('wishlist-dialog-open');cancel.focus();};
  document.querySelectorAll('[data-wishlist-delete]').forEach(button=>button.addEventListener('click',()=>{const scope=button.dataset.wishlistDelete==='shop'?button.closest('.wishlist-shop'):document;open(checks(scope).filter(item=>item.checked).map(item=>item.closest('.wishlist-item').querySelector('form')),button);}));
  document.querySelectorAll('.wishlist-remove-form').forEach(form=>form.addEventListener('submit',event=>{event.preventDefault();open([form],event.submitter);}));
  cancel.addEventListener('click',close);modal.addEventListener('click',event=>{if(event.target===modal)close();});
  modal.addEventListener('keydown',event=>{if(event.key==='Escape')close();if(event.key==='Tab'){if(event.shiftKey&&document.activeElement===cancel){event.preventDefault();confirm.focus();}else if(!event.shiftKey&&document.activeElement===confirm){event.preventDefault();cancel.focus();}}});
  confirm.addEventListener('click',async()=>{if(busy||!pending.length)return;busy=true;confirm.disabled=true;cancel.disabled=true;const error=document.getElementById('wishlistError');error.hidden=true;
    try{for(const form of pending){const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'},credentials:'same-origin'});if(!response.ok)throw new Error('Could not remove all selected items. Refresh the page and try again.');}location.reload();}
    catch(failure){busy=false;confirm.disabled=false;cancel.disabled=false;close();error.textContent=failure.message;error.hidden=false;error.scrollIntoView({block:'nearest'});}
  });
  sync();
})();
