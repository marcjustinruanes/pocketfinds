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
    <a href="{{ route('admin.messages.user', $u->id) }}" class="chat-conv {{ isset($selectedUser) && $selectedUser?->id == $u->id ? 'active' : '' }} {{ $u->unread_count > 0 ? 'has-unread' : '' }}" style="text-decoration:none;color:inherit">
      <x-user-avatar :user="$u" size="36" class="avatar-sm" />
      <div class="meta">
        <strong>{{ $u->given_names }} {{ $u->last_name }}</strong>
        <div class="role-tag">{{ ucfirst($u->account_type) }}</div>
        <p>
          @if($u->last_message)
            @if($u->last_message->sender_id === $myId)<span style="color:var(--muted)">You: </span>@endif
            {{ $u->last_message->body ?: '📎 Attachment' }}
          @else
            {{ $u->email }}
          @endif
        </p>
      </div>
      <div class="chat-conv-side">
        @if($u->last_message)
        <span class="chat-conv-time">{{ $u->last_message->created_at?->diffForHumans(null, true) ?? '' }}</span>
        @endif
        @if($u->unread_count > 0)
        <span class="unread">{{ $u->unread_count }}</span>
        @endif
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
    <div class="chat-head chat-head-clickable" data-modal-open="profileModal-{{ $selectedUser->id }}" title="View profile">
      <x-user-avatar :user="$selectedUser" size="36" class="avatar-sm" />
      <div>
        <strong style="font-size:13.5px;font-family:var(--font-body)">{{ $selectedUser->given_names }} {{ $selectedUser->last_name }}</strong>
        <div style="font-size:11px;color:var(--muted);font-family:var(--font-mono)">{{ ucfirst($selectedUser->account_type) }} · {{ $selectedUser->email }}</div>
      </div>
    </div>
    <div class="chat-body" id="chatBody">
      @forelse($messages as $msg)
      <div class="bubble {{ $msg->sender_id == auth()->id() ? 'out' : 'in' }}" data-msg-id="{{ $msg->id }}">
        <div class="bubble-actions">
          <button type="button" class="bubble-action-btn" data-reply-btn
                  data-reply-name="{{ $msg->sender->given_names ?? 'them' }}"
                  data-reply-text="{{ \Illuminate\Support\Str::limit($msg->body ?: '📎 Attachment', 50) }}"
                  data-reply-id="{{ $msg->id }}" title="Reply">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
          </button>
          <button type="button" class="bubble-action-btn" data-react-btn data-msg-id="{{ $msg->id }}" title="React">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
          </button>
        </div>

        <div class="reaction-picker" id="reactPicker-{{ $msg->id }}">
          @foreach(['👍','❤️','😂','😮','😢'] as $emoji)
          <form method="POST" action="{{ route('admin.messages.react', $msg->id) }}">
            @csrf
            <input type="hidden" name="emoji" value="{{ $emoji }}">
            <button type="submit">{{ $emoji }}</button>
          </form>
          @endforeach
        </div>

        @if($msg->replyTo)
        <div class="bubble-quote">
          <strong>{{ $msg->replyTo->sender->given_names ?? 'Someone' }}</strong>
          {{ \Illuminate\Support\Str::limit($msg->replyTo->body ?: '📎 Attachment', 60) }}
        </div>
        @endif

        @if($msg->attachment_path)
          @if($msg->attachment_type === 'image')
          <img src="{{ Storage::url($msg->attachment_path) }}" alt="{{ $msg->attachment_name }}" class="bubble-img" onclick="window.open(this.src,'_blank')">
          @else
          <a href="{{ Storage::url($msg->attachment_path) }}" target="_blank" class="bubble-file">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            {{ $msg->attachment_name }}
          </a>
          @endif
        @endif

        @if($msg->body)
        <div class="bubble-text">{{ $msg->body }}</div>
        @endif

        <time>
          {{ \Carbon\Carbon::parse($msg->created_at)->format('M d, H:i') }}
          @if($msg->sender_id == auth()->id())
            · {{ $msg->read ? '✓✓ Seen' : '✓ Delivered' }}
          @endif
        </time>

        @if(!empty($msg->reactions))
        <div class="bubble-reactions">
          @foreach($msg->reactions as $emoji => $userIds)
          <span class="reaction-badge {{ in_array(auth()->id(), $userIds) ? 'mine' : '' }}">{{ $emoji }} {{ count($userIds) }}</span>
          @endforeach
        </div>
        @endif
      </div>
      @empty
      <div class="empty" style="margin:auto">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
        <h3>No messages yet</h3>
        <p>Start the conversation below.</p>
      </div>
      @endforelse
    </div>

    <div class="reply-bar" id="replyBar">
      <span class="reply-bar-text">Replying to <strong id="replyBarName"></strong>: <span id="replyBarText"></span></span>
      <button type="button" class="reply-bar-close" id="replyBarClose" aria-label="Cancel reply">&times;</button>
    </div>
    <div class="chat-input">
      <form method="POST" action="{{ route('admin.messages.send', $selectedUser->id) }}" enctype="multipart/form-data" style="display:flex;gap:10px;flex:1" id="sendForm">
        @csrf
        <input type="hidden" name="reply_to_id" id="replyToInput" value="">
        <label class="attach-btn" id="attachBtn" title="Attach a photo">
          <input type="file" name="attachment" accept="image/*,video/*,.pdf" style="display:none" id="attachInput">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
        </label>
        <input type="text" name="body" placeholder="Type a message…" style="font-family:var(--font-body);font-size:13px" autocomplete="off">
        <button class="btn btn-primary" type="submit">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M18 2L2 9l6 3 3 6 7-16z"/></svg>
          Send
        </button>
      </form>
    </div>

    {{-- Profile modal --}}
    <div class="modal-overlay" id="profileModal-{{ $selectedUser->id }}">
      <div class="modal modal-lg">
        <div class="modal-head">
          <div class="modal-head-main">
            <span class="modal-icon"><x-admin-icon name="users" /></span>
            <div class="modal-head-copy">
              <h3>{{ $selectedUser->given_names }} {{ $selectedUser->last_name }}</h3>
              <p>{{ ucfirst($selectedUser->account_type) }} — {{ $selectedUser->email }}</p>
            </div>
          </div>
          <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
        </div>
        <div class="modal-body">
          @include('admin.partials.user-profile-body', ['user' => $selectedUser])
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" data-modal-close>Close</button>
        </div>
      </div>
    </div>

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

<script>
document.addEventListener('DOMContentLoaded', () => {
  const chatBody   = document.getElementById('chatBody');
  const replyBar   = document.getElementById('replyBar');
  const replyInput = document.getElementById('replyToInput');
  const attachBtn  = document.getElementById('attachBtn');
  const attachInput = document.getElementById('attachInput');

  if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;

  // Reply
  document.querySelectorAll('[data-reply-btn]').forEach(btn => {
    btn.addEventListener('click', () => {
      replyInput.value = btn.dataset.replyId;
      document.getElementById('replyBarName').textContent = btn.dataset.replyName;
      document.getElementById('replyBarText').textContent = btn.dataset.replyText;
      replyBar?.classList.add('show');
      document.querySelector('.chat-input input[name="body"]')?.focus();
    });
  });
  document.getElementById('replyBarClose')?.addEventListener('click', () => {
    replyInput.value = '';
    replyBar?.classList.remove('show');
  });

  // Reactions — toggle the picker, close others when one opens
  document.querySelectorAll('[data-react-btn]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const picker = document.getElementById('reactPicker-' + btn.dataset.msgId);
      const wasOpen = picker?.classList.contains('show');
      document.querySelectorAll('.reaction-picker.show').forEach(p => p.classList.remove('show'));
      if (picker && !wasOpen) picker.classList.add('show');
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.reaction-picker.show').forEach(p => p.classList.remove('show'));
  });

  // Attach button visual feedback + auto-submit isn't needed, just show a selected state
  attachInput?.addEventListener('change', () => {
    attachBtn?.classList.toggle('has-file', attachInput.files.length > 0);
  });
});
</script>
@endsection
