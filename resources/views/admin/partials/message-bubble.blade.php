@php($isMe = $msg->sender_id === $myId)
<div class="chat-msg-wrap {{ $isMe ? '' : 'chat-msg-wrap-in' }}" data-message-id="{{ $msg->id }}">
  <div class="chat-msg-content">
    @if($msg->attachment_path)
      @php($url = route('message.media', ['path' => $msg->attachment_path]))
      @if($msg->attachment_type === 'image')
        <button type="button" class="chat-media-button" onclick="openMediaViewer('{{ $url }}','image')">
          <img src="{{ $url }}" class="chat-attach-preview-img" alt="{{ $msg->attachment_name ?: 'Image attachment' }}">
        </button>
      @elseif($msg->attachment_type === 'video')
        <button type="button" class="chat-media-button" onclick="openMediaViewer('{{ $url }}','video')">
          <video src="{{ $url }}" class="chat-attach-preview-img" controls preload="metadata" playsinline></video>
        </button>
      @else
        <a class="chat-doc-bubble" href="{{ $url }}" target="_blank">📎 {{ $msg->attachment_name ?: 'File' }}</a>
      @endif
    @endif
    @if($msg->body)
      <div class="chat-bubble {{ $isMe ? 'chat-bubble-out' : 'chat-bubble-in' }}">{{ $msg->body }}</div>
    @endif
    <div class="chat-time">
      <span>{{ \Carbon\Carbon::parse($msg->created_at)->format('g:i A') }}</span>
      @if($isMe)<span class="chat-status">{{ $msg->read ? '✓✓ Seen' : '✓ Sent' }}</span>@endif
    </div>
  </div>
  <div class="chat-msg-actions">
    <button type="button" class="chat-msg-action" title="React" onclick="toggleReaction(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.7c0 5.2-8.8 10.3-8.8 10.3S3.2 13.9 3.2 8.7A4.7 4.7 0 0112 6.1a4.7 4.7 0 018.8 2.6z"/></svg></button>
    <button type="button" class="chat-msg-action" title="Reply" onclick="replyToMessage(this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 17l-5-5 5-5M4 12h10a6 6 0 016 6v1"/></svg></button>
  </div>
</div>
