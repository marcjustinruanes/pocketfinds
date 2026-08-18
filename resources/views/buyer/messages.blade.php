@extends('buyer.layout')
@section('title', 'Messages')
@section('page-title', 'Messages')
@section('page-sub', 'Chat with sellers')

@section('content')
<div class="chat-shell">
  <div class="chat-list" id="chatList">
    <div class="chat-list-head">
      <strong style="font-size:13.5px">Conversations</strong>
    </div>
    <div class="empty" style="padding:40px 20px">
      <div class="ic">✉</div>
      <h3>No messages</h3>
      <p>Start a conversation with a seller.</p>
    </div>
  </div>

  <div class="chat-main">
    <div class="chat-head">
      <button class="icon-btn" style="display:none" id="chatBack">←</button>
      <div style="color:var(--muted);font-size:13px">Select a conversation</div>
    </div>
    <div class="chat-body" style="align-items:center;justify-content:center">
      <div style="text-align:center;color:var(--muted)">
        <div style="font-size:32px;margin-bottom:8px">💬</div>
        <p style="font-size:13px">No conversation selected</p>
      </div>
    </div>
    <div class="chat-input">
      <input type="text" placeholder="Type a message…" disabled>
      <button class="btn btn-primary" disabled>Send</button>
    </div>
  </div>
</div>
@endsection
