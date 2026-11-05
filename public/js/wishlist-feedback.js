(() => {
 const form=document.getElementById('pdWishlistForm'),button=document.getElementById('pdWishlistBtn');if(!form||!button)return;
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(button.disabled)return;button.disabled=true;
  try{
   const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'}});const data=await response.json();if(!response.ok)throw new Error(data.message||'Unable to update Wishlist.');
   form.querySelector('[name=saved]').value=data.saved?'0':'1';button.setAttribute('aria-pressed',String(data.saved));button.title=data.saved?'Remove from Wishlist':'Add to Wishlist';button.setAttribute('aria-label',button.title);
   document.getElementById('wishlistFeedbackCard')?.remove();
   const card=document.createElement('div');card.id='wishlistFeedbackCard';card.setAttribute('role','status');card.style.cssText='position:fixed;right:24px;bottom:30px;z-index:500;display:flex;align-items:center;gap:12px;width:300px;max-width:calc(100vw - 48px);padding:14px;border:1px solid #eadfe6;border-radius:10px;background:white;box-shadow:0 8px 28px #59334626;color:#593346;font:13px Inter,system-ui,sans-serif';
   const source=document.getElementById('pdMainImg');
   if(source){const img=document.createElement('img');img.src=source.src;img.alt='';img.style.cssText='width:64px;height:48px;object-fit:cover;border-radius:5px';card.append(img);}
   const text=document.createElement('div'),title=document.createElement('strong'),name=document.createElement('div');title.textContent=data.saved?'Added to Wishlist':'Removed from Wishlist';name.textContent=document.querySelector('.pd-title')?.textContent||'';name.style.cssText='font-size:11px;color:#8c7381;margin-top:5px';text.append(title,name);card.append(text);document.body.append(card);
   const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;if(!reduced)card.animate([{opacity:0,transform:'translateY(16px)'},{opacity:1,transform:'translateY(0)'}],{duration:220});
   if(data.saved&&source&&!reduced){const target=document.querySelector('a[href$="/buyer/wishlist"]');if(target){const from=button.getBoundingClientRect(),to=target.getBoundingClientRect();const flying=document.createElement('img');flying.src=source.src;flying.alt='';flying.style.cssText=`position:fixed;left:${from.left}px;top:${from.top}px;width:64px;height:48px;object-fit:cover;border-radius:5px;z-index:600;pointer-events:none`;document.body.append(flying);flying.animate([{transform:'translate(0,0)',opacity:1},{transform:`translate(${to.left-from.left}px,${to.top-from.top}px) scale(.35)`,opacity:0}],{duration:650,easing:'ease-in-out'}).onfinish=()=>flying.remove();}}
   setTimeout(()=>card.remove(),3500);
  }catch(error){if(typeof showToast==='function')showToast(error.message,'error');}finally{button.disabled=false;}
 });
})();
