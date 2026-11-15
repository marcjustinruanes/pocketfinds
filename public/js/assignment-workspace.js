(() => {
  const modal = document.getElementById('assignmentDetailsModal');
  const content = document.getElementById('assignmentDetailsContent');
  if (!modal || !content) return;

  const back = modal.querySelector('[data-assignment-details-back]');
  const titleEl = document.getElementById('assignmentDetailsTitle');
  const kickerEl = document.getElementById('assignmentDetailsKicker');
  let pending, history = [], current = '';

  function setLoading() {
    content.innerHTML = '<div class="assignment-details-loading"><span></span><p>Loading details\u2026</p></div>';
  }

  async function load(url, remember = true) {
    const target = new URL(url, location.origin);
    if (target.origin !== location.origin || !target.pathname.startsWith('/logistics/')) return;
    if (remember && current && current !== target.href) history.push(current);
    current = target.href;
    pending?.abort();
    pending = new AbortController();
    modal.classList.add('open');
    setLoading();
    if (titleEl) titleEl.textContent = 'Loading\u2026';
    back.hidden = !history.length;
    try {
      const response = await fetch(target.href, {
        credentials: 'same-origin',
        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        signal: pending.signal,
      });
      if (!response.ok) throw Error('load');
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      const details = page.querySelector('.assignment-parcel-details, .hub-transfer-detail-page');
      if (!details) throw Error('content');
      content.replaceChildren(document.importNode(details, true));
      const detailTitle = details.dataset.detailTitle || 'Hub transfer details';
      if (titleEl) titleEl.textContent = detailTitle;
      const isTransfer = details.classList.contains('hub-transfer-detail-page');
      if (kickerEl) kickerEl.textContent = isTransfer ? 'Hub transfer' : 'Parcel details';
      try { sessionStorage.setItem('assignment-details-url', target.pathname + target.search); } catch {}
    } catch (error) {
      if (error.name === 'AbortError') return;
      content.innerHTML = '<p style="padding:24px;color:var(--muted,#987f8d);font-size:12px">Details could not be loaded. Please try again.</p>';
      if (titleEl) titleEl.textContent = 'Error';
    }
  }

  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-assignment-details-url], [data-transfer-details-url]');
    if (trigger) {
      event.preventDefault();
      load(trigger.dataset.assignmentDetailsUrl || trigger.dataset.transferDetailsUrl);
      return;
    }
    const page = event.target.closest('#assignmentDetailsContent nav[role=navigation] a[href]');
    if (page) { event.preventDefault(); load(page.href, false); return; }
    if (event.target.closest('[data-modal-close]')?.closest('#assignmentDetailsModal')) pending?.abort();
  });

  back.addEventListener('click', () => { const url = history.pop(); if (url) load(url, false); });
  modal.addEventListener('click', event => { if (event.target === modal) pending?.abort(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') pending?.abort(); });

  if (modal.dataset.reopen === 'true') {
    try { const last = sessionStorage.getItem('assignment-details-url'); if (last) load(last, false); } catch {}
  }
})();
