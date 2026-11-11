document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-seller-cancel-open]').forEach(button => {
    const modal = button.closest('[data-order-summary]');
    const panel = modal.querySelector('[data-seller-cancel-panel]');
    button.addEventListener('click', () => {
      panel.hidden = false;
      modal.classList.add('seller-cancelling');
      button.hidden = true;
      panel.scrollIntoView({block: 'nearest', behavior: 'smooth'});
      panel.querySelector('select')?.focus();
    });
    panel.querySelector('[data-seller-cancel-back]').addEventListener('click', () => {
      panel.hidden = true;
      modal.classList.remove('seller-cancelling');
      button.hidden = false;
      button.focus();
    });
    modal.querySelectorAll('[data-modal-close]').forEach(close => close.addEventListener('click', () => {
      panel.hidden = true;
      modal.classList.remove('seller-cancelling');
      button.hidden = false;
    }));
  });
  document.querySelectorAll('[data-seller-order-copy]').forEach(button => {
    button.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(button.dataset.sellerOrderCopy); button.textContent='Copied'; setTimeout(()=>{button.textContent='Copy';},1200); } catch { button.textContent='Copy'; }
    });
  });
  document.querySelectorAll('[data-seller-summary-waybill]').forEach(button => {
    button.addEventListener('click', () => {
      button.closest('.modal-overlay').classList.remove('open');
      if(typeof window.openWaybill==='function') window.openWaybill(button.dataset.sellerSummaryWaybill);
    });
  });
});
