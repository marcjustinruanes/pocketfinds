(() => {
    const api = 'https://psgc.gitlab.io/api';
    const get = async path => { const r = await fetch(`${api}/${path}/`); if(!r.ok) throw new Error('Unable to load locations'); return r.json(); };
    const options = (select, rows, prompt) => { select.replaceChildren(new Option(prompt,'')); rows.sort((a,b)=>a.name.localeCompare(b.name)).forEach(row=>{const o=new Option(row.name,row.name);o.dataset.code=row.code;select.add(o);}); select.disabled=false; };
    document.querySelectorAll('[data-national-mobile],#addrContactNo').forEach(input=>{
        const clean = value => value.replace(/\D/g,'').replace(/^(?:63|0)(?=9)/,'').slice(0,10);
        input.addEventListener('input',()=>{ input.value=clean(input.value); input.setCustomValidity(input.value && !/^9\d{9}$/.test(input.value)?'Enter 10 digits starting with 9.':''); });
        input.addEventListener('paste',event=>{event.preventDefault();input.value=clean(event.clipboardData.getData('text'));input.dispatchEvent(new Event('input'));});
    });
    const region = document.getElementById('addrRegion');
    const province = document.getElementById('addrProvince');
    if(region && province){
        let revision=0;
        const city=document.getElementById('addrMunicipality'), barangay=document.getElementById('addrBarangay');
        const reset = select => {select.replaceChildren(new Option('Select location above first',''));select.disabled=true;};
        get('regions').then(rows=>options(region,rows,'Select region')).catch(()=>{region.replaceChildren(new Option('Unable to load regions. Refresh to retry.',''));});
        region.addEventListener('change',async()=>{
            const version=++revision;reset(province);reset(city);reset(barangay);
            try{const all=await get('provinces'),code=region.selectedOptions[0].dataset.code;if(version!==revision)return;const rows=all.filter(row=>row.regionCode===code);
                if(rows.length)options(province,rows,'Select province');else{const o=new Option(region.value,region.value,true,true);o.dataset.code=code;o.dataset.isRegion='1';province.replaceChildren(o);province.disabled=false;const cities=await get(`regions/${code}/cities-municipalities`);if(version===revision)options(city,cities,'Select city / municipality');}
            }catch{province.replaceChildren(new Option('Select the region again to retry',''));}
        });
    }
    const accountRegion=document.querySelector('[data-account-region]');
    if(accountRegion){
        const form=accountRegion.closest('form');
        const fields=['province','municipality','barangay'].map(name=>{const old=form.querySelector(`[name="${name}"]`);const select=document.createElement('select');select.name=name;select.required=true;select.dataset.current=old.value;old.replaceWith(select);return select;});
        const [prov,city,brgy]=fields;let version=0;
        const cascade=async(level,keep=false)=>{const run=++version;const parent=level===0?accountRegion:fields[level-1];const code=parent.selectedOptions[0]?.dataset.code;for(let i=level;i<3;i++)options(fields[i],[],'Select location above first');if(!code)return;
            try{let rows;if(level===0){rows=(await get('provinces')).filter(p=>p.regionCode===code);if(!rows.length){const o=new Option(accountRegion.value,accountRegion.value,true,true);o.dataset.code=code;o.dataset.isRegion='1';prov.replaceChildren(o);rows=await get(`regions/${code}/cities-municipalities`);if(run!==version)return;options(city,rows,'Select city');if(keep)city.value=city.dataset.current;if(city.value)await cascade(2,keep);return;}}
                else rows=await get(`${level===1?(parent.selectedOptions[0]?.dataset.isRegion?'regions':'provinces'):'cities-municipalities'}/${code}/${level===1?'cities-municipalities':'barangays'}`);
                if(run!==version)return;options(fields[level],rows,'Select location');if(keep)fields[level].value=fields[level].dataset.current;if(keep && level<2 && fields[level].value)await cascade(level+1,true);
            }catch{fields[level].replaceChildren(new Option('Unable to load locations. Select again.',''));}};
        get('regions').then(async rows=>{options(accountRegion,rows,'Select region');accountRegion.value=accountRegion.dataset.current;if(accountRegion.value)await cascade(0,true);}).catch(()=>{accountRegion.replaceChildren(new Option('Unable to load regions. Refresh to retry.',''));});
        accountRegion.addEventListener('change',()=>cascade(0));prov.addEventListener('change',()=>cascade(1));city.addEventListener('change',()=>cascade(2));
    }
})();
