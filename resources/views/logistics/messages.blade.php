@extends('logistics.layout')
@section('title', 'Messages')
@section('page-title', 'Messages')
@section('page-sub', 'Communicate with couriers, sellers, and buyers')

@section('content')
<div class="chat-shell">
  {{-- Contact list --}}
  <div class="chat-list">
    <div class="chat-list-head">
      <div style="position:relative">
        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted)" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" width="14" height="14"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M15 15l-3-3"/></svg>
        <input type="text" id="contactSearch" style="width:100%;border:1px solid var(--border);border-radius:9px;padding:8px 12px 8px 32px;font-size:13px;font-family:var(--font-body)" placeholder="Search…">
      </div>
    </div>

    @if($couriers->isNotEmpty())
    <div style="padding:10px 16px 4px;font-family:var(--font-mono);font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)">Couriers</div>
    @foreach($couriers as $u)
    <a href="{{ route('logistics.messages.thread', $u->id) }}" class="chat-conv {{ isset($activeUser) && $activeUser->id === $u->id ? 'active' : '' }}">
      <div class="avatar-sm">{{ strtoupper(substr($u->first_name,0,1).substr($u->last_name,0,1)) }}</div>
      <div class="meta">
        <strong>{{ $u->first_name }} {{ $u->last_name }}</strong>
        <div class="role-tag">Courier</div>
        <p>{{ $u->email }}</p>
      </div>
    </a>
    @endforeach
    @endif

    @if($sellers->isNotEmpty())
    <div style="padding:10px 16px 4px;font-family:var(--font-mono);font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)">Sellers</div>
    @foreach($sellers as $u)
    <a href="{{ route('logistics.messages.thread', $u->id) }}" class="chat-conv {{ isset($activeUser) && $activeUser->id === $u->id ? 'active' : '' }}">
      <div class="avatar-sm">{{ strtoupper(substr($u->first_name,0,1).substr($u->last_name,0,1)) }}</div>
      <div class="meta">
        <strong>{{ $u->first_name }} {{ $u->last_name }}</strong>
        <div class="role-tag">Seller</div>
        <p>{{ $u->email }}</p>
      </div>
    </a>
    @endforeach
    @endif

    @if($buyers->isNotEmpty())
    <div style="padding:10px 16px 4px;font-family:var(--font-mono);font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)">Buyers</div>
    @foreach($buyers->take(10) as $u)
    <a href="{{ route('logistics.messages.thread', $u->id) }}" class="chat-conv {{ isset($activeUser) && $activeUser->id === $u->id ? 'active' : '' }}">
      <div class="avatar-sm">{{ strtoupper(substr($u->first_name,0,1).substr($u->last_name,0,1)) }}</div>
      <div class="meta">
        <strong>{{ $u->first_name }} {{ $u->last_name }}</strong>
        <div class="role-tag">Buyer</div>
        <p>{{ $u->email }}</p>
      </div>
    </a>
    @endforeach
    @endif

    @if($couriers->isEmpty() && $sellers->isEmpty() && $buyers->isEmpty())
    <div style="padding:20px;text-align:center;color:var(--muted);font-size:13px;font-family:var(--font-body)">No contacts found.</div>
    @endif
  </div>

  {{-- Chat main --}}
  <div class="chat-main">
    @if(isset($activeUser))
    <div class="chat-head">
      <div class="avatar-sm">{{ strtoupper(substr($activeUser->first_name,0,1).substr($activeUser->last_name,0,1)) }}</div>
      <div>
        <strong style="font-size:13.5px;font-family:var(--font-body)">{{ $activeUser->first_name }} {{ $activeUser->last_name }}</strong>
        <div style="font-size:11px;color:var(--muted);font-family:var(--font-mono)">{{ ucfirst($activeUser->account_type) }} · {{ $activeUser->email }}</div>
      </div>
    </div>

    <div class="chat-body" id="chatBody">
      @forelse($messages as $msg)
        @if($msg->sender_id === auth()->id())
        <div class="bubble out">
          {{ $msg->body }}
          <time>{{ \Carbon\Carbon::parse($msg->created_at)->format('M j, g:i a') }}</time>
        </div>
        @else
        <div class="bubble in">
          {{ $msg->body }}
          <time>{{ \Carbon\Carbon::parse($msg->created_at)->format('M j, g:i a') }}</time>
        </div>
        @endif
      @empty
      <div class="empty" style="margin:auto">
        <div class="ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h3>No messages yet</h3>
        <p>Send a message to start the conversation.</p>
      </div>
      @endforelse
    </div>

    <form class="chat-input" method="POST" action="{{ route('logistics.messages.send', $activeUser->id) }}">
      @csrf
      <input type="text" name="body" placeholder="Type a message…" autocomplete="off" required style="font-family:var(--font-body);font-size:13px">
      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M18 2L2 9l6 3 3 6 7-16z"/></svg>
        Send
      </button>
    </form>

    @else
    <div class="chat-head">
      <div class="avatar-sm">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" width="16" height="16"><circle cx="10" cy="7" r="3.5"/><path d="M3 17c0-3.3 3.1-6 7-6s7 2.7 7 6"/></svg>
      </div>
      <div>
        <strong style="font-size:13.5px;font-family:var(--font-body)">Select a conversation</strong>
        <div style="font-size:11px;color:var(--muted);font-family:var(--font-mono)">Choose a contact from the list</div>
      </div>
    </div>
    <div class="chat-body">
      <div class="empty" style="margin:auto">
        <div class="ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h3>No conversation selected</h3>
        <p>Pick a courier, seller, or buyer to start messaging.</p>
      </div>
    </div>
    @endif
  </div>
</div>

<script>
  // Auto-scroll to bottom
  const cb = document.getElementById('chatBody');
  if (cb) cb.scrollTop = cb.scrollHeight;

  // Contact search filter
  document.getElementById('contactSearch')?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.chat-conv').forEach(el => {
      el.style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
</script>
@endsection
