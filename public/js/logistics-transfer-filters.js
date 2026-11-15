(() => {
 const form=document.getElementById('hubTransferFilters');if(!form)return;
 let timer,submitting=false;
 const from=form.elements.namedItem('from'),to=form.elements.namedItem('to');
 const submit=()=>{clearTimeout(timer);if(from&&to)to.min=from.value;if(submitting||!form.checkValidity())return;submitting=true;form.requestSubmit();};
 form.addEventListener('change',submit);
 [...form.querySelectorAll('input:not([type="date"])'),...document.querySelectorAll('input[form="hubTransferFilters"]:not([type="date"])')].forEach(input=>{input.addEventListener('input',event=>{clearTimeout(timer);if(!event.isComposing)timer=setTimeout(submit,500);});input.addEventListener('compositionstart',()=>clearTimeout(timer));input.addEventListener('compositionend',()=>{clearTimeout(timer);timer=setTimeout(submit,500);});});
 document.querySelectorAll('[form="hubTransferFilters"]').forEach(control=>control.addEventListener('change',submit));
 form.addEventListener('submit',()=>clearTimeout(timer));
 window.addEventListener('pageshow',()=>{submitting=false;});
 if(from&&to)to.min=from.value;
})();
