(() => {
 const api='https://psgc.gitlab.io/api';
 const get=async path=>{const response=await fetch(`${api}/${path}/`);if(!response.ok)throw new Error('Unable to load locations.');return response.json();};
 document.querySelectorAll('[data-edit-delivery-address]').forEach(button=>button.addEventListener('click',event=>{
  event.preventDefault();event.stopPropagation();const panel=document.getElementById(`editDeliveryAddress${button.dataset.editDeliveryAddress}`);panel.hidden=!panel.hidden;
 }));
 document.querySelectorAll('[data-address-edit-region]').forEach(input=>{
  const form=input.closest('form');const names=['region','province','municipality','barangay'];
  const fields=names.map(name=>{const old=form.querySelector(`[name="${name}"]`);const select=document.createElement('select');select.name=name;select.required=true;select.dataset.current=old.value;select.add(new Option(old.value,old.value,true,true));old.replaceWith(select);return select;});
  const options=(select,rows)=>{select.replaceChildren(new Option('Select location',''));rows.sort((a,b)=>a.name.localeCompare(b.name)).forEach(row=>{const option=new Option(row.name,row.name);option.dataset.code=row.code;select.add(option);});select.disabled=false;};
  let revision=0;
  const cascade=async(level,keep=false)=>{
   const version=++revision;
   for(let i=level;i<4;i++){fields[i].replaceChildren(new Option('Select location above',''));fields[i].disabled=true;}
   try{
    const parent=fields[level-1],code=parent.selectedOptions[0]?.dataset.code;if(!code)return;
    let rows;
    if(level===1){rows=(await get('provinces')).filter(p=>p.regionCode===code);
     if(!rows.length){if(version!==revision)return;const option=new Option(parent.value,parent.value,true,true);option.dataset.code=code;option.dataset.isRegion='1';fields[1].replaceChildren(option);fields[1].disabled=false;await cascade(2,keep);return;}
    }else rows=await get(`${level===2?(parent.selectedOptions[0]?.dataset.isRegion?'regions':'provinces'):'cities-municipalities'}/${code}/${level===2?'cities-municipalities':'barangays'}`);
    if(version!==revision)return;options(fields[level],rows);if(keep)fields[level].value=fields[level].dataset.current;
    if(keep&&level<3&&fields[level].value)await cascade(level+1,true);
   }catch(_){if(version===revision)fields[level].replaceChildren(new Option('Unable to load. Choose the location above again.',''));}
  };
  get('regions').then(async rows=>{options(fields[0],rows);fields[0].value=fields[0].dataset.current;if(fields[0].value)await cascade(1,true);}).catch(()=>{});
  fields.slice(0,3).forEach((select,i)=>select.addEventListener('change',()=>cascade(i+1)));
  form.addEventListener('submit',async event=>{
   event.preventDefault();if(!form.reportValidity())return;
   const submit=form.querySelector('button');if(submit.disabled)return;submit.disabled=true;
   let feedback=form.querySelector('[role="alert"]');if(!feedback){feedback=document.createElement('p');feedback.setAttribute('role','alert');form.append(feedback);}
   try{if(fields.some(field=>field.disabled))throw new Error('Choose a complete delivery address.');const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'}});const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Unable to save address.');location.reload();}
   catch(error){feedback.textContent=error.message;submit.disabled=false;}
  });
 });
})();
