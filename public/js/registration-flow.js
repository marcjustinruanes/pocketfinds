(() => {
    'use strict';
    const stage = (panels, title, detail) => ({ panels, title: title.replace(/\b[a-z]/g, letter => letter.toUpperCase()), detail });
    function registrationStages(role, google = false) {
        const identity = stage([2], 'Identity Verification', 'Upload a valid ID and take a clear selfie.');
        const personal = stage([3], 'Personal details', 'Enter your legal name, date of birth, and other personal details.');
        const contact = stage([4], 'Contact details', 'Verify your email and enter your phone number.');
        const address = stage([5], 'Address', 'Select your location and complete your street address.');
        const account = stage([6], 'Account & confirmation', 'Choose a username and password, then read and accept the terms.');
        let stages;
        if (role === 'rider') stages = [address, stage([9], 'Company & hub', 'Choose an available hiring hub'), stage([2], 'Rider documents', 'License, clearance and selfie'), stage([7], 'Vehicle Details', 'Choose your vehicle type and ownership.'), personal, contact, account];
        else if (role === 'logistics-staff') stages = [address, stage([8], 'Company & hub', 'Choose where you want to work'), stage([2], 'Identity Verification', 'Upload your ID, take a selfie, and attach an optional PDF resume.'), personal, contact, account];
        else if (role === 'logistics') stages = [stage([7], 'Company Details', 'Register your company and upload its documents.'), stage([5], 'Company Address', 'Enter your company’s registered address.'), stage([8], 'Service areas', 'Choose your coverage locations and hubs.'), stage([3, 2], 'Representative Details', 'Enter personal and contact details, then verify your representative’s identity.'), stage([4], 'Company Contact Details', 'Verify the company email used for sign in and enter its contact number.'), account];
        else stages = role === 'seller' ? [stage([1], 'Shop Details', 'Enter your shop name and choose a product category.'), stage([10], 'Business Details', 'Select your seller type and upload the required documents.'), identity, personal, contact, address, account] : [identity, personal, contact, address, account];
        return google ? stages.map(item => ({ ...item, panels: item.panels.map(id => id - 1) })) : stages;
    }
    function advancePanels(panels, activate, advance, stillActive) {
        for (const id of panels) {
            activate(id);
            advance(id);
            if (stillActive(id)) return false;
        }
        return true;
    }
    if (typeof module !== 'undefined') module.exports = { registrationStages, advancePanels };
    if (typeof document === 'undefined') return;
    const form = document.getElementById('buyerForm') || document.getElementById('googleRegForm');
    const legacyIndicator = document.getElementById('stepIndicator');
    const sidebar = document.querySelector('.registration-progress-panel');
    if (!form || !sidebar || !legacyIndicator || typeof window.nextStep !== 'function') return;
    const staff = document.getElementById('joinCompanyGrid');
    const role = staff ? 'logistics-staff' : form.querySelector('[name="account_type"]').value;
    if(role==='seller') {
        const shop=document.getElementById(form.id==='googleRegForm'?'panel-0':'panel-1');
        const business=document.createElement('div');business.className='step-panel';business.id=form.id==='googleRegForm'?'panel-9':'panel-10';
        Array.from(shop.children).filter(child=>child.querySelector('.auth-title') || child.querySelector('a[title="Change account type"]')).forEach(child=>business.append(child.cloneNode(true)));
        const grid=document.createElement('div');grid.className='auth-form-grid';
        ['seller_type','tin'].forEach(id=>{const field=document.getElementById(id)?.closest('.auth-field');if(field)grid.append(field);});
        business.append(grid);
        const documents=shop.querySelector('.seller-document-fields');if(documents)business.append(documents);
        shop.after(business);
        const shopName=document.getElementById('business_name')?.closest('.auth-field');
        const shopLogo=document.getElementById('shop_logo')?.closest('.auth-field');
        if(shopName&&shopLogo){
            const row=document.createElement('div');row.className='registration-shop-row';shopName.before(row);
            shopName.classList.remove('full');shopLogo.classList.remove('full');row.append(shopName,shopLogo);
        }
        const identityPanel=document.getElementById(form.id==='googleRegForm'?'panel-1':'panel-2');
        const syncDocuments=()=>{
            const label=document.getElementById('idPhotoBox').parentElement.querySelector(':scope > label');
            if(label?.firstChild)label.firstChild.textContent=document.getElementById('seller_type').value==='corporation'?'Representative government-issued ID ':'Government-issued ID ';
        };
        document.getElementById('seller_type').addEventListener('change',syncDocuments);syncDocuments();
    }
    const stages = registrationStages(role, form.id === 'googleRegForm');
    if (stages.some(item => item.panels.some(id => !document.getElementById('panel-' + id)))) return;
    const originalNext = window.nextStep;
    const personalGrid = document.getElementById('middle_name')?.closest('.auth-form-grid');
    personalGrid?.classList.add('registration-personal-grid');
    if (personalGrid) {
        const field = id => document.getElementById(id)?.closest('.auth-field');
        const given = field('given_names');
        const names = document.createElement('div'); names.className='registration-name-row';
        const details = document.createElement('div'); details.className='registration-birthday-row';
        ['middle_name','last_name','suffix'].forEach(id=>{const item=field(id);if(item)names.append(item);});
        ['sex','birthday','age'].forEach(id=>{const item=field(id);if(item)details.append(item);});
        if(given) personalGrid.append(given);
        personalGrid.append(names,details);
        const ageInput=document.getElementById('age');
        const ageField=field('age');
        if(ageInput && ageField) {
            ageInput.type='hidden'; ageInput.className='';
            const capsule=document.createElement('span'); capsule.className='registration-age-capsule';
            capsule.setAttribute('role','status'); capsule.setAttribute('aria-live','polite');
            ageField.replaceChildren(ageInput,capsule); ageField.classList.add('registration-age-field');
            const updateAge=()=>{
                const birthday=document.getElementById('birthday').value;
                const date=new Date(birthday+'T00:00:00'); const today=new Date();
                let age=today.getFullYear()-date.getFullYear();
                if(today.getMonth()<date.getMonth() || (today.getMonth()===date.getMonth() && today.getDate()<date.getDate())) age--;
                const valid=birthday && !Number.isNaN(date.getTime()) && age>=0 && date<=today;
                ageInput.value=valid?String(age):'';
                capsule.textContent=valid?'Age: '+age:'Age: —';
            };
            document.getElementById('birthday').addEventListener('change',updateAge);
            document.getElementById('birthday').addEventListener('input',updateAge);
            updateAge();
        }
    }
    const emailField = document.getElementById('email')?.closest('.auth-field');
    const regionField=document.getElementById('region')?.closest('.auth-field');
    const addressGrid=regionField?.closest('.address-grid, .auth-form-grid');
    if(addressGrid) {
        const addRow=(ids,className)=>{
            const fields=ids.map(id=>document.getElementById(id)?.closest('.auth-field')).filter(Boolean);
            if(!fields.length) return;
            const row=document.createElement('div');row.className=className;
            fields[0].before(row); fields.forEach(field=>{field.classList.remove('full');row.append(field);});
        };
        addRow(['region','province','municipality'],'registration-region-row');
        const postal=document.getElementById('registrationPostalField');
        if(postal) document.getElementById('barangay').closest('.auth-field').after(postal);
        addRow(['barangay','postal_code'],'registration-barangay-row');
        addRow(['house_no','street'],'registration-street-row');
    }
    const otpGroup = document.getElementById('otpField');
    const emailHint = document.getElementById('emailHint');
    if (emailField && otpGroup && emailHint) {
        emailHint.after(otpGroup);
        otpGroup.classList.remove('full');
        otpGroup.classList.add('registration-code-group');
    }
    let current = 0;
    const allPanels = [...form.querySelectorAll('.step-panel')];
    legacyIndicator.hidden = true;
    legacyIndicator.setAttribute('aria-hidden', 'true');
    document.body.classList.add('registration-flow');

    const logo = sidebar.querySelector('.registration-progress-logo');
    const intro = document.createElement('p');
    intro.className = 'registration-progress-intro';
    const introLines = ({
        buyer: ['Explore local shops and save favourites', 'while tracking orders in one account.'],
        seller: ['Reach local buyers through your shop', 'and manage your products and orders.'],
        rider: ['Join a logistics company to deliver', 'orders through a hub near your home.'],
        logistics: ['Register your logistics company to', 'manage hubs, riders, and deliveries.'],
        'logistics-staff': ['Join a local logistics hub to sort', 'parcels and coordinate dispatch.'],
    })[role];
    const fullIntro = document.createElement('span');
    fullIntro.className = 'registration-intro-full';
    fullIntro.textContent = ({
        buyer: 'Create your buyer account to explore products from local shops, save your favourites, and track your purchases from checkout to delivery.',
        seller: 'Create your seller account to open a local shop, list products, connect with buyers, and manage your orders and inventory in one place.',
        rider: 'Apply as a rider with a logistics company, choose a hiring hub near your address, and manage the pickup and delivery of customer orders.',
        logistics: 'Register your logistics company to define service areas, organise your hubs, manage staff and riders, and coordinate parcel deliveries.',
        'logistics-staff': 'Apply as hub staff with a logistics company near your address to help receive, sort, and dispatch parcels through your assigned hub.',
    })[role];
    const compactIntro = document.createElement('span');
    compactIntro.className = 'registration-intro-compact';
    compactIntro.textContent = introLines.join(' ');
    intro.append(fullIntro, compactIntro);
    const introAnchor = logo;
    if (introAnchor) introAnchor.insertAdjacentElement('afterend', intro);
    else sidebar.prepend(intro);
    const stageIcons = {
        identity: '<rect x="2" y="4" width="20" height="16" rx="3"/><circle cx="8" cy="10" r="2"/><path d="M5 16v-1a3 3 0 0 1 6 0v1M14 9h5M14 13h5M14 17h3"/>',
        contact: '<path d="M7 3H4a1 1 0 0 0-1 1c0 9.4 7.6 17 17 17a1 1 0 0 0 1-1v-3l-5-2-2 2a14 14 0 0 1-7-7l2-2-2-5z"/><rect x="12" y="3" width="10" height="7" rx="1"/><path d="m12 4 5 3 5-3"/>',
        document: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        person: '<circle cx="12" cy="7" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/>',
        address: '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>',
        shop: '<path d="M3 10v11h18V10M2 10l2-7h16l2 7M2 10a3 3 0 0 0 5 2 3 3 0 0 0 5 0 3 3 0 0 0 5 0 3 3 0 0 0 5-2M9 21v-7h6v7"/>',
        vehicle: '<path d="M1 4h14v12H1zM15 9h4l4 4v3h-8"/><circle cx="5" cy="18" r="3"/><circle cx="19" cy="18" r="3"/>',
        account: '<rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 15v3"/>',
        coverage: '<path d="m9 18-6 3V6l6-3 6 3 6-3v15l-6 3-6-3zM9 3v15M15 6v15"/>',
        check: '<path d="m5 12 4 4L19 6"/>',
    };
    const iconMarkup = key => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + stageIcons[key] + '</svg>';
    const iconForStage = item => item.title.startsWith('Identity') || item.title === 'Rider Documents' ? 'identity' : item.title.includes('Contact') ? 'contact' : item.title.startsWith('Account') ? 'account' : item.title.includes('Personal') || item.title.includes('Representative') ? 'person' : item.title.includes('Address') ? 'address' : item.title.includes('Vehicle') ? 'vehicle' : item.title === 'Service Areas' ? 'coverage' : item.title.includes('Company') || item.title.includes('Shop') ? 'shop' : 'document';
    const list = document.createElement('ol');
    list.className = 'registration-stage-list';
    stages.forEach((item, index) => {
        const row = document.createElement('li');
        const number = document.createElement('span');
        number.className = 'registration-stage-number';
        number.dataset.icon = iconForStage(item);
        number.innerHTML = iconMarkup(number.dataset.icon);
        number.setAttribute('aria-hidden', 'true');
        row.setAttribute('aria-label', 'Step ' + (index + 1) + ': ' + item.title);
        const copy = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = item.title;
        const detail = document.createElement('span');
        detail.textContent = item.detail;
        copy.append(title, detail);
        row.append(number, copy);
        list.append(row);
    });
    sidebar.append(list);
    const progressBody = document.createElement('div');
    progressBody.className = 'registration-progress-body';
    progressBody.append(intro, list);
    sidebar.append(progressBody);
    const stepHeading = document.createElement('header');
    stepHeading.className = 'registration-stage-heading';
    stepHeading.setAttribute('aria-live', 'polite');
    form.prepend(stepHeading);
    const nav = document.createElement('div');
    nav.className = 'registration-stage-nav';
    const back = document.createElement('button');
    back.type = 'button'; back.className = 'btn-prev'; back.textContent = 'Back';
    const next = document.createElement('button');
    next.type = 'button'; next.className = 'btn-next'; next.textContent = 'Continue';
    const submit = form.querySelector('.btn-submit');
    nav.append(back, next);
    if (submit) nav.append(submit);
    form.append(nav);

    // Group related panels in DOM order without recreating their inputs or uploads.
    stages.forEach(item => item.panels.forEach(id => form.insertBefore(document.getElementById('panel-' + id), nav)));
    const titles = { 1: 'Shop details', 2: role === 'rider' ? 'License, clearance & selfie' : 'ID & selfie', 3: role === 'logistics' ? 'Contact person' : 'Personal information', 4: 'Email & phone', 5: role === 'logistics' ? 'Company address' : 'Address', 6: 'Sign-in details', 7: role === 'rider' ? 'Vehicle details' : 'Company registration', 8: role === 'logistics' ? 'Coverage areas' : 'Company & hub', 9: 'Company & hub', 10:'Business details' };
    allPanels.forEach(panel => {
        const id = Number(panel.id.replace('panel-', '')) + (form.id === 'googleRegForm' ? 1 : 0);
        const title = panel.querySelector('.auth-title');
        if (title && titles[id]) title.textContent = ['rider', 'logistics-staff'].includes(role) ? 'Complete your application' : 'Create your account';
        if (title) {
            const header = title.parentElement;
            const capsule = header.querySelector(':scope > span');
            if(capsule && ['seller','logistics','logistics-staff'].includes(role)) {
                const svg=capsule.querySelector('svg');
                if(svg){
                    svg.innerHTML=role==='seller'?stageIcons.shop:'<path d="M3 21V8l9-5 9 5v13M2 21h20M7 21V11h10v10M7 14h10M7 17h10"/>';
                    svg.setAttribute('stroke-width','1.8');svg.setAttribute('aria-hidden','true');
                }
            }
            let returnLink = panel.querySelector('a[title="Change account type"]');
            if (!returnLink) {
                const existing = form.querySelector('a[title="Change account type"]');
                if (existing) returnLink = existing.cloneNode(true);
            }
            const controls = document.createElement('div');
            header.classList.add('registration-panel-header');
            controls.className = 'registration-header-controls';
            if (capsule) controls.append(capsule);
            if (returnLink) controls.append(returnLink);
            header.append(controls);
        }
    });
    function activateOnly(id) { allPanels.forEach(panel => panel.classList.toggle('active', panel.id === 'panel-' + id)); }
    function show(index, scroll = true) {
        if (index !== current) {
            if (typeof window.stopCamera === 'function') window.stopCamera();
            if (typeof idCameraStream !== 'undefined' && idCameraStream) {
                idCameraStream.getTracks().forEach(track => track.stop());
                idCameraStream = null;
            }
        }
        current = index;
        const item = stages[index];
        allPanels.forEach(panel => panel.classList.toggle('active', item.panels.includes(Number(panel.id.replace('panel-', '')))));
        stepHeading.replaceChildren();
        const count = document.createElement('span'); count.textContent = 'Step ' + (index + 1) + ' of ' + stages.length;
        const title = document.createElement('h2'); title.textContent = item.title;
        stepHeading.append(count, title);
        [...list.children].forEach((row, i) => {
            row.classList.toggle('active', i === index);
            row.classList.toggle('done', i < index);
            const icon = row.querySelector('.registration-stage-number');
            icon.innerHTML = iconMarkup(icon.dataset.icon);
            if (i === index) row.setAttribute('aria-current', 'step'); else row.removeAttribute('aria-current');
        });
        back.hidden = index === 0;
        next.hidden = index === stages.length - 1;
        if (submit) submit.hidden = index !== stages.length - 1;
        if (scroll) window.scrollTo({ top: 0, behavior: 'instant' });
    }
    const identityFields=['given_names','last_name','middle_name','suffix','birthday','sex'];
    const identityInput=document.getElementById('given_names');
    let identityTimer,identityError=false,identityRequest=null,identityResult=null;
    const identityData=()=>{
        const data=Object.fromEntries(identityFields.map(id=>[id,document.getElementById(id)?.value.trim() || '']));
        data.no_middle_name=!!document.getElementById('no_middle_name')?.checked;
        if(data.no_middle_name)data.middle_name='';
        return data;
    };
    const identityKey=()=>JSON.stringify(identityData());
    async function checkIdentity() {
        const data=identityData(),key=JSON.stringify(data);
        if(!data.given_names || !data.last_name || !data.birthday || !data.sex || (!data.middle_name&&!data.no_middle_name))return true;
        if(identityResult?.key===key){
            if(!identityResult.valid){identityError=true;window.showError(identityInput,'An account with these personal details is already registered.');}
            return identityResult.valid;
        }
        if(identityRequest?.key===key)return identityRequest.promise;
        const promise=(async()=>{
            try {
                const response=await fetch('/register/check-identity',{
                    method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},
                    body:key,signal:AbortSignal.timeout(8000),
                });
                if(key!==identityKey())return false;
                if(!response.ok)throw new Error('Identity check failed');
                const result=await response.json();
                if(key!==identityKey())return false;
                identityResult={key,valid:!result.duplicate};
                if(result.duplicate){identityError=true;window.showError(identityInput,'An account with these personal details is already registered.');}
                else if(identityError){window.clearError(identityInput);identityError=false;}
                return !result.duplicate;
            } catch(error) {
                if(key===identityKey()){identityError=true;window.showError(identityInput,'Could not check your details. Please try Continue again.');}
                return false;
            } finally { if(identityRequest?.key===key)identityRequest=null; }
        })();
        identityRequest={key,promise};return promise;
    }
    [...identityFields,'no_middle_name'].forEach(id=>{
        const input=document.getElementById(id);
        ['input','change'].forEach(event=>input?.addEventListener(event,()=>{
            clearTimeout(identityTimer);identityResult=null;
            if(identityError){window.clearError(identityInput);identityError=false;}
            identityTimer=setTimeout(()=>{if(stages[current].panels.includes(form.id==='googleRegForm'?2:3))checkIdentity();},500);
        }));
    });
    let continuing=false;
    async function continueStage() {
        if (current >= stages.length - 1) return;
        if(continuing)return;
        const started=current;
        if(window.checkRegistrationBusinessNumbers){
            continuing=true;next.disabled=true;
            let validNumbers;
            try { validNumbers=await window.checkRegistrationBusinessNumbers(stages[current].panels); }
            finally { continuing=false;next.disabled=false; }
            if(current!==started||!validNumbers)return;
        }
        if(stages[current].panels.includes(form.id==='googleRegForm'?2:3)){
            continuing=true;next.disabled=true;
            let available;
            try { available=await checkIdentity(); }
            finally { continuing=false;next.disabled=false; }
            if(current!==started)return;
            if(!available){identityInput.focus();return;}
        }
        const valid = advancePanels(stages[current].panels, activateOnly, originalNext, id => document.getElementById('panel-' + id).classList.contains('active'));
        if (valid) show(current + 1);
        else {
            show(current, false);
            form.querySelector('.step-panel.active .auth-input.error')?.focus();
        }
    }
    next.addEventListener('click', continueStage);
    back.addEventListener('click', () => { if (current > 0) show(current - 1); });
    // Existing upload and role-specific controls can still call the shared navigation.
    window.nextStep = continueStage;
    window.prevStep = () => { if (current > 0) show(current - 1); };
    window.registrationFlow = { showPanel: id => { const index = stages.findIndex(item => item.panels.includes(id)); if (index >= 0) show(index, false); }, role };
    form.addEventListener('submit', event => {
        if (current === stages.length - 1) return;
        event.preventDefault(); event.stopImmediatePropagation(); continueStage();
    }, true);
    form.addEventListener('registration:saved', () => { sidebar.hidden = true; });
    // Make the outlined surface the upload control, retaining preview actions.
    const uploadBoxes = new Set(form.querySelectorAll('.document-upload, .upload-box, #idPhotoBox, #selfieBox, #businessPermitBox'));
    const selfieIdle = document.getElementById('selfieIdle');
    if (selfieIdle) uploadBoxes.add(selfieIdle.parentElement);
    uploadBoxes.forEach(box => {
        const selfie = box.contains(selfieIdle);
        const inputId = ({idPhotoBox:'id_file',businessPermitBox:'business_permit_file',licenseBox:'license_file',orBox:'or_file',crBox:'cr_file'})[box.id];
        const label = box.querySelector('label[for]');
        const input = document.getElementById(inputId || label?.htmlFor) || box.querySelector('input[type="file"]');
        if (!selfie && !input) return;
        if(input) {
            input.classList.add('registration-upload-file');input.tabIndex=-1;box.append(input);
        }
        box.classList.add('registration-upload-surface');
        box.setAttribute('role','button'); box.tabIndex=0;
        box.setAttribute('aria-label',selfie ? 'Take a selfie' : 'Upload or replace '+(input.name || 'document').replaceAll('_',' '));
        box.onclick = null;
        box.querySelectorAll('label[for], [id$="Idle"] button').forEach(control=>control.hidden=true);
        const idle=box.querySelector('[id$="Idle"]');
        if (idle) {
            const controls=Array.from(idle.querySelectorAll('button,label[for]'));
            const icon=document.createElementNS('http://www.w3.org/2000/svg','svg');
            icon.setAttribute('viewBox','0 0 24 24'); icon.setAttribute('fill','none'); icon.setAttribute('stroke','currentColor');
            icon.setAttribute('stroke-width','1.6'); icon.setAttribute('stroke-linecap','round'); icon.setAttribute('stroke-linejoin','round'); icon.setAttribute('aria-hidden','true');
            icon.innerHTML=selfie ? '<path d="M4 6h4l2-3h4l2 3h4a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/><circle cx="12" cy="13" r="4"/>' : '<path d="M12 16V3m-5 5 5-5 5 5M3 15v5a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-5"/>';
            const caption=document.createElement('span'); caption.className='registration-upload-label';
            caption.textContent=selfie ? 'Take Selfie' : 'Upload Document';
            const note=document.createElement('span'); note.className='registration-upload-note';
            note.textContent=selfie ? 'Take a clear photo with your camera' : 'JPG, PNG or PDF · max 5 MB';
            idle.replaceChildren(icon,caption,note,...controls); idle.classList.add('registration-upload-idle');
        }
        if (!selfie) box.querySelectorAll('[id$="Preview"] button:not(.enlarge-btn)').forEach(button=>{
            button.innerHTML='<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5M6.1 7a7 7 0 0 1 11.6-2L20 8M4 16l2.3 3A7 7 0 0 0 17.9 17"/></svg>'; button.setAttribute('aria-label','Reupload document');button.title='Reupload';
            button.onclick=event=>{event.stopPropagation();if(!input.disabled)input.click();};
        });
        const activate = () => {
            if (getComputedStyle(box).pointerEvents === 'none' || input?.disabled) return;
            if (selfie) {
                const camera = document.getElementById('selfieCamera');
                if (camera && getComputedStyle(camera).display !== 'none') return;
                if (!document.getElementById('openCameraBtn')?.disabled) window.startCamera();
            } else input.click();
        };
        box.addEventListener('click',event=>{ if(!event.target.closest('button,a,input,label,video')) activate(); });
        box.addEventListener('keydown',event=>{ if(event.target===box && ['Enter',' '].includes(event.key)){ event.preventDefault(); activate(); } });
    });
    // Use the same input + floating label + notched fieldset as the sign-in form.
    form.querySelectorAll('input.auth-input, select.auth-input, textarea.auth-input').forEach((input,index)=>{
        if (['hidden','file','checkbox','radio','button','submit'].includes(input.type) || input.closest('.registration-floating-field')) return;
        if (!input.id) input.id='registration-field-'+index;
        let original=Array.from(form.querySelectorAll('label')).find(label=>label.htmlFor===input.id);
        original ||= input.closest('label');
        original ||= input.closest('.auth-field')?.querySelector(':scope > label.auth-label:not([for])');
        if (!original) return;
        const copy=original.cloneNode(true);
        copy.querySelectorAll('input,select,textarea,button,small,.field-hint').forEach(node=>node.remove());
        copy.querySelectorAll('.auth-required').forEach(node=>node.remove());
        const text=copy.textContent.replace(/\*/g,'').replace(/\s+/g,' ').trim();
        if(!text) return;
        if (original.contains(input)) {
            const field=document.createElement('div');field.className='auth-field';original.before(field);
            Array.from(original.children).filter(node=>node===input || node.matches('small,.field-hint,.auth-input-wrap')).forEach(node=>field.append(node));
        }
        let wrap=input.parentElement.matches('.auth-input-wrap, .registration-mobile') ? input.parentElement : null;
        if(!wrap){wrap=document.createElement('div');wrap.className='auth-input-wrap';input.before(wrap);wrap.append(input);}
        wrap.classList.add('auth-floating-field','registration-floating-field');
        const label=document.createElement('label');label.className='auth-label';label.htmlFor=input.id;label.innerHTML=copy.innerHTML.replace(/\*/g,'');
        const outline=document.createElement('fieldset');outline.className='auth-field-outline';outline.setAttribute('aria-hidden','true');
        const legend=document.createElement('legend');const span=document.createElement('span');span.textContent=text;legend.append(span);outline.append(legend);
        input.after(label,outline);original.remove();
        if(input.type==='password') {
            input.classList.add('has-toggle');
            const toggle=document.createElement('button');toggle.type='button';toggle.className='auth-password-toggle auth-password-icon';
            toggle.dataset.passwordToggle=input.id;toggle.dataset.passwordIcon='';
            toggle.setAttribute('aria-label','Show password');toggle.setAttribute('aria-controls',input.id);toggle.setAttribute('aria-pressed','false');
            toggle.innerHTML='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="password-eye-slash" d="m4 4 16 16"/></svg>';
            wrap.append(toggle);
        }
        if(input.tagName!=='SELECT') input.placeholder=' ';
        if(['date','time','datetime-local'].includes(input.type)) wrap.classList.add('registration-floating-native');
        if(input.tagName==='SELECT' || input.id==='postal_code') wrap.classList.add('registration-floating-native');
    });
    const passwordField=document.getElementById('password')?.closest('.auth-field');
    const confirmationField=document.getElementById('password_confirmation')?.closest('.auth-field');
    const requirements=document.getElementById('registrationPasswordRequirements');
    if(passwordField && confirmationField && requirements) {
        const group=document.createElement('div');group.className='registration-password-group';
        const fields=document.createElement('div');fields.className='registration-password-fields';
        passwordField.before(group);
        fields.append(passwordField,confirmationField);group.append(fields,requirements);
        passwordField.classList.remove('full');confirmationField.classList.remove('full');
    }
    const companyCertificates=document.querySelector('#businessPermitField .logistics-document-fields');
    if(companyCertificates) document.getElementById('businessPermitField').after(companyCertificates);
    const secNumber=document.getElementById('sec_registration_no');
    secNumber?.addEventListener('input',()=>{
        const raw=secNumber.value.toUpperCase().replace(/[^A-Z0-9]/g,'');
        const letters=(raw.match(/^[A-Z]{0,2}/)||[''])[0];
        secNumber.value=letters+(letters.length===2?raw.slice(letters.length).replace(/\D/g,'').slice(0,9):'');
    },true);
    const compactFiles=[
        ['nbi_clearance_file','NBI clearance','nbiError'],['resume_file','Resume / CV','resumeFileError'],
        ['or_file','Official Receipt (OR)','orError'],['cr_file','Certificate of Registration (CR)','crError'],['shop_logo','Shop logo (optional)',null],
        ['logo_file','Company logo (optional)',null],['business_permit_file','DTI Permit','businessPermitError'],
        ['sec_certificate_file','SEC Certificate',null],['bir_certificate_file','BIR Certificate of Registration (Form 2303)',null],
    ];
    compactFiles.forEach(([id,title,errorId])=>{
        const input=document.getElementById(id);if(!input)return;
        const root=id==='resume_file'?document.getElementById('resumeCard'):id==='business_permit_file'?document.getElementById('businessPermitField'):id==='logo_file'?document.getElementById('logoBox').parentElement:id==='or_file'||id==='cr_file'?document.getElementById(id==='or_file'?'orBox':'crBox')?.parentElement:input.closest('.resume-upload-card,.auth-field');
        if(!root)return;
        const pdfOnly=id==='resume_file',logo=id==='shop_logo'||id==='logo_file';
        const legacy=document.createElement('div');legacy.hidden=true;
        const error=document.getElementById(errorId)||document.createElement('span');error.setAttribute('role','alert');error.style.cssText='display:none;color:#b91c1c;font-size:11px;line-height:1.4';
        while(root.firstChild)legacy.append(root.firstChild);
        root.classList.remove('resume-upload-card');root.classList.add('auth-field','registration-compact-upload');
        if(id==='or_file'||id==='cr_file')root.parentElement.classList.add('registration-compact-document-row');
        const box=document.createElement('fieldset');box.className='seller-upload-box';
        box.innerHTML='<legend></legend><span class="seller-upload-idle" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V3m-5 5 5-5 5 5M3 15v5a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-5"/></svg><span>Upload Document</span></span><img class="seller-upload-preview" hidden><svg class="seller-upload-pdf-icon" hidden viewBox="0 0 24 28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 1h10l6 6v19H4zM14 1v7h6M7 13h10M7 17h7"/><text x="6" y="23" fill="currentColor" stroke="none" font-size="6" font-family="sans-serif" font-weight="700">PDF</text></svg><span class="seller-upload-pdf" hidden></span><button type="button" class="seller-upload-enlarge" hidden><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7M21 3l-8 8M10 21H3v-7M3 21l8-8"/></svg></button><button type="button" class="seller-upload-replace" hidden><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5M6.1 7a7 7 0 0 1 11.6-2L20 8M4 16l2.3 3A7 7 0 0 0 17.9 17"/></svg></button>';
        box.querySelector('legend').textContent=title;input.setAttribute('aria-label',title);input.style.cssText='';input.classList.remove('auth-input','registration-upload-file');
        input.accept=pdfOnly?'.pdf,application/pdf':logo?'.jpg,.jpeg,.png,.webp':'.jpg,.jpeg,.png,.pdf';box.append(input);
        const note=document.createElement('span');note.className='field-hint seller-upload-note';note.textContent=(pdfOnly?'PDF':logo?'JPG, PNG or WEBP':'JPG, PNG or PDF')+' · maximum 5 MB';
        root.append(box,note,error,legacy);
        const image=box.querySelector('img'),filename=box.querySelector('.seller-upload-pdf'),pdfIcon=box.querySelector('.seller-upload-pdf-icon'),idle=box.querySelector('.seller-upload-idle');
        image.id='compact_'+id+'_preview';image.alt=title+' preview';
        const enlarge=box.querySelector('.seller-upload-enlarge'),replace=box.querySelector('.seller-upload-replace');
        enlarge.setAttribute('aria-label','Enlarge '+title);replace.setAttribute('aria-label','Reupload '+title);replace.title='Reupload';
        let url=null;
        const render=()=>{
            if(url){URL.revokeObjectURL(url);url=null;}
            const file=input.files[0],isPdf=/\.pdf$/i.test(file?.name||'');
            idle.hidden=!!file;image.hidden=!file||isPdf;pdfIcon.toggleAttribute('hidden',!file||!isPdf);filename.hidden=enlarge.hidden=replace.hidden=!file;
            if(!file){image.removeAttribute('src');return;}
            url=URL.createObjectURL(file);if(!isPdf)image.src=url;
            filename.textContent=file.name;filename.title=file.name;filename.classList.toggle('seller-upload-image-name',!isPdf);
            enlarge.onclick=()=>isPdf?window.showPdfLightbox(url):window.openLightbox(image.id);
        };
        replace.addEventListener('click',()=>input.click());
        input.addEventListener('change',event=>{
            const file=input.files[0],allowed=pdfOnly?/\.pdf$/i:logo?/\.(jpe?g|png|webp)$/i:/\.(jpe?g|png|pdf)$/i;
            const maxBytes=5*1024*1024;
            if(file&&(file.size>maxBytes||!allowed.test(file.name)||(pdfOnly&&file.type&&file.type!=='application/pdf'))){
                event.stopImmediatePropagation();
                if(id==='or_file'||id==='cr_file')window.clearUpload(id==='or_file'?'or':'cr');
                if(pdfOnly)window.removeResumeFile();input.value='';
                error.textContent=file.size>maxBytes?'Maximum file size is 5 MB.':pdfOnly?'Please upload your resume as a PDF.':'Choose a supported image or PDF file.';error.style.display='block';render();return;
            }
            error.style.display='none';render();
        },true);
        render();
    });
    if(role==='logistics') {
        const panel=document.getElementById('panel-7'),nav=panel.querySelector('.step-nav');
        const name=document.getElementById('business_name').closest('.auth-field'),logo=document.getElementById('logo_file').closest('.registration-compact-upload');
        const row=document.createElement('div');row.className='registration-shop-row';row.append(name,logo);
        const numbers=document.createElement('div');numbers.className='auth-form-grid registration-company-numbers';
        const tin=document.getElementById('tin').closest('.auth-field'),sec=secNumber.closest('.auth-field');
        tin.querySelectorAll('.field-hint').forEach(note=>note.remove());sec.querySelectorAll('.field-hint').forEach(note=>note.remove());numbers.append(tin,sec);
        const docs=document.createElement('div');docs.className='registration-company-documents';
        ['business_permit_file','sec_certificate_file','bir_certificate_file'].forEach(id=>docs.append(document.getElementById(id).closest('.registration-compact-upload')));
        nav.before(row,numbers,docs);
    }
    show(0, false);
})();
