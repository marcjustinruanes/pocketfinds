(() => {
 const root=document.querySelector('.seller-reviews');if(!root)return;
 const posted=root.querySelector('[data-review-posted]');
 if(posted){posted.showModal();posted.addEventListener('click',event=>{if(event.target===posted){const bounds=posted.getBoundingClientRect();if(event.clientX<bounds.left||event.clientX>bounds.right||event.clientY<bounds.top||event.clientY>bounds.bottom)posted.close();}});}
 const search=root.querySelector('#reviewSearch'),rating=root.querySelector('#reviewRating') || {value:''},cards=[...root.querySelectorAll('.review-card')],tabs=[...root.querySelectorAll('[data-review-status]')];
 let status='all';
 function update(){
  const query=search.value.trim().toLocaleLowerCase();let count=0;
  cards.forEach(card=>{const matches=(!rating.value||card.dataset.rating===rating.value)&&(status==='all'||card.dataset.replied===(status==='replied'?'true':'false'))&&card.textContent.toLocaleLowerCase().includes(query);card.hidden=!matches;if(matches)count++;});
  tabs.forEach(tab=>{const active=tab.dataset.reviewStatus===status;tab.classList.toggle('active',active);tab.setAttribute('aria-pressed',String(active));});
  root.querySelectorAll('[data-rating-filter]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.ratingFilter===rating.value)));
  root.querySelector('#reviewResultCount').textContent=count+' '+(count===1?'review':'reviews');root.querySelector('#reviewNoResults').hidden=count>0||cards.length===0;
 }
 search.addEventListener('input',update);rating.addEventListener?.('change',update);
 root.querySelectorAll('[data-rating-filter]').forEach(button=>button.addEventListener('click',()=>{rating.value=button.dataset.ratingFilter;update();}));
 const monthDetail=root.querySelector('[data-month-detail]');
 root.querySelectorAll('[data-review-month]').forEach(point=>{
  const selectMonth=()=>{
   const counts=JSON.parse(point.dataset.monthCounts),total=Object.values(counts).reduce((sum,value)=>sum+Number(value),0);
   root.querySelectorAll('[data-review-month]').forEach(other=>other.setAttribute('aria-pressed',String(other===point)));
   monthDetail.replaceChildren();const header=document.createElement('div');header.className='reviews-month-detail-head';const heading=document.createElement('strong');heading.textContent=point.dataset.reviewMonth;const summary=document.createElement('span');summary.textContent=total+' '+(total===1?'review':'reviews');header.append(heading,summary);const rows=document.createElement('div');rows.className='reviews-month-breakdown';
   for(let star=5;star>=1;star--){const count=Number(counts[star]||0),row=document.createElement('div'),label=document.createElement('span'),track=document.createElement('span'),fill=document.createElement('i'),value=document.createElement('b');row.className='reviews-month-rating-row';row.dataset.star=star;row.classList.toggle('is-empty',!count);label.textContent=star+(star===1?' star':' stars');track.className='reviews-month-rating-track';fill.style.width=(total?count/total*100:0)+'%';track.setAttribute('aria-hidden','true');track.append(fill);value.textContent=count;row.append(label,track,value);rows.append(row);}
   monthDetail.append(header,rows);monthDetail.hidden=false;
  };
  point.addEventListener('click',selectMonth);point.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();selectMonth();}});
 });
 tabs.forEach(tab=>tab.addEventListener('click',()=>{status=tab.dataset.reviewStatus;update();}));
 root.querySelector('#reviewReset').addEventListener('click',()=>{status='all';search.value='';rating.value='';update();search.focus();});update();
})();
