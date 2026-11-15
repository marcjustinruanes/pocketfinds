(() => {
  const root=document.querySelector('#accountLayout[data-account-role]');
  if(!root||root.dataset.accountRole==='buyer')return;
  const documentModal=root.querySelector('#docModal');
  if(documentModal)document.body.append(documentModal);
  const groups={
    'Profile Information':'profile-information','Account Information':'profile-information',
    'Account Overview':'profile-information','Address Information':'address-information',
    'Change Password':'account-security','Change Email':'account-security','Email verification':'account-security',
    'Shop Information':'shop-information','Documents & Verification':'account-documents',
    'Vehicle & Documents':'vehicle-documents','Vacation mode':'shipping-rates',
    'Shipping regions and rates':'shipping-rates','Salary GCash account':'salary-receiving',
  };
  root.querySelectorAll('.card').forEach(card=>{
    if(card.dataset.accountPanel)return;
    const title=card.querySelector('h2')?.textContent.trim();
    card.dataset.accountPanel=groups[title]||(card.id==='account-terms'?'account-terms':'profile-information');
    if(title==='Account Overview')card.classList.add('account-details-card');
    if(title==='Address Information')card.id||='address-information';
    if(title==='Shop Information')card.id||='shop-information';
    if(title==='Documents & Verification')card.id||='account-documents';
    if(title==='Vehicle & Documents')card.id||='vehicle-documents';
  });
  const promotions=root.querySelector('#flash-sales');
  if(promotions)promotions.dataset.accountPanel='shipping-rates';
  // Outlines must follow inputs replaced by the existing address loader.
  const syncFields=()=>root.querySelectorAll('.account-floating-field').forEach(wrapper=>{
    const field=wrapper.querySelector('input,select');
    if(field?.tagName==='SELECT')wrapper.classList.add('always-floating');
  });
  new MutationObserver(syncFields).observe(root,{subtree:true,childList:true});
  root.querySelectorAll('.form-row input[type=file]').forEach(input=>{
    if(input.closest('[data-account-upload],#idSelfieGrid'))return;
    input.addEventListener('change',()=>{
      let note=input.parentElement.querySelector('[data-account-file-name]');
      if(!note){note=document.createElement('small');note.dataset.accountFileName='';input.after(note);}
      note.textContent=[...input.files].map(file=>file.name).join(', ');
    });
  });
})();
