@extends(auth()->user()->account_type === 'admin' ? 'admin.layout' : (auth()->user()->account_type === 'buyer' ? 'buyer.layout' : 'seller.layout'))
@php
 $purchaseTitle = ($p->snapshot['items'][0]['name'] ?? 'GCash purchase').(count($p->snapshot['items'] ?? []) > 1 ? ' + '.(count($p->snapshot['items']) - 1).' more' : '');
@endphp
@section('title')
{{ $purchaseTitle }} - GCash payment
@endsection
@section('page-title')
{{ $purchaseTitle }}
@endsection
@include('gcash.assets')
@section('content')
@php
 $reviewer = auth()->user()->account_type === 'admin';
 $buyer = auth()->user()->account_type === 'buyer';
 $linkedOrder = \App\Models\Order::where('gcash_purchase_id', $p->id)->first();
 $other = \App\Models\User::find($buyer ? $p->seller_id : $p->buyer_id);
 $live = $p->reservation_state === 'held' && $p->expires_at && $p->expires_at->isFuture();
 $inactive = in_array($p->reservation_state, ['expired', 'released']);
 $ordered = $p->payment_state === 'order_placed';
 $verified = $p->payment_state === 'verified';
 $conversation = !$reviewer ? route($buyer ? 'buyer.messages' : 'seller.messages', $buyer ? ['seller' => $other?->username, 'purchase' => $p->id] : ['buyer' => $other?->id, 'purchase' => $p->id]) : null;
 $currentStep = $ordered ? 5 : (($inactive || $p->payment_state === 'manual_resolution' || $p->reservation_state === 'initializing') ? 0 : ($verified ? 4 : ($p->payment_state === 'proof_submitted' ? 3 : ($p->instructions ? 2 : 1))));
 [$heading, $guidance] = match (true) {
  $ordered => ['Your order has been placed', 'Payment review is complete. Follow the linked order for fulfillment updates.'],
  $p->payment_state === 'manual_resolution' || $inactive => ['This payment needs attention', 'This reservation is no longer available for normal checkout. If money was sent, contact the seller and request manual resolution. Do not pay again.'],
  $verified && $buyer && $placement['eligible'] => ['Payment verified — place your order', 'The seller confirmed receipt for this purchase. Your order will be created only when you select Place Order.'],
  $verified => ['Payment verified', 'The seller has confirmed receipt. The buyer must explicitly place this linked order.'],
  $p->reservation_state === 'initializing' => ['Preparing your reservation', 'Refresh this page to check activation. Do not start another payment while this purchase is being prepared.'],
  $p->payment_state === 'rejected' => ['Payment proof was rejected', 'Review the reason in payment history. Contact the seller before uploading corrected proof or sending any more money.'],
  $p->payment_state === 'proof_submitted' => [$buyer ? 'Waiting for seller verification' : 'Review the buyer payment', $buyer ? 'Your proof was submitted. The seller must check their actual GCash account before Place Order becomes available.' : 'Open the private receipt, then check your actual GCash transaction history. A screenshot alone does not confirm receipt.'],
  !$p->instructions => [$buyer ? 'Ask the seller for payment details' : 'Send your GCash payment details', $buyer ? 'Continue in your seller conversation. The seller will provide the GCash name and number for the exact amount shown here.' : 'Provide your GCash account name and number below so the buyer can pay the agreed amount.'],
  default => [$buyer ? 'Pay the seller, then upload your proof' : 'Waiting for buyer proof', $buyer ? 'Use the seller payment details below. After paying, upload your receipt here for manual review.' : 'The buyer has your payment instructions. Review the receipt after it is submitted.'],
 };
@endphp
<div class="gcash-page" data-purchase="{{ $p->id }}" data-orders-url="{{ route($buyer ? 'buyer.orders' : 'seller.orders') }}" data-payment-state="{{ $p->payment_state }}">
 <nav class="gcash-nav" aria-label="GCash navigation">
  <a href="{{ route('gcash.index') }}">All GCash purchases</a>
  @unless($reviewer)<a href="{{ $conversation }}">Open existing conversation</a>@endunless
  @if($linkedOrder && !$reviewer)<a href="{{ route($buyer ? 'buyer.orders' : 'seller.orders') }}">View linked order {{ $linkedOrder->order_number }}</a>@endif
 </nav>
 <section class="gcash-card gcash-hero" aria-labelledby="gcash-next-step">
  <div class="gcash-hero-head">
   <div><p class="gcash-eyebrow">{{ $reviewer ? 'Read-only payment review' : 'Your next step' }}</p><h2 id="gcash-next-step">{{ $heading }}</h2><span class="gcash-badge {{ $verified || $ordered ? 'gcash-badge-success' : '' }}">Payment: {{ str_replace('_', ' ', ucfirst($p->payment_state)) }}</span></div>
   <div><span class="gcash-muted">Amount payable</span><p class="gcash-amount">&#8369;{{ number_format($p->amount_due, 2) }}</p></div>
  </div>
  <p>{{ $guidance }}</p>
  @if($p->payment_state === 'rejected' && ($rejection = $history->firstWhere('event', 'rejected')))
   <p class="gcash-notice gcash-notice-danger"><strong>Seller rejection reason:</strong> {{ $rejection->reason }}</p>
  @endif
  @if($reviewer)<p class="gcash-muted">Review access does not allow verifying payment, resolving it, or placing an order.</p>@endif
  @if($live)<p class="gcash-notice">Stock reserved until <strong>{{ $p->expires_at->format('M j, Y g:i A') }} {{ config('app.timezone') }}</strong>. Proof submission does not extend this deadline.</p>@endif
  @if($buyer && $verified && !$placement['eligible'])<p class="gcash-notice gcash-notice-danger" role="alert">{{ $placement['reason'] }}</p>@endif
  <div class="gcash-actions">
   @if(!$reviewer && !$ordered)
    @if($buyer && $placement['eligible'])<a class="gcash-button" href="#place-order">Continue to Place Order</a>
    @elseif($inactive || $p->payment_state === 'manual_resolution')<a class="gcash-button gcash-button-secondary" href="#payment-help">Get payment help</a>
    @elseif(!$buyer && !$p->instructions && $live)<a class="gcash-button" href="#payment-instructions">Add payment details</a>
    @elseif(!$buyer && $p->payment_state === 'proof_submitted' && $live)<a class="gcash-button" href="#seller-review">Review payment</a>
    @elseif($buyer && $p->instructions && in_array($p->payment_state, ['awaiting_payment', 'rejected']))<a class="gcash-button" href="#upload-proof">Upload payment proof</a>
    @else<a class="gcash-button" href="{{ $conversation }}">Message {{ $buyer ? 'seller' : 'buyer' }}</a>@endif
    <a class="gcash-button gcash-button-secondary" href="{{ route('gcash.show', $p->id) }}">Refresh status</a>
   @endif
  </div>
  <ol class="gcash-progress" aria-label="Payment progress">
   @foreach(['Payment details', 'Submit proof', 'Seller verification', 'Place Order', 'Order confirmation'] as $step)
    <li class="{{ $loop->iteration < $currentStep ? 'is-complete' : '' }}" @if($loop->iteration === $currentStep) aria-current="step" @endif><span class="gcash-step-number">{{ $loop->iteration }}</span><span>{{ $step }}</span></li>
   @endforeach
  </ol>
 </section>
 <div id="gcash-message" role="status" aria-live="polite" tabindex="-1"></div>
 <div class="gcash-grid">
  <div class="gcash-stack">
   <section class="gcash-card" id="payment-instructions" aria-labelledby="instructions-heading">
    <h3 id="instructions-heading">Seller payment details</h3>
    @if($p->instructions)
     <dl class="gcash-fields"><div><dt>GCash name</dt><dd>{{ $p->instructions['name'] }}</dd></div><div><dt>GCash number</dt><dd><span id="gcash-number">{{ $p->instructions['number'] }}</span> <button type="button" class="gcash-button gcash-button-secondary" data-copy="gcash-number">Copy mobile number</button></dd></div></dl>
     <p class="gcash-muted">Pay only the amount agreed for this purchase. Sending a receipt does not automatically verify payment.</p>
    @else
     <p class="gcash-muted">Wait for the seller to provide payment instructions.</p>
     @if($buyer && !$inactive)<a class="gcash-button gcash-button-secondary" href="{{ $conversation }}">Ask seller for payment details</a>@endif
    @endif
    @if(!$reviewer && !$buyer && $live && !$p->instructions)
     <form class="gcash-form" method="post" action="{{ route('gcash.instructions', $p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}">
      <p><label>GCash account name <input name="name" required maxlength="150" data-account-name autocomplete="off" aria-describedby="name-guidance"></label><small id="name-guidance">Letters, numbers and single spaces. Existing saved details cannot be changed here.</small></p>
      <p><label>GCash number <input name="number" type="tel" inputmode="numeric" required pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" autocomplete="off" aria-describedby="number-guidance"></label><small id="number-guidance">Exactly 11 digits starting with 09, for example 09123456789. No spaces.</small></p>
      <button class="gcash-button" type="submit">Send payment instructions</button>
     </form>
    @endif
   </section>
   @if($buyer && !in_array($p->payment_state, ['verified', 'order_placed']) && $p->reservation_state !== 'initializing' && ($p->instructions || !$live))
    <section class="gcash-card" id="upload-proof" aria-labelledby="proof-heading">
     <h3 id="proof-heading">{{ $p->payment_state === 'proof_submitted' ? 'Submit additional evidence' : 'Upload payment proof' }}</h3>
     @if($inactive)<p class="gcash-notice">Evidence is for manual review. Uploading it does not reopen this reservation or place an order.</p>@endif
     <form class="gcash-form" data-action="proof" data-confirm="Submit this receipt for manual seller review? Uploading proof does not verify payment or place an order." method="post" enctype="multipart/form-data" action="{{ route('gcash.proof', $p->id) }}">@csrf<input type="hidden" name="submission_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
      <p><label>Payment receipt <input type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf" aria-describedby="receipt-guidance" required></label></p>
      <p class="gcash-muted" id="receipt-guidance">JPEG, PNG, WebP or PDF, up to 5 MB. Only purchase participants and authorized administrators can view it.</p>
      <div class="gcash-selected" hidden><img alt="Selected receipt preview"><p data-selected-file></p><div class="gcash-actions"><button class="gcash-button gcash-button-secondary" type="button" data-review-selected>Review Receipt</button><button class="gcash-button gcash-button-secondary" type="button" data-replace>Replace receipt</button><button class="gcash-button gcash-button-secondary" type="button" data-remove>Remove receipt</button></div></div>
      <button class="gcash-button" type="submit">Submit Payment Proof</button>
     </form>
    </section>
   @endif
   <section class="gcash-card" aria-labelledby="receipts-heading">
    <h3 id="receipts-heading">Receipt evidence</h3>
    <ul class="gcash-receipts">@forelse($proofs as $proof)
     <li><a href="{{ route('gcash.receipt', [$p->id, $proof->id]) }}" data-private-receipt data-submitted="{{ $proof->created_at }} {{ config('app.timezone') }}">View private receipt</a><time datetime="{{ \Illuminate\Support\Carbon::parse($proof->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($proof->created_at)->format('M j, Y g:i A') }} {{ config('app.timezone') }}</time></li>
    @empty<li class="gcash-muted">No proof submitted.</li>@endforelse</ul>
   </section>
   @if(!$reviewer && !$buyer && $live && $p->payment_state === 'proof_submitted')
    <section class="gcash-card" id="seller-review" aria-labelledby="review-heading">
     <h3 id="review-heading">Verify actual receipt</h3><p class="gcash-notice">Check your GCash account directly. Verify the exact amount and transaction reference before confirming.</p>
     <form class="gcash-form" data-action="verify" data-confirm="Have you checked your GCash account and confirmed that this exact payment was received? A screenshot alone is not confirmation." method="post" action="{{ route('gcash.decide', $p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}"><input type="hidden" name="accept" value="1">
      <p><label>Actual amount received <input type="number" step="0.01" min="0" inputmode="decimal" name="amount" required></label></p>
      <p><label>Actual GCash transaction reference <input name="reference" required maxlength="64" autocomplete="off"></label></p>
      <button class="gcash-button" type="submit">Verify Payment</button>
     </form>
     <details style="margin-top:20px"><summary>Payment not received or proof incorrect?</summary>
      <form class="gcash-form" data-action="reject" data-confirm="Reject this payment proof? Payment will not be marked verified. The buyer will see your reason." method="post" action="{{ route('gcash.decide', $p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}"><input type="hidden" name="accept" value="0">
       <p><label>Rejection reason <textarea name="reason" required maxlength="1000"></textarea></label></p><button class="gcash-button gcash-button-danger" type="submit">Reject proof</button>
      </form>
     </details>
    </section>
   @endif
   @if($buyer && $placement['eligible'])
    <section class="gcash-card" id="place-order" aria-labelledby="place-heading"><h3 id="place-heading">Ready to place your order</h3><p>The seller verified payment for these exact items and amount. Review the summary before continuing.</p>
     <form class="gcash-form" data-action="place" data-confirm="Confirming submits your order for these exact verified items and amount. This is your explicit order placement." method="post" action="{{ route('gcash.place', $p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}"><button class="gcash-button" type="submit">Place Order</button></form>
    </section>
   @endif
   @if(!$reviewer && !$ordered)
    <section class="gcash-card" id="payment-help"><details @if($inactive || $p->payment_state === 'manual_resolution') open @endif><summary>Payment help &amp; reservation options</summary>
     <p class="gcash-muted">If money was sent or payment is uncertain, request manual resolution instead of paying again.</p>
     @if($p->reservation_state !== 'initializing')
     <form class="gcash-form" method="post" action="{{ route('gcash.requestResolution', $p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}">
      <p><label>Payment problem / money already sent <textarea name="reason" required maxlength="1000"></textarea></label></p><button class="gcash-button gcash-button-secondary" type="submit">Request manual resolution</button>
     </form>
     @endif
     @if(!$buyer && $p->payment_state === 'manual_resolution' && $inactive)
      <hr><form class="gcash-form" data-confirm="Confirm actual receipt and this explicit resolution. A refund is not recorded by this action." method="post" action="{{ route('gcash.resolve', $p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}">
       <p><label>Actual amount received <input name="amount" type="number" step="0.01" min="0" required></label></p><p><label>GCash transaction reference <input name="reference" required maxlength="64"></label></p><p><label>Resolution reason <textarea name="reason" required maxlength="1000"></textarea></label></p>
       <p><label>Resolution <select name="reacquire">@if(!$linkedOrder && $p->reservation_state === 'expired')<option value="1">Explicitly reacquire stock at original amount</option>@endif<option value="0">Cannot fulfill - manual repayment unresolved</option></select></label></p><button class="gcash-button" type="submit">Record resolution</button>
      </form>
     @endif
     @if($buyer && in_array($p->reservation_state, ['held', 'initializing']) && !$verified)
      <hr><p class="gcash-muted">Only release the reservation if no money was sent.</p>
      <form class="gcash-form" data-confirm="Release this reservation? If money was sent, request manual resolution instead." method="post" action="{{ route('gcash.abandon', $p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}"><button class="gcash-button gcash-button-secondary" type="submit">Release reservation</button></form>
     @endif
    </details></section>
   @endif
  </div>
  <aside class="gcash-stack" aria-label="Purchase summary">
   <section class="gcash-card" id="gcash-summary"><h3>Purchase summary</h3>
    <ul class="gcash-items">@foreach($p->snapshot['items'] as $item)<li><strong>{{ $item['name'] }}</strong>@if($item['variation_value'])<span class="gcash-muted">{{ $item['variation_value'] }}</span>@endif<div class="gcash-muted">{{ $item['qty'] }} &times; &#8369;{{ number_format($item['price'], 2) }} each</div></li>@endforeach</ul>
    <dl class="gcash-totals"><div><dt>Subtotal</dt><dd>&#8369;{{ $p->snapshot['subtotal'] }}</dd></div><div><dt>Shipping</dt><dd>&#8369;{{ $p->snapshot['shipping_amount'] }}</dd></div><div><dt>Discount</dt><dd>&#8369;{{ $p->snapshot['discount_amount'] }}</dd></div></dl>
    <p><strong>Final total: &#8369;{{ number_format($p->amount_due, 2) }}</strong></p>
    <p class="gcash-muted">Delivery address: {{ implode(', ', array_filter($p->snapshot['shipping_address'] ?? [])) }}</p>
    <hr style="border:0;border-top:1px solid var(--gcash-border);margin:20px 0">
    <p class="gcash-muted">{{ $buyer ? 'Seller' : 'Buyer' }}: <strong>{{ $other?->business_name ?: trim(($other?->given_names ?? '').' '.($other?->last_name ?? '')) ?: 'Account unavailable' }}</strong></p>
    <details class="gcash-reference"><summary>Purchase reference</summary><p>{{ $p->id }}</p></details>
    <p class="gcash-reference">Allocation: {{ $p->reservation_state }}</p>
    @if($p->payment_reference)<p class="gcash-muted">Recorded GCash transaction reference: <strong>{{ $p->payment_reference }}</strong></p>@endif
    @unless($reviewer)
     <div class="gcash-actions">
      <a class="gcash-button gcash-button-secondary" href="{{ $conversation }}">Message {{ $buyer ? 'seller' : 'buyer' }}</a>
      @if($linkedOrder)<a class="gcash-button" href="{{ route($buyer ? 'buyer.orders' : 'seller.orders') }}">View order</a>@endif
      @if($buyer && $inactive)<a class="gcash-button gcash-button-secondary" href="{{ route('buyer.cart') }}">Back to cart</a>@endif
     </div>
    @endunless
   </section>
   <section class="gcash-card"><details><summary>Payment history</summary>
    <ol class="gcash-history">@foreach($history as $event)<li><strong>{{ ucfirst(str_replace('_', ' ', $event->event)) }}</strong><time datetime="{{ \Illuminate\Support\Carbon::parse($event->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('M j, Y g:i A') }} {{ config('app.timezone') }}</time><span class="gcash-muted">{{ $event->actor_id ? 'Account '.$event->actor_id : 'System' }}</span>@if($event->reason)<p>{{ $event->reason }}</p>@endif</li>@endforeach</ol>
    {{ $history->links() }}
   </details></section>
  </aside>
 </div>
 @include('gcash.dialogs')
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/gcash.js') }}?v={{ filemtime(public_path('js/gcash.js')) }}" defer></script>
@endpush
