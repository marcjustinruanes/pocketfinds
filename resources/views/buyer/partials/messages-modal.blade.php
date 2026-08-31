{{-- Shopee-style floating chat widget: content is fetched from buyer.messages and injected on open --}}
<div class="chat-widget" id="messagesModal">
  <div class="chat-widget-head">
    <div>
      <strong>Messages</strong>
      <span>Chat with sellers</span>
    </div>
    <button type="button" class="chat-widget-close" data-modal-close aria-label="Close">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div id="messagesModalBody" class="chat-widget-body">
    <div style="margin:auto;color:var(--muted);font-size:13px">Loading…</div>
  </div>
</div>
