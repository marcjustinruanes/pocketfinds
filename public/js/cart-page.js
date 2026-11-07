(() => {
  // Dialogs must cover the fixed storefront header, including address dialogs.
  const dialogs=[...document.querySelectorAll('.modal-overlay,.cart-voucher-modal,.cart-confirm-modal')];
  dialogs.forEach(dialog=>document.body.append(dialog));
  const syncScroll=()=>document.body.classList.toggle('cart-dialog-open',dialogs.some(dialog=>dialog.classList.contains('open')));
  dialogs.forEach(dialog=>new MutationObserver(syncScroll).observe(dialog,{attributes:true,attributeFilter:['class']}));
  syncScroll();
  const addressPicker=document.getElementById('cartAddressModal');
  if(addressPicker){
    addressPicker.addEventListener('keydown',event=>{
      if(event.key!=='Tab')return;
      const controls=[...addressPicker.querySelectorAll('button,input,select,a[href]')].filter(control=>!control.disabled&&control.getClientRects().length);
      const first=controls[0],last=controls[controls.length-1];
      if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}
      else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}
    });
    new MutationObserver(()=>{
      const open=addressPicker.classList.contains('open');addressPicker.setAttribute('aria-hidden',String(!open));
      if(!open&&!dialogs.some(dialog=>dialog!==addressPicker&&dialog.classList.contains('open')))document.getElementById('cartAddressToggle')?.focus();
    }).observe(addressPicker,{attributes:true,attributeFilter:['class']});
  }
  document.querySelectorAll('.delivery-address-editor .address-form-grid>label').forEach(label=>{
    const row=document.createElement('div');row.className=`form-row ${label.className}`;
    const caption=document.createElement('label');caption.textContent=label.childNodes[0]?.textContent||'';row.append(caption);
    label.querySelectorAll('input,select').forEach(field=>row.append(field));label.replaceWith(row);
  });
  const addressFields={addrLabel:'label',addrRecipientName:'recipient_name',addrContactNo:'contact_no',addrRegion:'region',addrProvince:'province',addrMunicipality:'municipality',addrBarangay:'barangay',addrPostalCode:'postal_code',addrHouseNo:'house_no',addrStreet:'street'};
  Object.entries(addressFields).forEach(([id,name])=>document.getElementById(id)?.closest('.form-row')?.classList.add(`address-field-${name}`));
  document.querySelectorAll('.cart-layout label[data-voucher-group]').forEach(label=>{
    const select=label.querySelector('select');if(!select)return;
    const text=label.childNodes[0]?.textContent.trim()||'Voucher';
    const wrapper=document.createElement('div');wrapper.className='cart-floating-field always-floating';wrapper.setAttribute('data-voucher-group','');wrapper.hidden=label.hidden;
    select.id ||= `cartVoucher${Math.random().toString(36).slice(2)}`;
    const caption=document.createElement('label');caption.htmlFor=select.id;caption.textContent=text;
    const outline=document.createElement('fieldset'),legend=document.createElement('legend'),span=document.createElement('span');span.textContent=text;legend.append(span);outline.setAttribute('aria-hidden','true');outline.append(legend);
    wrapper.append(select,caption,outline);label.replaceWith(wrapper);
  });
  document.querySelectorAll('.address-modal .form-row').forEach(row=>{
    const field=row.querySelector('input:not([type=hidden]),select'),label=row.querySelector('label');
    if(!field||!label||row.querySelector('.cart-floating-field'))return;
    const text=label.textContent.replace(/\s*\*/g,'').trim();label.remove();
    const wrapper=document.createElement('div');wrapper.className='cart-floating-field always-floating';
    field.before(wrapper);wrapper.append(field);field.placeholder=' ';field.id ||= `cartAddressField${Math.random().toString(36).slice(2)}`;
    const caption=document.createElement('label');caption.htmlFor=field.id;caption.textContent=text;
    const outline=document.createElement('fieldset'),legend=document.createElement('legend'),span=document.createElement('span');
    outline.setAttribute('aria-hidden','true');span.textContent=text;legend.append(span);outline.append(legend);wrapper.append(caption,outline);
    if(field.name==='contact_no'){
      const prefix=document.createElement('span');prefix.className='cart-mobile-prefix';prefix.textContent='+63';wrapper.classList.add('cart-mobile-field');wrapper.append(prefix);
    }
  });
})();
