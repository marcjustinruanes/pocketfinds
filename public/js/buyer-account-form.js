(() => {
  // Portals keep dialogs out of the account scroll area's stacking context.
  document.querySelectorAll('.modal-overlay').forEach(modal=>document.body.append(modal));
  const modals=[...document.querySelectorAll('.modal-overlay')];
  const syncModalScroll=()=>{document.body.classList.toggle('account-dialog-open',modals.some(modal=>modal.classList.contains('open')));};
  modals.forEach(modal=>new MutationObserver(syncModalScroll).observe(modal,{attributes:true,attributeFilter:['class']}));
  syncModalScroll();
  const addressFields={addrLabel:'label',addrRecipientName:'recipient_name',addrContactNo:'contact_no',addrRegion:'region',addrProvince:'province',addrMunicipality:'municipality',addrBarangay:'barangay',addrPostalCode:'postal_code',addrHouseNo:'house_no',addrStreet:'street'};
  Object.entries(addressFields).forEach(([id,name])=>{const field=document.getElementById(id);field?.closest('.form-row')?.classList.add(`address-field-${name}`);if(id==='addrContactNo'&&field)field.setAttribute('data-national-mobile','');});
  function styleFields(root=document) {
    root.querySelectorAll('.delivery-address-editor .address-form-grid>label').forEach(label=>{const row=document.createElement('div');row.className=`form-row ${label.className}`;const caption=document.createElement('label');caption.textContent=label.childNodes[0]?.textContent||'';row.append(caption);label.querySelectorAll('input,select').forEach(field=>row.append(field));label.replaceWith(row);});
    root.querySelectorAll('.form-row').forEach(row => {
      if(row.querySelector('.account-floating-field')||row.classList.contains('profile-age-field'))return;
      const label=row.querySelector('label')||(row.tagName==='LABEL'?row:null);
      const field=row.querySelector('input:not([type=file]):not([type=checkbox]):not([type=radio]),select');
      if(!label||!field)return;
      const text=(label.childNodes[0]?.textContent||label.textContent).trim().replace(/\s*\*$/,'');
      const wrapper=document.createElement('div');wrapper.className='account-floating-field';
      field.before(wrapper);wrapper.append(field);
      if(label===row){row.childNodes.forEach(node=>{if(node.nodeType===3)node.textContent='';});}
      else label.remove();
      field.placeholder=' ';field.id ||= `accountField${Math.random().toString(36).slice(2)}`;
      const caption=document.createElement('label');caption.htmlFor=field.id;caption.textContent=text;
      const outline=document.createElement('fieldset');outline.setAttribute('aria-hidden','true');
      const legend=document.createElement('legend'),span=document.createElement('span');span.textContent=text;legend.append(span);outline.append(legend);wrapper.append(caption,outline);
      if(field.tagName==='SELECT'||field.type==='date'||field.disabled||field.hasAttribute('data-national-mobile')||field.name==='postal_code')wrapper.classList.add('always-floating');
      if(row.classList.contains('address-field-contact_no')&&field.name==='contact_no'){wrapper.classList.add('account-mobile-field');const prefix=document.createElement('span');prefix.className='account-mobile-prefix';prefix.textContent='+63';wrapper.append(prefix);}
    });
    root.querySelectorAll('input[type=password]').forEach(field=>{
      const wrapper=field.closest('.account-floating-field');if(!wrapper||wrapper.querySelector('.account-password-toggle'))return;
      const button=document.createElement('button');button.type='button';button.className='account-password-toggle';button.setAttribute('aria-label','Show password');
      button.innerHTML='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
      wrapper.append(button);const sync=()=>button.hidden=!field.value;field.addEventListener('input',sync);sync();
      button.addEventListener('click',()=>{field.type=field.type==='password'?'text':'password';button.setAttribute('aria-label',field.type==='password'?'Show password':'Hide password');});
    });
  }
  styleFields();
  document.querySelectorAll('[data-unlock-on-focus]').forEach(field=>{const unlock=()=>{field.readOnly=false;};field.addEventListener('focus',unlock);field.addEventListener('pointerdown',unlock);});
  const emailField=document.getElementById('newBuyerEmail'),codeForm=document.getElementById('accountEmailCodeForm'),verifyForm=document.getElementById('accountEmailVerifyForm');
  if(emailField&&codeForm&&verifyForm){
    const feedback=document.getElementById('accountEmailFeedback'),pendingEmail=document.getElementById('accountPendingEmail'),send=document.getElementById('accountSendEmailCode');
    let emailRevision=0,verifiedEmail=codeForm.dataset.verifiedEmail||'';
    const google=document.getElementById('accountGoogleEmail'),verifiedBadge=document.getElementById('accountEmailVerified');
    const syncEmailControls=()=>{const verified=!!verifiedEmail&&emailField.value.trim().toLowerCase()===verifiedEmail.toLowerCase();send.hidden=verified;if(google)google.hidden=verified;if(verifiedBadge)verifiedBadge.hidden=!verified;if(verified)verifyForm.hidden=true;};
    syncEmailControls();
    const showFeedback=(message,failed=false)=>{feedback.textContent=message;feedback.hidden=false;feedback.className=failed?'profile-field-error':'account-email-success';};
    emailField.addEventListener('input',()=>{emailRevision++;verifyForm.hidden=true;pendingEmail.value='';document.getElementById('buyerEmailOtp').value='';feedback.hidden=true;send.textContent='Send Code';syncEmailControls();});
    async function post(form){const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors||{}).flat()[0]||data.message||'Please try again.');return data;}
    codeForm.addEventListener('submit',async event=>{event.preventDefault();if(send.disabled||!codeForm.reportValidity())return;const revision=emailRevision;send.disabled=true;send.textContent='Sending…';
      try{const data=await post(codeForm);if(revision!==emailRevision)return;pendingEmail.value=data.email;verifyForm.hidden=false;send.textContent='Resend Code';showFeedback(data.message);document.getElementById('buyerEmailOtp').focus();}
      catch(error){if(revision===emailRevision)showFeedback(error.message,true);}finally{send.disabled=false;if(send.textContent==='Sending…')send.textContent='Send Code';}
    });
    verifyForm.addEventListener('submit',async event=>{event.preventDefault();const button=verifyForm.querySelector('button');if(button.disabled||!verifyForm.reportValidity())return;const revision=emailRevision;button.disabled=true;
      try{const data=await post(verifyForm);if(revision!==emailRevision)return;verifyForm.hidden=true;verifiedEmail=data.email;emailField.value=data.email;const currentEmail=document.querySelector('.profile-form-section input[type=email]:disabled');if(currentEmail)currentEmail.value=data.email;feedback.hidden=true;syncEmailControls();}
      catch(error){if(revision===emailRevision)showFeedback(error.message,true);}finally{button.disabled=false;}
    });
  }
  const newPassword=document.getElementById('buyerNewPassword');
  if(newPassword){
    const rules=[['At least 8 characters',value=>value.length>=8],['Uppercase letter',value=>/[A-Z]/.test(value)],['Lowercase letter',value=>/[a-z]/.test(value)],['Number',value=>/\d/.test(value)],['Symbol',value=>/[^A-Za-z0-9]/.test(value)]];
    const list=document.createElement('ul');list.className='account-password-rules';(document.getElementById('accountPasswordRequirements')||newPassword.closest('.form-row')).append(list);
    const items=rules.map(([text])=>{const item=document.createElement('li');item.textContent=`○ ${text}`;list.append(item);return item;});
    newPassword.addEventListener('input',()=>rules.forEach(([text,test],index)=>{const valid=test(newPassword.value);items[index].textContent=`${valid?'✓':'○'} ${text}`;items[index].classList.toggle('met',valid);}));
    const confirm=document.getElementById('buyerConfirmPassword');if(confirm){const check=()=>confirm.setCustomValidity(confirm.value&&confirm.value!==newPassword.value?'Passwords do not match.':'');confirm.addEventListener('input',check);newPassword.addEventListener('input',check);}
  }
  const documentModal=document.getElementById('accountDocumentModal'),image=document.getElementById('accountDocumentImage'),pdf=document.getElementById('accountDocumentPdf');
  const closeDoc=()=>{documentModal.classList.remove('open');documentModal.setAttribute('aria-hidden','true');image.removeAttribute('src');pdf.pdfPreviewToken=null;pdf.replaceChildren();};
  document.addEventListener('click',event=>{const button=event.target.closest('[data-document-view]');if(!button)return;event.preventDefault();
    const isPdf=button.dataset.documentKind==='pdf';image.hidden=isPdf;pdf.hidden=!isPdf;
    if(isPdf){const script=document.querySelector('script[src*="buyer-account-form.js"]');import(new URL('pdf-paper-preview.mjs',script.src).href).then(module=>{if(documentModal.classList.contains('open')&&!pdf.hidden)module.renderPdfPaper(pdf,button.dataset.documentSrc);});}
    else {pdf.pdfPreviewToken=null;pdf.replaceChildren();image.src=button.dataset.documentSrc;}
    documentModal.classList.add('open');documentModal.setAttribute('aria-hidden','false');documentModal.querySelector('button').focus();
  });
  const idFile=document.getElementById('profileIdFile');
  idFile?.addEventListener('change',()=>{
    const file=idFile.files?.[0],box=idFile.closest('.profile-identity-upload');box.querySelector('.profile-upload-tools')?.remove();if(!file)return;
    const tools=document.createElement('span');tools.className='profile-upload-tools';
    const enlarge=document.createElement('button');enlarge.type='button';enlarge.textContent='↗';enlarge.setAttribute('aria-label','Preview uploaded ID');enlarge.dataset.documentView='';enlarge.dataset.documentKind=file.type==='application/pdf'?'pdf':'image';enlarge.dataset.documentSrc=URL.createObjectURL(file);
    const replace=document.createElement('button');replace.type='button';replace.textContent='↻';replace.setAttribute('aria-label','Reupload ID');replace.addEventListener('click',event=>{event.preventDefault();idFile.click();});tools.append(enlarge,replace);box.append(tools);
  });
  document.querySelector('[data-document-close]')?.addEventListener('click',closeDoc);
  documentModal?.addEventListener('click',e=>{if(e.target===documentModal)closeDoc();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&documentModal?.classList.contains('open'))closeDoc();});
  const video=document.getElementById('profileSelfieVideo'),start=document.getElementById('startProfileSelfie'),capture=document.getElementById('captureProfileSelfie'),retake=document.getElementById('retakeProfileSelfie'),preview=document.getElementById('profileSelfiePreviewImage'),error=document.getElementById('profileSelfieError');
  if(!video)return;
  let stream=null,monitor=null,pending=false,requestId=0,saving=false;
  const stop=()=>{requestId++;clearInterval(monitor);monitor=null;stream?.getTracks().forEach(track=>track.stop());stream=null;video.srcObject=null;capture.disabled=true;pending=false;};
  const ready=()=>!!stream&&video.srcObject===stream&&stream.getVideoTracks().some(track=>track.readyState==='live'&&track.enabled&&!track.muted)&&video.readyState>=2&&!video.paused&&video.videoWidth>0&&video.videoHeight>0;
  function hasCameraImage(){
    if(!ready())return false;
    try{const sample=document.createElement('canvas');sample.width=32;sample.height=24;const ctx=sample.getContext('2d',{willReadFrequently:true});ctx.drawImage(video,0,0,32,24);const pixels=ctx.getImageData(0,0,32,24).data,colours=new Map();let largest=0;
      for(let i=0;i<pixels.length;i+=4){const key=((pixels[i]>>4)<<8)|((pixels[i+1]>>4)<<4)|(pixels[i+2]>>4);const count=(colours.get(key)||0)+1;colours.set(key,count);largest=Math.max(largest,count);}return largest/(pixels.length/4)<.94;
    }catch(_){return false;}
  }
  const sync=()=>{capture.disabled=pending||saving||!hasCameraImage();retake.disabled=pending||saving;start.disabled=pending;};
  async function openCamera(){
    if(pending||saving)return;
    stop();pending=true;const current=++requestId;sync();error.hidden=true;
    try{const next=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false});if(current!==requestId){next.getTracks().forEach(track=>track.stop());return;}
      stream=next;video.srcObject=stream;video.hidden=false;preview.hidden=true;start.hidden=true;retake.hidden=true;capture.hidden=false;await video.play();
      if(current!==requestId)return;pending=false;
      stream.getVideoTracks().forEach(track=>['ended','mute','unmute'].forEach(event=>track.addEventListener(event,sync)));monitor=setInterval(sync,250);sync();
    }catch(_){if(current!==requestId)return;stop();video.hidden=true;start.hidden=false;preview.hidden=!preview.getAttribute('src');retake.hidden=preview.hidden;capture.hidden=true;error.textContent='Allow camera access and turn on your camera to take a selfie.';error.hidden=false;sync();}
  }
  ['loadeddata','playing','pause','waiting','stalled','emptied','ended'].forEach(event=>video.addEventListener(event,sync));
  start.addEventListener('click',openCamera);retake.addEventListener('click',openCamera);
  capture.addEventListener('click',()=>{
    if(saving||!hasCameraImage()){sync();return;}
    const canvas=document.getElementById('profileSelfieCanvas');canvas.width=video.videoWidth;canvas.height=video.videoHeight;const ctx=canvas.getContext('2d');ctx.drawImage(video,0,0);
    saving=true;sync();canvas.toBlob(blob=>{saving=false;if(!blob||blob.size>5*1024*1024){sync();return;}
      const file=new File([blob],'selfie.jpg',{type:'image/jpeg'}),transfer=new DataTransfer();transfer.items.add(file);document.getElementById('profileSelfieFile').files=transfer.files;
      preview.src=URL.createObjectURL(blob);preview.hidden=false;video.hidden=true;start.hidden=true;capture.hidden=true;retake.hidden=false;stop();sync();error.hidden=true;
    },'image/jpeg',.92);
  });
  window.addEventListener('pagehide',stop);
  document.querySelectorAll('[data-account-section]').forEach(button=>button.addEventListener('click',()=>{if(button.dataset.accountSection!=='profile-information'){stop();video.hidden=true;capture.hidden=true;start.hidden=!!preview.getAttribute('src');sync();}}));
  sync();
})();
