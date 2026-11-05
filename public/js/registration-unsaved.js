(() => {
    const forms = [...document.querySelectorAll('#buyerForm, #googleRegForm, form[data-registration-form]')];
    if (!forms.length) return;
    const dirty = new Set();
    const pending = new Set();
    forms.forEach(form => {
        const mark = () => dirty.add(form);
        form.addEventListener('input', mark);
        form.addEventListener('change', mark);
        form.addEventListener('registration:dirty', mark);
        if (form.dataset?.registrationDirty === 'true') mark();
        form.addEventListener('registration:saved', () => { dirty.delete(form); pending.delete(form); });
        form.addEventListener('registration:verification-navigation', () => pending.add(form));
        form.addEventListener('submit', event => {
            if (form.hasAttribute('data-registration-form')) queueMicrotask(() => {
                if (!event.defaultPrevented) pending.add(form);
            });
        });
    });
    const hasUnsaved = () => [...dirty].some(form => !pending.has(form));
    window.addEventListener('beforeunload', event => {
        if (!hasUnsaved()) return;
        event.preventDefault(); event.returnValue = '';
    });
    let modal=null, destination=null, previousFocus=null;
    function showLeaveDialog(url) {
        destination=url;previousFocus=document.activeElement;
        if(!modal) {
            modal=document.createElement('dialog');modal.className='registration-leave-dialog';
            modal.setAttribute('aria-labelledby','registrationLeaveTitle');modal.setAttribute('aria-describedby','registrationLeaveMessage');
            modal.innerHTML='<h2 id="registrationLeaveTitle">Leave registration?</h2><p id="registrationLeaveMessage">Your unsaved changes will be lost.</p><div class="registration-leave-actions"><button type="button" data-registration-stay autofocus>Keep editing</button><button type="button" data-registration-leave>Leave registration</button></div>';
            document.body.append(modal);
            modal.querySelector('[data-registration-stay]').addEventListener('click',()=>modal.close());
            modal.querySelector('[data-registration-leave]').addEventListener('click',()=>{const url=destination;dirty.clear();modal.close();window.location.assign(url);});
            modal.addEventListener('close',()=>previousFocus?.focus());
            modal.addEventListener('click',event=>{if(event.target===modal){const r=modal.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)modal.close();}});
        }
        if(!modal.open)modal.showModal();
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || !hasUnsaved() || link.target === '_blank' || link.hasAttribute('download') || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const target = new URL(link.href, location.href);
        if (!['http:', 'https:'].includes(target.protocol)) return;
        if (target.origin === location.origin && target.pathname === location.pathname && target.search === location.search) return;
        event.preventDefault();showLeaveDialog(target.href);
    });
})();
