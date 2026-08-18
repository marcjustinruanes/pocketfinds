@extends('admin.layout')
@section('title', 'Messages')
@section('page-title', 'Messages')
@section('page-sub', 'Platform messaging and support inbox')

@section('content')
<div class="chat-shell">
  <div class="chat-list">
    <div class="chat-list-head">
      <input type="text" style="width:100%;border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px" placeholder="Search users…">
    </div>
    @forelse($users as $user)
    <div class="chat-conv {{ $loop->first ? 'active' : '' }}">
      <div class="avatar-sm">{{ strtoupper(substr($user->first_name,0,1).substr($user->last_name,0,1)) }}</div>
      <div class="meta">
        <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>
        <div class="role-tag">{{ ucfirst($user->account_type) }}</div>
        <p>{{ $user->email }}</p>
      </div>
    </div>
    @empty
    <div style="padding:20px;text-align:center;color:var(--muted);font-size:13px">No users yet.</div>
    @endforelse
  </div>

  <div class="chat-main">
    @if($users->isNotEmpty())
    <div class="chat-head">
      <div class="avatar-sm">{{ strtoupper(substr($users->first()->first_name,0,1).substr($users->first()->last_name,0,1)) }}</div>
      <div>
        <strong>{{ $users->first()->first_name }} {{ $users->first()->last_name }}</strong>
        <div style="font-size:11px;color:var(--muted)">{{ ucfirst($users->first()->account_type) }} · {{ $users->first()->email }}</div>
      </div>
    </div>
    <div class="chat-body">
      <div class="empty" style="margin:auto">
        <div class="ic">✉</div>
        <h3>No messages yet</h3>
        <p>Messaging functionality coming soon.</p>
      </div>
    </div>
    <div class="chat-input">
      <input type="text" placeholder="Type a message…">
      <button class="btn btn-primary" data-toast="Message sent!">Send</button>
    </div>
    @else
    <div class="empty" style="margin:auto">
      <div class="ic">✉</div>
      <h3>No users to message</h3>
    </div>
    @endif
  </div>
</div>
@endsection
