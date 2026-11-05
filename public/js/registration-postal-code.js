(() => {
    const field=document.getElementById('registrationPostalField');
    const city=document.getElementById('municipality');
    if(!field || !city) return;
    const postal=document.getElementById('postal_code'),code=document.getElementById('municipality_psgc_code'),capsule=document.getElementById('registrationPostalCapsule');
    let version=0, pending=null, last='';
    const clear=()=>{
        version++;pending?.abort();pending=null;last='';postal.value='';postal.readOnly=true;code.value='';capsule.textContent='';capsule.removeAttribute('title');
    };
    async function lookup() {
        const selected=city.selectedOptions[0], selectedCode=selected?.dataset.code;
        if(!city.value || !/^\d{9,10}$/.test(selectedCode||'')){clear();return;}
        const key=selectedCode+'|'+city.value;if(key===last)return;
        clear();last=key;code.value=selectedCode;
        const request=version;pending=new AbortController();
        capsule.textContent='Looking up postal code…';
        try {
            const url=new URL(field.dataset.lookupUrl,location.href);url.searchParams.set('municipality_code',selectedCode);url.searchParams.set('municipality',city.value);
            const response=await fetch(url,{signal:pending.signal,headers:{Accept:'application/json'}});
            if(!response.ok)throw new Error('Postal lookup unavailable');
            const data=await response.json();if(request!==version)return;
            postal.value=/^\d{4}$/.test(data.postal_code||'')?data.postal_code:'';
            postal.readOnly=!!postal.value;
            capsule.textContent=postal.value?'':'Enter if known, or leave blank.';
            if(!postal.value)capsule.title='Enter your postal code if known, or leave blank.';
        } catch(error) {
            if(request!==version || error.name==='AbortError')return;
            postal.value='';postal.readOnly=false;capsule.textContent='Enter if known, or leave blank.';capsule.title='Enter your postal code if known, or leave blank.';
            last='';
        }
    }
    city.addEventListener('change',lookup);
    ['region','province'].forEach(id=>document.getElementById(id)?.addEventListener('change',clear));
    new MutationObserver(lookup).observe(city,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled']});
    lookup();
})();
