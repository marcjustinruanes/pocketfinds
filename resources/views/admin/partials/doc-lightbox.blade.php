{{-- Shared verification-document lightbox. Include once per page; pairs with <x-admin-doc-thumb>. --}}
<div class="modal-overlay" id="adminDocLightbox">
  <div class="modal" style="width:min(780px,100%);max-height:90vh;display:flex;flex-direction:column">
    <div class="modal-head">
      <div><h3 id="adminDocLightboxTitle">Document Preview</h3></div>
      <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
    </div>
    <div id="adminDocLightboxBody" style="flex:1;overflow:auto;padding:20px;display:flex;align-items:center;justify-content:center;min-height:300px"></div>
  </div>
</div>

<script>
if (!window.__adminDocLightboxBound) {
  window.__adminDocLightboxBound = true;
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-doc-trigger]');
    if (!trigger) return;
    const src = trigger.dataset.src, type = trigger.dataset.type, title = trigger.dataset.title;
    const body = document.getElementById('adminDocLightboxBody');
    document.getElementById('adminDocLightboxTitle').textContent = title || (type === 'pdf' ? 'Document Preview' : 'Image Preview');
    body.innerHTML = type === 'image'
      ? `<img src="${src}" style="max-width:100%;max-height:70vh;border-radius:8px;object-fit:contain" alt="${title || 'Document'}">`
      : `<iframe src="${src}" style="width:100%;height:70vh;border:0;border-radius:8px"></iframe>`;
    document.getElementById('adminDocLightbox').classList.add('open');
  });
}
</script>
