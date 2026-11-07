(() => {
 const form=document.getElementById('pdWishlistForm'),button=document.getElementById('pdWishlistBtn');if(!form||!button)return;
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(button.disabled)return;button.disabled=true;
  try{
   const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'}});const data=await response.json();if(!response.ok)throw new Error(data.message||'Unable to update Wishlist.');
   form.querySelector('[name=saved]').value=data.saved?'0':'1';button.setAttribute('aria-pressed',String(data.saved));button.title=data.saved?'Remove from Wishlist':'Add to Wishlist';button.setAttribute('aria-label',button.title);
   showProductFeedback({id:'wishlistFeedbackCard',title:data.saved?'Added to Wishlist':'Removed from Wishlist',name:document.querySelector('.pd-title')?.textContent||'',image:document.getElementById('pdMainImg')?.src||'',origin:button,target:document.querySelector('a[href$="/buyer/wishlist"]'),animate:data.saved});
  }catch(error){if(typeof showToast==='function')showToast(error.message,'error');}finally{button.disabled=false;}
 });
})();
