(() => {
    const statusIcon=(kind)=>{
        const color=kind==='available'?'#16a34a':kind==='used'?'#dc2626':'#94a3b8';
        const path=kind==='available'?'<polyline points="9 12 11 14 15 10"/>':kind==='used'?'<path d="m15 9-6 6m0-6 6 6"/>':kind==='reserved'?'<path d="M12 7v6m0 4h.01"/>':'<polyline points="12 6 12 12 16 14"/>';
        return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="'+color+'" stroke-width="'+(kind==='checking'?'2':'2.5')+'" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle" aria-hidden="true"><circle cx="12" cy="12" r="10"/>'+path+'</svg>';
    };
    const states=[];
    ['tin','sec_registration_no'].forEach(id=>{
        const input=document.getElementById(id);if(!input)return;
        const label=id==='tin'?'TIN':'SEC registration number',state={input,timer:null,result:null,request:null,error:false};states.push(state);
        const icon=document.createElement('span');icon.id=id+'Availability';icon.setAttribute('role','status');icon.setAttribute('aria-live','polite');icon.style.cssText='position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:12px;pointer-events:none';
        input.parentElement.style.position='relative';input.style.paddingRight='36px';input.parentElement.append(icon);
        const value=()=>id==='tin'?input.value.replace(/[-\s]/g,''):input.value.trim().toUpperCase();
        const valid=key=>(id==='tin'?/^\d{12}$/:/^[A-Z]{2}\d{9}$/).test(key);
        state.check=async()=>{
            const key=value();if(!valid(key))return true;
            if(state.result?.key===key)return state.result.allowed;
            if(state.request?.key===key)return state.request.promise;
            icon.innerHTML=statusIcon('checking');icon.title='Checking availability';icon.setAttribute('aria-label',icon.title);
            const promise=(async()=>{
                try{
                    const response=await fetch('/register/check-business-number?'+new URLSearchParams({field:id,value:key}),{headers:{Accept:'application/json'},signal:AbortSignal.timeout(8000)});
                    if(!response.ok)throw Error();const data=await response.json();if(value()!==key)return false;
                    const allowed=data.available||data.reapplication_only;state.result={key,allowed};
                    icon.innerHTML=statusIcon(data.available?'available':data.reapplication_only?'reserved':'used');
                    icon.title=data.available?'Available':data.reapplication_only?'Reserved for the same rejected applicant.':'Already registered';
                    icon.setAttribute('aria-label',icon.title);
                    if(!allowed){state.error=true;showError(input,'This '+label+' is already registered.');}
                    else if(data.reapplication_only){state.error=true;showError(input,'Reserved for the same rejected applicant. Use your existing application details.');}
                    else if(state.error){clearError(input);state.error=false;}
                    return allowed;
                }catch{if(value()===key){icon.textContent='';state.error=true;showError(input,'Could not check this '+label+'. Please try Continue again.');}return false;}
                finally{if(state.request?.key===key)state.request=null;}
            })();state.request={key,promise};return promise;
        };
        input.addEventListener('input',()=>{clearTimeout(state.timer);state.result=null;icon.textContent='';icon.removeAttribute('aria-label');icon.removeAttribute('title');if(state.error){clearError(input);state.error=false;}if(valid(value())){icon.innerHTML=statusIcon('checking');icon.setAttribute('aria-label','Checking availability');state.timer=setTimeout(state.check,500);}});
    });
    window.checkRegistrationBusinessNumbers=async panels=>{
        for(const state of states){if(panels.includes(Number(state.input.closest('.step-panel').id.replace('panel-','')))&&!await state.check()){state.input.focus();return false;}}
        return true;
    };
})();
