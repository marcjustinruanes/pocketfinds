(() => {
 'use strict';
 const page = document.querySelector('.gcash-page[data-purchase]');
 if (!page) return;
 page.querySelectorAll('[data-cancellation-repaid]').forEach(checkbox => {
  const form = checkbox.closest('form');
  const proof = form.querySelector('[data-cancellation-refund-proof]');
  const syncRefundProof = () => {
   proof.hidden = !checkbox.checked;
   proof.querySelectorAll('input,button').forEach(control => { control.disabled = !checkbox.checked; });
  };
  checkbox.addEventListener('change', syncRefundProof);
  syncRefundProof();
 });
 const notice = document.getElementById('gcash-message');
 const confirmation = document.getElementById('gcash-confirm');
 const viewer = document.getElementById('gcash-receipt');
 let popupHost = null;
 if (document.body.classList.contains('gcash-embedded')) {
  try {
   const hostDocument = window.parent.document;
   if (hostDocument !== document && confirmation && viewer) {
    if (!hostDocument.getElementById('gcash-popup-stylesheet')) {
     const script = document.querySelector('script[src*="gcash.js"]');
     const stylesheet = hostDocument.createElement('link');
     stylesheet.id = 'gcash-popup-stylesheet'; stylesheet.rel = 'stylesheet';
     stylesheet.href = new URL('../css/gcash.css', script.src).href;
     hostDocument.head.append(stylesheet);
    }
    popupHost = hostDocument.createElement('div'); popupHost.className = 'gcash-page gcash-popup-host';
    popupHost.append(confirmation, viewer); hostDocument.body.append(popupHost);
    window.addEventListener('pagehide', () => popupHost?.remove(), {once:true});
   }
  } catch { /* Keep dialogs in this document when it cannot access the parent. */ }
 }
 let busy = false, pending = null, trigger = null, selectedUrl = null, viewerUrl = null, viewerRequest = null, buyerPreviewDialog = null;
 const say = (message, state = 'success') => { notice.textContent = message; notice.dataset.state = state; };
 const successNotice = (message, title = 'Submitted successfully') => new Promise(resolve => {
  const owner = confirmation.ownerDocument;
  const dialog = owner.createElement('dialog');
  dialog.className = 'gcash-dialog gcash-success-dialog';
  const heading = owner.createElement('h2'); heading.textContent = title;
  heading.id = 'gcash-success-title'; dialog.setAttribute('aria-labelledby', heading.id);
  const description = owner.createElement('p'); description.textContent = message;
  const actions = owner.createElement('div'); actions.className = 'gcash-actions';
  const close = owner.createElement('button'); close.type = 'button'; close.className = 'gcash-button'; close.textContent = 'Close';
  actions.append(close); dialog.append(heading, description, actions);
  (popupHost || page).append(dialog);
  close.addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => { dialog.remove(); resolve(); }, {once:true});
  notice.textContent = '';
  dialog.showModal(); close.focus();
 });
 const fieldError = (input, message) => {
  input.setAttribute('aria-invalid', 'true');
  const error = input.parentElement.querySelector('[data-field-error]');
  if (error) error.textContent = message;
 };
 const clearError = input => {
  input.setCustomValidity(''); input.removeAttribute('aria-invalid');
  const error = input.parentElement.querySelector('[data-field-error]');
  if (error) error.textContent = '';
 };
 const validate = form => {
  form.querySelectorAll('input,textarea,select').forEach(input => {
   clearError(input);
   if (input.matches('[data-account-name]')) {
    input.value = input.value.trim();
    if (!/^[\p{L}\p{N}]+(?: [\p{L}\p{N}]+)*$/u.test(input.value)) input.setCustomValidity('Use letters, numbers and single spaces.');
   }
   if (input.name === 'reference') input.value = input.value.trim();
   if (input.name === 'number' && !/^9[0-9]{9}$/.test(input.value)) input.setCustomValidity('Enter 10 digits starting with 9 after +63.');
   if (input.type === 'file' && input.files[0]) {
    const file = input.files[0];
    if (!['image/jpeg','image/png','image/webp','application/pdf'].includes(file.type) || file.size > 5 * 1024 * 1024 || file.size === 0) input.setCustomValidity('Choose a JPEG, PNG, WebP or PDF up to 5 MB.');
   }
   if (!input.validity.valid) fieldError(input, input.validationMessage);
  });
  if (!form.checkValidity()) { form.querySelector('[aria-invalid]')?.focus(); return false; }
  return true;
 };
 const summary = () => {
  const source = document.getElementById('gcash-summary') || page.querySelector('.gcash-modal-summary');
  if (!source) return document.createElement('div');
  const copy = source.cloneNode(true);
  copy.removeAttribute('id');
  copy.querySelectorAll('[id], .gcash-actions, details').forEach(el => el.remove());
  return copy;
 };
 const textDetail = (parent, label, value) => {
  if (!value) return;
  const p = document.createElement('p'); p.textContent = label + ': ' + value; parent.append(p);
 };
 const submit = async form => {
  if (busy) return;
  busy = true; form.setAttribute('aria-busy', 'true');
  const buttons = [...page.querySelectorAll('.gcash-form button, [data-confirm-submit]')];
  buttons.forEach(button => button.disabled = true);
  say(form.dataset.action === 'proof' ? 'Uploading receipt. Please wait for server confirmation...' : 'Submitting. Please wait for server confirmation...', 'pending');
  try {
   const response = await fetch(form.action, {method:'POST', credentials:'same-origin', headers:{Accept:'application/json'}, body:new FormData(form)});
   let result;
   try { result = await response.json(); } catch { throw new Error('The server returned an unexpected response. Refresh to check the purchase before retrying.'); }
   if (!response.ok) {
    Object.entries(result.errors || {}).forEach(([name, messages]) => {
     const input = form.elements.namedItem(name);
     if (input?.setCustomValidity) fieldError(input, [].concat(messages).join(' '));
    });
    throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to submit.');
   }
   if (result.order) { location.assign(page.dataset.ordersUrl); return; }
   if (!result.purchase) throw new Error('Confirmation is missing. Refresh to check the purchase before retrying.');
   // Show success only after the server confirms the saved payment state.
   const platform=page.dataset.reviewFlow==='admin';
   const messages = {proof:platform?'Receipt uploaded. Waiting for admin payment approval.':'Receipt uploaded successfully. The seller must independently verify payment.', verify:platform?'Payment approved. The seller has been notified and can now confirm and prepare the order.':'Payment verified. The buyer must still explicitly place the order.', reject:'The buyer has been notified about the payment proof.'};
   const titles = {proof:'Receipt submitted', verify:'Payment approved', reject:'Buyer notified', refund:'Refund completed'};
   await successNotice(result.message || messages[form.dataset.action] || 'Your changes have been saved.', titles[form.dataset.action] || 'Submitted successfully');
   location.reload();
  } catch (error) {
   say(error.message, 'error'); (form.querySelector('[aria-invalid]') || notice).focus();
   busy = false; form.removeAttribute('aria-busy'); buttons.forEach(button => button.disabled = false);
  }
 };
 page.querySelectorAll('.gcash-form').forEach((form, index) => {
  form.noValidate = true;
  form.querySelectorAll('input:not([type=hidden]),textarea,select').forEach((input, field) => {
   const error = document.createElement('span'); error.id = `gcash-error-${index}-${field}`; error.dataset.fieldError = ''; error.className = 'gcash-field-error'; error.setAttribute('aria-live', 'polite');
   input.parentElement.append(error); input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
   input.addEventListener('input', () => clearError(input));
   if (input.name === 'reference') { input.pattern = '[A-Za-z0-9-]{6,64}'; input.minLength = 6; const help = document.createElement('small'); help.textContent = '6 to 64 letters, numbers or hyphens, for example TEST-123456. Use the actual transaction reference.'; input.parentElement.append(help); }
  });
  form.addEventListener('submit', event => {
   event.preventDefault(); if (busy || pending || !validate(form)) return;
   if (!form.dataset.confirm) { submit(form); return; }
   if (typeof confirmation.showModal !== 'function') { say('This browser cannot display the required review dialog. Use a current browser.', 'error'); return; }
   pending = form; trigger = event.submitter || document.activeElement;
   const titles = {proof:'Review payment proof', verify:'Verify actual payment', reject:'Notify buyer', place:'Confirm your order', refund:'Confirm returned payment'};
   const labels = {proof:'Submit Payment Proof', verify:'Confirm Payment Verification', reject:'Notify buyer', place:'Confirm & Place Order', refund:'Confirm refund'};
   confirmation.querySelector('#gcash-confirm-title').textContent = titles[form.dataset.action] || 'Confirm action';
   confirmation.querySelector('#gcash-confirm-description').textContent = form.dataset.confirm;
   const details = confirmation.querySelector('[data-confirm-details]');
   details.replaceChildren();
   if (!['reject', 'verify', 'refund'].includes(form.dataset.action)) details.append(summary());
   for (const name of ['amount','reference','reason']) textDetail(details, name === 'amount' ? 'Actual amount received' : name === 'reference' ? 'Transaction reference' : 'Reason', form.elements.namedItem(name)?.value);
   if (form.dataset.action === 'proof') {
    textDetail(details, 'Selected receipt', form.elements.receipt.files[0]?.name);
    textDetail(details, 'Payee', document.querySelector('#payment-instructions .gcash-fields')?.textContent);
    if (selectedUrl && form.elements.receipt.files[0]?.type.startsWith('image/')) { const img = document.createElement('img'); img.src = selectedUrl; img.alt = 'Receipt to submit'; img.className = 'gcash-confirm-image'; details.append(img); }
   }
   confirmation.querySelector('[data-confirm-submit]').textContent = labels[form.dataset.action] || 'Confirm';
   confirmation.showModal();
  });
 });
 page.querySelectorAll('[data-rejection-choice]').forEach(select => {
  const fields = select.form.querySelector('[data-underpayment-fields]');
  const returnChoice = select.form.querySelector('[data-returned-payment-choice]');
  const returnToggle = returnChoice?.querySelector('[data-returned-payment-toggle]');
  const returnFields = returnChoice?.querySelector('[data-return-proof-fields]');
  const clearReturnedProof = () => {
   const input = returnFields?.querySelector('[data-admin-received-proof]');
   if (input?.files?.length) { input.value = ''; input.dispatchEvent(new Event('change', {bubbles:true})); }
  };
  const sync = () => {
   const underpaid = select.value === 'underpaid';
   fields.hidden = !underpaid;
   fields.querySelectorAll('input').forEach(input => { input.required = underpaid; if (!underpaid) { input.value = ''; clearError(input); } });
   const mayReturn = select.value === 'other';
   returnChoice.hidden = !mayReturn;
   if (!mayReturn && returnToggle.checked) { returnToggle.checked = false; clearReturnedProof(); }
   returnFields.hidden = !returnToggle.checked;
   const refundProof = returnFields.querySelector('[data-admin-received-proof]');
   refundProof.required = returnToggle.checked;
   if (!returnToggle.checked) clearReturnedProof();
  };
  select.addEventListener('change', sync); sync();
  returnToggle?.addEventListener('change', sync);
 });
 confirmation.querySelector('[data-cancel]').addEventListener('click', () => confirmation.close());
 confirmation.addEventListener('close', () => { pending = null; trigger?.focus(); });
 confirmation.querySelector('[data-confirm-submit]').addEventListener('click', () => { const form = pending; if (!form || busy) return; confirmation.close(); if (validate(form)) submit(form); });
 const receiptInput = page.querySelector('input[name=receipt]');
 const selected = page.querySelector('.gcash-selected');
 const updateSelection = () => {
  if (selectedUrl) URL.revokeObjectURL(selectedUrl); selectedUrl = null;
  const file = receiptInput.files[0]; selected.hidden = !file;
  const fileName = page.querySelector('[data-gcash-file-name]');
  if (fileName) fileName.textContent = file ? file.name : 'Upload Document';
  page.querySelector('.gcash-file-drop')?.classList.toggle('has-file', Boolean(file));
  const img = selected.querySelector('img'); img.removeAttribute('src'); img.hidden = true;
  if (!file) return;
  selected.querySelector('[data-selected-file]').textContent = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
  if (validate(receiptInput.form)) { selectedUrl = URL.createObjectURL(file); if (file.type.startsWith('image/')) { img.src = selectedUrl; img.hidden = false; } }
 };
 if (receiptInput) {
  receiptInput.addEventListener('change', updateSelection);
  page.querySelector('[data-remove]').addEventListener('click', () => { receiptInput.value = ''; clearError(receiptInput); updateSelection(); receiptInput.focus(); });
  page.querySelector('[data-replace]').addEventListener('click', () => receiptInput.click());
 }
 const canvas = viewer.querySelector('.gcash-receipt-canvas');
 const viewerStatus = viewer.querySelector('[data-viewer-status]');
 const zoom = viewer.querySelector('[data-zoom]');
 const renderReceipt = (url, mime) => {
  if (!['image/jpeg','image/png','image/webp','application/pdf'].includes(mime)) throw new Error('This file type cannot be previewed.');
  if (page.dataset.reviewer === '1') {
   const content = buyerPreviewDialog?.querySelector('.order-payment-preview-content');
   if (!content) return;
   content.pdfPreviewToken = null; content.replaceChildren();
   if (mime === 'application/pdf') {
    const script = document.querySelector('script[src*="gcash.js"]');
    import(new URL('pdf-paper-preview.mjs', script.src).href).then(module => { if (buyerPreviewDialog?.open) module.renderPdfPaper(content, url); });
   } else {
    const image = document.createElement('img'); image.alt = 'Payment receipt'; image.src = url; content.append(image);
   }
   return;
  }
  const el = document.createElement(mime === 'application/pdf' ? 'iframe' : 'img');
  if (el.tagName === 'IMG') el.alt = 'Private payment receipt'; else el.title = 'Private PDF receipt';
  el.src = url; el.addEventListener('error', () => { viewerStatus.textContent = 'Receipt preview unavailable. Close the viewer and retry.'; });
  canvas.replaceChildren(el); viewerStatus.textContent = mime === 'application/pdf' ? 'PDF display depends on your browser. If the preview is blank, open the receipt in a new tab using the link below.' : 'Receipt loaded. Fit or zoom to inspect it.';
  if (mime === 'application/pdf') { const fallback = viewer.querySelector('[data-receipt-fallback]'); if (fallback.hidden) { fallback.href = url; fallback.hidden = false; } }
  zoom.value = '100'; zoom.disabled = mime === 'application/pdf';
 };
 const openViewer = opener => {
  trigger = opener;
  if (page.dataset.reviewer === '1') {
   if (!buyerPreviewDialog) {
    let previewDocument = document;
    try { previewDocument = window.parent.document; } catch { /* Keep the preview in this document if the host page is cross-origin. */ }
    if (!previewDocument.getElementById('gcash-parent-receipt-preview-style')) {
     const style = previewDocument.createElement('style');
     style.id = 'gcash-parent-receipt-preview-style';
     style.textContent = '.order-payment-preview-dialog{box-sizing:border-box;width:100vw;max-width:100vw;height:100dvh;max-height:100dvh;margin:0;padding:32px;border:0;border-radius:0;background:transparent;color:#fff;overflow:auto}.order-payment-preview-dialog[open]{display:grid;place-items:center}.order-payment-preview-dialog::backdrop{background:rgb(37 24 34 / 65%)}.order-payment-preview-close{position:fixed;top:12px;right:18px;z-index:2;width:36px;height:36px;padding:0;border:0;background:transparent;color:#fff;font-size:32px;cursor:pointer}.order-payment-preview-content{display:grid;place-items:center;max-width:100%;background:transparent}.order-payment-preview-content img{display:block;max-width:calc(100vw - 64px);max-height:calc(100dvh - 64px);object-fit:contain}.order-payment-preview-content:has(canvas){display:flex;flex-direction:column;align-items:center;gap:16px;width:min(900px,calc(100vw - 64px));max-height:calc(100dvh - 64px);overflow:auto}.order-payment-preview-content canvas{display:block;width:100%;height:auto;flex:none;background:#fff}';
     previewDocument.head.append(style);
    }
    buyerPreviewDialog = previewDocument.createElement('dialog');
    buyerPreviewDialog.className = 'order-payment-preview-dialog';
    buyerPreviewDialog.setAttribute('aria-label','Payment receipt preview');
    buyerPreviewDialog.innerHTML = '<button type="button" class="order-payment-preview-close" aria-label="Close receipt preview">&times;</button><div class="order-payment-preview-content"></div>';
    (popupHost || previewDocument.body).append(buyerPreviewDialog);
    buyerPreviewDialog.querySelector('button').addEventListener('click',() => buyerPreviewDialog.close());
    buyerPreviewDialog.addEventListener('click',event => { if (event.target === buyerPreviewDialog) buyerPreviewDialog.close(); });
    buyerPreviewDialog.addEventListener('close',() => { viewerRequest?.abort(); const content=buyerPreviewDialog.querySelector('.order-payment-preview-content'); content.pdfPreviewToken=null; content.replaceChildren(); if(viewerUrl){URL.revokeObjectURL(viewerUrl);viewerUrl=null;} buyerPreviewDialog.previewOpener?.focus(); });
   }
   buyerPreviewDialog.previewOpener=opener;
   buyerPreviewDialog.querySelector('.order-payment-preview-content').replaceChildren();
   buyerPreviewDialog.showModal();
   return;
  }
  canvas.replaceChildren(); viewer.querySelector('[data-receipt-fallback]').hidden = true; viewerStatus.textContent = 'Loading private receipt...'; viewer.querySelector('[data-receipt-date]').textContent = opener.dataset.submitted ? 'Submitted: ' + opener.dataset.submitted : 'Selected file, not yet submitted'; viewer.showModal();
 };
 page.querySelector('[data-review-selected]')?.addEventListener('click', event => { if (!selectedUrl) return; openViewer(event.currentTarget); renderReceipt(selectedUrl, receiptInput.files[0].type); });
 const adminProofUrls = new Map();
 const updateAdminProof = input => {
  const drop = input.closest('.order-payment-file-drop');
  const file = input.files?.[0];
  const oldUrl = adminProofUrls.get(input);
  if (oldUrl) URL.revokeObjectURL(oldUrl);
  adminProofUrls.delete(input);
  if (file) adminProofUrls.set(input, URL.createObjectURL(file));
  const name = drop?.querySelector('[data-payment-file-name]');
  const preview = drop?.querySelector('[data-payment-file-preview]');
  const pdfName = drop?.querySelector('[data-payment-file-pdf]');
  const pdfIcon = drop?.querySelector('[data-payment-file-pdf-icon]');
  const uploadIcon = drop?.querySelector(':scope > svg:not([data-payment-file-pdf-icon])');
  const previewButton = input.form?.querySelector('[data-admin-proof-preview]');
  const retakeButton = input.form?.querySelector('[data-admin-proof-retake]');
  if (name) { name.textContent = file?.name || 'Upload Document'; name.hidden = Boolean(file); }
  if (uploadIcon) uploadIcon.toggleAttribute('hidden', Boolean(file));
  drop?.classList.toggle('has-file', Boolean(file));
  if (preview) {
   preview.hidden = true;
   preview.removeAttribute('src');
   if (file && /^image\/(jpeg|png|webp)$/.test(file.type)) { preview.src = adminProofUrls.get(input); preview.hidden = false; }
  }
  if (pdfName) {
   const isPdf = Boolean(file && file.type === 'application/pdf');
   pdfName.hidden = !file;
   pdfName.textContent = file?.name || '';
   pdfName.classList.toggle('is-image-name', Boolean(file && !isPdf));
   pdfName.title = file?.name || '';
  }
  if (pdfIcon) pdfIcon.toggleAttribute('hidden', !(file && file.type === 'application/pdf'));
  previewButton?.toggleAttribute('hidden', !file);
  retakeButton?.toggleAttribute('hidden', !file);
  clearError(input);
 };
 page.querySelectorAll('[data-admin-received-proof]').forEach(input => {
  input.addEventListener('change', () => updateAdminProof(input));
  input.closest('.gcash-admin-proof-upload')?.addEventListener('dragover', event => { event.preventDefault(); event.currentTarget.classList.add('is-dragging'); });
  input.closest('.gcash-admin-proof-upload')?.addEventListener('dragleave', event => { if (!event.currentTarget.contains(event.relatedTarget)) event.currentTarget.classList.remove('is-dragging'); });
  input.closest('.gcash-admin-proof-upload')?.addEventListener('drop', event => {
   event.preventDefault(); event.currentTarget.classList.remove('is-dragging');
   const file = event.dataTransfer?.files?.[0];
   if (!file) return;
   const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files;
   input.dispatchEvent(new Event('change', {bubbles:true}));
  });
  input.form?.querySelector('[data-admin-proof-preview]')?.addEventListener('click', event => {
   const file = input.files?.[0], url = adminProofUrls.get(input);
   if (!file || !url) return;
   openViewer(event.currentTarget); renderReceipt(url, file.type);
  });
  input.form?.querySelector('[data-admin-proof-retake]')?.addEventListener('click', () => {
   if (typeof input.showPicker === 'function') { try { input.showPicker(); } catch { input.click(); } } else input.click();
  });
 });
 window.addEventListener('pagehide', () => adminProofUrls.forEach(url => URL.revokeObjectURL(url)), {once:true});
 page.querySelectorAll('[data-private-receipt]').forEach(link => link.addEventListener('click', async event => {
  event.preventDefault(); openViewer(link); const request = new AbortController(); viewerRequest = request;
  try {
   const receiptUrl = link.dataset.privateReceiptUrl || link.href;
   if (!receiptUrl) throw new Error('Receipt unavailable. Refresh this purchase and try again.');
   const url = new URL(receiptUrl, location.href); if (url.origin !== location.origin) throw new Error('Receipt access must stay on this site.');
   const response = await fetch(url, {credentials:'same-origin', headers:{Accept:'application/json'}, cache:'no-store', signal:request.signal});
   if (!response.ok) throw new Error(response.status === 403 || response.status === 401 ? 'You are not authorized to view this receipt. Sign in with the correct account.' : 'Receipt unavailable. Refresh this purchase and try again.');
   const blob = await response.blob(); const previewOpen = page.dataset.reviewer === '1' ? buyerPreviewDialog?.open : viewer.open; if (!previewOpen || request !== viewerRequest || request.signal.aborted) return;
   if (page.dataset.reviewer !== '1') { const fallback = viewer.querySelector('[data-receipt-fallback]'); fallback.href = url.href; fallback.hidden = false; }
   viewerUrl = URL.createObjectURL(blob); renderReceipt(viewerUrl, blob.type);
  } catch (error) { if (error.name !== 'AbortError') viewerStatus.textContent = error.message; }
 }));
 viewer.querySelector('[data-close-receipt]').addEventListener('click', () => viewer.close());
 viewer.addEventListener('close', () => { viewerRequest?.abort(); canvas.replaceChildren(); if (viewerUrl) URL.revokeObjectURL(viewerUrl); viewerUrl = null; trigger?.focus(); });
 zoom.addEventListener('input', () => { const img = canvas.querySelector('img'); if (img) { img.style.width = zoom.value + '%'; img.style.maxWidth = 'none'; } });
 viewer.querySelector('[data-fit]').addEventListener('click', () => { zoom.value = '100'; canvas.querySelector('img')?.removeAttribute('style'); });
 page.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', async () => {
  try { await navigator.clipboard.writeText(document.getElementById(button.dataset.copy).textContent.trim()); say('Mobile number copied.'); } catch { say('Copy is unavailable. Select and copy the mobile number manually.', 'error'); }
 }));
 try { const key = 'gcash-result-' + page.dataset.purchase; const result = JSON.parse(sessionStorage.getItem(key)); sessionStorage.removeItem(key); if (result?.state === page.dataset.paymentState) successNotice(result.message); } catch { /* Optional feedback only. */ }
})();
