(() => {
    const email=document.getElementById('representative_email');
    if(!email)return;
    const phone=document.getElementById('representative_contact_no'),send=document.getElementById('representativeSendCode'),verify=document.getElementById('representativeVerifyCode'),group=document.getElementById('representativeOtpGroup'),code=document.getElementById('representative_otp'),hint=document.getElementById('representativeEmailHint');
    const contactFields=email.closest('.auth-form-grid');contactFields.classList.add('representative-contact-fields');document.querySelector('#panel-3 .step-nav').before(contactFields);
    contactFields.querySelectorAll(':scope > .auth-field').forEach(field=>field.classList.add('full'));
    const googleControls=document.querySelector('#registrationGoogleEmail')?.closest('.registration-google-email')?.cloneNode(true);
    const googleButton=googleControls?.querySelector('button'),googleHint=googleControls?.querySelector('.field-hint');
    if(googleControls){googleButton.id='representativeGoogleEmail';googleHint.id='representativeGoogleHint';googleControls.style.display='';email.closest('.auth-field').append(googleControls);}
    let verified='',sent='',generation=0,resendTimer=null;
    const codeHint=document.createElement('span');codeHint.className='field-hint';
    const codeMessage=document.createElement('span'),resend=document.createElement('button');resend.type='button';resend.className='btn-inline-link';resend.textContent='Resend';codeHint.append(codeMessage,document.createTextNode(' '),resend);group.append(codeHint);
    resend.addEventListener('click',()=>{send.disabled=false;send.click();});
    window.representativeEmailIsVerified=()=>verified!==''&&verified===email.value.trim().toLowerCase();
    const post=async(url,payload)=>{
        const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':email.form.querySelector('[name="_token"]').value},body:JSON.stringify({...payload,purpose:'representative'})});
        const data=await response.json();if(!response.ok||data.success===false)throw Error(data.message||Object.values(data.errors||{}).flat()[0]||'Please try again.');return data;
    };
    const badge=document.createElement('div');badge.className='email-verified-badge';badge.hidden=true;badge.textContent='✓ Email verified';email.closest('.auth-field').append(badge);
    const sync=()=>{const done=window.representativeEmailIsVerified();send.hidden=done;if(googleControls)googleControls.style.display=done?'none':'';badge.hidden=!done;if(done)hint.textContent='';};
    email.value='';email.readOnly=true;['focus','pointerdown'].forEach(event=>email.addEventListener(event,()=>email.readOnly=false));
    email.addEventListener('input',()=>{generation++;verified='';sent='';clearInterval(resendTimer);group.hidden=true;send.disabled=false;send.textContent='Send Code';hint.textContent='';clearError(email);sync();});
    phone.addEventListener('input',()=>{let value=phone.value.replace(/\D/g,'');if(value.startsWith('63'))value=value.slice(2);if(value.startsWith('0'))value=value.slice(1);phone.value=value.slice(0,10);});
    send.addEventListener('click',async()=>{
        const value=email.value.trim().toLowerCase(),request=++generation;
        if(!/^[^\s@]+@gmail\.com$/i.test(value)){showError(email,'Enter a valid Gmail address.');return;}
        sent='';send.disabled=true;send.textContent='Sending…';hint.textContent='';
        try{await post('/register/send-otp',{email:value});if(request!==generation)return;sent=value;group.hidden=false;hint.textContent='Code sent! Check your inbox (and spam folder).';send.textContent='Sent ✓';code.value='';codeMessage.textContent='Enter the code sent to your email.';codeHint.style.color='';clearError(email);let seconds=60;clearInterval(resendTimer);resend.disabled=true;resend.textContent='Resend (60s)';resendTimer=setInterval(()=>{seconds--;resend.disabled=seconds>0;resend.textContent=seconds>0?'Resend ('+seconds+'s)':'Resend';if(seconds<=0)clearInterval(resendTimer);},1000);}
        catch(error){if(request===generation){hint.textContent='';showError(email,error.message);}}
        finally{if(request===generation&&!sent){send.disabled=false;send.textContent='Send Code';}}
    });
    verify.addEventListener('click',async()=>{
        const value=email.value.trim().toLowerCase(),request=generation;if(value!==sent)return;
        if(!/^\d{6}$/.test(code.value.trim())){codeMessage.textContent='Please enter the full 6-digit code.';codeHint.style.color='var(--auth-danger)';resend.disabled=false;return;}
        verify.disabled=true;
        try{await post('/register/verify-otp',{email:value,otp:code.value.trim()});if(request!==generation)return;verified=value;clearInterval(resendTimer);group.hidden=true;badge.textContent='✓ Email verified';sync();clearError(email);}
        catch(error){if(request===generation){codeMessage.textContent=error.message;codeHint.style.color='var(--auth-danger)';resend.disabled=false;}}
        finally{verify.disabled=false;}
    });
    googleButton?.addEventListener('click',async()=>{
        verified='';email.value='';group.hidden=true;sync();googleButton.disabled=true;googleHint.textContent='Opening Google to verify your email…';
        try{
            await window.registrationDraft.save();
            const start=await post(googleButton.dataset.startUrl,{type:'logistics',same_tab:true});
            email.form.dispatchEvent(new Event('registration:verification-navigation'));window.location.assign(start.url);
        }catch(error){googleButton.disabled=false;googleHint.textContent=error.message;}
    });
    window.applyRepresentativeGoogleResult=()=>{
        if(window.registrationEmailResetOnReload)return;
        const result=JSON.parse(document.getElementById('registrationGoogleResult').textContent);
        if(!result.returning||result.target!=='representative')return;
        if(result.representative_error){email.value='';verified='';sync();showError(email,result.representative_error);return;}
        if(!result.representative_email)return;
        email.value=result.representative_email;email.dispatchEvent(new Event('input',{bubbles:true}));verified=email.value.toLowerCase();badge.textContent='✓ Verified via Google';sync();clearError(email);
    };
    window.applyRepresentativeGoogleResult();sync();

    const panel=document.getElementById('panel-8'),region=document.getElementById('hub_region_input');
    const controls=region.closest('[style*="grid-template-columns"]');controls.classList.add('service-area-dropdowns');
    document.getElementById('hub_province_input').hidden=false;

    // Regions and their covered areas are presented in separate adjacent panels.
    const overview=document.createElement('dialog');overview.id='coverageOverview';overview.className='coverage-overview';overview.setAttribute('aria-label','View all areas');
    overview.innerHTML='<section class="coverage-region-panel"><header><h2>Regions</h2><button type="button" data-close aria-label="Close all areas">×</button></header><nav aria-label="Covered regions"></nav></section><section class="coverage-area-panel"><header><div><h2>View all areas</h2></div><button type="button" data-close aria-label="Close all areas">×</button></header><div class="coverage-province-list"></div></section>';
    panel.append(overview);let selectedRegion='',selectedProvince='',selectedMunicipality='',pendingAction=null;
    const selectedMunicipalities=new Set();
    const chevron=direction=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'+(direction==='right'?'m9 5 7 7-7 7':direction==='up'?'m5 15 7-7 7 7':'m5 9 7 7 7-7')+'"/></svg>';
    const toolbar=document.createElement('div');toolbar.className='coverage-selection-toolbar';toolbar.hidden=true;
    toolbar.innerHTML='<span role="status" aria-live="polite"></span><button type="button" class="coverage-gateway" aria-label="Set selected municipality as gateway hub" title="Set as gateway hub"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/></svg></button><button type="button" class="coverage-remove-area" aria-label="Remove selected municipalities" title="Remove selected"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg></button>';
    overview.querySelector('.coverage-area-panel header').after(toolbar);
    const selectedAreas=()=>hubAreas.filter(area=>selectedMunicipalities.has(JSON.stringify([area.province,area.municipality])));
    const confirmation=document.createElement('dialog');confirmation.className='coverage-delete-confirm';confirmation.setAttribute('aria-labelledby','coverageDeleteTitle');
    confirmation.innerHTML='<h2 id="coverageDeleteTitle">Remove municipality?</h2><p></p><footer><button type="button" class="btn-prev" data-cancel>Cancel</button><button type="button" class="btn-next" data-confirm>Remove</button></footer>';
    panel.append(confirmation);
    const restoreMunicipalityFocus=()=>{overview.querySelectorAll('.coverage-municipality-select').forEach(button=>{if(button.dataset.area===selectedMunicipality)button.focus();});};
    confirmation.querySelector('[data-cancel]').addEventListener('click',()=>confirmation.close());
    confirmation.addEventListener('close',()=>{pendingAction=null;restoreMunicipalityFocus();});
    const confirmAction=type=>{
        const areas=selectedAreas();if(!areas.length||(type==='gateway'&&areas.length!==1))return;
        pendingAction={type,areas};
        confirmation.querySelector('h2').textContent=type==='gateway'?'Change gateway hub?':'Remove selected municipalities?';
        confirmation.querySelector('p').textContent=type==='gateway'?'Set '+areas[0].municipality+' as the gateway hub for '+areas[0].province+'?':'Remove '+(areas.length===1?areas[0].municipality:areas.length+' selected municipalities')+' from your service areas?';
        confirmation.querySelector('[data-confirm]').textContent=type==='gateway'?'Set gateway':'Remove';confirmation.showModal();
    };
    toolbar.querySelector('.coverage-gateway').addEventListener('click',()=>confirmAction('gateway'));
    toolbar.querySelector('.coverage-remove-area').addEventListener('click',()=>confirmAction('remove'));
    const unselect=document.createElement('button');unselect.type='button';unselect.className='coverage-unselect';unselect.textContent='Unselect';
    unselect.addEventListener('click',()=>{selectedMunicipalities.clear();overviewRender();restoreMunicipalityFocus();});toolbar.querySelector('span').after(unselect);
    confirmation.querySelector('[data-confirm]').addEventListener('click',()=>{
        if(!pendingAction)return;
        const {type,areas}=pendingAction;
        if(type==='gateway'){
            const area=areas[0];hubAreas.filter(entry=>entry.province===area.province).forEach(entry=>entry.is_regional=entry.municipality===area.municipality);
        }else{
            const keys=new Set(areas.map(area=>JSON.stringify([area.province,area.municipality])));
            hubAreas=hubAreas.filter(entry=>!keys.has(JSON.stringify([entry.province,entry.municipality])));
            new Set(areas.map(area=>area.province)).forEach(province=>{const remaining=hubAreas.filter(entry=>entry.province===province);if(remaining.length&&!remaining.some(entry=>entry.is_regional))remaining[0].is_regional=true;});
        }
        selectedMunicipalities.clear();selectedMunicipality='';syncHubAreas();renderHubMuniCheckboxes(hubMuniFilter.value);confirmation.close();
    });
    const overviewRender=()=>{
        const regions=[...new Set(hubAreas.map(area=>area.region))].sort(),nav=overview.querySelector('nav'),content=overview.querySelector('.coverage-province-list');
        if(!regions.includes(selectedRegion))selectedRegion='';toolbar.remove();nav.replaceChildren();content.replaceChildren();overview.querySelector('.coverage-area-panel header').after(toolbar);
        overview.querySelector('.coverage-area-panel').hidden=!selectedRegion;
        overview.classList.toggle('region-selected',!!selectedRegion);
        const selected=selectedAreas();toolbar.hidden=!selected.length;toolbar.querySelector('span').textContent=selected.length;toolbar.querySelector('span').setAttribute('aria-label',selected.length+' selected municipalities');
        toolbar.querySelector('.coverage-gateway').disabled=selected.length!==1;
        toolbar.querySelector('.coverage-gateway').title=selected.length===1?'Set as gateway hub':'Select one municipality to set its gateway';
        if(!regions.length){const empty=document.createElement('p');empty.textContent='No service areas selected yet.';nav.append(empty);}
        regions.forEach(name=>{const button=document.createElement('button'),title=document.createElement('strong'),count=document.createElement('span'),arrow=document.createElement('span');button.type='button';title.textContent=name;count.className='coverage-province-count';const total=new Set(hubAreas.filter(area=>area.region===name).map(area=>area.province)).size;count.textContent=total+(total===1?' province':' provinces');arrow.className='coverage-region-arrow';arrow.innerHTML=chevron('right');arrow.setAttribute('aria-hidden','true');button.append(title,count,arrow);button.setAttribute('aria-pressed',String(selectedRegion===name));button.addEventListener('click',()=>{selectedRegion=name;selectedProvince='';selectedMunicipalities.clear();overviewRender();});nav.append(button);});
        const areas=hubAreas.filter(area=>area.region===selectedRegion),provinces=[...new Set(areas.map(area=>area.province))].sort();
        if(!provinces.length){const empty=document.createElement('p');empty.textContent='No service areas selected yet.';content.append(empty);}
        provinces.forEach(name=>{
            const section=document.createElement('section'),heading=document.createElement('button'),list=document.createElement('ul'),provinceAreas=areas.filter(area=>area.province===name).sort((a,b)=>a.municipality.localeCompare(b.municipality));
            section.className='coverage-province-section';heading.type='button';heading.className='coverage-province-toggle';
            const title=document.createElement('strong'),count=document.createElement('span'),arrow=document.createElement('span'),header=document.createElement('div');header.className='coverage-province-header';title.textContent=name;count.className='coverage-municipality-count';count.textContent=provinceAreas.length+' municipalities';arrow.className='coverage-province-arrow';arrow.innerHTML=chevron(selectedProvince===name?'up':'down');arrow.hidden=selectedProvince===name&&selected.length>0;arrow.setAttribute('aria-hidden','true');heading.append(title,count,arrow);header.append(heading);
            if(selectedProvince===name)header.append(toolbar);
            heading.setAttribute('aria-expanded',String(selectedProvince===name));list.hidden=selectedProvince!==name;
            heading.addEventListener('click',()=>{selectedProvince=selectedProvince===name?'':name;selectedMunicipalities.clear();overviewRender();content.querySelectorAll('.coverage-province-toggle')[provinces.indexOf(name)]?.focus();});
            provinceAreas.forEach((area,index)=>{
                const item=document.createElement('li'),label=document.createElement('label'),checkbox=document.createElement('input');label.textContent=area.municipality;
                if(area.is_regional){const star=document.createElementNS('http://www.w3.org/2000/svg','svg');star.setAttribute('viewBox','0 0 24 24');star.setAttribute('width','12');star.setAttribute('height','12');star.setAttribute('fill','currentColor');star.setAttribute('aria-hidden','true');star.classList.add('coverage-gateway-mark');star.innerHTML='<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/>';label.prepend(star);label.title='Current gateway hub';}
                const areaKey=JSON.stringify([name,area.municipality]);checkbox.type='checkbox';checkbox.className='coverage-municipality-select';checkbox.dataset.area=areaKey;checkbox.checked=selectedMunicipalities.has(areaKey);label.className='coverage-municipality-option';label.prepend(checkbox);
                checkbox.addEventListener('change',()=>{selectedMunicipality=areaKey;if(checkbox.checked)selectedMunicipalities.add(areaKey);else selectedMunicipalities.delete(areaKey);overviewRender();restoreMunicipalityFocus();});
                item.append(label);list.append(item);
            });section.append(header,list);content.append(section);
        });
    };
    let directRegion=false;
    window.openCoverageModal=(regionName=null)=>{directRegion=typeof regionName==='string'&&hubAreas.some(area=>area.region===regionName);selectedRegion=directRegion?regionName:'';selectedProvince='';selectedMunicipality='';selectedMunicipalities.clear();overview.classList.toggle('direct-region',directRegion);overviewRender();overview.showModal();};
    window.closeCoverageModal=()=>overview.close();
    const returnToRegions=()=>{if(directRegion){overview.close();return;}selectedRegion='';selectedProvince='';selectedMunicipalities.clear();overviewRender();overview.querySelector('nav button')?.focus();};
    overview.querySelector('.coverage-area-panel [data-close]').addEventListener('click',returnToRegions);
    overview.querySelector('.coverage-region-panel [data-close]').addEventListener('click',()=>overview.close());
    overview.addEventListener('cancel',event=>{if(selectedRegion){event.preventDefault();returnToRegions();}});
    overview.addEventListener('click',event=>{if(event.target===overview){const rect=overview.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)overview.close();}});
    const originalRender=window.renderHubAreaList;
    window.renderHubAreaList=()=>{
        originalRender();document.getElementById('addedCoverageHeader').hidden=!hubAreas.length;
        const list=document.getElementById('hubAreaList');list.replaceChildren();
        [...new Set(hubAreas.map(area=>area.region))].sort().forEach(regionName=>{
            const areas=hubAreas.filter(area=>area.region===regionName),provinces=new Set(areas.map(area=>area.province));
            const card=document.createElement('button');card.type='button';card.className='coverage-region-card';
            const icon=document.createElement('span');icon.className='coverage-region-card-icon';icon.innerHTML='<svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true"><path d="m9 18-6 3V6l6-3 6 3 6-3v15l-6 3-6-3zM9 3v15M15 6v15"/></svg>';
            const name=document.createElement('strong');name.textContent=regionName;
            const capsule=document.createElement('span');capsule.className='coverage-total-capsule';
            const provinceCount=document.createElement('span'),hubCount=document.createElement('span');provinceCount.textContent=provinces.size+(provinces.size===1?' province':' provinces');hubCount.textContent=areas.length+(areas.length===1?' hub':' hubs');capsule.append(provinceCount,hubCount);
            const arrow=document.createElement('span');arrow.className='coverage-region-card-arrow';arrow.innerHTML=chevron('right');
            card.append(icon,name,capsule,arrow);card.addEventListener('click',()=>window.openCoverageModal(regionName));list.append(card);
        });
        if(overview.open)overviewRender();
    };
    const viewAll=document.getElementById('hubViewAllBtn');viewAll.textContent='View all areas';
    window.renderHubAreaList();
})();
