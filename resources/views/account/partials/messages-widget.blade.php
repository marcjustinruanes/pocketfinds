<link rel="stylesheet" href="{{ asset('css/buyer-messages-widget.css') }}?v={{ filemtime(public_path('css/buyer-messages-widget.css')) }}">
<div class="chat-widget" id="{{ $messageWidgetId }}" role="dialog" aria-labelledby="{{ $messageWidgetTitleId }}" aria-hidden="true">
 <div class="chat-widget-head">
  <div><strong id="{{ $messageWidgetTitleId }}">Messages</strong><span>Chat with {{ $sellerMessageWidget ? 'buyers' : 'sellers' }}</span></div>
  <div class="chat-widget-controls"><button type="button" class="chat-widget-close" @if($sellerMessageWidget) data-seller-messages-close @else data-modal-close @endif aria-label="Close messages"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12"/></svg></button></div>
 </div>
 <div id="{{ $messageWidgetBodyId }}" class="chat-widget-body"></div>
</div>
