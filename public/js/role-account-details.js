(() => {
    const get=async path=>{const response=await fetch(`https://psgc.gitlab.io/api/${path}/`);if(!response.ok)throw new Error('Unable to load locations. Refresh to retry.');return response.json();};
    document.querySelectorAll('input[name="given_names"],input[name="last_name"],input[name="middle_name"]').forEach(input=>{
        input.minLength=2;input.maxLength=50;input.pattern='[\\p{L}\\-]+';
        if(input.name==='middle_name'){input.removeAttribute('style');input.placeholder='Middle name (optional)';}
    });
    document.querySelectorAll('input[name="contact_no"]').forEach(input=>{
        const clean=value=>value.replace(/\D/g,'').replace(/^(?:63|0)(?=9)/,'').slice(0,10);
        input.value=clean(input.value);input.type='tel';input.inputMode='numeric';input.maxLength=10;input.minLength=10;input.pattern='9[0-9]{9}';input.placeholder='9XXXXXXXXX';
        const wrapper=document.createElement('span');wrapper.style.cssText='display:flex;gap:8px;align-items:center';
        const prefix=document.createElement('span');prefix.textContent='+63';input.replaceWith(wrapper);wrapper.append(prefix,input);
        input.addEventListener('input',()=>{input.value=clean(input.value);input.setCustomValidity(input.value&&!/^9[0-9]{9}$/.test(input.value)?'Enter ten digits starting with 9.':'');});
        input.addEventListener('paste',event=>{event.preventDefault();input.value=clean(event.clipboardData.getData('text'));input.dispatchEvent(new Event('input'));});
    });
    document.querySelectorAll('input[name="password"]').forEach(input=>{input.minLength=8;const hint=document.createElement('small');hint.textContent='At least 8 characters with uppercase, lowercase, a number and a symbol.';input.after(hint);});
    document.querySelectorAll('[data-role-region]').forEach(async region=>{
        const form=region.closest('form'),error=form.querySelector('[data-role-address-error]');
        const fields=['province','municipality','barangay'].map(name=>{
            const old=form.querySelector(`[name="${name}"]`),select=document.createElement('select');
            select.name=name;select.id=old.id;select.className='select';select.required=true;select.dataset.current=old.value;
            select.add(new Option(old.value||'Select location above',old.value));old.replaceWith(select);return select;
        });
        const [province,city,barangay]=fields;let revision=0,provinces=[];
        const options=(select,rows,label)=>{select.replaceChildren(new Option(label,''));rows.sort((a,b)=>a.name.localeCompare(b.name)).forEach(row=>{const option=new Option(row.name,row.name);option.dataset.code=row.code;select.add(option);});select.disabled=false;};
        const clear=select=>{select.replaceChildren(new Option('Select location above',''));select.disabled=true;};
        async function cascade(level,restore=false){
            const run=++revision,parent=level===0?region:fields[level-1],code=parent.selectedOptions[0]?.dataset.code;
            for(let index=level;index<3;index++)clear(fields[index]);if(!code)return;
            error.textContent='';
            try{
                if(level===0){
                    const rows=provinces.filter(row=>row.regionCode===code);
                    if(!rows.length){const option=new Option(region.value,region.value,true,true);option.dataset.code=code;option.dataset.region='1';province.replaceChildren(option);province.disabled=false;const cities=await get(`regions/${code}/cities-municipalities`);if(run!==revision)return;options(city,cities,'Select city / municipality');if(restore)city.value=city.dataset.current;if(city.value)await cascade(2,restore);return;}
                    options(province,rows,'Select province');
                }else{
                    const path=level===1?`${parent.selectedOptions[0]?.dataset.region?'regions':'provinces'}/${code}/cities-municipalities`:`cities-municipalities/${code}/barangays`;
                    const rows=await get(path);if(run!==revision)return;options(fields[level],rows,'Select location');
                }
                if(restore)fields[level].value=fields[level].dataset.current;
                if(restore&&level<2&&fields[level].value)await cascade(level+1,true);
            }catch(failure){if(run===revision)error.textContent=failure.message;}
        }
        region.addEventListener('change',()=>cascade(0));province.addEventListener('change',()=>cascade(1));city.addEventListener('change',()=>cascade(2));
        try{
            const [regions,allProvinces]=await Promise.all([get('regions'),get('provinces')]);provinces=allProvinces;options(region,regions,'Select region');region.value=region.dataset.current;
            if(!region.value){const saved=provinces.find(row=>row.name.toLowerCase()===province.dataset.current.toLowerCase());const inferred=regions.find(row=>row.code===saved?.regionCode||row.name===province.dataset.current);if(inferred)region.value=inferred.name;}
            if(region.value)await cascade(0,true);else fields.forEach(clear);
            const submit=form.querySelector('#addrSubmitBtn');if(submit){submit.disabled=false;submit.style.opacity='';submit.style.cursor='';}
        }catch(failure){error.textContent=failure.message;}
    });
})();
