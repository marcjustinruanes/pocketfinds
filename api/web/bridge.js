(() => {
  'use strict';
  const send = (action, data = {}) => window.PocketFindsMobile?.postMessage(JSON.stringify({
    action, ...data, nonce: window.pocketFindsBridgeNonce,
  }));
  document.addEventListener('click', event => {
    if (event.target.closest?.('a[href*="/buyer/account/google/email"]')) {
      event.preventDefault(); send('google-email'); return;
    }
    const link = event.target.closest?.('.chat-doc-bubble');
    if (!link) return;
    event.preventDefault();
    send('document', {url: link.href});
  });
  const start = () => {
    // The website itself supplies every page, style, form and shopping action.
    // These links expose only the explicitly retained mobile-only screens.
    const menu = document.querySelector('.pf-buyer-account-navigation') || document.querySelector('.pf-buyer-dropdown');
    if (menu && !menu.querySelector('[data-mobile-settings]')) {
      const settings = document.createElement('a');
      settings.href = '/mobile-buyer/settings'; settings.dataset.mobileSettings = 'true'; settings.textContent = 'Settings';
      menu.append(settings);
    }
    if (location.pathname === '/buyer/account') {
      const summary = document.querySelector('.profile-summary-info') || document.querySelector('main');
      if (summary) {
        const settings = document.createElement('a'); settings.href = '/mobile-buyer/settings';
        settings.className = 'btn btn-outline'; settings.textContent = 'App Settings'; summary.append(settings);
      }
    }
    // Preserve selections when an older native cart/buy-now entry opens the web cart.
    // The website's own change handlers still calculate totals and validate checkout.
    if (location.pathname === '/buyer/cart' && new URLSearchParams(location.search).has('mobile_items')) {
      requestAnimationFrame(() => {
        const selected = new Set(new URLSearchParams(location.search).get('mobile_items').split(','));
        const inputs = [...document.querySelectorAll('.cart-select')];
        for (const input of inputs) input.checked = selected.has(input.closest('.cart-item')?.querySelector('[data-edit-key]')?.dataset.editKey);
        inputs[0]?.dispatchEvent(new Event('change', {bubbles: true}));
      });
    }
    if (location.pathname === '/mobile-buyer/settings') send('settings');
    if (location.pathname === '/mobile-buyer/payments') send('payments');
    const labels = {pending: 'Pending Payment', pending_verification: 'Awaiting Payment Verification', paid: 'Payment Verified', rejected: 'Payment Verification Failed', refunded: 'Refunded', expired: 'Payment Expired'};
    for (const payment of window.pocketFindsMobilePayments || []) {
      const details = document.querySelector(`[data-order-details="orderDetails${payment.order_id}"]`);
      const card = details?.closest('.order-card');
      if (!card) continue;
      const panel = document.createElement('div'); panel.className = 'order-actions';
      panel.style.cssText = 'padding:12px 16px;border-top:1px solid var(--border);display:flex;flex-wrap:wrap;gap:10px;align-items:center';
      const status = document.createElement('strong'); status.textContent = `GCash · ${labels[payment.status] || payment.status}`; panel.append(status);
      if (payment.status === 'pending_verification') {
        const note = document.createElement('p'); note.textContent = 'Receipt submitted. PocketFinds admin is checking the transaction; payment is not yet verified.'; panel.append(note);
      }
      for (const [key, title] of [['submitted_at', 'Submitted'], ['verification_deadline', 'Verification deadline']]) {
        if (payment[key]) { const text = document.createElement('span'); text.textContent = `${title}: ${new Date(payment[key]).toLocaleString()}`; panel.append(text); }
      }
      if (payment.rejection_reason) { const reason = document.createElement('p'); reason.textContent = `Reason: ${payment.rejection_reason}`; panel.append(reason); }
      if (payment.can_submit) {
        const pay = document.createElement('button'); pay.className = 'btn btn-primary'; pay.type = 'button';
        pay.textContent = payment.status === 'rejected' ? 'Submit additional evidence' : 'Continue GCash payment';
        pay.addEventListener('click', () => send('payment', {order_id: payment.order_id})); panel.append(pay);
      }
      const tracking = document.createElement('button'); tracking.className = 'btn btn-outline'; tracking.type = 'button'; tracking.textContent = 'Payment and delivery details';
      tracking.addEventListener('click', () => send('order', {order_id: payment.order_id})); panel.append(tracking);
      card.append(panel);
    }
    for (const details of document.querySelectorAll('[data-order-details^="orderDetails"]')) {
      const card = details.closest('.order-card');
      if (card) card.id = details.dataset.orderDetails.replace(/^orderDetails/, 'order-');
    }
    if (location.hash.startsWith('#order-')) document.getElementById(location.hash.slice(1))?.scrollIntoView({block: 'start'});
    // Browser sharing/target=_blank stay within the trusted navigation delegate.
    document.querySelectorAll('a[target="_blank"]').forEach(a => a.removeAttribute('target'));
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true});
  else start();
})();
