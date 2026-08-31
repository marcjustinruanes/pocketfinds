@extends('admin.layout')
@section('title', 'Messages')
@section('page-title', 'Messages')
@section('page-sub', 'Platform messaging and support inbox')

@php($myId = auth()->id())

@push('head')
<style>
  /* Visual language matched to buyer/seller's chat UI. All font-family values below
     come from admin.css's own --font-* variables (Inter/IBM Plex Mono), so this page
     keeps its existing typography — only layout, color, and shape are being matched. */
  .chat-list-item{display:flex;align-items:center;gap:10px;padding:12px 16px;cursor:pointer;border-bottom:1px solid var(--border);transition:background .15s;text-decoration:none;color:inherit}
  .chat-list-item:hover{background:var(--paper)}
  .chat-list-item.active{background:var(--pink-soft)}
  .cli-av{width:36px;height:36px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);color:#fff;display:grid;place-items:center;font-size:13px;font-weight:700;flex:none}
  .cli-body{min-width:0;flex:1}
  .cli-name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .cli-role{font-family:var(--font-mono);font-size:9px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
  .cli-preview{font-size:11.5px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
  .chat-unread .cli-name,.chat-unread .cli-preview{font-weight:800;color:var(--text)}
  .cli-side{display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex:none;margin-left:auto;padding-left:6px}
  .cli-time{font-size:10px;color:var(--muted);font-family:var(--font-mono);white-space:nowrap}
  .cli-unread{background:var(--pink);color:#fff;font-size:10px;font-weight:700;border-radius:20px;padding:1px 7px;min-width:16px;text-align:center}

  .chat-head-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);color:#fff;display:grid;place-items:center;font-size:12px;font-weight:700;flex:none}
  .chat-head-info{display:flex;flex-direction:column;gap:2px}
  .chat-head-name{font-size:13.5px;font-weight:700}
  .chat-head-sub{font-size:11px;color:var(--muted);font-family:var(--font-mono)}

  .chat-msg-wrap{position:relative;align-self:flex-end;width:fit-content;max-width:100%;display:flex;flex-direction:row-reverse;align-items:center;gap:8px}
  .chat-msg-wrap-in{align-self:flex-start;flex-direction:row}
  .chat-msg-content{position:relative;display:flex;flex-direction:column;align-items:flex-end;gap:6px;min-width:0;max-width:min(72%,720px)}
  .chat-msg-wrap-in .chat-msg-content{align-items:flex-start}
  .chat-bubble{position:relative;max-width:100%;padding:9px 13px;border-radius:12px;font-size:13px;line-height:1.5;word-break:break-word}
  .chat-bubble-out{background:var(--pink);color:#fff;border-bottom-right-radius:3px}
  .chat-bubble-in{background:var(--surface);color:var(--text);border:1px solid var(--border);border-bottom-left-radius:3px}
  .chat-time{display:inline-flex;align-items:center;justify-content:flex-end;gap:4px;white-space:nowrap;font-family:var(--font-mono);font-size:10px;color:var(--muted)}
  .chat-msg-wrap-in .chat-time{justify-content:flex-start}
  .chat-status{white-space:nowrap}
  .chat-msg-actions{display:flex;flex:none;gap:2px;opacity:0;pointer-events:none;transition:opacity .12s}
  .chat-msg-wrap:hover .chat-msg-actions{opacity:1;pointer-events:auto}
  .chat-msg-action{width:22px;height:22px;border:1px solid transparent;border-radius:6px;background:transparent;color:var(--muted);cursor:pointer;display:grid;place-items:center;padding:0}
  .chat-msg-action:hover{background:var(--surface);border-color:var(--border);color:var(--pink-dark)}
  .chat-msg-action svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
  .chat-reaction-badge{position:absolute;bottom:-8px;right:-8px;width:22px;height:22px;display:grid;place-items:center;border:2px solid var(--paper);border-radius:50%;background:var(--surface);font-size:11px;z-index:3}
  .chat-msg-wrap-in .chat-reaction-badge{right:auto;left:-8px}

  .chat-attach-preview-img{display:block;width:auto;max-width:min(300px,72vw);max-height:280px;border:1px solid var(--border);border-radius:12px;background:var(--surface);object-fit:contain}
  .chat-media-button{position:relative;display:block;padding:0;border:0;background:transparent;cursor:zoom-in;max-width:min(300px,72vw)}
  .chat-doc-bubble{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:10px;background:var(--pink-soft);border:1px solid var(--pink-line);color:var(--pink-dark);text-decoration:none;font-size:12.5px;font-weight:600;max-width:min(280px,72vw)}

  .media-viewer{position:fixed;inset:0;z-index:300;display:none;align-items:center;justify-content:center;padding:24px;background:rgba(20,16,24,.82)}
  .media-viewer.open{display:flex}
  .media-viewer-content img,.media-viewer-content video{display:block;max-width:92vw;max-height:90vh;border-radius:12px;background:#000;box-shadow:var(--shadow-lg)}
  .media-viewer-close{position:absolute;top:18px;right:22px;width:38px;height:38px;border:1px solid rgba(255,255,255,.35);border-radius:50%;background:rgba(0,0,0,.35);color:#fff;font-size:24px;line-height:1;cursor:pointer}

  .reply-box{display:none;align-items:center;gap:10px;padding:9px 16px;border-top:1px solid var(--border);background:var(--surface)}
  .reply-box.open{display:flex}
  .reply-copy{min-width:0;flex:1;border-left:3px solid var(--pink);padding-left:9px}
  .reply-label{font-size:10px;font-weight:700;color:var(--pink-dark);text-transform:uppercase;letter-spacing:.06em;font-family:var(--font-mono)}
  .reply-text{font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
  .reply-close{width:24px;height:24px;border:0;border-radius:50%;background:var(--paper);color:var(--muted);cursor:pointer;font-size:16px;line-height:1}

  .chat-input{gap:10px}
  .chat-input .icon-btn{width:38px;height:38px;padding:0;flex:none;border-radius:9px;display:inline-flex;align-items:center;justify-content:center}
  .file-chip{display:flex;align-items:center;gap:6px;background:var(--pink-soft);border:1px solid var(--pink-line);border-radius:8px;padding:5px 9px;font-size:11.5px;margin:0 16px 10px}
  .file-chip button{border:0;background:none;cursor:pointer;color:var(--pink-dark);font-weight:700}
</style>
@endpush

@push('scripts')
<script>document.getElementById('chatBody')?.scrollTo(0, document.getElementById('chatBody').scrollHeight);</script>
@endpush

@section('content')
<div class="chat-shell">
  <div class="chat-list">
    <div class="chat-list-head">
      <div style="position:relative">
        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted)" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" width="14" height="14"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M15 15l-3-3"/></svg>
        <input type="text" data-table-search style="width:100%;border:1px solid var(--border);border-radius:9px;padding:8px 12px 8px 32px;font-size:13px;font-family:var(--font-body)" placeholder="Search users…">
      </div>
    </div>
    @forelse($users as $u)
    <a href="{{ route('admin.messages.user', $u->id) }}" class="chat-list-item {{ isset($selectedUser) && $selectedUser?->id == $u->id ? 'active' : '' }} {{ $u->unread_count > 0 ? 'chat-unread' : '' }}">
      <div class="cli-av">{{ strtoupper(substr($u->given_names,0,1).substr($u->last_name,0,1)) }}</div>
      <div class="cli-body">
        <div class="cli-name">{{ $u->given_names }} {{ $u->last_name }}</div>
        <div class="cli-role">{{ ucfirst($u->account_type) }}</div>
        <div class="cli-preview">
          @if($u->last_message)
            @if($u->last_message->sender_id === $myId)<span style="color:var(--muted)">You: </span>@endif
            {{ $u->last_message->body ?: '📎 Attachment' }}
          @else
            {{ $u->email }}
          @endif
        </div>
      </div>
      <div class="cli-side">
        @if($u->last_message)<span class="cli-time">{{ $u->last_message->created_at->diffForHumans(null, true) }}</span>@endif
        @if($u->unread_count > 0)<span class="cli-unread">{{ $u->unread_count }}</span>@endif
      </div>
    </a>
    @empty
    <div class="empty" style="padding:40px 20px">
      <div class="ic"><x-admin-icon name="users" /></div>
      <h3>No users yet</h3>
      <p>Platform accounts will appear here.</p>
    </div>
    @endforelse
  </div>

  <div class="chat-main">
    @if(isset($selectedUser) && $selectedUser)
    <div class="chat-head">
      <div class="chat-head-av">{{ strtoupper(substr($selectedUser->given_names,0,1).substr($selectedUser->last_name,0,1)) }}</div>
      <div class="chat-head-info">
        <div class="chat-head-name">{{ $selectedUser->given_names }} {{ $selectedUser->last_name }}</div>
        <div class="chat-head-sub">{{ ucfirst($selectedUser->account_type) }} · {{ $selectedUser->email }}</div>
      </div>
    </div>
    <div class="chat-body" id="chatBody">
      @forelse($messages as $msg)
        @include('admin.partials.message-bubble', ['msg' => $msg, 'myId' => $myId])
      @empty
      <div class="empty" style="margin:auto">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
        <h3>No messages yet</h3>
        <p>Start the conversation below.</p>
      </div>
      @endforelse
    </div>

    <div class="reply-box" id="replyBox">
      <div class="reply-copy"><div class="reply-label">Replying to</div><div class="reply-text" id="replyText"></div></div>
      <button type="button" class="reply-close" onclick="clearReply()">×</button>
    </div>
    <div id="fileChips"></div>

    <form class="chat-input" id="chatForm">
      <input type="file" id="fileInput" style="display:none" multiple onchange="onFilesChosen(this)">
      <button type="button" class="icon-btn btn btn-outline" onclick="document.getElementById('fileInput').click()" title="Attach file">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
      </button>
      <input type="text" id="chatInput" name="body" placeholder="Type a message…" style="font-family:var(--font-body);font-size:13px" autocomplete="off">
      <button class="btn btn-primary" type="submit">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M18 2L2 9l6 3 3 6 7-16z"/></svg>
        Send
      </button>
    </form>
    @else
    <div class="chat-head">
      <div class="avatar-sm"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" width="16" height="16"><circle cx="10" cy="7" r="3.5"/><path d="M3 17c0-3.3 3.1-6 7-6s7 2.7 7 6"/></svg></div>
      <div>
        <strong style="font-size:13.5px;font-family:var(--font-body)">Select a conversation</strong>
        <div style="font-size:11px;color:var(--muted);font-family:var(--font-mono)">Choose a user from the list</div>
      </div>
    </div>
    <div class="chat-body">
      <div class="empty" style="margin:auto">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
        <h3>No conversation selected</h3>
        <p>Pick a user from the left to start messaging.</p>
      </div>
    </div>
    <div class="chat-input">
      <input type="text" placeholder="Type a message…" disabled style="font-family:var(--font-body);font-size:13px;opacity:.5">
      <button class="btn btn-primary" disabled style="opacity:.5">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M18 2L2 9l6 3 3 6 7-16z"/></svg>
        Send
      </button>
    </div>
    @endif
  </div>
</div>

<div class="media-viewer" id="mediaViewer">
  <button type="button" class="media-viewer-close" aria-label="Close">×</button>
  <div class="media-viewer-content"></div>
</div>

@if(isset($selectedUser) && $selectedUser)
<script>
const CSRF     = document.querySelector('meta[name=csrf-token]')?.content || '';
const MY_ID    = {{ $myId }};
const RECEIVER = {{ $selectedUser->id }};
const SEND_URL = '{{ route('admin.messages.send') }}';
const POLL_URL = '{{ route('admin.messages.poll') }}';

let attachedFiles = [];
let replyTarget   = null;

function onFilesChosen(input) {
  attachedFiles.push(...Array.from(input.files));
  input.value = '';
  renderFileChips();
}
function removeFile(i) { attachedFiles.splice(i, 1); renderFileChips(); }
function renderFileChips() {
  const box = document.getElementById('fileChips');
  box.innerHTML = '';
  attachedFiles.forEach((f, i) => {
    const chip = document.createElement('div');
    chip.className = 'file-chip';
    chip.innerHTML = `<span>${f.name}</span>`;
    const btn = document.createElement('button'); btn.type = 'button'; btn.textContent = '×';
    btn.onclick = () => removeFile(i);
    chip.appendChild(btn);
    box.appendChild(chip);
  });
}

function openMediaViewer(url, type) {
  const viewer = document.getElementById('mediaViewer');
  const content = viewer.querySelector('.media-viewer-content');
  content.replaceChildren();
  const media = document.createElement(type === 'video' ? 'video' : 'img');
  media.src = url;
  if (type === 'video') { media.controls = true; media.autoplay = true; media.playsInline = true; }
  content.appendChild(media);
  viewer.classList.add('open');
}
document.getElementById('mediaViewer').addEventListener('click', (e) => {
  if (e.target.id === 'mediaViewer' || e.target.classList.contains('media-viewer-close')) e.currentTarget.classList.remove('open');
});

function toggleReaction(btn) {
  const wrap = btn.closest('.chat-msg-content');
  const existing = wrap.querySelector('.chat-reaction-badge');
  if (existing) { existing.remove(); return; }
  const span = document.createElement('span');
  span.className = 'chat-reaction-badge';
  span.textContent = '❤️';
  wrap.appendChild(span);
}
function replyToMessage(btn) {
  const wrap = btn.closest('.chat-msg-wrap');
  const text = wrap.querySelector('.chat-bubble')?.textContent?.trim() || 'Attachment';
  replyTarget = text;
  document.getElementById('replyText').textContent = text;
  document.getElementById('replyBox').classList.add('open');
  document.getElementById('chatInput').focus();
}
function clearReply() {
  replyTarget = null;
  document.getElementById('replyBox').classList.remove('open');
}

function actionButtonsHtml() {
  return `<div class="chat-msg-actions">
    <button type="button" class="chat-msg-action" title="React" onclick="toggleReaction(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.7c0 5.2-8.8 10.3-8.8 10.3S3.2 13.9 3.2 8.7A4.7 4.7 0 0112 6.1a4.7 4.7 0 018.8 2.6z"/></svg></button>
    <button type="button" class="chat-msg-action" title="Reply" onclick="replyToMessage(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 17l-5-5 5-5M4 12h10a6 6 0 016 6v1"/></svg></button>
  </div>`;
}

function appendMessage(msg) {
  const isMe = msg.sender_id === MY_ID;
  const body = document.getElementById('chatBody');
  body.querySelector('.empty')?.remove();

  const wrap = document.createElement('div');
  wrap.className = 'chat-msg-wrap' + (isMe ? '' : ' chat-msg-wrap-in');
  wrap.dataset.messageId = msg.id;

  const content = document.createElement('div');
  content.className = 'chat-msg-content';

  if (msg.attachment_path) {
    if (msg.attachment_type === 'image') {
      const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'chat-media-button';
      btn.onclick = () => openMediaViewer(msg.attachment_path, 'image');
      btn.innerHTML = `<img src="${msg.attachment_path}" class="chat-attach-preview-img" alt="${msg.attachment_name || 'Image attachment'}">`;
      content.appendChild(btn);
    } else if (msg.attachment_type === 'video') {
      const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'chat-media-button';
      btn.onclick = () => openMediaViewer(msg.attachment_path, 'video');
      btn.innerHTML = `<video src="${msg.attachment_path}" class="chat-attach-preview-img" controls preload="metadata" playsinline></video>`;
      content.appendChild(btn);
    } else {
      const a = document.createElement('a'); a.href = msg.attachment_path; a.target = '_blank'; a.className = 'chat-doc-bubble';
      a.textContent = '📎 ' + (msg.attachment_name || 'File');
      content.appendChild(a);
    }
  }
  if (msg.body) {
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble ' + (isMe ? 'chat-bubble-out' : 'chat-bubble-in');
    bubble.textContent = msg.body;
    content.appendChild(bubble);
  }
  const time = document.createElement('div');
  time.className = 'chat-time';
  time.innerHTML = `<span>${msg.created_at}</span>` + (isMe ? `<span class="chat-status">${msg.read ? '✓✓ Seen' : '✓ Sent'}</span>` : '');
  content.appendChild(time);

  wrap.appendChild(content);
  const actions = document.createElement('div');
  actions.innerHTML = actionButtonsHtml();
  wrap.appendChild(actions.firstElementChild);

  body.appendChild(wrap);
  body.scrollTop = body.scrollHeight;
}

async function sendMessage(e) {
  e?.preventDefault();
  const input = document.getElementById('chatInput');
  const text  = input.value.trim();
  if (!text && !attachedFiles.length) return;

  const fd = new FormData();
  fd.append('receiver_id', RECEIVER);
  if (text) fd.append('body', text);
  attachedFiles.forEach(f => fd.append('attachments[]', f));

  const sendBtn = document.querySelector('#chatForm .btn-primary');
  sendBtn.disabled = true;
  try {
    const res = await fetch(SEND_URL, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.message || 'Message was not sent.');
    input.value = '';
    attachedFiles = []; renderFileChips(); clearReply();
    (data.messages || [data.message]).forEach(appendMessage);
  } catch (err) {
    alert(err.message || 'Message could not be sent.');
  } finally { sendBtn.disabled = false; }
}
document.getElementById('chatForm').addEventListener('submit', sendMessage);

async function pollMessages() {
  try {
    const res = await fetch(`${POLL_URL}?receiver_id=${RECEIVER}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) return;
    const data = await res.json();
    data.messages?.forEach(msg => {
      const existing = document.querySelector(`[data-message-id="${msg.id}"]`);
      if (existing) {
        const status = existing.querySelector('.chat-status');
        if (status) status.textContent = msg.read ? '✓✓ Seen' : '✓ Sent';
      } else {
        appendMessage(msg);
      }
    });
  } catch (e) {}
}
setInterval(pollMessages, 3000);
</script>
@endif
@endsection
