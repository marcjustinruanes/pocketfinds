(() => {
  const modal=document.getElementById('wishlistOptionsModal');if(!modal)return;document.body.append(modal);
  const form=document.getElementById('wishlistOptionsForm'),fields=document.getElementById('wishlistOptionFields'),qty=document.getElementById('wishlistOptionQty'),save=document.getElementById('wishlistOptionsSave'),cancel=modal.querySelector('[data-wishlist-options-cancel]'),error=document.getElementById('wishlistOptionError');
  const selections=new WeakMap();let row,product,trigger,adding=false,busy=false;
  document.querySelectorAll('[data-wishlist-message]').forEach(button=>button.addEventListener('click',()=>{
    const choice=selections.get(button.closest('.wishlist-item'));
    const url=new URL(button.dataset.messagesUrl,location.origin);
    if(choice?.group&&choice?.value){url.searchParams.set('variation_group',choice.group);url.searchParams.set('variation_value',choice.value);}else{url.searchParams.delete('variation_group');url.searchParams.delete('variation_value');}
    button.dataset.messagesUrl=url.href;
  },true));
  const selected=()=>fields.querySelector('.pd-opt-btn.active')?._option;
  const stock=()=>Number(selected()?.stock??product.stock);
  const sync=()=>{qty.max=Math.min(99,stock());save.disabled=stock()<=0||!!((product.variations||[]).length&&!selected());if(Number(qty.value)>stock()){qty.value=Math.max(1,stock());error.textContent=stock()>0?`Only ${stock()} item(s) available — quantity adjusted.`:'This option is out of stock.';error.hidden=false;}else error.hidden=true;};
  const close=()=>{if(busy)return;modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.classList.remove('wishlist-dialog-open');trigger?.focus();};
  const open=(button,add)=>{
    trigger=button;row=button.closest('.wishlist-item');product=JSON.parse(button.closest('[data-wishlist-product]').dataset.wishlistProduct);adding=add;fields.replaceChildren();error.hidden=true;const previous=selections.get(row);qty.value=previous?.qty||1;document.getElementById('wishlistOptionName').textContent=product.name;
    const image=document.getElementById('wishlistOptionImage');image.src=product.img||'';image.alt=product.name;document.getElementById('wishlistOptionImageWrap').hidden=!product.img;
    document.getElementById('wishlistOptionPrice').textContent='₱'+Number(product.price).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2})+' each';
    const groups=product.variations||[];
    groups.forEach(variation=>{
      const group=document.createElement('div');group.className='pd-opt-group';
      const label=document.createElement('div');label.className='pd-opt-label';label.textContent=variation.name;
      const options=document.createElement('div');options.className='pd-opt-row';
      (variation.options||[]).forEach(option=>{
        const button=document.createElement('button');button.type='button';button.className='pd-opt-btn';button._option={...option,group:variation.name};button.dataset.group=variation.name;button.dataset.value=option.value;button.dataset.stock=option.stock??0;
        const available=Number(option.stock??0)>0;button.disabled=!available;if(!available){button.style.opacity='.4';button.style.cursor='not-allowed';}
        button.append(document.createTextNode(option.value));const remaining=document.createElement('span');remaining.style.cssText=`font-size:10px;color:var(--${available?'muted':'danger'});display:block`;remaining.textContent=available?`${option.stock} left`:'Out of stock';button.append(remaining);
        if(available&&previous?.group===variation.name&&previous?.value===option.value)button.classList.add('active');
        button.addEventListener('click',()=>{fields.querySelectorAll('.pd-opt-btn').forEach(node=>node.classList.remove('active'));button.classList.add('active');sync();});options.append(button);
      });group.append(label,options);fields.append(group);
    });
    if(!selected())fields.querySelector('.pd-opt-btn:not(:disabled)')?.classList.add('active');
    save.textContent=add?'Add to cart':'Save item';modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.classList.add('wishlist-dialog-open');sync();qty.focus();qty.select();
  };
  async function addToCart(button,p,choice){button.disabled=true;const feedback=document.getElementById('wishlistError');feedback.hidden=true;
    try{const response=await fetch('/buyer/cart/add',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({product_id:p.id,qty:choice.qty,variation_group:choice.group||'',variation_value:choice.value||''})});const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Could not add this item.');
      document.querySelectorAll('[data-cart-count],#cartCount,#cartBadge,.cart-badge').forEach(node=>{node.textContent=data.count;node.hidden=!data.count;node.style.display=data.count?'':'none';});if(typeof window.flyToCart==='function')window.flyToCart(button);else{feedback.textContent='Added to cart.';feedback.style.color='#267548';feedback.hidden=false;}return true;
    }catch(failure){feedback.style.color='';feedback.textContent=failure.message;feedback.hidden=false;return false;}finally{button.disabled=false;}
  }
  document.querySelectorAll('[data-wishlist-options]').forEach(button=>button.addEventListener('click',()=>open(button,false)));
  document.querySelectorAll('[data-wishlist-add]').forEach(button=>button.addEventListener('click',()=>{const item=button.closest('.wishlist-item'),p=JSON.parse(button.closest('[data-wishlist-product]').dataset.wishlistProduct),choice=selections.get(item);if(!choice&&(p.variations||[]).length){open(button,true);return;}addToCart(button,p,choice||{qty:1});}));
  form.addEventListener('submit',async event=>{event.preventDefault();if(busy||!form.reportValidity())return;const option=selected();if(Number(qty.value)>stock()||Number(qty.value)<1)return;const choice={qty:Number(qty.value),group:option?.group||'',value:option?.value||''};selections.set(row,choice);const text=row.querySelector('.wishlist-selected-options');const parts=option?.attributes?Object.entries(option.attributes).map(([name,value])=>`${name}: ${value}`):option?[`${option.group}: ${option.value}`]:[];parts.push(`Qty ${choice.qty}`);text.textContent=parts.join(' · ');text.hidden=false;
    if(adding){busy=true;save.disabled=true;const ok=await addToCart(trigger,product,choice);busy=false;save.disabled=false;if(!ok){error.textContent=document.getElementById('wishlistError').textContent;error.hidden=false;return;}}close();
  });
  cancel.addEventListener('click',close);modal.addEventListener('click',event=>{if(event.target===modal)close();});modal.addEventListener('keydown',event=>{if(event.key==='Escape')close();if(event.key==='Tab'){const controls=[...modal.querySelectorAll('button,input,select')].filter(node=>!node.disabled);const first=controls[0],last=controls[controls.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}}});
  qty.addEventListener('input',()=>{error.hidden=Number(qty.value)<=stock();error.textContent=stock()>0?`Only ${stock()} item(s) available.`:'This option is out of stock.';});
})();
