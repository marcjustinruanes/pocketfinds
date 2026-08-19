@extends('seller.layout')
@section('title', 'Messages')
@section('page-title', 'Messages')
@section('page-sub', 'Chat with your customers')

@section('content')
<div class="chat-shell">
  {{-- Conversation list --}}
  <div class="chat-list">
    <div class="chat-list-head">
      <div style="position:relative">
        <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);display:flex;align-items:center;color:var(--muted)">@include('seller.partials.icon', ['name' => 'search', 'size' => 13])</span>
        <input type="text" placeholder="Search conversations…" style="width:100%;border:1px solid var(--border);border-radius:9px;padding:8px 12px 8px 32px;font-size:12.5px">
      </div>
    </div>
    @php $convos = [
      ['Alice','Hey, is this still available?','2m','A',true],
      ['Bob','When will my order arrive?','1h','B',false],
      ['Carol','Can I get a discount?','Yesterday','C',false],
    ]; @endphp
    @foreach($convos as [$name,$preview,$time,$initial,$unread])
    <div class="chat-row {{ $loop->first ? 'active' : '' }}">
      <div class="chat-avatar">{{ $initial }}</div>
      <div class="chat-info">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span class="chat-name">{{ $name }}</span>
          <span style="font-family:var(--font-mono);font-size:10px;color:var(--muted)">{{ $time }}</span>
        </div>
        <div class="chat-preview">{{ $preview }}</div>
      </div>
      @if($unread)<span style="width:8px;height:8px;border-radius:50%;background:var(--pink);flex:none"></span>@endif
    </div>
    @endforeach
  </div>

  {{-- Chat main --}}
  <div class="chat-main">
    <div class="chat-head">
      <div class="chat-avatar">A</div>
      <div>
        <div style="font-size:13px;font-weight:650">Alice</div>
        <div style="font-size:11px;color:var(--muted)">Customer · Online</div>
      </div>
    </div>
    <div class="chat-body">
      <div class="chat-bubble bubble-in">Hey, is this still available?</div>
      <div class="chat-bubble bubble-out">Yes, it's still in stock! Would you like to place an order?</div>
      <div class="chat-bubble bubble-in">Great! How long does delivery take?</div>
    </div>
    <div class="chat-input">
      <input type="text" placeholder="Type a message…">
      <button class="btn btn-primary">@include('seller.partials.icon', ['name' => 'send', 'size' => 15])</button>
    </div>
  </div>
</div>
@endsection
