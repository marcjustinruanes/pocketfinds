(() => {
 const root=document.getElementById('accountLayout');if(!root)return;
 const sections={
  'profile-information':{title:'My profile',description:'Manage your personal details and profile photo.',ids:['profile-information','account-details']},
  'account-security':{title:'Login & security',description:'Update your password or verify a new email address.',ids:['account-security','change-email']},
  'delivery-addresses':{title:'My addresses',description:'Manage your delivery addresses and registered account address.',ids:['delivery-addresses','address-information']},
  'my-vouchers':{title:'My vouchers',description:'Your offers and savings for your next order.',ids:['my-vouchers']},
  'payment-methods':{title:'Payment accounts',description:'Manage the payment accounts connected to your profile.',ids:['payment-methods']},
  'following-shops':{title:'Following shops',description:'Keep your favourite shops close by.',ids:['following-shops']}
 };
 const cards=new Map();
 for(const[key,section]of Object.entries(sections)){
  section.ids.forEach(id=>{const target=document.getElementById(id);const card=target?.closest('.card');if(card)cards.set(card,key);});
 }
 root.querySelectorAll('.account-photo-section .card').forEach(card=>cards.set(card,'profile-information'));
 const show=()=>{
  const hash=decodeURIComponent(location.hash.slice(1));
  let remembered='';try{remembered=sessionStorage.getItem('pocketfinds.account.section')||'';}catch(_){}
  const key=Object.keys(sections).find(key=>key===hash||sections[key].ids.includes(hash))||(sections[remembered]?remembered:'profile-information');
  try{sessionStorage.setItem('pocketfinds.account.section',key);}catch(_){}
  for(const[card,group]of cards)card.hidden=group!==key;
  root.querySelectorAll('[data-account-section]').forEach(link=>{
   if(link.dataset.accountSection===key)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');
  });
  document.getElementById('accountPanelTitle').textContent=sections[key].title;
  document.getElementById('accountPanelDescription').textContent=sections[key].description;
 };
 root.classList.add('account-panels-ready');show();
 window.addEventListener('hashchange',show);
 // Keep validation feedback and pending verification visible after a form redirect.
 const error=root.querySelector('.form-row .error,.form-row [aria-invalid="true"]');
 if(error){const card=error.closest('.card');if(cards.has(card)){location.hash=cards.get(card);show();}}
})();
