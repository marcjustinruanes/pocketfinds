document.addEventListener('DOMContentLoaded', () => {
 let viewer;
 function enlargePhoto(url,name){
  if(!viewer){
   viewer=document.createElement('dialog');viewer.className='review-photo-viewer';viewer.setAttribute('aria-label','Photo preview');
   const close=document.createElement('button');close.type='button';close.textContent='×';close.setAttribute('aria-label','Close photo preview');close.addEventListener('click',()=>viewer.close());
   viewer.append(close,document.createElement('img'));document.body.append(viewer);
   viewer.addEventListener('click',event=>{if(event.target===viewer){const bounds=viewer.getBoundingClientRect();if(event.clientX<bounds.left||event.clientX>bounds.right||event.clientY<bounds.top||event.clientY>bounds.bottom)viewer.close();}});
  }
  const photo=viewer.querySelector('img');photo.src=url;photo.alt=name;viewer.showModal();
 }
 document.querySelectorAll('.order-review-modal .review-media-picker input[type=file],.order-refund-modal .refund-photo-picker input[type=file]').forEach(input => {
  const picker=input.closest('.review-media-picker'),caption=picker.querySelector('span');
  const previews=document.createElement('div');previews.className='review-upload-previews';previews.setAttribute('aria-label','Selected review attachments');
  const message=document.createElement('p');message.className='review-upload-message';message.setAttribute('role','status');message.setAttribute('aria-live','polite');message.hidden=true;
  const selection=input.form.querySelector('[data-review-file-selection]');
  if(selection)selection.after(previews,message);else picker.after(previews,message);
  let files=[],urls=[];
  const key=file=>[file.name,file.size,file.lastModified].join(':');
  function render(){
   const transfer=new DataTransfer();files.forEach(file=>transfer.items.add(file));input.files=transfer.files;
   urls.forEach(url=>URL.revokeObjectURL(url));urls=[];previews.replaceChildren();
   caption.textContent=input.name==='photos[]'?'Choose photos':'Add photos or video';
   if(selection){selection.textContent='';selection.hidden=true;}
   files.forEach((file,index)=>{
    const item=document.createElement('div');item.className='review-upload-preview';
    const video=file.type.startsWith('video/'),preview=document.createElement(video?'video':'img'),url=URL.createObjectURL(file);urls.push(url);preview.src=url;
    if(video){preview.controls=true;preview.preload='metadata';preview.setAttribute('aria-label',file.name);}else preview.alt=file.name;
    const remove=document.createElement('button');remove.type='button';remove.textContent='×';remove.setAttribute('aria-label','Remove '+file.name);
    remove.addEventListener('click',()=>{files.splice(index,1);render();message.hidden=true;message.textContent='';message.classList.remove('is-error');});
    const name=document.createElement('span');name.className='review-upload-filename';name.textContent=file.name;name.title=file.name;
    item.append(preview,name,remove);
    if(!video){const enlarge=document.createElement('button');enlarge.type='button';enlarge.className='review-upload-enlarge';enlarge.setAttribute('aria-label','Enlarge photo');enlarge.title='Enlarge photo';enlarge.innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3h7v7M21 3l-8 8M10 21H3v-7M3 21l8-8"/></svg>';enlarge.addEventListener('click',()=>enlargePhoto(url,file.name));item.append(enlarge);}
    previews.append(item);
   });
  }
  input.addEventListener('change',()=>{
   const added=[...input.files],next=[...files];let error='';
   for(const file of added){
    const allowed=input.name==='photos[]'?['image/jpeg','image/png']:['image/jpeg','image/png','video/mp4','video/quicktime'];
    if(!allowed.includes(file.type)){error=input.name==='photos[]'?'Choose JPG or PNG photos.':'Choose JPG, PNG, MP4 or MOV files.';break;}
    const video=file.type.startsWith('video/');if(file.size>(video?20:5)*1024*1024){error=file.name+' exceeds the '+(video?'20 MB video':'5 MB photo')+' limit.';break;}
    if(!next.some(existing=>key(existing)===key(file)))next.push(file);
   }
   if(next.length>5)error='You can attach up to 5 photos or videos per review.';
   if(!error)files=next;
   render();message.hidden=!error;message.textContent=error;message.classList.toggle('is-error',Boolean(error));
  });
 });
});
