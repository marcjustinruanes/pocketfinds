(() => {
 const root=document.getElementById('accountLayout');if(!root)return;
 const defaults={
  'profile-information':{title:'My profile',description:'Manage your personal details and profile photo.',ids:['profile-information','account-details']},
  'account-security':{title:'Login & security',description:'Update your password or verify a new email address.',ids:['account-security','change-email']},
  'delivery-addresses':{title:'My addresses',description:'Manage your delivery addresses and registered account address.',ids:['delivery-addresses','address-information']},
  'my-vouchers':{title:'My vouchers',description:'Your offers and savings for your next order.',ids:['my-vouchers']},
  'payment-methods':{title:'Payment accounts',description:'Manage the payment accounts connected to your profile.',ids:['payment-methods']},
  'following-shops':{title:'Following shops',description:'Keep your favourite shops close by.',ids:['following-shops']}
 };
 const config=root.querySelector('[data-account-sections]');
 let sections=defaults;
 if(config){try{sections=JSON.parse(config.textContent);}catch{sections=defaults;}}
 const storageKey='pocketfinds.account.section'+(root.dataset.accountRole&&root.dataset.accountRole!=='buyer'?'.'+root.dataset.accountRole:'');
 const cards=new Map();
 for(const[key,section]of Object.entries(sections)){
  section.ids.forEach(id=>{const target=document.getElementById(id);const card=target?.closest('.card');if(card)cards.set(card,key);});
 }
 root.querySelectorAll('[data-account-panel]').forEach(card=>cards.set(card,card.dataset.accountPanel));
 root.querySelectorAll('.account-photo-section .card').forEach(card=>cards.set(card,'profile-information'));
 const show=(requested)=>{
  let hash=typeof requested==='string'?requested:location.hash.slice(1);
  try{hash=decodeURIComponent(hash);}catch{hash='';}
  let remembered='';try{remembered=sessionStorage.getItem(storageKey)||'';}catch(_){}
  const key=Object.keys(sections).find(key=>key===hash||sections[key].ids.includes(hash))||(sections[remembered]?remembered:'profile-information');
  try{sessionStorage.setItem(storageKey,key);}catch(_){}
  for(const[card,group]of cards)card.hidden=group!==key;
  root.querySelectorAll('[data-account-section]').forEach(link=>{
   if(link.dataset.accountSection===key)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');
  });
  const title=document.getElementById('accountPanelTitle');
  const description=document.getElementById('accountPanelDescription');
  if(title)title.textContent=sections[key].title;
  if(description)description.textContent=sections[key].description;
  root.querySelector('.account-main')?.scrollTo({top:0,behavior:'instant'});
 };
 root.classList.add('account-panels-ready');show();
 root.querySelectorAll('[data-account-section]').forEach(link=>link.addEventListener('click',event=>{
  event.preventDefault();
  const key=link.dataset.accountSection;
  try{history.replaceState(null,'','#'+key);}catch{}
  show(key);
 }));
 window.addEventListener('hashchange',show);
 // Keep validation feedback and pending verification visible after a form redirect.
 const error=root.querySelector('.form-row .error,.form-row [aria-invalid="true"]');
 if(error){const card=error.closest('.card');if(cards.has(card)){location.hash=cards.get(card);show();}}
})();
