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
    <div style="padding:20px;text-align:center;color:var(--muted);font-size:13px;font-family:var(--font-body)">No users yet.</div>
    @endforelse
  </div>

  <div class="chat-main">
    @if($users->isNotEmpty())
    <div class="chat-head">
      <div class="avatar-sm">{{ strtoupper(substr($users->first()->first_name,0,1).substr($users->first()->last_name,0,1)) }}</div>
      <div>
        <strong style="font-size:13.5px;font-family:var(--font-body)">{{ $users->first()->first_name }} {{ $users->first()->last_name }}</strong>
        <div style="font-size:11px;color:var(--muted);font-family:var(--font-mono)">{{ ucfirst($users->first()->account_type) }} · {{ $users->first()->email }}</div>
      </div>
    </div>
    <div class="chat-body">
      <div class="empty" style="margin:auto">
        <div class="ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h3>No messages yet</h3>
        <p>Messaging functionality coming soon.</p>
      </div>
    </div>
    <div class="chat-input">
      <input type="text" placeholder="Type a message…" style="font-family:var(--font-body);font-size:13px">
      <button class="btn btn-primary" data-toast="Message sent!">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M18 2L2 9l6 3 3 6 7-16z"/></svg>
        Send
      </button>
    </div>
    @else
    <div class="empty" style="margin:auto">
      <div class="ic">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </div>
      <h3>No users to message</h3>
    </div>
    @endif
  </div>
</div>
@endsection
