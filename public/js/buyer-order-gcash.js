(() => {
  'use strict';

  const receiptPreviewUrls = new WeakMap();
  const paymentNotice = (title, message, confirmLabel, cancellable = false) => new Promise(resolve => {
    const opener = document.activeElement;
    const dialog = document.createElement('dialog');
    dialog.className = 'order-payment-notice';
    const heading = document.createElement('h3');
    heading.textContent = title;
    const description = document.createElement('p');
    description.textContent = message;
    const actions = document.createElement('div');
    const accept = document.createElement('button');
    accept.type = 'button'; accept.className = 'btn btn-primary'; accept.textContent = confirmLabel;
    accept.addEventListener('click', () => dialog.close('confirmed'));
    if (cancellable) {
      const cancel = document.createElement('button');
      cancel.type = 'button'; cancel.className = 'btn btn-outline'; cancel.textContent = 'Cancel';
      cancel.addEventListener('click', () => dialog.close());
      actions.append(cancel);
    }
    actions.append(accept);
    dialog.append(heading, description, actions);
    dialog.setAttribute('aria-label', title);
    dialog.addEventListener('close', () => {
      const confirmed = dialog.returnValue === 'confirmed';
      dialog.remove(); opener?.focus(); resolve(confirmed);
    }, {once: true});
    document.body.append(dialog); dialog.showModal();
  });
  let receiptDialog;
  const showReceiptPreview = (input, opener, savedUrl = null, savedMime = null) => {
    const url = savedUrl || receiptPreviewUrls.get(input);
    const file = savedUrl ? {type:savedMime} : input.files?.[0];
    if (!url || !file) return;
    if (!receiptDialog) {
      receiptDialog = document.createElement('dialog');
      receiptDialog.className = 'order-payment-preview-dialog';
      receiptDialog.setAttribute('aria-label', 'Payment receipt preview');
      receiptDialog.innerHTML = '<button type="button" class="order-payment-preview-close" aria-label="Close receipt preview">&times;</button><div class="order-payment-preview-content"></div>';
      document.body.append(receiptDialog);
      receiptDialog.querySelector('button').addEventListener('click', () => receiptDialog.close());
      receiptDialog.addEventListener('click', event => { if (event.target === receiptDialog) receiptDialog.close(); });
      receiptDialog.addEventListener('close', () => {
        receiptDialog.querySelector('.order-payment-preview-content').pdfPreviewToken = null;
        receiptDialog.querySelector('.order-payment-preview-content').replaceChildren();
        receiptDialog.previewOpener?.focus();
      });
    }
    const isPdf = file.type === 'application/pdf';
    const content = receiptDialog.querySelector('.order-payment-preview-content');
    content.pdfPreviewToken = null;
    content.replaceChildren();
    if (isPdf) {
      const script = document.querySelector('script[src*="buyer-order-gcash.js"]');
      import(new URL('pdf-paper-preview.mjs', script.src).href).then(module => {
        if (receiptDialog.open) module.renderPdfPaper(content, url);
      });
    } else {
      const media = document.createElement('img');
      media.alt = 'Payment receipt'; media.src = url; content.append(media);
    }
    receiptDialog.previewOpener = opener;
    receiptDialog.showModal();
  };
  const updateReceiptName = input => {
    const drop = input.closest('.order-payment-file-drop');
    const name = drop?.querySelector('[data-payment-file-name]');
    const preview = drop?.querySelector('[data-payment-file-preview]');
    const pdfName = drop?.querySelector('[data-payment-file-pdf]');
    const pdfIcon = drop?.querySelector('[data-payment-file-pdf-icon]');
    const uploadIcon = drop?.querySelector(':scope > svg');
    const form = input.closest('[data-order-payment-proof]');
    const previewButton = form?.querySelector('[data-payment-file-preview-open]');
    const retakeButton = form?.querySelector('[data-payment-file-retake]');
    const file = input.files?.[0];
    const previousUrl = receiptPreviewUrls.get(input);
    if (previousUrl) URL.revokeObjectURL(previousUrl);
    receiptPreviewUrls.delete(input);
    if (file) receiptPreviewUrls.set(input, URL.createObjectURL(file));
    if (name) name.textContent = file ? file.name : 'Upload Document';
    if (name) name.hidden = Boolean(file);
    if (uploadIcon) uploadIcon.toggleAttribute('hidden', Boolean(file));
    drop?.classList.toggle('has-file', Boolean(file));
    if (preview) {
      preview.hidden = true;
      preview.removeAttribute('src');
      if (file && /^image\/(jpeg|png|webp)$/.test(file.type)) {
        const url = receiptPreviewUrls.get(input);
        preview.src = url;
        preview.hidden = false;
      }
    }
    if (pdfName) {
      const isPdf = Boolean(file && file.type === 'application/pdf');
      pdfName.hidden = !file;
      pdfName.textContent = file?.name || '';
      pdfName.classList.toggle('is-image-name', Boolean(file && !isPdf));
      pdfName.title = file?.name || '';
    }
    if (pdfIcon) pdfIcon.toggleAttribute('hidden', !(file && file.type === 'application/pdf'));
    if (previewButton) previewButton.hidden = !file;
    if (retakeButton) retakeButton.hidden = !file;
  };

  document.addEventListener('change', event => {
    const input = event.target.closest('[data-payment-file]');
    if (input) updateReceiptName(input);
  });

  document.addEventListener('dragover', event => {
    const drop = event.target.closest('.order-payment-file-drop');
    if (!drop) return;
    event.preventDefault();
    drop.classList.add('is-dragging');
  });

  document.addEventListener('dragleave', event => {
    const drop = event.target.closest('.order-payment-file-drop');
    if (drop && !drop.contains(event.relatedTarget)) drop.classList.remove('is-dragging');
  });

  document.addEventListener('drop', event => {
    const drop = event.target.closest('.order-payment-file-drop');
    if (!drop) return;
    event.preventDefault();
    drop.classList.remove('is-dragging');
    const input = drop.querySelector('[data-payment-file]');
    const file = event.dataTransfer?.files?.[0];
    if (!input || !file) return;
    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    input.dispatchEvent(new Event('change', {bubbles: true}));
  });

  document.addEventListener('click', event => {
    const savedReceipt = event.target.closest('[data-submitted-receipt-url]');
    if (savedReceipt) {
      event.preventDefault();
      showReceiptPreview(null, savedReceipt, savedReceipt.dataset.submittedReceiptUrl, savedReceipt.dataset.submittedReceiptMime);
      return;
    }
    const retake = event.target.closest('[data-payment-file-retake]');
    if (retake) {
      event.preventDefault();
      event.stopPropagation();
      const input = retake.closest('[data-order-payment-proof]')?.querySelector('[data-payment-file]');
      if (!input) return;
      if (typeof input.showPicker === 'function') {
        try { input.showPicker(); } catch { input.click(); }
      } else input.click();
      return;
    }
    const previewButton = event.target.closest('[data-payment-file-preview-open]');
    if (!previewButton) return;
    event.preventDefault();
    event.stopPropagation();
    const input = previewButton.closest('[data-order-payment-proof]')?.querySelector('[data-payment-file]');
    const url = input && receiptPreviewUrls.get(input);
    if (!input?.files?.[0]) return;
    if (url) showReceiptPreview(input, previewButton);
  });

  document.addEventListener('submit', async event => {
    const form = event.target.closest('[data-order-payment-proof], [data-order-payment-help]');
    if (!form) return;
    event.preventDefault();
    if (form.dataset.submitting === 'true' || form.dataset.confirming === 'true') return;

    if (form.matches('[data-order-payment-proof]')) {
      const file = form.elements.receipt.files[0];
      if (!file) { form.elements.receipt.reportValidity(); return; }
      if (!['image/jpeg', 'image/png', 'image/webp', 'application/pdf'].includes(file.type) || file.size > 5 * 1024 * 1024 || file.size === 0) {
        const feedback = form.querySelector('[data-payment-feedback]');
        feedback.textContent = 'Choose a JPEG, PNG, WebP or PDF receipt up to 5 MB.';
        return;
      }
    }
    const isReceipt = form.matches('[data-order-payment-proof]');
    form.dataset.confirming = 'true';
    const confirmed = await paymentNotice(isReceipt ? 'Submit receipt?' : 'Send payment problem?', isReceipt ? 'Submit this receipt to PocketFinds for payment review?' : 'Send this payment problem to PocketFinds for review?', isReceipt ? 'Submit receipt' : 'Send for review', true);
    form.dataset.confirming = 'false';
    if (!confirmed) return;

    const feedback = form.querySelector('[data-payment-feedback]');
    const submit = form.querySelector('button[type="submit"]');
    form.dataset.submitting = 'true';
    submit.disabled = true;
    feedback.textContent = 'Submitting. Please wait for confirmation…';
    try {
      const response = await fetch(form.action, {
        method: 'POST', credentials: 'same-origin',
        headers: {Accept: 'application/json'}, body: new FormData(form),
      });
      let result;
      try { result = await response.json(); } catch { throw new Error('Unexpected server response. Refresh this order before retrying.'); }
      if (!response.ok || !result.purchase) {
        throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'The request could not be completed.');
      }
      const next = new URL(location.href);
      next.searchParams.set('order_details', form.dataset.orderId);
      next.searchParams.set('payment', 'gcash');
      await paymentNotice(isReceipt ? 'Receipt submitted' : 'Payment problem sent', isReceipt ? 'Your receipt has been sent to PocketFinds for review.' : 'Your payment problem has been sent to PocketFinds for review.', 'Done');
      location.assign(next.href);
    } catch (error) {
      feedback.textContent = error.message;
      form.dataset.submitting = 'false';
      submit.disabled = false;
      await paymentNotice('Submission unsuccessful', error.message, 'Close');
    }
  });

  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-copy-payment-number]');
    if (!button) return;
    const number = button.closest('dd')?.querySelector('span')?.textContent.trim();
    if (!number) return;
    try {
      await navigator.clipboard.writeText(number);
      button.title = 'Number copied';
      button.setAttribute('aria-label', 'GCash number copied');
      window.setTimeout(() => { button.title = 'Copy number'; button.setAttribute('aria-label', 'Copy GCash number'); }, 1600);
    } catch {
      button.title = 'Copy is unavailable';
      button.setAttribute('aria-label', 'Copy is unavailable; select the number to copy');
    }
  });
})();
