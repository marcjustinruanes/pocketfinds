@php
  $sellerName = $seller ? ($seller->business_name ?? ($seller->given_names . ' ' . $seller->last_name)) : null;
  $myId = auth()->id();
@endphp

<div class="chat-shell">
  {{-- Conversation list --}}
  <div class="chat-list" id="chatList">
    <div class="chat-list-head"><strong style="font-size:13.5px">Conversations</strong></div>
    @forelse($conversations as $otherId => $last)
      @php
        $other = $last->sender_id === $myId ? $last->receiver : $last->sender;
        $otherName = $other->business_name ?? ($other->given_names . ' ' . $other->last_name);
        $isActive = $seller && $seller->id === $other->id;
      @endphp
      @php $isUnread = $last->receiver_id === $myId && !$last->read; @endphp
      <a href="{{ route('buyer.messages', ['seller' => $other->username]) }}" class="chat-list-item {{ $isActive ? 'active' : '' }} {{ $isUnread ? 'chat-unread' : '' }}">
        <div class="cli-av">{{ strtoupper(substr($otherName,0,1)) }}</div>
        <div class="cli-body">
          <div class="cli-name">{{ $otherName }}</div>
          <div class="cli-preview">{{ $last->body ?: ($last->product_id ? '📦 Product' : '📎 Attachment') }} · {{ $last->created_at->format('g:i A') }} {{ $last->sender_id === $myId ? ($last->read ? '✓✓ Seen' : '✓ Delivered') : '' }}</div>
        </div>
      </a>
    @empty
      @if(!$seller)
      <div class="empty" style="padding:40px 20px">
        <div class="ic">@include('buyer.partials.icon', ['name' => 'mail', 'size' => 28])</div>
        <h3>No messages</h3><p>Start a conversation with a seller.</p>
      </div>
      @endif
    @endforelse
    @if($seller && !$conversations->has($seller->id))
      <a href="{{ route('buyer.messages', ['seller' => $seller->username]) }}" class="chat-list-item active">
        <div class="cli-av">{{ strtoupper(substr($sellerName,0,1)) }}</div>
        <div class="cli-body">
          <div class="cli-name">{{ $sellerName }}</div>
          <div class="cli-preview">New conversation</div>
        </div>
      </a>
    @endif
  </div>

  {{-- Chat main --}}
  <div class="chat-main">
    <div class="chat-head">
      @if($seller)
        <div class="chat-head-av">{{ strtoupper(substr($sellerName,0,1)) }}</div>
        <div class="chat-head-info">
          <div class="chat-head-name">{{ $sellerName }}</div>
          <div class="pd-shop-status {{ $sellerOnline ? 'online' : 'offline' }}"><i></i>{{ $sellerOnline ? 'Online' : 'Offline' }}</div>
        </div>
      @else
        <div style="color:var(--muted);font-size:13px">Select a conversation</div>
      @endif
    </div>

    <div class="chat-body" id="chatBody" style="{{ !$seller ? 'align-items:center;justify-content:center' : '' }}">
      @if(!$seller)
        <div style="text-align:center;color:var(--muted)">
          <div style="margin-bottom:8px;opacity:.4">@include('buyer.partials.icon', ['name' => 'chat', 'size' => 36])</div>
          <p style="font-size:13px">No conversation selected</p>
        </div>
      @else
        @foreach($messages as $msg)
          @php $isMe = $msg->sender_id === $myId; @endphp
          <div class="chat-msg-wrap {{ $isMe ? '' : 'chat-msg-wrap-in' }}" data-message-id="{{ $msg->id }}">
            <div class="chat-msg-content">
            @if($msg->product_id && $msg->product)
              @php
                $cardImg = $msg->variation_image ? Storage::url($msg->variation_image) : ($msg->product->image ? Storage::url($msg->product->image) : null);
                $cardName = $msg->variation_label ?: $msg->product->name;
                $cardPrice = $msg->variation_price ?? $msg->product->price;
              @endphp
              <a href="{{ route('buyer.product', $msg->product_id) }}" class="chat-product-card">
                <div class="chat-product-img">
                  @if($cardImg)
                    <img src="{{ $cardImg }}" style="width:100%;height:100%;object-fit:cover;border-radius:8px">
                  @else
                    @include('buyer.partials.icon', ['name' => 'bag', 'size' => 20])
                  @endif
                </div>
                <div class="chat-product-info">
                  <div class="chat-product-name">{{ $cardName }}</div>
                  <div class="chat-product-price">₱{{ number_format($cardPrice) }}</div>
                </div>
              </a>
            @endif
            @if($msg->attachment_path)
              @php $attachmentType = $msg->attachment_type ?: (str_starts_with((string) $msg->attachment_mime, 'image/') ? 'image' : (str_starts_with((string) $msg->attachment_mime, 'video/') ? 'video' : 'document')); @endphp
              @if($attachmentType === 'image')
                <button type="button" class="chat-media-button" data-media-type="image" data-media-url="{{ route('message.media', ['path' => $msg->attachment_path]) }}"><img src="{{ route('message.media', ['path' => $msg->attachment_path]) }}" class="chat-attach-preview-img" alt="{{ $msg->attachment_name ?: 'Image attachment' }}"></button>
              @elseif($attachmentType === 'video')
                <button type="button" class="chat-media-button" data-media-type="video" data-media-url="{{ route('message.media', ['path' => $msg->attachment_path]) }}"><video src="{{ route('message.media', ['path' => $msg->attachment_path]) }}" class="chat-attach-preview-img" controls preload="metadata" playsinline></video></button>
              @else
                <a href="{{ Storage::disk('public')->url($msg->attachment_path) }}" target="_blank" class="chat-doc-bubble">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  {{ $msg->attachment_name }}
                </a>
              @endif
            @endif
            @if($msg->body)
              <div class="chat-bubble {{ $isMe ? 'chat-bubble-out' : 'chat-bubble-in' }}">{{ $msg->body }}</div>
            @endif
            <div class="chat-time">
              <span>{{ $msg->created_at->format('g:i A') }}</span>
              @if($isMe)
                <span class="chat-status">{{ $msg->read ? '✓✓ Seen' : '✓ Delivered' }}</span>
              @endif
            </div>
            </div>
            <div class="chat-msg-actions">
              <button type="button" class="chat-msg-action" title="React" onclick="toggleReaction(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.7c0 5.2-8.8 10.3-8.8 10.3S3.2 13.9 3.2 8.7A4.7 4.7 0 0112 6.1a4.7 4.7 0 018.8 2.6z"/></svg></button>
              <button type="button" class="chat-msg-action" title="Reply" onclick="replyToMessage(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 17l-5-5 5-5M4 12h10a6 6 0 016 6v1"/></svg></button>
              <button type="button" class="chat-msg-action" title="Report" onclick="reportMessage(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 21V4m0 1h10l-2 3 2 3H5"/></svg></button>
            </div>
          </div>
        @endforeach
      @endif
    </div>

    @if($seller)
      <div class="chat-attachments" id="chatAttachments" style="{{ $product ? '' : 'display:none' }}">
      {{-- Product attachment preview --}}
      @if($product)
      @php
        $attachImg   = $productVariation['image'] ?? ($product->image ? Storage::url($product->image) : null);
        $attachName  = $productVariation['label'] ?? $product->name;
        $attachPrice = $productVariation['price'] ?? $product->price;
        $productJson = json_encode([
          'id'    => $product->id,
          'name'  => $attachName,
          'price' => $attachPrice,
          'img'   => $attachImg,
          'url'   => route('buyer.product', $product->id),
          'variation_group' => $productVariation['group'] ?? null,
          'variation_value' => $productVariation['value'] ?? null,
        ]);
      @endphp
      <div class="chat-attachment" id="chatAttachment">
        <div class="chat-attach-inner">
          <div class="chat-attach-img">
            @if($attachImg)<img src="{{ $attachImg }}" style="width:100%;height:100%;object-fit:cover;border-radius:6px">
            @else @include('buyer.partials.icon', ['name' => 'bag', 'size' => 20]) @endif
          </div>
          <div class="chat-attach-info">
            <div class="chat-attach-label">{{ $productVariation ? 'Selected Option' : 'Product' }}</div>
            <div class="chat-attach-name">{{ $attachName }}</div>
            <div class="chat-attach-price">₱{{ number_format($attachPrice) }}</div>
          </div>
          <button class="chat-attach-remove" onclick="removeAttachment()" title="Remove">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
      </div>
      @endif

      {{-- File attachment preview --}}
      <div id="filePreview" style="display:none"></div>
      </div>

      <div class="chat-picker" id="productPickerPanel">
        <div id="productPicker" class="chat-picker-grid">
          @forelse($sellerProducts as $shopProduct)
            <div class="chat-picker-item">
              @if($shopProduct->image)<img src="{{ Storage::url($shopProduct->image) }}" alt="{{ $shopProduct->name }}">@else <div class="chat-attach-img">@include('buyer.partials.icon', ['name' => 'bag', 'size' => 18])</div>@endif
              <div class="chat-picker-info"><div class="chat-picker-name">{{ $shopProduct->name }}</div><div class="chat-picker-price">₱{{ number_format($shopProduct->price) }}</div></div>
              <button type="button" class="chat-picker-send" data-product-id="{{ $shopProduct->id }}" data-product-name="{{ $shopProduct->name }}" data-product-price="{{ $shopProduct->price }}" data-product-img="{{ $shopProduct->image ? Storage::url($shopProduct->image) : '' }}">Send</button>
            </div>
          @empty
            <div class="chat-picker-empty">No active products in this shop.</div>
          @endforelse
        </div>
      </div>
      <div class="chat-picker" id="orderPickerPanel" style="display:none"><div class="chat-picker-empty">No orders are available yet.</div></div>

      <div class="chat-reply-box" id="chatReplyBox">
        <div class="chat-reply-copy"><div class="chat-reply-label">Replying to message</div><div class="chat-reply-text" id="chatReplyText"></div></div>
        <button type="button" class="chat-reply-close" onclick="clearReply()" title="Cancel reply">×</button>
      </div>

      <form class="chat-input" id="chatForm">
        <input type="file" id="fileInput" style="display:none" accept="image/*,video/*" multiple onchange="previewFile(this)">
        <button class="icon-btn" type="button" onclick="document.getElementById('fileInput').click()" title="Attach file">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
        </button>
        <button class="icon-btn" type="button" title="Products" onclick="togglePicker('products')">@include('buyer.partials.icon', ['name' => 'bag', 'size' => 16])</button>
        <button class="icon-btn" type="button" title="Orders" onclick="togglePicker('orders')">@include('buyer.partials.icon', ['name' => 'package', 'size' => 16])</button>
        <input type="text" id="chatInput" placeholder="Type a message…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage()}">
        <button type="submit" class="btn btn-primary">
          @include('buyer.partials.icon', ['name' => 'send', 'size' => 15]) <span class="send-button-label">Send</span>
        </button>
      </form>
    @else
      <div class="chat-input">
        <input type="text" placeholder="Type a message…" disabled>
        <button class="btn btn-primary" disabled>@include('buyer.partials.icon', ['name' => 'send', 'size' => 15]) Send</button>
      </div>
    @endif
  </div>
</div>

<div class="modal-overlay" id="reportModal">
  <div class="modal" style="max-width:520px">
    <div class="modal-head"><div><h3>Report Message</h3><p>Send this report to the admin team.</p></div><button class="modal-close" type="button" onclick="closeReportModal()">×</button></div>
    <form id="reportForm" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="message_id" id="reportMessageId">
        <div class="chat-report-context"><div class="field-label">Message from</div><div id="reportSender" class="field-value"></div><div class="field-label" style="margin-top:10px">Shop</div><div id="reportShop" class="field-value"></div><div class="field-label" style="margin-top:10px">Reported message</div><div id="reportPreview" class="chat-report-preview"></div></div>
        <div class="form-row"><label for="reportReason">Why are you reporting this?</label><select id="reportReason" name="reason" required><option value="">Choose a reason</option><option>Harassment or abuse</option><option>Scam or fraud</option><option>Inappropriate content</option><option>Spam</option><option>Other</option></select></div>
        <div class="form-row"><label for="reportDescription">More details</label><textarea id="reportDescription" name="description" rows="4" maxlength="3000" placeholder="Tell admin what happened..."></textarea></div>
        <div class="form-row"><label for="reportEvidence">Image or video evidence (optional)</label><input id="reportEvidence" name="evidence" type="file" accept="image/*,video/*"><div id="reportEvidenceName" style="font-size:11px;color:var(--muted);margin-top:5px"></div></div>
        <div id="reportError" style="display:none;color:var(--danger);font-size:12px;margin-top:8px"></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-outline" onclick="closeReportModal()">Cancel</button><button type="submit" class="btn btn-danger" id="reportSubmit">Send Report</button></div>
    </form>
  </div>
</div>

@if($seller)
<script>
const SEND_URL   = '{{ route('buyer.messages.send') }}';
const POLL_URL   = '{{ route('buyer.messages.poll') }}';
const CSRF       = '{{ csrf_token() }}';
const MY_ID      = {{ $myId }};
const RECEIVER   = {{ $seller->id }};
const productData = {!! isset($productJson) ? $productJson : 'null' !!};
const REPORT_URL = '{{ route('buyer.messages.report') }}';

function openMediaViewer(url, type) {
  let viewer = document.getElementById('mediaViewer');
  if (!viewer) {
    viewer = document.createElement('div'); viewer.id = 'mediaViewer'; viewer.className = 'media-viewer';
    viewer.innerHTML = '<button type="button" class="media-viewer-close" aria-label="Close">×</button><div class="media-viewer-content"></div>';
    document.body.appendChild(viewer);
    viewer.addEventListener('click', event => { if (event.target === viewer || event.target.classList.contains('media-viewer-close')) viewer.classList.remove('open'); });
  }
  const content = viewer.querySelector('.media-viewer-content');
  content.replaceChildren();
  const media = document.createElement(type === 'video' ? 'video' : 'img'); media.src = url;
  if (type === 'video') { media.controls = true; media.autoplay = true; media.playsInline = true; }
  content.appendChild(media); viewer.classList.add('open');
}

document.addEventListener('click', event => { const button = event.target.closest('.chat-media-button'); if (button) openMediaViewer(button.dataset.mediaUrl, button.dataset.mediaType); });
document.querySelectorAll('.chat-picker-send').forEach(button => button.addEventListener('click', () => {
  attachedProduct = { id: button.dataset.productId, name: button.dataset.productName, price: button.dataset.productPrice, img: button.dataset.productImg };
  document.getElementById('productPickerPanel').classList.remove('open'); sendMessage();
}));

function togglePicker(type) {
  const productPanel = document.getElementById('productPickerPanel');
  const orderPanel = document.getElementById('orderPickerPanel');
  const showProducts = type === 'products' && !productPanel.classList.contains('open');
  productPanel.classList.toggle('open', showProducts);
  orderPanel.style.display = type === 'orders' && !orderPanel.classList.contains('open') ? 'block' : 'none';
  orderPanel.classList.toggle('open', type === 'orders' && orderPanel.style.display === 'block');
}
function toggleReaction(button) {
  const wrap = button.closest('.chat-msg-wrap');
  const badge = wrap.querySelector('.chat-reaction-badge');
  if (badge) { badge.remove(); button.classList.remove('active'); return; }
  button.classList.add('active');
  const reaction = document.createElement('span'); reaction.className = 'chat-reaction-badge'; reaction.title = 'Liked';
  reaction.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.7c0 5.2-8.8 10.3-8.8 10.3S3.2 13.9 3.2 8.7A4.7 4.7 0 0112 6.1a4.7 4.7 0 018.8 2.6z"/></svg>';
  const target = wrap.querySelector('.chat-bubble, .chat-product-card, .chat-media-button, .chat-doc-bubble');
  (target || wrap.querySelector('.chat-msg-content')).appendChild(reaction);
}
function replyToMessage(button) {
  const wrap = button.closest('.chat-msg-wrap');
  const bubble = wrap.querySelector('.chat-bubble');
  const media = wrap.querySelector('.chat-media-button');
  const product = wrap.querySelector('.chat-product-card');
  const text = bubble?.textContent?.trim() || (media?.dataset.mediaType === 'video' ? 'Video' : media ? 'Image' : product ? 'Product' : 'Message');
  document.getElementById('chatReplyText').textContent = text;
  document.getElementById('chatReplyBox').classList.add('open');
  document.getElementById('chatInput').focus();
}
function clearReply() { document.getElementById('chatReplyBox').classList.remove('open'); document.getElementById('chatReplyText').textContent = ''; }
function reportMessage(button) {
  const wrap = button.closest('.chat-msg-wrap');
  document.getElementById('reportMessageId').value = wrap.dataset.messageId || '';
  document.getElementById('reportSender').textContent = '{{ $sellerName }}';
  document.getElementById('reportShop').textContent = '{{ $sellerName }}';
  document.getElementById('reportPreview').textContent = wrap.querySelector('.chat-bubble')?.textContent || (wrap.querySelector('video') ? 'Video attachment' : wrap.querySelector('img') ? 'Image attachment' : wrap.querySelector('.chat-product-card') ? 'Product attachment' : 'Attachment');
  document.getElementById('reportModal').classList.add('open');
}
function closeReportModal() { document.getElementById('reportModal').classList.remove('open'); document.getElementById('reportForm').reset(); document.getElementById('reportError').style.display = 'none'; }
document.getElementById('reportForm').addEventListener('submit', async event => {
  event.preventDefault(); const button = document.getElementById('reportSubmit'); button.disabled = true; button.textContent = 'Sending...';
  try { const response = await fetch(REPORT_URL, { method: 'POST', body: new FormData(event.target), headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } }); const data = await response.json(); if (!response.ok || !data.ok) throw new Error(data.message || 'Report could not be sent.'); closeReportModal(); alert('Report sent to admin.'); } catch (error) { const errorBox = document.getElementById('reportError'); errorBox.textContent = error.message; errorBox.style.display = 'block'; } finally { button.disabled = false; button.textContent = 'Send Report'; }
});

let attachedProduct = productData;
let attachedFiles   = [];

function removeAttachment() {
  attachedProduct = null;
  const el = document.getElementById('chatAttachment');
  if (el) el.style.display = 'none';
  updateAttachmentsVisibility();
}

function updateAttachmentsVisibility() {
  document.getElementById('chatAttachments').style.display = (attachedProduct || attachedFiles.length) ? 'flex' : 'none';
}

function previewFile(input) {
  attachedFiles.push(...Array.from(input.files));
  input.value = '';
  renderFileTabs();
  updateAttachmentsVisibility();
}

function renderFileTabs() {
  const preview = document.getElementById('filePreview');
  preview.replaceChildren();
  preview.style.display = attachedFiles.length ? 'contents' : 'none';
  attachedFiles.forEach((file, index) => {
    const tab = document.createElement('div');
    tab.className = 'chat-attachment';
    const inner = document.createElement('div'); inner.className = 'chat-attach-inner';
    const thumb = document.createElement('div'); thumb.className = 'chat-attach-img'; thumb.style.background = 'var(--paper)';
    const mime = file.type;
    const label = mime.startsWith('image/') ? 'Image' : mime.startsWith('video/') ? 'Video' : 'File';
    if (mime.startsWith('image/')) {
      const image = document.createElement('img'); image.src = URL.createObjectURL(file); image.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:6px'; thumb.appendChild(image);
    } else if (mime.startsWith('video/')) {
      const video = document.createElement('video'); video.src = URL.createObjectURL(file); video.muted = true; video.playsInline = true; video.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:6px'; thumb.appendChild(video);
    } else {
      thumb.innerHTML = '<span style="font-size:18px">📎</span>';
    }
    const info = document.createElement('div'); info.className = 'chat-attach-info';
    info.innerHTML = `<div class="chat-attach-label">${label}</div><div class="chat-attach-name"></div><div class="chat-attach-price"></div>`;
    info.querySelector('.chat-attach-name').textContent = file.name;
    info.querySelector('.chat-attach-price').textContent = `${(file.size / 1024).toFixed(1)} KB`;
    const remove = document.createElement('button'); remove.className = 'chat-attach-remove'; remove.type = 'button'; remove.title = 'Remove'; remove.textContent = '×'; remove.onclick = () => removeFile(index);
    inner.append(thumb, info, remove); tab.appendChild(inner); preview.appendChild(tab);
  });
}

function removeFile(index = null) {
  if (index === null) attachedFiles = [];
  else attachedFiles.splice(index, 1);
  renderFileTabs();
  updateAttachmentsVisibility();
}

async function sendMessage(event) {
  event?.preventDefault();
  const input = document.getElementById('chatInput');
  const text  = input.value.trim();
  const productId = attachedProduct?.id;
  if (!text && !productId && !attachedFiles.length) return;

  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('receiver_id', RECEIVER);
  if (text)                          fd.append('body', text);
  if (productId)                      fd.append('product_id', productId);
  if (attachedProduct?.variation_group) fd.append('variation_group', attachedProduct.variation_group);
  if (attachedProduct?.variation_value) fd.append('variation_value', attachedProduct.variation_value);
  attachedFiles.forEach(file => fd.append('attachments[]', file));

  const sendButton = document.querySelector('.chat-input .btn-primary');
  const sendButtonLabel = sendButton.querySelector('.send-button-label');
  sendButton.disabled = true;
  sendButtonLabel.textContent = 'Sending...';
  try {
    const res = await fetch(SEND_URL, { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
    const raw = await res.text();
    let data;
    try { data = JSON.parse(raw); } catch (_) { throw new Error(`Message could not be sent (${res.status}).`); }
    if (!res.ok || !data.ok) throw new Error(data.message || data.errors?.body?.[0] || 'Message was not saved.');
    input.value = '';
    const prodSnap = attachedProduct;
    const fileSnapshots = attachedFiles.map(file => ({ url: URL.createObjectURL(file), type: file.type.startsWith('video/') ? 'video' : file.type.startsWith('image/') ? 'image' : 'document' }));
    removeAttachment();
    removeFile();
    const sentMessages = data.messages?.length ? data.messages : [data.message];
    sentMessages.forEach((message, index) => appendMessage(message || {}, prodSnap, true, fileSnapshots[index]?.url, fileSnapshots[index]?.type));
  } catch (error) {
    alert(error.message || 'Message could not be sent.');
  } finally {
    sendButton.disabled = false;
    sendButtonLabel.textContent = 'Send';
  }
}

document.getElementById('chatForm').addEventListener('submit', sendMessage);

function appendMessage(msg, prodSnap, isMe = msg.sender_id === MY_ID, localMediaUrl = null, localMediaType = null) {
  // Render only the product returned after Supabase has saved the message.
  prodSnap = msg.product_id ? {
    id: msg.product_id,
    name: msg.product_name,
    price: msg.product_price,
    img: msg.product_img,
    url: msg.product_url
  } : null;
  const body = document.getElementById('chatBody');
  const wrap = document.createElement('div');
  wrap.className = `chat-msg-wrap ${isMe ? '' : 'chat-msg-wrap-in'}`;
  wrap.dataset.messageId = msg.id;

  const content = document.createElement('div');
  content.className = 'chat-msg-content';

  if (prodSnap) {
    const card = document.createElement('a');
    card.href = prodSnap.url;
    card.className = 'chat-product-card';
    const imageWrap = document.createElement('div');
    imageWrap.className = 'chat-product-img';
    if (prodSnap.img) {
      const image = document.createElement('img');
      image.src = prodSnap.img;
      image.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:8px';
      imageWrap.appendChild(image);
    }
    const info = document.createElement('div'); info.className = 'chat-product-info';
    const name = document.createElement('div'); name.className = 'chat-product-name'; name.textContent = prodSnap.name;
    const price = document.createElement('div'); price.className = 'chat-product-price'; price.textContent = `₱${Number(prodSnap.price).toLocaleString()}`;
    info.append(name, price);
    card.append(imageWrap, info);
    content.appendChild(card);
  }

  if (localMediaUrl || msg.attachment_path) {
    const attachmentType = msg.attachment_type || (msg.attachment_mime?.startsWith('image/') ? 'image' : msg.attachment_mime?.startsWith('video/') ? 'video' : 'document');
    const mediaUrl = localMediaUrl || msg.attachment_path;
    const mediaType = localMediaType || attachmentType;
    if (mediaType === 'image') {
      const button = document.createElement('button'); button.type = 'button'; button.className = 'chat-media-button'; button.dataset.mediaType = 'image'; button.dataset.mediaUrl = mediaUrl;
      const img = document.createElement('img'); img.src = mediaUrl; img.className = 'chat-attach-preview-img'; img.alt = msg.attachment_name || 'Image attachment'; button.appendChild(img); content.appendChild(button);
    } else if (mediaType === 'video') {
      const button = document.createElement('button'); button.type = 'button'; button.className = 'chat-media-button'; button.dataset.mediaType = 'video'; button.dataset.mediaUrl = mediaUrl;
      const vid = document.createElement('video'); vid.src = mediaUrl; vid.className = 'chat-attach-preview-img'; vid.controls = true; vid.preload = 'metadata'; vid.playsInline = true; button.appendChild(vid); content.appendChild(button);
    } else {
      const a = document.createElement('a');
      a.href = msg.attachment_path; a.target = '_blank'; a.className = 'chat-doc-bubble';
      a.textContent = msg.attachment_name;
      content.appendChild(a);
    }
  }

  if (msg.body) {
    const bubble = document.createElement('div');
    bubble.className = `chat-bubble ${isMe ? 'chat-bubble-out' : 'chat-bubble-in'}`;
    bubble.textContent = msg.body;
    content.appendChild(bubble);
  }

  const time = document.createElement('div');
  time.className = 'chat-time';
  const timeText = document.createElement('span'); timeText.textContent = msg.created_at;
  time.appendChild(timeText);
  if (isMe) {
    const status = document.createElement('span');
    status.className = 'chat-status';
    status.textContent = msg.read ? '✓✓ Seen' : '✓ Delivered';
    time.appendChild(status);
  }
  content.appendChild(time);
  wrap.appendChild(content);
  const actions = document.createElement('div'); actions.className = 'chat-msg-actions';
  actions.innerHTML = '<button type="button" class="chat-msg-action" title="React" onclick="toggleReaction(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.7c0 5.2-8.8 10.3-8.8 10.3S3.2 13.9 3.2 8.7A4.7 4.7 0 0112 6.1a4.7 4.7 0 018.8 2.6z"/></svg></button><button type="button" class="chat-msg-action" title="Reply" onclick="replyToMessage(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 17l-5-5 5-5M4 12h10a6 6 0 016 6v1"/></svg></button><button type="button" class="chat-msg-action" title="Report" onclick="reportMessage(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 21V4m0 1h10l-2 3 2 3H5"/></svg></button>';
  wrap.appendChild(actions);

  body.appendChild(wrap);
  body.scrollTop = body.scrollHeight;
}

async function pollMessages() {
  try {
    const res = await fetch(`${POLL_URL}?receiver_id=${RECEIVER}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) return;
    const data = await res.json();
    data.messages?.forEach(msg => {
      const existing = document.querySelector(`[data-message-id="${msg.id}"]`);
      if (existing) {
        if (msg.sender_id === MY_ID) {
          const time = existing.querySelector('.chat-time');
          const timeText = time.querySelector('span');
          const status = time.querySelector('.chat-status');
          if (timeText) timeText.textContent = msg.created_at;
          if (status) status.textContent = msg.read ? '✓✓ Seen' : '✓ Delivered';
          else {
            const newStatus = document.createElement('span');
            newStatus.className = 'chat-status';
            newStatus.textContent = msg.read ? '✓✓ Seen' : '✓ Delivered';
            time.appendChild(newStatus);
          }
        }
      } else {
        appendMessage(msg, null, msg.sender_id === MY_ID);
      }
    });
  } catch (_) {}
}

setInterval(pollMessages, 3000);

// scroll to bottom on load
document.getElementById('chatBody').scrollTop = document.getElementById('chatBody').scrollHeight;
</script>
@endif
