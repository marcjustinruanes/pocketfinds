(() => {
    const grid=document.getElementById('companyGrid')||document.getElementById('joinCompanyGrid');
    if(!grid)return;
    const chooser=document.createElement('button');chooser.type='button';chooser.className='rider-company-choice';
    chooser.setAttribute('aria-haspopup','dialog');chooser.setAttribute('aria-controls','riderCompanyDialog');
    grid.before(chooser);
    const dialog=document.createElement('dialog');dialog.id='riderCompanyDialog';dialog.className='rider-company-dialog';
    dialog.setAttribute('aria-labelledby','riderCompanyTitle');
    dialog.innerHTML='<header><h2 id="riderCompanyTitle">Choose a company that’s hiring</h2><button type="button" class="rider-company-close" aria-label="Close company selection">×</button></header><div id="riderCompanyResults"><p class="rider-company-empty" role="status" hidden></p></div>';
    grid.closest('form').append(dialog);
    const results=dialog.querySelector('#riderCompanyResults'),empty=dialog.querySelector('.rider-company-empty');results.prepend(grid);
    const entries=()=>[...grid.querySelectorAll('.company-option-label')];
    function render(){
        let count=0;
        entries().forEach(label=>{
            const radio=label.querySelector('input[type="radio"]');
            label.hidden=!!radio.disabled;
            label.tabIndex=radio.disabled?-1:0;
            label.setAttribute('aria-disabled',String(radio.disabled));
            if(!label.hidden)count++;
        });
        empty.hidden=count>0;empty.textContent='No companies are currently hiring in your area.';
        grid.querySelectorAll(':scope > p').forEach(p=>p.hidden=true);
        const selected=grid.querySelector('input:checked:not(:disabled)');
        chooser.replaceChildren();
        if(selected){
            const card=selected.nextElementSibling;
            [...card.children].forEach(child=>{const clone=child.cloneNode(true);clone.removeAttribute('id');chooser.append(clone);});
        } else {
            const icon=document.createElement('span');icon.className='rider-company-placeholder';icon.innerHTML='<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21V8l9-5 9 5v13M2 21h20M7 21V11h10v10M7 14h10M7 17h10"/></svg>';
            const name=document.createElement('span');name.className='company-title';name.textContent='Choose a company that’s hiring';chooser.append(icon,name);
        }
        const arrow=document.createElement('span');arrow.className='rider-company-arrow';arrow.textContent='›';arrow.setAttribute('aria-hidden','true');chooser.append(arrow);
    }
    chooser.addEventListener('click',()=>{window.refreshCompanyAndHubOptions();render();dialog.showModal();});
    dialog.querySelector('.rider-company-close').addEventListener('click',()=>dialog.close());
    dialog.addEventListener('click',event=>{if(event.target===dialog){const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();}});
    grid.addEventListener('change',event=>{if(event.target.matches('input[type="radio"]')&&!event.target.disabled){render();dialog.close();chooser.focus();}});
    grid.addEventListener('click',event=>{
        const radio=event.target.closest('.company-option-label')?.querySelector('input[type="radio"]');
        if(radio?.checked&&!radio.disabled){render();dialog.close();chooser.focus();}
    });
    entries().forEach(label=>{
        label.tabIndex=0;label.addEventListener('keydown',event=>{if(['Enter',' '].includes(event.key)){event.preventDefault();const radio=label.querySelector('input');if(!radio.disabled)radio.click();}});
    });
    new MutationObserver(render).observe(grid,{subtree:true,attributes:true,attributeFilter:['disabled','class']});
    render();
})();
