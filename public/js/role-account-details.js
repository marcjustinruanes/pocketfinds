(() => {
    const get=async path=>{const response=await fetch(`https://psgc.gitlab.io/api/${path}/`);if(!response.ok)throw new Error('Unable to load locations. Refresh to retry.');return response.json();};
    document.querySelectorAll('input[name="given_names"],input[name="last_name"],input[name="middle_name"]').forEach(input=>{
        input.removeAttribute('minlength');input.maxLength=50;input.minLength=2;
        // HTML pattern does not support \p{L} Unicode property escapes; use a
        // character class that covers ASCII letters plus common accented/Unicode
        // Latin characters and allows single internal spaces between words.
        // The backend regex /^[\p{L}-]+(\s[\p{L}-]+)*$/u is the authoritative rule.
        input.removeAttribute('pattern');
        if(input.name==='middle_name'){input.removeAttribute('style');input.placeholder='Middle name (optional)';}
    });
    // Name validation matching the backend rule: Unicode letters, hyphens, single spaces between words.
    const validateManagedName=value=>{
        if(!value)return null;
        if(value.length<2||value.length>50)return'Name must be between 2 and 50 characters.';
        return/^[\p{L}-]+(\s[\p{L}-]+)*$/u.test(value)?null:'Use only letters, hyphens, and single spaces between words.';
    };
    document.querySelectorAll('input[name="given_names"],input[name="last_name"],input[name="middle_name"]').forEach(input=>{
        input.addEventListener('blur',()=>{
            const msg=validateManagedName(input.value.trim());
            input.setCustomValidity(msg??'');
        });
        input.addEventListener('input',()=>input.setCustomValidity(''));
    });
    document.querySelectorAll('input[name="contact_no"]').forEach(input=>{
        const clean=value=>value.replace(/\D/g,'').replace(/^(?:63|0)(?=9)/,'').slice(0,10);
        input.value=clean(input.value);input.type='tel';input.inputMode='numeric';input.removeAttribute('minlength');input.maxLength=10;input.minLength=10;input.pattern='9[0-9]{9}';input.placeholder='9XXXXXXXXX';
        const wrapper=document.createElement('span');wrapper.style.cssText='display:flex;gap:8px;align-items:center';
        const prefix=document.createElement('span');prefix.textContent='+63';input.replaceWith(wrapper);wrapper.append(prefix,input);
        input.addEventListener('input',()=>{input.value=clean(input.value);input.setCustomValidity(input.value&&!/^9[0-9]{9}$/.test(input.value)?'Enter ten digits starting with 9.':'');});
        input.addEventListener('paste',event=>{event.preventDefault();input.value=clean(event.clipboardData.getData('text'));input.dispatchEvent(new Event('input'));});
    });
    // License number format validation on the managed form (matches backend regex).
    const licenseInput=document.querySelector('input[name="license_number"]');
    if(licenseInput){
        licenseInput.placeholder=licenseInput.placeholder||'e.g. N01-23-456789';
        const validateLicense=value=>{
            if(!value)return null;
            return/^[A-Z]\d{2}-\d{2}-\d{6}$/.test(value)?null:"Enter a valid licence number (e.g. N01-23-456789).";
        };
        licenseInput.addEventListener('blur',()=>{
            const msg=validateLicense(licenseInput.value.trim());
            licenseInput.setCustomValidity(msg??'');
        });
        licenseInput.addEventListener('input',()=>licenseInput.setCustomValidity(''));
    }
    document.querySelectorAll('input[name="password"]').forEach(input=>{input.minLength=Math.max(input.minLength,8);const hint=document.createElement('small');hint.textContent='At least 8 characters with uppercase, lowercase, a number and a symbol.';input.after(hint);});
    document.querySelectorAll('[data-role-region]').forEach(async region=>{
        const form=region.closest('form'),error=form.querySelector('[data-role-address-error]');
        const api=region.dataset.addressApi;
        const submit=form.querySelector('#addrSubmitBtn');
        const loadLocations=async path=>{
            if(!api)return get(path);
            const parts=path.split('/');
            const level=parts.length===1?parts[0]:({regions:'regional-municipalities',provinces:'municipalities','cities-municipalities':'barangays'})[parts[0]];
            if(!level)throw new Error('Invalid address request.');
            const url=new URL(api+'/'+level+(parts[1]?'/'+encodeURIComponent(parts[1]):''),location.origin);
            if(url.origin!==location.origin)throw new Error('Invalid address endpoint.');
            const cacheKey='logistics-locations:v1:'+url.pathname;
            const valid=rows=>Array.isArray(rows)&&rows.length>0&&rows.every(row=>typeof row.name==='string'&&/^\d{9}$/.test(row.code));
            try{const cached=JSON.parse(sessionStorage.getItem(cacheKey));if(cached&&cached.expires>Date.now()&&valid(cached.rows))return cached.rows;}catch{}
            const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},signal:AbortSignal.timeout(12000)});
            if(!response.ok)throw new Error(response.status===429?'Too many address requests. Wait a minute and refresh.':'Unable to load locations. Refresh to retry.');
            const rows=await response.json();
            if(!Array.isArray(rows)||rows.some(row=>typeof row.name!=='string'||!/^\d{9}$/.test(row.code)))throw new Error('Invalid address choices.');
            try{sessionStorage.setItem(cacheKey,JSON.stringify({rows,expires:Date.now()+300000}));}catch{}
            return rows;
        };
        if(api&&submit)submit.disabled=true;

        const fields=['province','municipality','barangay'].map(name=>{
            const old=form.querySelector(`[name="${name}"]`),select=document.createElement('select');
            select.name=name;select.id=old.id;select.className='select';select.required=true;select.dataset.current=old.value;
            select.add(new Option(old.value||'Select location above',old.value));old.replaceWith(select);return select;
        });
        const [province,city,barangay]=fields;let revision=0,provinces=[],provinceRequest;
        const ready=()=>{if(api&&submit)submit.disabled=region.disabled||fields.some(field=>field.disabled||!field.value);};
        const options=(select,rows,label)=>{select.replaceChildren(new Option(label,''));rows.sort((a,b)=>a.name.localeCompare(b.name)).forEach(row=>{const option=new Option(row.name,row.name);option.dataset.code=row.code;select.add(option);});select.disabled=false;};
        const clear=select=>{select.replaceChildren(new Option('Select location above',''));select.disabled=true;};
        async function cascade(level,restore=false){
            const run=++revision,parent=level===0?region:fields[level-1],code=parent.selectedOptions[0]?.dataset.code;
            for(let index=level;index<3;index++)clear(fields[index]);ready();if(!code)return;
            error.textContent='';
            try{
                if(level===0){
                    if(provinceRequest){provinces=await provinceRequest;if(run!==revision)return;}
                    const rows=provinces.filter(row=>row.regionCode===code);
                    if(!rows.length){const option=new Option(region.value,region.value,true,true);option.dataset.code=code;option.dataset.region='1';province.replaceChildren(option);province.disabled=false;const cities=await loadLocations(`regions/${code}/cities-municipalities`);if(run!==revision)return;options(city,cities,'Select city / municipality');if(restore)city.value=city.dataset.current;if(city.value)await cascade(2,restore);return;}
                    options(province,rows,'Select province');
                }else{
                    const path=level===1?`${parent.selectedOptions[0]?.dataset.region?'regions':'provinces'}/${code}/cities-municipalities`:`cities-municipalities/${code}/barangays`;
                    const rows=await loadLocations(path);if(run!==revision)return;options(fields[level],rows,'Select location');
                }
                if(restore)fields[level].value=fields[level].dataset.current;
                if(restore&&level<2&&fields[level].value)await cascade(level+1,true);
            }catch(failure){if(run===revision)error.textContent=failure.message;}finally{ready();}
        }
        region.addEventListener('change',()=>cascade(0));province.addEventListener('change',()=>cascade(1));city.addEventListener('change',()=>cascade(2));
        if(api){region.disabled=true;fields.forEach(clear);form.addEventListener('submit',event=>{if(region.disabled||fields.some(field=>field.disabled||!field.value)){event.preventDefault();error.textContent='Load and select a complete address before saving.';}});}
        try{
            provinceRequest=loadLocations('provinces');
            provinceRequest.catch(()=>{});
            const regions=await loadLocations('regions');
            options(region,regions,'Select region');region.value=region.dataset.current;
            const initialRevision=revision;
            provinces=await provinceRequest;
            if(initialRevision!==revision)return;
            if(!region.value){const saved=provinces.find(row=>row.name.toLowerCase()===province.dataset.current.toLowerCase());const inferred=regions.find(row=>row.code===saved?.regionCode||row.name===province.dataset.current);if(inferred)region.value=inferred.name;}
            if(region.value)await cascade(0,true);else fields.forEach(clear);
            if(submit){if(api)ready();else submit.disabled=false;submit.style.opacity='';submit.style.cursor='';}
        }catch(failure){error.textContent=failure.message;}
    });
})();
