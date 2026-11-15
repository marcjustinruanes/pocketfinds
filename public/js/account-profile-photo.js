document.querySelectorAll('[data-upload-input]').forEach(input => input.addEventListener('change', () => {
  const preview = document.getElementById(input.dataset.uploadInput);
  const empty = input.closest('.profile-identity-upload')?.querySelector('.profile-upload-empty');
  const file = input.files?.[0];
  if (!preview || !empty) return;
  if (!file) { preview.hidden = true; preview.replaceChildren(); empty.hidden = false; return; }
  if (file.size > 5 * 1024 * 1024) { input.value = ''; preview.hidden = true; preview.replaceChildren(); empty.hidden = false; return; }
  preview.replaceChildren();
  if (file.type.startsWith('image/')) {
    const image = document.createElement('img');
    image.alt = file.name;
    image.src = URL.createObjectURL(file);
    preview.append(image);
  } else {
    const label = document.createElement('span');
    label.className = 'profile-upload-file';
    label.textContent = `PDF · ${file.name}`;
    preview.append(label);
  }
  preview.hidden = false;
  if (empty) empty.hidden = true;
}));

(() => {
  const input=document.getElementById('buyerProfilePhotoInput');
  const choose=document.getElementById('chooseBuyerProfilePhoto');
  const chooseAnother=document.getElementById('chooseAnotherBuyerPhoto');
  const save=document.getElementById('saveBuyerProfilePhoto');
  const error=document.getElementById('buyerProfilePhotoError');
  const preview=document.getElementById('buyerProfilePhotoPreview');
  const cropModal=document.getElementById('profilePhotoCropModal');
  const currentPanel=document.getElementById('profilePhotoManagerCurrent');
  const cropPanel=document.getElementById('profilePhotoCropEditor');
  const readyPanel=document.getElementById('profilePhotoReady');
  const readyImage=document.getElementById('profilePhotoReadyImage');
  const enlargeModal=document.getElementById('buyerProfilePhotoEnlargeModal');
  const enlargeImage=document.getElementById('buyerProfilePhotoEnlargeImage');
  const canvas=document.getElementById('profilePhotoCropCanvas');
  if(!input||!canvas||!cropModal)return;
  const ctx=canvas.getContext('2d');
  const zoomInput=document.getElementById('profilePhotoZoom');
  const crop={image:null,zoom:1,dx:0,dy:0,ratio:'circle',dragging:false,lastX:0,lastY:0,rect:null};
  const cw=canvas.width,ch=canvas.height;
  let acceptedPhoto=null;
  function cropRect(){
    return{x:50,y:50,w:500,h:500,circle:true};
  }
  function draw(){
    if(!crop.image)return;
    crop.rect=cropRect();const r=crop.rect;
    const scale=Math.max(r.w/crop.image.naturalWidth,r.h/crop.image.naturalHeight)*crop.zoom;
    const iw=crop.image.naturalWidth*scale,ih=crop.image.naturalHeight*scale;
    const ix=(cw-iw)/2+crop.dx,iy=(ch-ih)/2+crop.dy;
    ctx.clearRect(0,0,cw,ch);ctx.fillStyle='#252129';ctx.fillRect(0,0,cw,ch);
    ctx.drawImage(crop.image,ix,iy,iw,ih);
    ctx.fillStyle='rgba(14,12,16,.58)';ctx.fillRect(0,0,cw,ch);
    ctx.save();
    if(r.circle){ctx.beginPath();ctx.ellipse(r.x+r.w/2,r.y+r.h/2,r.w/2,r.h/2,0,0,Math.PI*2);ctx.clip();}
    else ctx.beginPath(),ctx.rect(r.x,r.y,r.w,r.h),ctx.clip();
    ctx.drawImage(crop.image,ix,iy,iw,ih);ctx.restore();
    ctx.save();ctx.strokeStyle='#fff';ctx.lineWidth=3;ctx.setLineDash([9,6]);
    if(r.circle){ctx.beginPath();ctx.arc(r.x+r.w/2,r.y+r.h/2,r.w/2,0,Math.PI*2);ctx.stroke();}
    else ctx.strokeRect(r.x,r.y,r.w,r.h);
    ctx.restore();
  }
  function clampPhoto(){
    if(!crop.image||!crop.rect)return;
    const r=crop.rect,scale=Math.max(r.w/crop.image.naturalWidth,r.h/crop.image.naturalHeight)*crop.zoom;
    const iw=crop.image.naturalWidth*scale,ih=crop.image.naturalHeight*scale;
    const baseX=(cw-iw)/2,baseY=(ch-ih)/2;
    crop.dx=Math.max(r.x+r.w-(baseX+iw),Math.min(r.x-baseX,crop.dx));
    crop.dy=Math.max(r.y+r.h-(baseY+ih),Math.min(r.y-baseY,crop.dy));
  }
  function openCrop(file){
    if(file.size>5*1024*1024){error.textContent='Choose an image no larger than 5 MB.';error.hidden=false;restoreAcceptedPhoto();return;}
    error.hidden=true;
    const image=new Image();image.onload=()=>{crop.image=image;crop.zoom=1;crop.dx=0;crop.dy=0;zoomInput.value='1';currentPanel.hidden=true;readyPanel.hidden=true;cropPanel.hidden=false;save.hidden=true;draw();};image.onerror=()=>{error.textContent='This image could not be opened. Choose another JPG, PNG, or WEBP photo.';error.hidden=false;restoreAcceptedPhoto();};
    image.src=URL.createObjectURL(file);
  }
  function restoreAcceptedPhoto(){const transfer=new DataTransfer();if(acceptedPhoto)transfer.items.add(acceptedPhoto);input.files=transfer.files;save.disabled=!acceptedPhoto;save.hidden=!acceptedPhoto;cropPanel.hidden=true;readyPanel.hidden=!acceptedPhoto;currentPanel.hidden=!!acceptedPhoto;if(acceptedPhoto){readyImage.src=URL.createObjectURL(acceptedPhoto);}}
  function closePhotoModal(){cropModal.classList.remove('open');cropModal.setAttribute('aria-hidden','true');acceptedPhoto=null;restoreAcceptedPhoto();error.hidden=true;}
  document.getElementById('openBuyerProfilePhotoModal')?.addEventListener('click',()=>{restoreAcceptedPhoto();cropModal.classList.add('open');cropModal.setAttribute('aria-hidden','false');});
  choose?.addEventListener('click',()=>input.click());
  chooseAnother?.addEventListener('click',()=>input.click());
  input.addEventListener('change',()=>{const file=input.files?.[0];if(file)openCrop(file);});
  document.getElementById('cancelProfilePhotoCrop').textContent='Choose another photo';
  document.getElementById('cancelProfilePhotoCrop')?.addEventListener('click',()=>input.click());
  document.getElementById('chooseAnotherPhotoFooter')?.addEventListener('click',()=>input.click());
  document.querySelectorAll('[data-photo-modal-close]').forEach(button=>button.addEventListener('click',closePhotoModal));
  zoomInput.addEventListener('input',()=>{crop.zoom=Number(zoomInput.value);clampPhoto();draw();});
  canvas.addEventListener('pointerdown',event=>{crop.dragging=true;crop.lastX=event.offsetX*cw/canvas.clientWidth;crop.lastY=event.offsetY*ch/canvas.clientHeight;canvas.setPointerCapture(event.pointerId);});
  canvas.addEventListener('pointermove',event=>{if(!crop.dragging)return;const x=event.offsetX*cw/canvas.clientWidth,y=event.offsetY*ch/canvas.clientHeight;crop.dx+=x-crop.lastX;crop.dy+=y-crop.lastY;crop.lastX=x;crop.lastY=y;clampPhoto();draw();});
  const finishCropDrag=()=>{crop.dragging=false;};
  canvas.addEventListener('pointerup',finishCropDrag);canvas.addEventListener('pointercancel',finishCropDrag);
  document.getElementById('applyProfilePhotoCrop')?.addEventListener('click',()=>{
    if(!crop.image||!crop.rect)return;
    const r=crop.rect,scale=Math.max(r.w/crop.image.naturalWidth,r.h/crop.image.naturalHeight)*crop.zoom;
    const iw=crop.image.naturalWidth*scale,ih=crop.image.naturalHeight*scale,ix=(cw-iw)/2+crop.dx,iy=(ch-ih)/2+crop.dy;
    const sx=Math.max(0,(r.x-ix)/scale),sy=Math.max(0,(r.y-iy)/scale),sw=Math.min(crop.image.naturalWidth-sx,r.w/scale),sh=Math.min(crop.image.naturalHeight-sy,r.h/scale);
    const out=document.createElement('canvas'),factor=Math.min(1000/Math.max(r.w,r.h),1.5);out.width=Math.round(r.w*factor);out.height=Math.round(r.h*factor);
    const outCtx=out.getContext('2d');outCtx.drawImage(crop.image,sx,sy,sw,sh,0,0,out.width,out.height);
    out.toBlob(blob=>{if(!blob)return;const cropped=new File([blob],'profile-photo.jpg',{type:'image/jpeg'});acceptedPhoto=cropped;const transfer=new DataTransfer();transfer.items.add(cropped);input.files=transfer.files;
      readyImage.src=URL.createObjectURL(cropped);cropPanel.hidden=true;currentPanel.hidden=true;readyPanel.hidden=false;save.hidden=false;save.disabled=false;
    },'image/jpeg',.92);
  });
  document.getElementById('enlargeBuyerProfilePhoto')?.addEventListener('click',()=>{
    const image=preview.querySelector('img'),avatar=document.getElementById('buyerProfilePhotoEnlargeAvatar');if(image){enlargeImage.src=image.src;enlargeImage.hidden=false;if(avatar)avatar.hidden=true;}else{enlargeImage.removeAttribute('src');enlargeImage.hidden=true;if(avatar)avatar.hidden=false;}
    enlargeModal.classList.add('open');enlargeModal.setAttribute('aria-hidden','false');
  });
  document.querySelectorAll('[data-photo-enlarge-close]').forEach(button=>button.addEventListener('click',()=>{enlargeModal.classList.remove('open');enlargeModal.setAttribute('aria-hidden','true');}));
})();
(() => {
  const birthday = document.querySelector('.profile-birthday-row input[name="birthday"]');
  const age = document.querySelector('[data-buyer-age]');
  if (!birthday || !age) return;
  birthday.addEventListener('change', () => {
    if (!birthday.value) { age.textContent = '—'; return; }
    const birth = new Date(`${birthday.value}T00:00:00`);
    if (Number.isNaN(birth.getTime())) { age.textContent = '—'; return; }
    const today = new Date();
    let years = today.getFullYear() - birth.getFullYear();
    if (today.getMonth() < birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate())) years--;
    age.textContent = years >= 0 ? `${years} years old` : '—';
  });
})();
