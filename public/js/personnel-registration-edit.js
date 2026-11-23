(() => {
  'use strict';
  const config = JSON.parse(document.getElementById('personnel-edit-data').textContent);
  const form = document.getElementById('buyerForm');
  form.dataset.personnelEditing = 'true';
  form.action = config.action;
  const rider = form.querySelector('[name="account_type"]').value === 'rider';
  const sequence = rider ? [5,9,2,7,3,4,6] : [5,8,2,3,4,6];
  const protectedFields = ['id_file','selfie_file','license_file','or_file','cr_file','id_type_id','license_number','plate_number','vehicle_brand','vehicle_model','nbi_clearance_file','resume_file'];
  const originalFetch = window.fetch;
  // The live duplicate checks must recognize the account being edited.
  window.fetch = (input, init) => {
    if (typeof input === 'string' && (input.includes('/register/check-') || input.includes('/registration/check-'))) {
      const url = new URL(input, location.origin);
      let same = false;
      const names = {'check-username':'username','check-license':'license_number','check-plate':'plate_number'};
      const name = names[url.pathname.split('/').pop()];
      if (name) same = url.searchParams.get(name) === String(config.values[name] || '');
      if (url.pathname.endsWith('/check-identity') && init?.body) {
        const data = JSON.parse(init.body);
        same = ['given_names','last_name','middle_name','suffix','birthday','sex'].every(key => String(data[key] || '') === String(config.values[key] || ''));
      }
      if (same) return Promise.resolve(new Response(JSON.stringify({available:true}), {headers:{'Content-Type':'application/json'}}));
    }
    return originalFetch(input, init);
  };
  function validatePanel(id) {
    const panel = document.getElementById('panel-' + id);
    let valid = true;
    panel.querySelectorAll('input,select,textarea').forEach(field => {
      if (field.disabled || field.type === 'hidden' || field.type === 'file' || field.type === 'checkbox' || field.type === 'radio') return;
      clearError(field);
      if (field.required && !field.value.trim()) { showError(field, 'This field is required.'); valid = false; }
      else if (field.value && !field.checkValidity()) { showError(field, field.validationMessage); valid = false; }
    });
    if (id === 5 && ['region','province','municipality','barangay'].some(name => !form.elements[name]?.value)) valid = false;
    if (id === (rider ? 9 : 8) && !form.elements.logistics_hub_id.value) {
      document.getElementById('hubSelectError').style.display = 'block'; valid = false;
    }
    if (id === 7) ['vehicle_type','vehicle_ownership'].forEach(name => {
      if (!form.querySelector('[name="'+name+'"]:checked')) { showRegisterErrorModal('Select your vehicle type and ownership.'); valid = false; }
    });
    if (id === 6 && form.elements.password?.value !== form.elements.password_confirmation?.value) {
      showError(form.elements.password_confirmation, 'Passwords do not match.'); valid = false;
    }
    return valid;
  }
  // registration-flow.js wraps this to retain the exact shared stage layout/navigation.
  window.nextStep = current => {
    if (!validatePanel(current)) return;
    if (current === 5 && ['province','municipality','barangay'].every(key => form.elements[key]?.value === config.values[key])) restoreHub();
    const target = sequence[sequence.indexOf(current)+1];
    if (!target) return;
    if (typeof stopCamera === 'function') stopCamera();
    document.getElementById('panel-'+current).classList.remove('active');
    document.getElementById('panel-'+target).classList.add('active');
  };
  function restoreHub() {
    const company = [...form.querySelectorAll('[name="business_name"]')].find(input => input.value === config.company);
    if (company) { company.checked = true; company.closest('label')?.classList.add('selected'); }
    if (typeof renderHubOptions === 'function') renderHubOptions();
    currentSelectedHubId = Number(config.hub);
    if (typeof updateSelectedHubPreview === 'function') updateSelectedHubPreview();
    form.elements.logistics_hub_id.value = config.hub || '';
  }
  // Registered before the shared submit listener; only handles the final edit step.
  form.addEventListener('submit', async event => {
    if (!document.getElementById('panel-6').classList.contains('active')) return;
    event.preventDefault(); event.stopImmediatePropagation();
    for (const id of sequence) if (!validatePanel(id)) { window.registrationFlow.showPanel(id); return; }
    const button = form.querySelector('.btn-submit');
    if (button.disabled) return;
    button.disabled = true; button.textContent = 'Saving...';
    const data = new FormData(form); data.set('_method','PATCH');
    if (selfieBlob) data.set('selfie_file',selfieBlob,'selfie.jpg');
    if (idPhotoBlob) data.set('id_file',idPhotoBlob,idPhotoBlob.type === 'application/pdf' ? 'id.pdf' : 'id.jpg');
    ['license','or','cr'].forEach(key => { if (docBlobs[key]) data.set(key+'_file',docBlobs[key],key+(docBlobs[key].type === 'application/pdf' ? '.pdf' : '.jpg')); });
    if (form.elements.username) { form.elements.username.minLength = config.onboarding ? 8 : 1; form.elements.username.maxLength = config.onboarding ? 30 : 60; }
    if (!config.onboarding) {
      const note = document.createElement('p'); note.className = 'auth-subtitle'; note.textContent = 'Previously approved documents are shown for reference and remain unchanged.'; document.getElementById('panel-2').prepend(note);
    }
    if (!config.onboarding) protectedFields.forEach(name => data.delete(name));
    if (!data.get('password') || config.activationPending) { data.delete('password'); data.delete('password_confirmation'); }
    // Untouched file inputs must not replace existing private documents.
    for (const [key,value] of [...data.entries()]) if (value instanceof File && value.size === 0) data.delete(key);
    try {
      const response = await fetch(form.action,{method:'POST',body:data,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
      const result = await response.json();
      if (!response.ok || !result.success) {
        let first = null;
        Object.entries(result.errors || {}).forEach(([name,messages]) => {
          const field = form.querySelector('[name="'+CSS.escape(name)+'"]');
          if (field) { showError(field,Array.isArray(messages)?messages[0]:messages); first ||= field; }
        });
        if (first?.closest('.step-panel')) window.registrationFlow.showPanel(Number(first.closest('.step-panel').id.replace('panel-','')));
        showRegisterErrorModal(result.errors ? Object.values(result.errors).flat().join('\n') : result.message || 'Unable to save changes.');
        return;
      }
      form.dispatchEvent(new Event('registration:saved'));
      window.parent.postMessage({type:'personnel-updated'},location.origin);
    } catch (_) { showRegisterErrorModal('Unable to save changes. Please try again.'); }
    finally { button.disabled = false; button.textContent = 'Save changes'; }
  },true);
  document.addEventListener('DOMContentLoaded', () => {
    for (const [name,value] of Object.entries(config.values)) {
      if (['region','province','municipality','barangay'].includes(name)) continue;
      const fields = [...form.querySelectorAll('[name="'+name+'"]')];
      fields.forEach(field => { if (field.type === 'radio') field.checked = field.value === String(value); else if (field.type !== 'file') field.value = value ?? ''; });
    }
    if (form.elements.contact_no) form.elements.contact_no.value = mobileNationalDigits(String(config.values.contact_no || ''));
    const noMiddle = document.getElementById('no_middle_name');
    if (noMiddle) { noMiddle.checked = !config.values.middle_name; syncMiddleName(); }
    document.getElementById('birthday')?.dispatchEvent(new Event('change'));
    if (rider && typeof onOwnershipChange === 'function') onOwnershipChange();
    ['password','password_confirmation'].forEach(name => {
      const field = form.elements[name]; if (!field) return;
      field.required = false; field.placeholder = 'Leave blank to keep current password';
      field.closest('.auth-field').querySelector('.auth-label')?.append(document.createTextNode(' (optional)'));
      if (config.activationPending) field.closest('.auth-field').hidden = true;
    });
    if (!config.onboarding) protectedFields.forEach(name => form.querySelectorAll('[name="'+name+'"]').forEach(field => { field.disabled = true; field.required = false; }));
    Object.entries(config.documents).forEach(([name,url]) => {
      if (!url) return;
      const field = form.querySelector('[name="'+name+'"]');
      const root = field?.closest('.registration-compact-upload,.auth-field') || (name === 'selfie_file' ? document.getElementById('selfieBox')?.parentElement : null);
      if (!root) return;
      if (field) field.required = false;
      if (!config.onboarding) root.querySelectorAll('button').forEach(button => button.disabled = true);
      const link = document.createElement('a'); link.href=url;link.target='_blank';link.rel='noopener';link.textContent='View current document';link.className='field-hint';root.append(link);
    });
    ['tcCheckbox'].forEach(id => { const field=document.getElementById(id); if(field)field.parentElement.style.display='none'; });
    form.querySelectorAll('[title="Change account type"]').forEach(link => link.remove());
    document.querySelectorAll('.auth-title').forEach(title => title.textContent='Edit account');
    document.querySelectorAll('.registration-intro-full,.registration-intro-compact').forEach(intro => intro.textContent='Update account details. Existing documents are kept unless replaced.');
    const stages = document.querySelectorAll('.registration-stage-list li');
    if(stages.length)stages[stages.length-1].querySelector('div>span').textContent='Update your username and optionally change the password.';
    form.querySelector('.btn-submit').textContent='Save changes';
    const addressNames = ['region','province','municipality','barangay'];
    addressNames.forEach(name => {
      const select = form.elements[name]; if(!select || !config.values[name])return;
      let completed=false;
      const fill = () => {
        if(completed)return;
        const option=[...select.options].find(option=>option.value===config.values[name]);
        if(!option || select.disabled)return;
        completed=true;observer.disconnect();select.value=option.value;select.dispatchEvent(new Event('change',{bubbles:true}));
        if(name==='barangay'){restoreHub();}
      };
      const observer=new MutationObserver(fill);observer.observe(select,{childList:true,attributes:true});fill();
      setTimeout(()=>{observer.disconnect();if(completed)return;select.append(new Option(config.values[name],config.values[name],true,true));select.disabled=false;if(name==='barangay'){restoreHub();}},15000);
    });
    restoreHub();
  });
})();
