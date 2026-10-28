(() => {
    let regions;
    document.querySelectorAll('[data-complete-delivery-address]').forEach(form=>{
        const select=form.querySelector('[data-completion-region]');
        const feedback=form.querySelector('[data-completion-feedback]');
        if(select){
            select.disabled=true;
            regions??=fetch('https://psgc.gitlab.io/api/regions/').then(response=>{
                if(!response.ok)throw new Error('Unable to load regions. Refresh to retry.');return response.json();
            });
            regions.then(rows=>{
                select.replaceChildren(new Option('Select your delivery region',''));
                rows.sort((a,b)=>a.name.localeCompare(b.name)).forEach(region=>select.add(new Option(region.name,region.name)));
                select.disabled=false;
            }).catch(error=>{feedback.textContent=error.message;});
        }
        form.addEventListener('submit',async event=>{
            event.preventDefault();if(!form.reportValidity())return;
            if(select?.disabled){feedback.textContent='Wait for regions to load, or refresh to retry.';return;}
            const button=form.querySelector('button[type="submit"]');button.disabled=true;feedback.textContent='Saving delivery details…';
            try{
                const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'}});
                const result=await response.json();
                if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message||'Unable to save delivery details.');
                window.location.reload();
            }catch(error){feedback.textContent=error.message;button.disabled=false;}
        });
    });
})();
