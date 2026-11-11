(() => {
  const widget = document.getElementById('sellerMessagesWidget');
  if (!widget) return;
  const body = widget.querySelector('.chat-widget-body');
  let opener;
  const close = () => {
    widget.classList.remove('open');
    widget.setAttribute('aria-hidden', 'true');
    body.replaceChildren();
    opener?.focus();
  };
  document.addEventListener('click', event => {
    if (event.target.closest('[data-seller-messages-close]')) { close(); return; }
    const button = event.target.closest('[data-seller-messages], a[href]');
    if (!button || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
    let target;
    try { target = new URL(button.dataset.sellerMessages || button.href, location.origin); } catch { return; }
    if (target.origin !== location.origin || target.pathname !== '/seller/messages') return;
    if (location.pathname === '/seller/messages') return;
    event.preventDefault();
    opener = button;
    if (widget.classList.contains('open') && widget.dataset.url === target.href) { close(); return; }
    widget.dataset.url = target.href;
    target.searchParams.set('embedded', '1');
    const frame = document.createElement('iframe');
    frame.className = 'buyer-messages-frame';
    frame.title = 'Messages with buyers';
    frame.src = target.href;
    body.replaceChildren(frame);
    const assistant = document.getElementById('pfAssistantPanel');
    if (assistant) assistant.hidden = true;
    document.querySelector('.pf-assistant-launch')?.setAttribute('aria-expanded', 'false');
    widget.classList.add('open');
    widget.setAttribute('aria-hidden', 'false');
    widget.querySelector('[data-seller-messages-close]').focus();
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && widget.classList.contains('open')) close(); });
  window.addEventListener('message', event => { if (event.origin === location.origin && event.source === body.querySelector('iframe')?.contentWindow && event.data === 'seller-close-messages') close(); });
})();
