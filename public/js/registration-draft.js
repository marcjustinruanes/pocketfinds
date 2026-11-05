// Preserve this tab's registration only while it leaves for Google verification.
// Passwords and CSRF tokens are deliberately excluded. Files stay on this device.
(() => {
    const form = document.getElementById('buyerForm') || document.getElementById('googleRegForm');
    if (!form) return;
    const key = 'pocketfinds-registration-google-draft';
    let tab = sessionStorage.getItem(key);
    if (!tab) { tab = crypto.randomUUID(); sessionStorage.setItem(key, tab); }
    const open = () => new Promise((resolve, reject) => {
        const request = indexedDB.open('pocketfinds-registration', 1);
        request.onupgradeneeded = () => request.result.createObjectStore('drafts');
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(new Error('Unable to preserve your registration. Please try again.'));
    });
    async function access(mode, action) {
        const db = await open();
        try { return await new Promise((resolve, reject) => {
            const tx = db.transaction('drafts', mode);
            const request = action(tx.objectStore('drafts'));
            tx.oncomplete = () => resolve(request.result);
            tx.onerror = tx.onabort = () => reject(new Error('Unable to preserve your registration. Please try again.'));
        }); } finally { db.close(); }
    }
    const fields = () => Array.from(form.elements).filter(el => el.name && !['_token','google_id','auth_method'].includes(el.name) && el.type !== 'password' && !['submit','button'].includes(el.type));
    const snapshot = el => ({id:el.id, name:el.name, type:el.type, value:el.type === 'file' ? '' : el.value, checked:el.checked, files:el.type === 'file' ? Array.from(el.files) : []});
    window.registrationDraft = {async save() {
        const draft = {path:location.pathname, expires:Date.now()+30*60*1000, fields:fields().map(snapshot),
            uploads:{id:idPhotoBlob, selfie:selfieBlob, permit:businessPermitBlob, ...docBlobs},
            category:form.querySelector('#categoryGrid .selected')?.dataset.categoryId || (form.querySelector('#categoryGrid .selected') ? 'other' : null),
            hub:typeof currentSelectedHubId !== 'undefined' ? currentSelectedHubId : null,
            areas:typeof hubAreas !== 'undefined' ? hubAreas : null};
        await access('readwrite', store => store.put(draft, tab));
    }};
    const pause = () => new Promise(resolve => setTimeout(resolve, 100));
    async function ready(test) { for (let i=0; i<150; i++) { if(test()) return true; await pause(); } return false; }
    const find = saved => saved.id ? document.getElementById(saved.id) : Array.from(form.elements).find(el => el.name === saved.name && (el.type !== 'radio' || el.value === saved.value));
    function fill(saved, notify = false) {
        const el = find(saved);
        if (!el || el.type === 'file') return;
        // A Google return supplies its own verified email; never restore a stale attempt.
        if (['email','postal_code','municipality_psgc_code'].includes(el.name)) return;
        if (el.type === 'checkbox' || el.type === 'radio') el.checked = saved.checked;
        else el.value = saved.value;
        if (notify) el.dispatchEvent(new Event('change', {bubbles:true}));
    }
    function preview(prefix, blob) {
        if (!blob) return;
        const idle = document.getElementById(prefix+'Idle'), panel = document.getElementById(prefix+'Preview'), img = document.getElementById(prefix+'Img');
        if (idle) idle.style.display='none';
        if (panel) panel.style.display='block';
        if (img && blob.type !== 'application/pdf') { img.src=URL.createObjectURL(blob); img.style.display=''; }
        else if (panel) {
            if(img) img.style.display='none';
            let card=panel.querySelector('.pdf-card');
            if(!card){card=document.createElement('div');card.className='pdf-card';panel.append(card);}
            card.style.cssText='display:flex;flex-direction:column;align-items:center;justify-content:center;';
            const label=document.createElement('span');label.textContent=blob.name||'PDF document';
            const badge=document.createElement('span');badge.textContent='PDF';card.replaceChildren(label,badge);
            const url=URL.createObjectURL(blob);
            const enlarge=panel.querySelector('.enlarge-btn');
            if(enlarge)enlarge.onclick=event=>{event.stopPropagation();showPdfLightbox(url);};
        }
    }
    async function restore() {
        const result=JSON.parse(document.getElementById('registrationGoogleResult').textContent);
        if(window.registrationEmailResetOnReload) return;
        window.applyRegistrationGoogleResult?.();
        if (!result.returning) return;
        window.registrationFlow?.showPanel(result.target==='representative'?3:4);
        try {
            const draft=await access('readonly', store=>store.get(tab));
            if (draft && draft.path === location.pathname && draft.expires > Date.now()) {
                draft.fields.forEach(saved=>fill(saved));
                for (const saved of draft.fields.filter(item=>find(item)?.tagName === 'SELECT')) {
                    const el=find(saved);
                    if (saved.value && await ready(()=>Array.from(el.options).some(option=>option.value === saved.value))) fill(saved,true);
                }
                draft.fields.filter(saved=>find(saved)?.tagName !== 'SELECT').forEach(saved=>fill(saved));
                document.getElementById('no_middle_name')?.dispatchEvent(new Event('change'));
                for (const saved of draft.fields.filter(item=>item.type==='file' && item.files.length)) {
                    const el=find(saved); if (!el) continue;
                    const transfer=new DataTransfer(); saved.files.forEach(file=>transfer.items.add(file)); el.files=transfer.files;
                    if (el.name !== 'id_file') el.dispatchEvent(new Event('change',{bubbles:true}));
                }
                idPhotoBlob=draft.uploads.id; selfieBlob=draft.uploads.selfie; businessPermitBlob=draft.uploads.permit;
                Object.keys(docBlobs).forEach(name=>docBlobs[name]=draft.uploads[name]);
                preview('idPhoto',idPhotoBlob); preview('selfie',selfieBlob); preview('businessPermit',businessPermitBlob);
                if (idPhotoBlob) { idPhotoUrl=URL.createObjectURL(idPhotoBlob); const box=document.getElementById('idPhotoBox'); if(box){box.style.opacity='1';box.style.pointerEvents='auto';} }
                const category = () => draft.category === 'other' ? Array.from(form.querySelectorAll('#categoryGrid .category-box')).find(el=>!el.dataset.categoryId) : form.querySelector(`[data-category-id="${CSS.escape(draft.category || '')}"]`);
                if (draft.category && await ready(category)) { category().click(); draft.fields.filter(item=>item.name === 'category_other' || item.id === 'category_other_input').forEach(saved=>fill(saved)); }
                if (draft.areas && typeof hubAreas !== 'undefined') { hubAreas=draft.areas; if(typeof renderHubAreaList==='function') renderHubAreaList(); if(typeof syncHubAreas==='function') syncHubAreas(); }
                if (draft.hub && typeof currentSelectedHubId !== 'undefined') { currentSelectedHubId=draft.hub; if(typeof updateSelectedHubPreview==='function') updateSelectedHubPreview(); }
                await access('readwrite',store=>store.delete(tab));
            } else if (draft) await access('readwrite',store=>store.delete(tab));
        } catch (error) { document.getElementById('googleEmailHint').textContent='Please check your details and upload any missing documents before continuing.'; }
        window.applyRegistrationGoogleResult?.();
        window.applyRepresentativeGoogleResult?.();
        window.registrationFlow?.showPanel(result.target==='representative'?3:4);
        history.replaceState(null,'',location.pathname);
    }
    form.addEventListener('registration:saved',()=>access('readwrite',store=>store.delete(tab)).catch(()=>{}));
    restore();
})();
