(() => {
 'use strict';
 const page = document.querySelector('.gcash-page[data-purchase]');
 if (!page) return;
 const notice = document.getElementById('gcash-message');
 const confirmation = document.getElementById('gcash-confirm');
 const viewer = document.getElementById('gcash-receipt');
 let busy = false, pending = null, trigger = null, selectedUrl = null, viewerUrl = null, viewerRequest = null;
 const say = (message, state = 'success') => { notice.textContent = message; notice.dataset.state = state; };
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
   if (input.name === 'number' && !/^09[0-9]{9}$/.test(input.value)) input.setCustomValidity('Enter a valid 11-digit Philippine mobile number starting with 09.');
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
  const copy = document.getElementById('gcash-summary').cloneNode(true);
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
   // Only carry server-confirmed state across the reload; no receipt or account values.
   const messages = {proof:'Receipt uploaded successfully. The seller must independently verify payment.', verify:'Payment verified. The buyer must still explicitly place the order.', reject:'Payment proof rejected. The reason is recorded in payment history.'};
   try { sessionStorage.setItem('gcash-result-' + page.dataset.purchase, JSON.stringify({state:result.purchase.payment_state, message:messages[form.dataset.action] || 'Changes saved. Review the updated purchase below.'})); } catch { /* Storage may be disabled. The server state remains authoritative. */ }
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
   const titles = {proof:'Review payment proof', verify:'Verify actual payment', reject:'Reject payment proof', place:'Confirm your order'};
   const labels = {proof:'Submit Payment Proof', verify:'Confirm Payment Verification', reject:'Confirm Rejection', place:'Confirm & Place Order'};
   document.getElementById('gcash-confirm-title').textContent = titles[form.dataset.action] || 'Confirm action';
   document.getElementById('gcash-confirm-description').textContent = form.dataset.confirm;
   const details = confirmation.querySelector('[data-confirm-details]'); details.replaceChildren(summary());
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
 confirmation.querySelector('[data-cancel]').addEventListener('click', () => confirmation.close());
 confirmation.addEventListener('close', () => { pending = null; trigger?.focus(); });
 confirmation.querySelector('[data-confirm-submit]').addEventListener('click', () => { const form = pending; if (!form || busy) return; confirmation.close(); if (validate(form)) submit(form); });
 const receiptInput = page.querySelector('input[name=receipt]');
 const selected = page.querySelector('.gcash-selected');
 const updateSelection = () => {
  if (selectedUrl) URL.revokeObjectURL(selectedUrl); selectedUrl = null;
  const file = receiptInput.files[0]; selected.hidden = !file;
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
  const el = document.createElement(mime === 'application/pdf' ? 'iframe' : 'img');
  if (el.tagName === 'IMG') el.alt = 'Private payment receipt'; else el.title = 'Private PDF receipt';
  el.src = url; el.addEventListener('error', () => { viewerStatus.textContent = 'Receipt preview unavailable. Close the viewer and retry.'; });
  canvas.replaceChildren(el); viewerStatus.textContent = mime === 'application/pdf' ? 'PDF display depends on your browser. If the preview is blank, open the receipt in a new tab using the link below.' : 'Receipt loaded. Fit or zoom to inspect it.';
  if (mime === 'application/pdf') { const fallback = viewer.querySelector('[data-receipt-fallback]'); if (fallback.hidden) { fallback.href = url; fallback.hidden = false; } }
  zoom.value = '100'; zoom.disabled = mime === 'application/pdf';
 };
 const openViewer = opener => { trigger = opener; canvas.replaceChildren(); viewer.querySelector('[data-receipt-fallback]').hidden = true; viewerStatus.textContent = 'Loading private receipt...'; viewer.querySelector('[data-receipt-date]').textContent = opener.dataset.submitted ? 'Submitted: ' + opener.dataset.submitted : 'Selected file, not yet submitted'; viewer.showModal(); };
 page.querySelector('[data-review-selected]')?.addEventListener('click', event => { if (!selectedUrl) return; openViewer(event.currentTarget); renderReceipt(selectedUrl, receiptInput.files[0].type); });
 page.querySelectorAll('[data-private-receipt]').forEach(link => link.addEventListener('click', async event => {
  event.preventDefault(); openViewer(link); const request = new AbortController(); viewerRequest = request;
  try {
   const url = new URL(link.href); if (url.origin !== location.origin) throw new Error('Receipt access must stay on this site.');
   const response = await fetch(url, {credentials:'same-origin', headers:{Accept:'application/json'}, cache:'no-store', signal:request.signal});
   if (!response.ok) throw new Error(response.status === 403 || response.status === 401 ? 'You are not authorized to view this receipt. Sign in with the correct account.' : 'Receipt unavailable. Refresh this purchase and try again.');
   const blob = await response.blob(); if (!viewer.open || request !== viewerRequest || request.signal.aborted) return;
   const fallback = viewer.querySelector('[data-receipt-fallback]'); fallback.href = url.href; fallback.hidden = false;
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
 try { const key = 'gcash-result-' + page.dataset.purchase; const result = JSON.parse(sessionStorage.getItem(key)); sessionStorage.removeItem(key); if (result?.state === page.dataset.paymentState) say(result.message); } catch { /* Optional feedback only. */ }
})();
