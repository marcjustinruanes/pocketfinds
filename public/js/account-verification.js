(() => {
  const root = document.querySelector('[data-account-role="seller"]');
  if (!root) return;
  const preview = (url, pdf = false) => {
    const dialog = document.createElement('dialog');
    dialog.className = 'account-capture-preview';
    const close = document.createElement('button');
    close.type = 'button'; close.textContent = '×'; close.setAttribute('aria-label', 'Close preview');
    close.onclick = () => dialog.close();
    const content = document.createElement(pdf ? 'iframe' : 'img');
    content.src = url;
    if (pdf) content.title = 'Document preview'; else content.alt = 'Uploaded document preview';
    dialog.append(close, content); document.body.append(dialog);
    dialog.addEventListener('close', () => dialog.remove());
    dialog.showModal();
  };
  window.openAccountCapturePreview = id => preview(document.getElementById(id).src);
  root.querySelectorAll('[data-account-upload]').forEach(box => {
    const input = box.querySelector('input'), image = box.querySelector('img');
    const name = box.querySelector('.seller-upload-pdf'), icon = box.querySelector('.seller-upload-pdf-icon');
    const enlarge = box.querySelector('.seller-upload-enlarge'), replace = box.querySelector('.seller-upload-replace');
    let url;
    input.addEventListener('change', () => {
      if (url) URL.revokeObjectURL(url);
      const file = input.files[0];
      const allowed = input.accept.includes('pdf') ? /\.(jpe?g|png|pdf)$/i : /\.(jpe?g|png)$/i;
      input.setCustomValidity(file && (file.size > 5 * 1024 * 1024 || !allowed.test(file.name)) ? 'Choose an accepted file up to 5 MB.' : '');
      const valid = !!file && input.checkValidity(), pdf = valid && /\.pdf$/i.test(file.name);
      box.querySelector('.seller-upload-idle').hidden = valid;
      image.hidden = !valid || pdf; name.hidden = enlarge.hidden = replace.hidden = !valid; icon.hidden = !pdf;
      if (!valid) { image.removeAttribute('src'); if (file) input.reportValidity(); return; }
      url = URL.createObjectURL(file); name.textContent = file.name; name.title = file.name;
      name.classList.toggle('seller-upload-image-name', !pdf);
      if (!pdf) image.src = url;
      enlarge.onclick = () => preview(url, pdf);
    });
    replace.onclick = () => input.click();
  });
  const idInput = document.getElementById('id_file'), selfieInput = document.getElementById('accountSelfieFile');
  if (!idInput || !selfieInput) return;
  const form = idInput.form, video = document.getElementById('selfieVideo');
  const idle = document.getElementById('selfieIdle'), camera = document.getElementById('selfieCamera'), captured = document.getElementById('selfiePreview');
  const error = document.getElementById('selfieError'), snap = camera.querySelector('button');
  let stream, request = 0, selfieUrl, idUrl;
  const showError = message => { error.textContent = message; error.style.display = 'block'; };
  const stop = () => { ++request; stream?.getTracks().forEach(track => track.stop()); stream = null; video.srcObject = null; snap.disabled = true; };
  const syncRequired = () => { document.getElementById('id_type_id').required = !!idInput.files.length; };
  idInput.addEventListener('change', () => {
    if (idUrl) URL.revokeObjectURL(idUrl);
    const file = idInput.files[0];
    idInput.setCustomValidity(file && (file.size > 5 * 1024 * 1024 || !/\.(jpe?g|png|pdf)$/i.test(file.name)) ? 'Choose a JPG, PNG or PDF up to 5 MB.' : '');
    const valid = !!file && idInput.checkValidity();
    document.getElementById('idPhotoIdle').style.display = valid ? 'none' : 'block';
    document.getElementById('idPhotoPreview').style.display = valid ? 'block' : 'none';
    const image = document.getElementById('idPhotoImg');
    const pdf = valid && /\.pdf$/i.test(file.name);
    image.hidden = pdf;
    if (valid) {
      idUrl = URL.createObjectURL(file); if (!pdf) image.src = idUrl;
      let pdfLabel = document.getElementById('accountIdPdfName');
      if (!pdfLabel) { pdfLabel = document.createElement('p'); pdfLabel.id = 'accountIdPdfName'; image.after(pdfLabel); }
      pdfLabel.textContent = pdf ? file.name : ''; pdfLabel.hidden = !pdf;
      document.querySelector('#idPhotoPreview .enlarge-btn').onclick = () => preview(idUrl, pdf);
    }
    syncRequired();
    if (file && !valid) { document.getElementById('idPhotoError').textContent = idInput.validationMessage; document.getElementById('idPhotoError').style.display = 'block'; }
    else document.getElementById('idPhotoError').style.display = 'none';
  });
  window.retakeIdPhoto = () => { idInput.value = ''; idInput.dispatchEvent(new Event('change')); idInput.click(); };
  window.startCamera = async () => {
    stop(); const revision = request; error.style.display = 'none';
    try {
      if (!navigator.mediaDevices?.getUserMedia) throw Error('Camera unavailable. Use a supported browser on HTTPS or localhost.');
      const incoming = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'user'}, audio: false});
      if (revision !== request) { incoming.getTracks().forEach(track => track.stop()); return; }
      stream = incoming; video.srcObject = incoming;
      idle.style.display = captured.style.display = 'none'; camera.style.display = 'block';
      await video.play();
    } catch (failure) { stop(); camera.style.display = 'none'; idle.style.display = 'block'; showError(failure.message || 'Allow camera access to take your selfie.'); }
  };
  video.addEventListener('playing', () => { snap.disabled = false; });
  window.snapSelfie = () => {
    if (!stream || video.readyState < 2 || !video.videoWidth || !stream.getVideoTracks().some(track => track.readyState === 'live' && !track.muted)) return showError('Wait for a live camera image before taking your selfie.');
    const canvas = document.getElementById('selfieCanvas'); canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    const context = canvas.getContext('2d'); context.setTransform(-1, 0, 0, 1, canvas.width, 0); context.drawImage(video, 0, 0);
    const sample = document.createElement('canvas'); sample.width = 32; sample.height = 24;
    const sampleContext = sample.getContext('2d', {willReadFrequently:true}); sampleContext.drawImage(video,0,0,32,24);
    const pixels = sampleContext.getImageData(0,0,32,24).data, colours = new Map(); let largest = 0;
    for (let i=0;i<pixels.length;i+=4) { const key=((pixels[i]>>4)<<8)|((pixels[i+1]>>4)<<4)|(pixels[i+2]>>4); const count=(colours.get(key)||0)+1; colours.set(key,count); largest=Math.max(largest,count); }
    if (largest/(pixels.length/4)>=.94) return showError('Turn on your camera and make sure your face is visible before taking a selfie.');
    stop();
    return new Promise(resolve => canvas.toBlob(blob => {
      if (!blob) { showError('Could not capture your selfie. Try again.'); resolve(false); return; }
      const transfer = new DataTransfer(); transfer.items.add(new File([blob], 'selfie.jpg', {type: 'image/jpeg'})); selfieInput.files = transfer.files;
      if (selfieUrl) URL.revokeObjectURL(selfieUrl); selfieUrl = URL.createObjectURL(blob);
      document.getElementById('selfieImg').src = selfieUrl; camera.style.display = idle.style.display = 'none'; captured.style.display = 'block'; error.style.display = 'none'; syncRequired();
      resolve(true);
    }, 'image/jpeg', .9));
  };
  window.retakeSelfie = () => { selfieInput.value = ''; syncRequired(); window.startCamera(); };
  form.addEventListener('submit', event => {
    syncRequired();
    if (idInput.files.length && !selfieInput.files.length) { event.preventDefault(); showError('Take a selfie to submit with your new ID.'); }
    if (selfieInput.files.length && !idInput.files.length) { event.preventDefault(); document.getElementById('idPhotoError').textContent = 'Upload your new ID to submit with this selfie.'; document.getElementById('idPhotoError').style.display = 'block'; }
    if (!idInput.checkValidity()) { event.preventDefault(); document.getElementById('idPhotoError').textContent = idInput.validationMessage; document.getElementById('idPhotoError').style.display = 'block'; }
  });
  new MutationObserver(() => { if (form.closest('[data-account-panel]')?.hidden) { stop(); camera.style.display = 'none'; if (!selfieInput.files.length) idle.style.display = 'block'; } }).observe(form.closest('.card'), {attributes:true,attributeFilter:['hidden']});
  window.addEventListener('pagehide', stop); snap.disabled = true;
})();

(() => {
 const form=document.querySelector('[data-account-role="seller"]');
 if(!form)return;
    // Make the outlined surface the upload control, retaining preview actions.
    const uploadBoxes = new Set(form.querySelectorAll('.document-upload, .upload-box, #idPhotoBox, #selfieBox, #businessPermitBox'));
    const selfieIdle = document.getElementById('selfieIdle');
    if (selfieIdle) uploadBoxes.add(selfieIdle.parentElement);
    uploadBoxes.forEach(box => {
        const selfie = box.contains(selfieIdle);
        const inputId = ({idPhotoBox:'id_file',businessPermitBox:'business_permit_file',licenseBox:'license_file',orBox:'or_file',crBox:'cr_file'})[box.id];
        const label = box.querySelector('label[for]');
        const input = document.getElementById(inputId || label?.htmlFor) || box.querySelector('input[type="file"]');
        if (!selfie && !input) return;
        if(input) {
            input.classList.add('registration-upload-file');input.tabIndex=-1;box.append(input);
        }
        box.classList.add('registration-upload-surface');
        box.setAttribute('role','button'); box.tabIndex=0;
        box.setAttribute('aria-label',selfie ? 'Take a selfie' : 'Upload or replace '+(input.name || 'document').replaceAll('_',' '));
        box.onclick = null;
        box.querySelectorAll('label[for], [id$="Idle"] button').forEach(control=>control.hidden=true);
        const idle=box.querySelector('[id$="Idle"]');
        if (idle) {
            const controls=Array.from(idle.querySelectorAll('button,label[for]'));
            const icon=document.createElementNS('http://www.w3.org/2000/svg','svg');
            icon.setAttribute('viewBox','0 0 24 24'); icon.setAttribute('fill','none'); icon.setAttribute('stroke','currentColor');
            icon.setAttribute('stroke-width','1.6'); icon.setAttribute('stroke-linecap','round'); icon.setAttribute('stroke-linejoin','round'); icon.setAttribute('aria-hidden','true');
            icon.innerHTML=selfie ? '<path d="M4 6h4l2-3h4l2 3h4a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/><circle cx="12" cy="13" r="4"/>' : '<path d="M12 16V3m-5 5 5-5 5 5M3 15v5a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-5"/>';
            const caption=document.createElement('span'); caption.className='registration-upload-label';
            caption.textContent=selfie ? 'Take Selfie' : 'Upload Document';
            const note=document.createElement('span'); note.className='registration-upload-note';
            note.textContent=selfie ? 'Take a clear photo with your camera' : 'JPG, PNG or PDF · max 5 MB';
            idle.replaceChildren(icon,caption,note,...controls); idle.classList.add('registration-upload-idle');
        }
        if (!selfie) box.querySelectorAll('[id$="Preview"] button:not(.enlarge-btn)').forEach(button=>{
            button.innerHTML='<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5M6.1 7a7 7 0 0 1 11.6-2L20 8M4 16l2.3 3A7 7 0 0 0 17.9 17"/></svg>'; button.setAttribute('aria-label','Reupload document');button.title='Reupload';
            button.onclick=event=>{event.stopPropagation();if(!input.disabled)input.click();};
        });
        const activate = () => {
            if (getComputedStyle(box).pointerEvents === 'none' || input?.disabled) return;
            if (selfie) {
                const camera = document.getElementById('selfieCamera');
                if (camera && getComputedStyle(camera).display !== 'none') return;
                if (!document.getElementById('openCameraBtn')?.disabled) window.startCamera();
            } else input.click();
        };
        box.addEventListener('click',event=>{ if(!event.target.closest('button,a,input,label,video')) activate(); });
        box.addEventListener('keydown',event=>{ if(event.target===box && ['Enter',' '].includes(event.key)){ event.preventDefault(); activate(); } });
    });

})();
