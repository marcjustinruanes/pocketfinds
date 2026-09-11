{{-- Message bubble list — shared by the full page load and the AJAX
     send/poll/react responses so there is exactly one place this markup
     lives. Expects $messages (a collection with sender/replyTo.sender
     eager-loaded) and uses the authenticated admin as the "out" side. --}}
@forelse($messages as $msg)
<div class="bubble {{ $msg->sender_id == auth()->id() ? 'out' : 'in' }}" data-msg-id="{{ $msg->id }}">
  <div class="bubble-actions">
    <button type="button" class="bubble-action-btn" data-reply-btn
            data-reply-name="{{ $msg->sender->given_names ?? 'them' }}"
            data-reply-text="{{ \Illuminate\Support\Str::limit($msg->body ?: ($msg->product_id ? '🛍️ Product' : '📎 Attachment'), 50) }}"
            data-reply-id="{{ $msg->id }}" title="Reply">
      <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
    </button>
    <button type="button" class="bubble-action-btn" data-react-btn data-msg-id="{{ $msg->id }}" title="React">
      <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
    </button>
  </div>

  <div class="reaction-picker" id="reactPicker-{{ $msg->id }}">
    @foreach(['👍','❤️','😂','😮','😢'] as $emoji)
    <button type="button" class="react-emoji-btn" data-react-emoji="{{ $emoji }}" data-msg-id="{{ $msg->id }}">{{ $emoji }}</button>
    @endforeach
  </div>

  @if($msg->replyTo)
  <div class="bubble-quote">
    <strong>{{ $msg->replyTo->sender->given_names ?? 'Someone' }}</strong>
    {{ \Illuminate\Support\Str::limit($msg->replyTo->body ?: ($msg->replyTo->product_id ? '🛍️ Product' : '📎 Attachment'), 60) }}
  </div>
  @endif

  @if($msg->attachment_path)
    @if($msg->attachment_type === 'image')
    <button type="button" class="chat-media-button" data-media-url="{{ route('message.media', ['path' => $msg->attachment_path]) }}" data-media-type="image">
      <img src="{{ route('message.media', ['path' => $msg->attachment_path]) }}" alt="{{ $msg->attachment_name }}" class="bubble-img">
    </button>
    @elseif($msg->attachment_type === 'video')
    <button type="button" class="chat-media-button" data-media-url="{{ route('message.media', ['path' => $msg->attachment_path]) }}" data-media-type="video">
      <video src="{{ route('message.media', ['path' => $msg->attachment_path]) }}" class="bubble-img" style="max-height:280px"></video>
    </button>
    @else
    <a href="{{ route('message.media', ['path' => $msg->attachment_path]) }}" target="_blank" class="bubble-file">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      {{ $msg->attachment_name }}
    </a>
    @endif
  @endif

  @if($msg->product)
  <a href="{{ route('admin.products') }}?q={{ urlencode($msg->product->name) }}" class="bubble-product" style="display:flex;align-items:center;gap:9px;padding:9px;border-radius:10px;background:var(--paper);border:1px solid var(--border);text-decoration:none;color:inherit;max-width:min(280px,72vw)">
    <span style="width:38px;height:38px;border-radius:8px;background:var(--pink-soft);display:grid;place-items:center;overflow:hidden;flex:none">
      @if($msg->product->image)
        <img src="{{ rtrim(config('filesystems.disks.supabase.url'), '/') . '/' . ltrim($msg->product->image, '/') }}" style="width:100%;height:100%;object-fit:cover">
      @else
        🛍️
      @endif
    </span>
    <span style="min-width:0">
      <span style="display:block;font-size:12.5px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $msg->product->name }}</span>
      <span style="display:block;font-size:11.5px;color:var(--pink-dark);font-weight:700">₱{{ number_format($msg->product->price, 2) }}</span>
    </span>
  </a>
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
