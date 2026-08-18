@extends('logistics.layout')
@section('title', 'Messages')
@section('page-title', 'Messages')
@section('page-sub', 'Communicate with couriers, sellers, and buyers')

@section('content')
<div class="chat-shell">
  <div class="chat-list">
    <div class="chat-list-head">
      <input type="text" style="width:100%;border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px" placeholder="Search…">
    </div>

    @if($couriers->isNotEmpty())
    <div style="padding:8px 16px 4px;font-family:var(--font-mono);font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)">Couriers</div>
    @foreach($couriers as $u)
    <div class="chat-conv {{ $loop->first ? 'active' : '' }}">
      <div class="avatar-sm">{{ strtoupper(substr($u->first_name,0,1).substr($u->last_name,0,1)) }}</div>
      <div class="meta">
        <strong>{{ $u->first_name }} {{ $u->last_name }}</strong>
        <div class="role-tag">Courier</div>
        <p>{{ $u->email }}</p>
      </div>
    </div>
    @endforeach
    @endif

    @if($sellers->isNotEmpty())
    <div style="padding:8px 16px 4px;font-family:var(--font-mono);font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)">Sellers</div>
    @foreach($sellers as $u)
    <div class="chat-conv">
      <div class="avatar-sm">{{ strtoupper(substr($u->first_name,0,1).substr($u->last_name,0,1)) }}</div>
      <div class="meta">
        <strong>{{ $u->first_name }} {{ $u->last_name }}</strong>
        <div class="role-tag">Seller</div>
        <p>{{ $u->email }}</p>
      </div>
    </div>
    @endforeach
    @endif

    @if($buyers->isNotEmpty())
    <div style="padding:8px 16px 4px;font-family:var(--font-mono);font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)">Buyers</div>
    @foreach($buyers->take(10) as $u)
    <div class="chat-conv">
      <div class="avatar-sm">{{ strtoupper(substr($u->first_name,0,1).substr($u->last_name,0,1)) }}</div>
      <div class="meta">
        <strong>{{ $u->first_name }} {{ $u->last_name }}</strong>
        <div class="role-tag">Buyer</div>
        <p>{{ $u->email }}</p>
      </div>
    </div>
    @endforeach
    @endif
  </div>

  <div class="chat-main">
    <div class="chat-head">
      <div class="avatar-sm">L</div>
      <div><strong>Select a conversation</strong><div style="font-size:11px;color:var(--muted)">Choose a courier, seller, or buyer from the list</div></div>
    </div>
    <div class="chat-body">
      <div class="empty" style="margin:auto">
        <div class="ic">✉</div>
        <h3>No conversation selected</h3>
        <p>Messaging functionality coming soon.</p>
      </div>
    </div>
    <div class="chat-input">
      <input type="text" placeholder="Type a message…">
      <button class="btn btn-primary" data-toast="Message sent!">Send</button>
    </div>
  </div>
</div>
@endsection
