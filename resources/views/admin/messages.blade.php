@extends('admin.layout')
@section('title', 'Messages')
@section('page-title', 'Messages')
@section('page-sub', 'Platform messaging and support inbox')

@section('content')
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">
  {{ session('success') }}
</div>
@endif
@error('body')
<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">
  {{ $message }}
</div>
@enderror

<div class="chat-shell">
  <div class="chat-list">
    <div class="chat-list-head">
      <input type="text" data-chat-search style="width:100%;border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px" placeholder="Search users...">
    </div>
    @forelse($users as $user)
    @php
      $unread = \App\Models\Message::where('sender_id', $user->id)->where('receiver_id', auth()->id())->where('read', false)->count();
    @endphp
    <a href="{{ route('admin.messages.user', $user) }}" class="chat-conv {{ $selectedUser && $selectedUser->id === $user->id ? 'active' : '' }}" data-chat-user>
      <div class="avatar-sm">{{ strtoupper(substr($user->first_name, 0, 1).substr($user->last_name, 0, 1)) }}</div>
      <div class="meta">
        <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>
        <div class="role-tag">{{ ucfirst($user->account_type) }}</div>
        <p>{{ $user->email }}</p>
      </div>
      @if($unread)
      <span class="unread">{{ $unread }}</span>
      @endif
    </a>
    @empty
    <div style="padding:20px;text-align:center;color:var(--muted);font-size:13px">No users yet.</div>
    @endforelse
  </div>

  <div class="chat-main">
    @if($selectedUser)
    <div class="chat-head">
      <div class="avatar-sm">{{ strtoupper(substr($selectedUser->first_name, 0, 1).substr($selectedUser->last_name, 0, 1)) }}</div>
      <div>
        <strong>{{ $selectedUser->first_name }} {{ $selectedUser->last_name }}</strong>
        <div style="font-size:11px;color:var(--muted)">{{ ucfirst($selectedUser->account_type) }} - {{ $selectedUser->email }}</div>
      </div>
    </div>
    <div class="chat-body" id="chatBody">
      @forelse($messages as $message)
      <div class="bubble {{ $message->sender_id === auth()->id() ? 'out' : 'in' }}">
        {{ $message->body }}
        <time>{{ $message->created_at?->format('M d, Y g:i A') }}</time>
      </div>
      @empty
      <div class="empty" style="margin:auto">
        <div class="ic"><x-admin-icon name="mail" /></div>
        <h3>No messages yet</h3>
        <p>Start the conversation with {{ $selectedUser->first_name }}.</p>
      </div>
      @endforelse
    </div>
    <form class="chat-input" method="POST" action="{{ route('admin.messages.send', $selectedUser) }}">
      @csrf
      <input type="text" name="body" value="{{ old('body') }}" placeholder="Type a message..." maxlength="2000" required>
      <button class="btn btn-primary" type="submit">Send</button>
    </form>
    @else
    <div class="empty" style="margin:auto">
      <div class="ic"><x-admin-icon name="mail" /></div>
      <h3>No users to message</h3>
    </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
  const chatBody = document.getElementById('chatBody');
  if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;

  document.querySelector('[data-chat-search]')?.addEventListener('input', (event) => {
    const query = event.target.value.trim().toLowerCase();
    document.querySelectorAll('[data-chat-user]').forEach((item) => {
      item.hidden = query && !item.textContent.toLowerCase().includes(query);
    });
  });
</script>
@endpush
