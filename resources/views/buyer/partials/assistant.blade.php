@php($assistantGuest = $assistantGuest ?? false)
@if($assistantGuest || (auth()->user()?->account_type === 'buyer' && !auth()->user()->is_admin))
@php($assistantProduct = in_array(request()->route()?->getName(), ['guest.product', 'buyer.product'], true) ? request()->route('id') : null)
<link rel="stylesheet" href="{{ asset('css/buyer-assistant.css') }}?v={{ filemtime(public_path('css/buyer-assistant.css')) }}">
<div class="pf-assistant" data-endpoint="{{ route($assistantGuest ? 'guest.assistant.send' : 'buyer.assistant.send') }}" @unless($assistantGuest) data-conversations="{{ route('buyer.assistant.conversations') }}" @endunless data-support="{{ route('support.contact') }}" data-guest="{{ $assistantGuest ? 'true' : 'false' }}">
  <button class="pf-assistant-launch" type="button" aria-label="Open PocketFinds assistant" title="PocketFinds assistant" aria-expanded="false" aria-controls="pfAssistantPanel">
    @include('buyer.partials.icon', ['name' => 'chat', 'size' => 24])
    <span>Ask PocketFinds</span>
  </button>
  <section id="pfAssistantPanel" class="pf-assistant-panel" role="dialog" aria-label="PocketFinds assistant" hidden>
    <header class="pf-assistant-head">
      <img src="{{ asset('images/logo.png') }}" alt="" width="32" height="32">
      <div><strong>PocketFinds Assistant</strong><small data-engine-title>Automated replies</small></div>
      @unless($assistantGuest)<button type="button" data-history aria-label="View conversations" title="Conversations">@include('buyer.partials.icon', ['name' => 'chat', 'size' => 18])</button>@endunless
      <button type="button" data-clear aria-label="Start a new chat" title="Start a new chat">@include('buyer.partials.icon', ['name' => 'edit', 'size' => 18])</button>
      <button type="button" data-close aria-label="Close assistant" title="Close">@include('buyer.partials.icon', ['name' => 'x', 'size' => 20])</button>
    </header>
    @unless($assistantGuest)
    <template data-icon-rename>@include('buyer.partials.icon', ['name' => 'edit', 'size' => 16])</template>
    <template data-icon-delete>@include('buyer.partials.icon', ['name' => 'trash', 'size' => 16])</template>
    <section class="pf-assistant-history" aria-label="Saved conversations" hidden>
      <header><strong>Conversations</strong><button type="button" data-history-close aria-label="Back to chat" title="Back to chat">@include('buyer.partials.icon', ['name' => 'x', 'size' => 18])</button></header>
      <div data-conversation-list></div>
      <button type="button" data-more-chats hidden>More conversations</button>
      <form data-rename-form hidden><label for="pfChatTitle">Conversation name</label><input id="pfChatTitle" maxlength="100" required><button type="submit">Save</button><button type="button" data-rename-cancel>Cancel</button></form>
      <form data-delete-form hidden><p>Delete this conversation and all its messages?</p><button type="submit">Delete conversation</button><button type="button" data-delete-cancel>Keep it</button></form>
    </section>
    @endunless
    <fieldset class="pf-assistant-engine"><legend class="pf-assistant-sr">Reply mode</legend>@unless($assistantGuest)<label><input type="radio" name="pfAssistantEngine" value="smart" checked> Smart</label>@endunless<label><input type="radio" name="pfAssistantEngine" value="automated" @if($assistantGuest) checked @endif> Automated</label><label><input type="radio" name="pfAssistantEngine" value="ai"> AI chat</label></fieldset>
    @unless($assistantGuest)<button type="button" class="pf-assistant-older" data-older hidden>Earlier messages</button>@endunless
    <div class="pf-assistant-feed" role="log" aria-label="Conversation" aria-live="polite" aria-relevant="additions"></div>
    <div class="pf-assistant-prompts">
      <button type="button" data-prompt="Help me find something under 500 pesos.">Under &#8369;500</button>
      <button type="button" data-prompt="Help me choose a gift. Ask me about the recipient and budget.">Find a gift</button>
      @unless($assistantGuest)<button type="button" data-prompt="What are the statuses of my recent orders?" data-orders>My orders</button>@endunless
    </div>
    <nav class="pf-assistant-shortcuts" aria-label="Shopping help links">
      <a href="{{ $assistantGuest ? url('/') : route('buyer.dashboard') }}#products">Products</a>
      <a href="{{ $assistantGuest ? route('login') : route('buyer.orders') }}">{{ $assistantGuest ? 'Sign in' : 'Orders' }}</a>
      @unless($assistantGuest)<a href="{{ route('buyer.faqs') }}">FAQs</a>@endunless
      <button type="button" data-support-open>Contact support</button>
    </nav>
    <div class="pf-assistant-consent">
      @if(is_string($assistantProduct))<label><input type="checkbox" data-share-product value="{{ $assistantProduct }}"> Include this product for comparisons</label>@endif
      @unless($assistantGuest)<label><input type="checkbox" data-share-orders> Include my recent order statuses</label>@endunless
      <small data-engine-note>Automated replies stay within PocketFinds. Don't share passwords or payment details.</small>
    </div>
    <p class="pf-assistant-status" role="status"></p>
    <form class="pf-assistant-form">
      <label class="pf-assistant-sr" for="pfAssistantMessage">Message</label>
      <textarea id="pfAssistantMessage" rows="1" maxlength="1500" placeholder="Ask about a product or order..." required></textarea>
      <button type="submit" aria-label="Send message" title="Send message">@include('buyer.partials.icon', ['name' => 'send', 'size' => 21])</button>
    </form>
    <form class="pf-assistant-support" hidden>
      <strong>Contact human support</strong>
      <label for="pfSupportEmail">Reply email</label><input id="pfSupportEmail" name="email" type="email" autocomplete="email" maxlength="255" required>
      <label for="pfSupportMessage">Your issue</label><textarea id="pfSupportMessage" name="message" rows="3" maxlength="2000" required></textarea>
      <small>Sent to PocketFinds support, not to AI. Do not include passwords or payment details.</small>
      <div><button type="submit">Send to support</button><button type="button" data-support-close>Cancel</button></div>
      <p role="status" data-support-status></p>
    </form>
  </section>
</div>
<script src="{{ asset('js/buyer-assistant.js') }}?v={{ filemtime(public_path('js/buyer-assistant.js')) }}" defer></script>

@endif
