(() => {
 const endpoint='/register/postal-code';
 const pairs=[...document.querySelectorAll('input[name="postal_code"],#addrPostalCode')];
 pairs.forEach(postal=>{
  if(postal.id==='postal_code'&&document.getElementById('registrationPostalField'))return;
  const form=postal.closest('form');if(!form)return;
  postal.required=false;postal.readOnly=true;
  const hint=document.createElement('small');hint.style.cssText='display:block;font-size:11px;color:#8c7381;margin-top:5px';postal.after(hint);
  const find=name=>form.querySelector(`[name="${name}"]`)||document.getElementById({municipality:'addrMunicipality',province:'addrProvince',region:'addrRegion'}[name]);
  let version=0,last='',controller;
  const sync=async()=>{
   const city=find('municipality'),region=find('region'),province=find('province');if(!city)return;
   const code=city.selectedOptions?.[0]?.dataset.code||'';
   const key=[region?.value,province?.value,city.value,code].join('|');if(key===last)return;last=key;
   const run=++version;controller?.abort();controller=new AbortController();postal.value='';hint.textContent='';
   if(!city.value||!/^\d{9,10}$/.test(code))return;
   hint.textContent='Looking up postal code…';
   try{
    const url=new URL(endpoint,location.origin);url.searchParams.set('municipality_code',code);url.searchParams.set('municipality',city.value);
    const response=await fetch(url,{signal:controller.signal,headers:{Accept:'application/json'}});if(!response.ok)throw new Error();const data=await response.json();if(run!==version)return;
    postal.value=/^\d{4}$/.test(data.postal_code||'')?data.postal_code:'';hint.textContent=postal.value?'':'Postal code unavailable.';
   }catch(error){if(run!==version||error.name==='AbortError')return;postal.value='';hint.textContent='Postal code unavailable.';}
  };
  form.addEventListener('change',sync);
  new MutationObserver(sync).observe(form,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled']});
  sync();
 });
})();
