<?php $__env->startSection('title', 'Messages'); ?>
<?php $__env->startSection('page-title', 'Messages'); ?>
<?php $__env->startSection('page-sub', 'Chat with your customers'); ?>

<?php $__env->startSection('content'); ?>
<?php $myId = auth()->id(); ?>

<div class="chat-shell">
  <div class="chat-list" id="chatList">
    <div class="chat-list-head"><strong style="font-size:13.5px">Conversations</strong></div>
    <?php $__empty_1 = true; $__currentLoopData = $conversations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $otherId => $last): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php
        $other = $last->sender_id === $myId ? $last->receiver : $last->sender;
        $otherName = $other->given_names . ' ' . $other->last_name;
        $isActive = $buyer && $buyer->id === $other->id;
      ?>
      <?php $isUnread = $last->receiver_id === $myId && !$last->read; ?>
      <a href="<?php echo e(route('seller.messages', ['buyer' => $other->id])); ?>" class="chat-list-item <?php echo e($isActive ? 'active' : ''); ?> <?php echo e($isUnread ? 'chat-unread' : ''); ?>">
        <div class="cli-av"><?php echo e(strtoupper(substr($otherName,0,1))); ?></div>
        <div class="cli-body">
          <div class="cli-name"><?php echo e($otherName); ?></div>
          <div class="cli-preview"><?php echo e($last->body ?: ($last->product_id ? '📦 Product' : '📎 Attachment')); ?> · <?php echo e($last->created_at->format('g:i A')); ?> <?php echo e($last->sender_id === $myId ? ($last->read ? '✓✓ Seen' : '✓ Delivered') : ''); ?></div>
        </div>
      </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="empty" style="padding:40px 20px">
        <div class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'mail', 'size' => 28], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
        <h3>No messages</h3><p>Buyers will appear here when they message you.</p>
      </div>
    <?php endif; ?>
  </div>

  <div class="chat-main">
    <div class="chat-head">
      <?php if($buyer): ?>
        <?php $buyerName = $buyer->given_names . ' ' . $buyer->last_name; ?>
        <div class="chat-head-av"><?php echo e(strtoupper(substr($buyerName,0,1))); ?></div>
        <div class="chat-head-info">
          <div class="chat-head-name"><?php echo e($buyerName); ?></div>
          <div style="font-size:11px;color:var(--muted)">Customer</div>
        </div>
      <?php else: ?>
        <div style="color:var(--muted);font-size:13px">Select a conversation</div>
      <?php endif; ?>
    </div>

    <div class="chat-body" id="chatBody" style="<?php echo e(!$buyer ? 'align-items:center;justify-content:center' : ''); ?>">
      <?php if(!$buyer): ?>
        <div style="text-align:center;color:var(--muted)">
          <div style="margin-bottom:8px;opacity:.4"><?php echo $__env->make('seller.partials.icon', ['name' => 'chat', 'size' => 36], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
          <p style="font-size:13px">No conversation selected</p>
        </div>
      <?php else: ?>
        <?php $__currentLoopData = $messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $msg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php $isMe = $msg->sender_id === $myId; ?>
          <div class="chat-msg-wrap <?php echo e($isMe ? '' : 'chat-msg-wrap-in'); ?>" data-message-id="<?php echo e($msg->id); ?>">
            <div class="chat-msg-content">
            <?php if($msg->product_id && $msg->product): ?>
              <a href="<?php echo e(route('seller.inventory')); ?>" class="chat-product-card">
                <div class="chat-product-img">
                  <?php if($msg->product->image): ?>
                    <img src="<?php echo e(Storage::url($msg->product->image)); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:8px">
                  <?php else: ?>
                    <?php echo $__env->make('seller.partials.icon', ['name' => 'bag', 'size' => 20], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                  <?php endif; ?>
                </div>
                <div class="chat-product-info">
                  <div class="chat-product-name"><?php echo e($msg->product->name); ?></div>
                  <div class="chat-product-price">₱<?php echo e(number_format($msg->product->price)); ?></div>
                </div>
              </a>
            <?php endif; ?>
            <?php if($msg->attachment_path): ?>
              <?php $attachmentType = $msg->attachment_type ?: (str_starts_with((string) $msg->attachment_mime, 'image/') ? 'image' : (str_starts_with((string) $msg->attachment_mime, 'video/') ? 'video' : 'document')); ?>
              <?php if($attachmentType === 'image'): ?>
                <button type="button" class="chat-media-button" data-media-type="image" data-media-url="<?php echo e(route('message.media', ['path' => $msg->attachment_path])); ?>"><img src="<?php echo e(route('message.media', ['path' => $msg->attachment_path])); ?>" class="chat-attach-preview-img" alt="<?php echo e($msg->attachment_name ?: 'Image attachment'); ?>"></button>
              <?php elseif($attachmentType === 'video'): ?>
                <button type="button" class="chat-media-button" data-media-type="video" data-media-url="<?php echo e(route('message.media', ['path' => $msg->attachment_path])); ?>"><video src="<?php echo e(route('message.media', ['path' => $msg->attachment_path])); ?>" class="chat-attach-preview-img" controls preload="metadata" playsinline></video></button>
              <?php else: ?>
                <a href="<?php echo e(Storage::disk('public')->url($msg->attachment_path)); ?>" target="_blank" class="chat-doc-bubble">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  <?php echo e($msg->attachment_name); ?>

                </a>
              <?php endif; ?>
            <?php endif; ?>
            <?php if($msg->body): ?>
              <div class="chat-bubble <?php echo e($isMe ? 'chat-bubble-out' : 'chat-bubble-in'); ?>"><?php echo e($msg->body); ?></div>
            <?php endif; ?>
            <div class="chat-time">
              <span><?php echo e($msg->created_at->format('g:i A')); ?></span>
              <?php if($isMe): ?>
                <span class="chat-status"><?php echo e($msg->read ? '✓✓ Seen' : '✓ Delivered'); ?></span>
              <?php endif; ?>
            </div>
            </div>
            <div class="chat-msg-actions">
              <button type="button" class="chat-msg-action" title="React" onclick="toggleReaction(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.7c0 5.2-8.8 10.3-8.8 10.3S3.2 13.9 3.2 8.7A4.7 4.7 0 0112 6.1a4.7 4.7 0 018.8 2.6z"/></svg></button>
              <button type="button" class="chat-msg-action" title="Reply" onclick="replyToMessage(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 17l-5-5 5-5M4 12h10a6 6 0 016 6v1"/></svg></button>
              <button type="button" class="chat-msg-action" title="Report" onclick="reportMessage(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 21V4m0 1h10l-2 3 2 3H5"/></svg></button>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      <?php endif; ?>
    </div>

    <?php if($buyer): ?>
      <div class="chat-attachments" id="chatAttachments" style="display:none">
      <div id="filePreview" style="display:none"></div>
      </div>

      <div class="chat-picker" id="productPickerPanel">
        <div id="productPicker" class="chat-picker-grid">
          <?php $__empty_1 = true; $__currentLoopData = $sellerProducts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shopProduct): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="chat-picker-item">
              <?php if($shopProduct->image): ?><img src="<?php echo e(Storage::url($shopProduct->image)); ?>" alt="<?php echo e($shopProduct->name); ?>"><?php else: ?> <div class="chat-attach-img"><?php echo $__env->make('seller.partials.icon', ['name' => 'bag', 'size' => 18], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div><?php endif; ?>
              <div class="chat-picker-info"><div class="chat-picker-name"><?php echo e($shopProduct->name); ?></div><div class="chat-picker-price">₱<?php echo e(number_format($shopProduct->price)); ?></div></div>
              <button type="button" class="chat-picker-send" data-product-id="<?php echo e($shopProduct->id); ?>" data-product-name="<?php echo e($shopProduct->name); ?>" data-product-price="<?php echo e($shopProduct->price); ?>" data-product-img="<?php echo e($shopProduct->image ? Storage::url($shopProduct->image) : ''); ?>">Send</button>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="chat-picker-empty">No active products in your shop.</div>
          <?php endif; ?>
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
        <button class="icon-btn" type="button" title="Products" onclick="togglePicker('products')"><?php echo $__env->make('seller.partials.icon', ['name' => 'inventory', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></button>
        <button class="icon-btn" type="button" title="Orders" onclick="togglePicker('orders')"><?php echo $__env->make('seller.partials.icon', ['name' => 'orders', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></button>
        <input type="text" id="chatInput" placeholder="Type a message…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage()}">
        <button type="submit" class="btn btn-primary">
          <?php echo $__env->make('seller.partials.icon', ['name' => 'send', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <span class="send-button-label">Send</span>
        </button>
      </form>
    <?php else: ?>
      <div class="chat-input">
        <input type="text" placeholder="Type a message…" disabled>
        <button class="btn btn-primary" disabled><?php echo $__env->make('seller.partials.icon', ['name' => 'send', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Send</button>
      </div>
    <?php endif; ?>
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

<?php if($buyer): ?>
<script>
const SEND_URL = '<?php echo e(route('seller.messages.send')); ?>';
const POLL_URL = '<?php echo e(route('seller.messages.poll')); ?>';
const CSRF     = '<?php echo e(csrf_token()); ?>';
const MY_ID    = <?php echo e($myId); ?>;
const RECEIVER = <?php echo e($buyer->id); ?>;
const REPORT_URL = '<?php echo e(route('seller.messages.report')); ?>';
let attachedProduct = null;

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
  document.getElementById('reportSender').textContent = '<?php echo e($buyerName ?? ($buyer->given_names . " " . $buyer->last_name)); ?>';
  document.getElementById('reportShop').textContent = '<?php echo e(auth()->user()->business_name ?? "Shop"); ?>';
  document.getElementById('reportPreview').textContent = wrap.querySelector('.chat-bubble')?.textContent || (wrap.querySelector('video') ? 'Video attachment' : wrap.querySelector('img') ? 'Image attachment' : wrap.querySelector('.chat-product-card') ? 'Product attachment' : 'Attachment');
  document.getElementById('reportModal').classList.add('open');
}
function closeReportModal() { document.getElementById('reportModal').classList.remove('open'); document.getElementById('reportForm').reset(); document.getElementById('reportError').style.display = 'none'; }
document.getElementById('reportForm').addEventListener('submit', async event => {
  event.preventDefault(); const button = document.getElementById('reportSubmit'); button.disabled = true; button.textContent = 'Sending...';
  try { const response = await fetch(REPORT_URL, { method: 'POST', body: new FormData(event.target), headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } }); const data = await response.json(); if (!response.ok || !data.ok) throw new Error(data.message || 'Report could not be sent.'); closeReportModal(); alert('Report sent to admin.'); } catch (error) { const errorBox = document.getElementById('reportError'); errorBox.textContent = error.message; errorBox.style.display = 'block'; } finally { button.disabled = false; button.textContent = 'Send Report'; }
});
let attachedFiles = [];

function previewFile(input) {
  attachedFiles.push(...Array.from(input.files));
  input.value = '';
  renderFileTabs();
  document.getElementById('chatAttachments').style.display = attachedFiles.length ? 'flex' : 'none';
}

function renderFileTabs() {
  const preview = document.getElementById('filePreview');
  preview.replaceChildren();
  preview.style.display = attachedFiles.length ? 'contents' : 'none';
  attachedFiles.forEach((file, index) => {
    const tab = document.createElement('div'); tab.className = 'chat-attachment';
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
  document.getElementById('chatAttachments').style.display = attachedFiles.length ? 'flex' : 'none';
}

async function sendMessage(event) {
  event?.preventDefault();
  const input = document.getElementById('chatInput');
  const text  = input.value.trim();
  if (!text && !attachedFiles.length) return;

  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('receiver_id', RECEIVER);
  if (text)         fd.append('body', text);
  if (attachedProduct?.id) fd.append('product_id', attachedProduct.id);
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
    const fileSnapshots = attachedFiles.map(file => ({ url: URL.createObjectURL(file), type: file.type.startsWith('video/') ? 'video' : file.type.startsWith('image/') ? 'image' : 'document' }));
    removeFile();
    const sentMessages = data.messages?.length ? data.messages : [data.message];
    sentMessages.forEach((message, index) => appendMessage(message || {}, true, fileSnapshots[index]?.url, fileSnapshots[index]?.type));
    attachedProduct = null;
  } catch (error) {
    alert(error.message || 'Message could not be sent.');
  } finally {
    sendButton.disabled = false;
    sendButtonLabel.textContent = 'Send';
  }
}

document.getElementById('chatForm').addEventListener('submit', sendMessage);

function appendMessage(msg, isMe = msg.sender_id === MY_ID, localMediaUrl = null, localMediaType = null) {
  const body = document.getElementById('chatBody');
  const wrap = document.createElement('div');
  wrap.className = `chat-msg-wrap ${isMe ? '' : 'chat-msg-wrap-in'}`;
  wrap.dataset.messageId = msg.id;

  const content = document.createElement('div');
  content.className = 'chat-msg-content';

  if (msg.product_id) {
    const card = document.createElement('a');
    card.href = msg.product_url;
    card.className = 'chat-product-card';
    const imageWrap = document.createElement('div');
    imageWrap.className = 'chat-product-img';
    if (msg.product_img) {
      const image = document.createElement('img');
      image.src = msg.product_img;
      image.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:8px';
      imageWrap.appendChild(image);
    }
    const info = document.createElement('div'); info.className = 'chat-product-info';
    const name = document.createElement('div'); name.className = 'chat-product-name'; name.textContent = msg.product_name;
    const price = document.createElement('div'); price.className = 'chat-product-price'; price.textContent = `PHP ${Number(msg.product_price).toLocaleString()}`;
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
        appendMessage(msg, msg.sender_id === MY_ID);
      }
    });
  } catch (_) {}
}

setInterval(pollMessages, 3000);

document.getElementById('chatBody').scrollTop = document.getElementById('chatBody').scrollHeight;
</script>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views/seller/messages.blade.php ENDPATH**/ ?>