(()=>{
const modal=document.getElementById('assignmentDetailsModal'),content=document.getElementById('assignmentDetailsContent');if(!modal||!content)return;
const back=modal.querySelector('[data-assignment-details-back]');let pending,history=[],current='';
async function load(url,remember=true){
 const target=new URL(url,location.origin);if(target.origin!==location.origin||!target.pathname.startsWith('/logistics/'))return;
 if(remember&&current&&current!==target.href)history.push(current);current=target.href;
 pending?.abort();pending=new AbortController();modal.classList.add('open');content.replaceChildren();const note=document.createElement('p');note.textContent='Loading assignment details?';content.append(note);back.hidden=!history.length;
 try{const response=await fetch(target.href,{credentials:'same-origin',headers:{Accept:'text/html','X-Requested-With':'XMLHttpRequest'},signal:pending.signal});if(!response.ok)throw Error('load');
 const page=new DOMParser().parseFromString(await response.text(),'text/html');const details=page.querySelector('.assignment-parcel-details,.hub-transfer-detail-page');if(!details)throw Error('content');content.replaceChildren(document.importNode(details,true));
 document.getElementById('assignmentDetailsTitle').textContent=details.dataset.detailTitle||'Hub transfer details';try{sessionStorage.setItem('assignment-details-url',target.pathname+target.search);}catch{}
 }catch(error){if(error.name==='AbortError')return;content.replaceChildren();note.textContent='Details could not be loaded. Please try again.';content.append(note);}
}
document.addEventListener('click',event=>{const link=event.target.closest('[data-assignment-details-url],[data-transfer-details-url]');if(link){event.preventDefault();load(link.dataset.assignmentDetailsUrl||link.dataset.transferDetailsUrl);return;}const page=event.target.closest('#assignmentDetailsContent nav[role=navigation] a[href]');if(page){event.preventDefault();load(page.href,false);return;}if(event.target.closest('[data-modal-close]')?.closest('#assignmentDetailsModal'))pending?.abort();});
back.addEventListener('click',()=>{const url=history.pop();if(url)load(url,false);});
modal.addEventListener('click',event=>{if(event.target===modal)pending?.abort();});
document.addEventListener('keydown',event=>{if(event.key==='Escape')pending?.abort();});
if(modal.dataset.reopen==='true'){try{const last=sessionStorage.getItem('assignment-details-url');if(last)load(last,false);}catch{}}
})();
