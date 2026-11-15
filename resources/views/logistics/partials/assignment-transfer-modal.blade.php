<div class="modal-overlay" id="transferDetailsModal" aria-hidden="true">
  <section class="modal modal-xl logistics-transfer-details-modal" role="dialog" aria-modal="true" aria-labelledby="transferDetailsTitle" aria-describedby="transferDetailsDescription">
    <header class="modal-head">
      <div class="modal-head-copy"><h3 id="transferDetailsTitle">Transfer details</h3><p id="transferDetailsDescription">Review the transfer timeline, parcels, and available actions.</p></div>
      <button type="button" class="modal-close" data-modal-close aria-label="Close transfer details"><x-admin-icon name="close" /></button>
    </header>
    <div class="modal-body logistics-transfer-details-content" id="transferDetailsContent" aria-live="polite">
      <p class="logistics-transfer-details-loading">Loading transfer details…</p>
    </div>
    <footer class="modal-foot">
      <button type="button" class="btn btn-primary" data-modal-close>Close</button>
    </footer>
  </section>
</div>
