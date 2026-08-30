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
      <div class="avatar-sm">{{ strtoupper(substr($u->given_names,0,1).substr($u->last_name,0,1)) }}</div>
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
        <span class="chat-conv-time">{{ $u->last_message->created_at->diffForHumans(null, true) }}</span>
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
    <div class="chat-head">
      <div class="avatar-sm">{{ strtoupper(substr($selectedUser->given_names,0,1).substr($selectedUser->last_name,0,1)) }}</div>
      <div>
        <strong style="font-size:13.5px;font-family:var(--font-body)">{{ $selectedUser->given_names }} {{ $selectedUser->last_name }}</strong>
        <div style="font-size:11px;color:var(--muted);font-family:var(--font-mono)">{{ ucfirst($selectedUser->account_type) }} · {{ $selectedUser->email }}</div>
      </div>
    </div>
    <div class="chat-body">
      @forelse($messages as $msg)
      <div class="bubble {{ $msg->sender_id == auth()->id() ? 'out' : 'in' }}">
        {{ $msg->body }}
        <time>{{ \Carbon\Carbon::parse($msg->created_at)->format('M d, H:i') }}</time>
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
    <div class="chat-input">
      <form method="POST" action="{{ route('admin.messages.send', $selectedUser->id) }}" style="display:flex;gap:10px;flex:1">
        @csrf
        <input type="text" name="body" placeholder="Type a message…" style="font-family:var(--font-body);font-size:13px" required autocomplete="off">
        <button class="btn btn-primary" type="submit">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M18 2L2 9l6 3 3 6 7-16z"/></svg>
          Send
        </button>
      </form>
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
@endsection
