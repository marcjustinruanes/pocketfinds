@extends('admin.layout')
@section('title', 'Messages')
@section('page-title', 'Messages')
@section('page-sub', 'Platform messaging and support inbox')

@section('content')
<div class="chat-shell">
  <div class="chat-list">
    <div class="chat-list-head">
      <div style="position:relative">
        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted)" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" width="14" height="14"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M15 15l-3-3"/></svg>
        <input type="text" style="width:100%;border:1px solid var(--border);border-radius:9px;padding:8px 12px 8px 32px;font-size:13px;font-family:var(--font-body)" placeholder="Search users…">
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
            @if($u->last_message->sender_id === auth()->id())<span style="color:var(--muted)">You: </span>@endif
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
        <div class="ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
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
          @include('admin.partials.user-profile-body', ['user' => $selectedUser, 'riderProfile' => $selectedUser->riderProfile ?? null])
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" data-modal-close>Close</button>
        </div>
      </div>
    </div>

    @else
    <div class="chat-head">
      <div class="avatar-sm">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" width="16" height="16"><circle cx="10" cy="7" r="3.5"/><path d="M3 17c0-3.3 3.1-6 7-6s7 2.7 7 6"/></svg>
      </div>
      <div>
        <strong style="font-size:13.5px;font-family:var(--font-body)">Select a conversation</strong>
        <div style="font-size:11px;color:var(--muted);font-family:var(--font-mono)">Choose a user from the list</div>
      </div>
    </div>
    <div class="chat-body">
      <div class="empty" style="margin:auto">
        <div class="ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
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
