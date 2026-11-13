(() => {
  const modal = document.getElementById('transferDetailsModal');
  const content = document.getElementById('transferDetailsContent');
  const fullPageLink = document.getElementById('transferDetailsFullPage');
  if (!modal || !content || !fullPageLink) return;

  let requestController = null;

  const setMessage = (message) => {
    const paragraph = document.createElement('p');
    paragraph.className = 'logistics-transfer-details-loading';
    paragraph.textContent = message;
    content.replaceChildren(paragraph);
  };

  const loadDetails = async (url) => {
    requestController?.abort();
    requestController = new AbortController();
    setMessage('Loading transfer details…');

    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        signal: requestController.signal,
      });
      if (!response.ok) throw new Error(`Request failed (${response.status}).`);

      const documentView = new DOMParser().parseFromString(await response.text(), 'text/html');
      const details = documentView.querySelector('.hub-transfer-detail-page');
      if (!details) throw new Error('Transfer details could not be loaded.');
      content.replaceChildren(document.importNode(details, true));
    } catch (error) {
      if (error.name === 'AbortError') return;
      setMessage('Transfer details could not be loaded. Open the full page to review this transfer.');
    }
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-transfer-details-url]');
    if (trigger) {
      event.preventDefault();
      fullPageLink.href = trigger.href;
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      loadDetails(trigger.dataset.transferDetailsUrl);
      return;
    }

    const pageLink = event.target.closest('#transferDetailsContent nav[role="navigation"] a[href]');
    if (pageLink && modal.classList.contains('open')) {
      event.preventDefault();
      loadDetails(pageLink.href);
    }

    if (event.target.closest('[data-modal-close]')?.closest('#transferDetailsModal')) {
      modal.setAttribute('aria-hidden', 'true');
      requestController?.abort();
    }
  });

  modal.addEventListener('click', (event) => {
    if (event.target === modal) modal.setAttribute('aria-hidden', 'true');
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('open')) {
      modal.setAttribute('aria-hidden', 'true');
      requestController?.abort();
    }
  });
})();
