@extends(in_array(auth()->user()->account_type, ['buyer', 'seller', 'admin'], true) && request()->boolean('embedded') ? 'gcash.embedded-layout' : (auth()->user()->is_admin ? 'admin.layout' : (auth()->user()->account_type === 'buyer' ? 'buyer.layout' : 'seller.layout')))
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
 $reviewer = (bool) auth()->user()->is_admin && auth()->user()->status === 'approved';
 $buyer = !$reviewer && auth()->user()->account_type === 'buyer';
 $embeddedPayment = in_array(auth()->user()->account_type, ['buyer', 'seller', 'admin'], true) && request()->boolean('embedded');
 $platformFlow=$p->usesAdminReview();
 $canReview=$platformFlow?$reviewer:(!$reviewer&&!$buyer);
 $linkedOrder = \App\Models\Order::where('gcash_purchase_id', $p->id)->first();
 $other = $reviewer ? null : \App\Models\User::find($buyer ? $p->seller_id : $p->buyer_id);
 $live = $p->reservation_state === 'held' && $p->expires_at && $p->expires_at->isFuture();
 $inactive = in_array($p->reservation_state, ['expired', 'released']);
 $ordered = $p->payment_state === 'order_placed';
 $verified = $p->payment_state === 'verified';
 if($platformFlow){$live=$p->reservation_state==='transferred'&&$linkedOrder?->status==='placed';$verified=(bool)$p->verified_at;}
 $conversation = !$reviewer ? route($buyer ? 'buyer.messages' : 'seller.messages', $buyer ? ['seller' => $other?->username, 'purchase' => $p->id] : ['buyer' => $other?->id, 'purchase' => $p->id]) : null;
 $currentStep = $ordered ? 5 : (($inactive || $p->payment_state === 'manual_resolution' || $p->reservation_state === 'initializing') ? 0 : ($verified ? 4 : ($p->payment_state === 'proof_submitted' ? 3 : ($p->instructions ? 2 : 1))));
 if($platformFlow)$currentStep=$ordered?4:($p->payment_state==='proof_submitted'?4:3);
 [$heading, $guidance] = match (true) {
  $ordered => ['Your order has been placed', 'Payment review is complete. Follow the linked order for fulfillment updates.'],
  $p->payment_state === 'manual_resolution' || $inactive => ['This payment needs attention', 'This reservation is no longer available for normal checkout. If money was sent, contact the seller and request manual resolution. Do not pay again.'],
  $verified && $buyer && $placement['eligible'] => ['Payment verified — place your order', 'The seller confirmed receipt for this purchase. Your order will be created only when you select Place Order.'],
  $verified => ['Payment verified', 'The seller has confirmed receipt. The buyer must explicitly place this linked order.'],
  $p->reservation_state === 'initializing' => ['Preparing your reservation', 'Refresh this page to check activation. Do not start another payment while this purchase is being prepared.'],
  $p->payment_state === 'rejected' => ['Payment proof was rejected', 'Review the reason in payment history. Contact the seller before uploading corrected proof or sending any more money.'],
  $p->payment_state === 'proof_submitted' => [$buyer ? 'Waiting for seller verification' : 'Review the buyer payment', $buyer ? 'Your proof was submitted. The payment reviewer must check actual receipt of funds.' : 'Open the private receipt, then check your actual GCash transaction history. A screenshot alone does not confirm receipt.'],
  !$p->instructions => [$buyer ? 'Ask the seller for payment details' : 'Send your GCash payment details', $buyer ? 'Continue in your seller conversation. The seller will provide the GCash name and number for the exact amount shown here.' : 'Provide your GCash account name and number below so the buyer can pay the agreed amount.'],
  default => [$buyer ? 'Pay the seller, then upload your proof' : 'Waiting for buyer proof', $buyer ? 'Use the seller payment details below. After paying, upload your receipt here for manual review.' : 'The buyer has your payment instructions. Review the receipt after it is submitted.'],
 };
 if($platformFlow){
  [$heading,$guidance]=match(true){
   $ordered=>['Payment approved by admin','The platform admin confirmed payment. The seller can now confirm and prepare the order.'],
   $inactive||$p->payment_state==='manual_resolution'=>['Payment needs admin attention','Contact platform support for payment reconciliation. Do not pay again.'],
   $p->payment_state==='rejected'=>['Admin rejected the payment proof','Review the rejection reason, then upload corrected evidence. Do not send another payment without checking with support.'],
   $p->payment_state==='underpaid'=>['Payment short — top-up needed','Review the platform GCash proof for the amount received, then submit a top-up receipt for admin approval.'],
   $p->payment_state==='proof_submitted'=>['Waiting for admin payment approval','Your order is placed. The platform admin will check the payment before the seller can begin preparation.'],
   default=>['Order placed — payment pending','Pay the platform GCash account below and upload your receipt. The seller can prepare the order after admin approval.'],
  };
 }
@endphp
<div class="gcash-page" data-purchase="{{ $p->id }}" data-review-flow="{{ $platformFlow?'admin':'seller' }}" data-reviewer="{{ $reviewer ? '1' : '0' }}" data-orders-url="{{ route($buyer ? 'buyer.orders' : 'seller.orders') }}" data-payment-state="{{ $p->payment_state }}">
 <nav class="gcash-nav" aria-label="GCash navigation">
  <a href="{{ route('gcash.index') }}">All GCash purchases</a>
  @if($buyer)<a href="{{ $conversation }}">Open existing conversation</a>@endif
  @if($linkedOrder && !$reviewer)<a href="{{ route($buyer ? 'buyer.orders' : 'seller.orders') }}">View linked order {{ $linkedOrder->order_number }}</a>@endif
 </nav>
 @unless($reviewer)
 <section class="gcash-card gcash-hero" @if($embeddedPayment && $ordered) aria-label="Payment progress" @else aria-labelledby="gcash-next-step" @endif>
  @unless($embeddedPayment && $ordered)
  <div class="gcash-hero-head">
   <div><p class="gcash-eyebrow">{{ $reviewer ? ($platformFlow?'Admin payment review':'Read-only payment review') : 'Your next step' }}</p><h2 id="gcash-next-step">{{ $heading }}</h2><span class="gcash-badge {{ $verified || $ordered ? 'gcash-badge-success' : '' }}">Payment: {{ str_replace('_', ' ', ucfirst($p->payment_state)) }}</span></div>
   <div><span class="gcash-muted">Amount payable</span><p class="gcash-amount">&#8369;{{ number_format($p->amount_due, 2) }}</p></div>
  </div>
  <p>{{ $guidance }}</p>
  @endunless
  @if($p->payment_state === 'rejected' && ($rejection = $history->firstWhere('event', $platformFlow?'admin_rejected':'rejected')))
   <p class="gcash-notice gcash-notice-danger"><strong>{{ $platformFlow?'Admin':'Seller' }} rejection reason:</strong> {{ $rejection->reason }}</p>
  @endif
  @if($reviewer && !$platformFlow)<p class="gcash-muted">Review access does not allow verifying payment, resolving it, or placing an order.</p>@endif
  @if($live && !$platformFlow)<p class="gcash-notice">Stock reserved until <strong>{{ $p->expires_at->format('M j, Y g:i A') }}</strong>. Proof submission does not extend this deadline.</p>@endif
  @if($buyer && $verified && !$placement['eligible'])<p class="gcash-notice gcash-notice-danger" role="alert">{{ $placement['reason'] }}</p>@endif
  <div class="gcash-actions">
   @if(!$reviewer && !$ordered)
    @if($buyer && $placement['eligible'])<a class="gcash-button" href="#place-order">Continue to Place Order</a>
    @elseif($inactive || $p->payment_state === 'manual_resolution')<a class="gcash-button gcash-button-secondary" href="#payment-help">Get payment help</a>
    @elseif(!$buyer && !$p->instructions && $live)<a class="gcash-button" href="#payment-instructions">Add payment details</a>
    @elseif($canReview && $p->payment_state === 'proof_submitted' && $live)<a class="gcash-button" href="#seller-review">Review payment</a>
    @elseif($buyer && $p->instructions && in_array($p->payment_state, ['awaiting_payment', 'rejected', 'underpaid']))<a class="gcash-button" href="#upload-proof">{{ $p->payment_state === 'underpaid' ? 'Upload top-up proof' : 'Upload payment proof' }}</a>
    @elseif($buyer && !$platformFlow)<a class="gcash-button" href="{{ $conversation }}">Message seller</a>@endif
    <a class="gcash-button gcash-button-secondary" href="{{ route('gcash.show', $p->id) }}">Refresh status</a>
   @endif
  </div>
  @if($embeddedPayment)
   @include('gcash.partials.payment-timeline')
  @else
  <ol class="gcash-progress" aria-label="Payment progress">
   @foreach($platformFlow?['Order placed','Payment details','Submit proof','Admin verification']:['Payment details', 'Submit proof', 'Seller verification', 'Place Order', 'Order confirmation'] as $step)
    <li class="{{ $loop->iteration < $currentStep ? 'is-complete' : '' }}" @if($loop->iteration === $currentStep) aria-current="step" @endif><span class="gcash-step-number">{{ $loop->iteration }}</span><span>{{ $step }}</span></li>
   @endforeach
  </ol>
  @endif
 </section>
 @endunless
 <div id="gcash-message" role="status" aria-live="polite" tabindex="-1"></div>
 @if($reviewer && $platformFlow)
  @include('gcash.partials.cancellation-refund',['refundOrder'=>$linkedOrder,'refundPurchase'=>$p,'refundReviewer'=>true])
 @endif
 @if($embeddedPayment) @include('gcash.partials.modal-summary') @endif
 <div class="gcash-grid">
  <div class="gcash-stack">
   <section class="gcash-card" id="payment-instructions" aria-labelledby="instructions-heading">
     @unless($reviewer && $embeddedPayment)<h3 id="instructions-heading">{{ $platformFlow?'Platform GCash payment details':'Seller payment details' }}</h3>@endunless
    @if($embeddedPayment && !($reviewer && $platformFlow))
    <dl class="gcash-fields gcash-payment-references">
     <div><dt>Transaction reference</dt><dd>{{ $p->payment_reference ?: 'Not recorded yet' }}</dd></div>
    @if($linkedOrder)<div><dt>Linked order</dt><dd><a href="{{ route($buyer ? 'buyer.orders' : 'seller.orders', $buyer ? [] : ['q'=>$linkedOrder->order_number]) }}" target="_top">{{ $linkedOrder->order_number }}</a></dd></div>@endif
    </dl>
    @endif
     @if($reviewer && $embeddedPayment)<div class="gcash-admin-payment-columns">@endif
     @if($p->instructions)
      <dl class="gcash-fields {{ $reviewer && $embeddedPayment ? 'gcash-admin-account-table' : '' }}"><div><dt>{{ $reviewer && $embeddedPayment ? 'Account name' : 'GCash name' }}</dt><dd>{{ $p->instructions['name'] }}</dd></div><div><dt>{{ $reviewer && $embeddedPayment ? 'Number' : 'GCash number' }}</dt><dd><span id="gcash-number">{{ preg_replace('/^0(?=9[0-9]{9}$)/', '+63', (string) $p->instructions['number']) }}</span> @if($reviewer && $embeddedPayment)<button type="button" class="gcash-button gcash-button-secondary gcash-copy-icon" data-copy="gcash-number" aria-label="Copy GCash number" title="Copy GCash number"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M15 9V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h3"/></svg></button>@else<button type="button" class="gcash-button gcash-button-secondary" data-copy="gcash-number">Copy mobile number</button>@endif</dd></div></dl>
     @unless($reviewer && $embeddedPayment)<p class="gcash-muted">Pay only the amount agreed for this purchase. Sending a receipt does not automatically verify payment.</p>@endunless
    @else
     <p class="gcash-muted">Wait for the seller to provide payment instructions.</p>
     @if($buyer && !$inactive)<a class="gcash-button gcash-button-secondary" href="{{ $conversation }}">Ask seller for payment details</a>@endif
    @endif
    @if(!$reviewer && !$buyer && $live && !$p->instructions)
     <form class="gcash-form" method="post" action="{{ route('gcash.instructions', $p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}">
      <p><label>GCash account name <input name="name" required maxlength="150" data-account-name autocomplete="off" aria-describedby="name-guidance"></label><small id="name-guidance">Letters, numbers and single spaces. Existing saved details cannot be changed here.</small></p>
      <p><label>GCash number <span class="gcash-mobile-input"><b>+63</b><input name="number" type="tel" inputmode="numeric" required pattern="9[0-9]{9}" maxlength="10" placeholder="9XXXXXXXXX" autocomplete="off" aria-describedby="number-guidance"></span></label><small id="number-guidance">Enter 10 digits starting with 9 after +63.</small></p>
      <button class="gcash-button" type="submit">Send payment instructions</button>
     </form>
    @endif
    <div class="gcash-payment-receipts {{ $reviewer && $embeddedPayment ? 'gcash-admin-receipts' : '' }}" aria-labelledby="receipts-heading">
     @unless($reviewer && $embeddedPayment)<h3 id="receipts-heading">Receipt evidence</h3>@endunless
    @if($reviewer && $embeddedPayment)
     @forelse($proofs->sortBy('created_at')->take(1) as $proof)
      @php $receiptUrl = route('gcash.receipt', [$p->id, $proof->id]); @endphp
       <fieldset class="order-payment-upload-fieldset order-submitted-receipt gcash-admin-submitted-receipt">
       <legend>{{ $loop->first ? 'Original submitted receipt' : 'Updated submitted receipt '.$loop->index }}</legend>
       <div class="order-submitted-receipt-content">
        @if($proof->mime === 'application/pdf')
         @include('buyer.partials.icon',['name'=>'file','size'=>28])
        @else
         <img src="{{ $receiptUrl }}" alt="Submitted payment receipt" loading="lazy">
        @endif
        <div><span>Payment receipt{{ $proof->mime === 'application/pdf' ? '.pdf' : '' }}</span><time datetime="{{ \Illuminate\Support\Carbon::parse($proof->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($proof->created_at)->format('M j, Y · g:i A') }}</time></div>
       </div>
       <button type="button" class="order-payment-file-action order-payment-file-retake" data-private-receipt data-private-receipt-url="{{ $receiptUrl }}" data-submitted-receipt-mime="{{ $proof->mime }}" data-submitted="{{ \Illuminate\Support\Carbon::parse($proof->created_at)->format('M j, Y g:i A') }}" aria-label="Enlarge submitted receipt" title="Enlarge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7M21 3l-8 8M10 21H3v-7M3 21l8-8"/></svg></button>
       </fieldset>
     @empty
      <p class="gcash-muted">No proof submitted.</p>
     @endforelse
    @else
    <ul class="gcash-receipts">@forelse($proofs as $proof)
     <li><a href="{{ route('gcash.receipt', [$p->id, $proof->id]) }}" data-private-receipt data-submitted="{{ $proof->created_at ? \Illuminate\Support\Carbon::parse($proof->created_at)->format('M j, Y g:i A') : '—' }}">View private receipt</a><time datetime="{{ \Illuminate\Support\Carbon::parse($proof->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($proof->created_at)->format('M j, Y g:i A') }}</time></li>
     @empty<li class="gcash-muted">No proof submitted.</li>@endforelse</ul>
    @endif
    </div>
    @if($reviewer && $embeddedPayment)</div>@endif
   </section>
   @if($buyer && !in_array($p->payment_state, ['verified', 'order_placed']) && $p->reservation_state !== 'initializing' && ($p->instructions || !$live))
    <section class="gcash-card" id="upload-proof" aria-labelledby="proof-heading">
     <h3 id="proof-heading">{{ $p->payment_state === 'underpaid' ? 'Upload top-up payment proof' : ($p->payment_state === 'proof_submitted' ? 'Submit additional evidence' : 'Upload payment proof') }}</h3>
     @if($p->payment_state === 'underpaid')<p class="gcash-notice">Review the platform GCash proof for the amount received, then upload a top-up receipt for admin approval.</p>@endif
     @if($inactive)<p class="gcash-notice">Evidence is for manual review. Uploading it does not reopen this reservation or place an order.</p>@endif
     <form class="gcash-form" data-action="proof" data-confirm="{{ $platformFlow ? 'Submit this receipt for platform admin review? This does not verify payment.' : 'Submit this receipt for manual seller review? Uploading proof does not verify payment or place an order.' }}" method="post" enctype="multipart/form-data" action="{{ route('gcash.proof', $p->id) }}">@csrf<input type="hidden" name="submission_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
      <fieldset class="gcash-upload-fieldset"><legend>Payment receipt</legend><label class="gcash-file-drop" for="gcashPaymentReceipt"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 16V4m0 0L7 9m5-5 5 5"/><path d="M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></svg><span data-gcash-file-name>Upload Document</span><input id="gcashPaymentReceipt" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf" aria-label="Payment receipt" aria-describedby="receipt-guidance" required></label></fieldset>
      <p class="gcash-muted" id="receipt-guidance">JPG, PNG or PDF · maximum 5 MB. {{ $platformFlow?'The platform admin checks actual receipt of payment before the seller can prepare your order.':'Only purchase participants and authorized administrators can view it.' }}</p>
      <div class="gcash-selected" hidden><img alt="Selected receipt preview"><p data-selected-file></p><div class="gcash-actions"><button class="gcash-button gcash-button-secondary" type="button" data-review-selected>Review Receipt</button><button class="gcash-button gcash-button-secondary" type="button" data-replace>Replace receipt</button><button class="gcash-button gcash-button-secondary" type="button" data-remove>Remove receipt</button></div></div>
      <button class="gcash-button" type="submit">Submit Payment Proof</button>
     </form>
    </section>
   @endif

   @if($reviewer && $embeddedPayment)
    @include('gcash.partials.related-proofs')
   @endif
   @foreach($reviewer && $embeddedPayment ? collect() : \Illuminate\Support\Facades\DB::table('gcash_purchase_history')->where('purchase_id',$p->id)->whereIn('event',['admin_received_proof','admin_refund_proof','admin_rejection_refund_proof'])->get() as $adminEvidence)
    @php $adminProof=json_decode($adminEvidence->before,true)??[]; $adminProofUrl=route('gcash.adminEvidence',[$p->id,$adminEvidence->id]); $adminProofLabel=in_array($adminEvidence->event,['admin_refund_proof','admin_rejection_refund_proof'],true) ? ($adminEvidence->event === 'admin_refund_proof' ? 'Proof of returned excess payment' : 'Proof of returned payment') : 'Proof of actual amount received'; @endphp
     <fieldset class="order-payment-upload-fieldset order-submitted-receipt gcash-admin-submitted-receipt gcash-admin-evidence-receipt">
      <legend>{{ $adminProofLabel }}</legend>
      <div class="order-submitted-receipt-content">
       @if(($adminProof['mime']??'') === 'application/pdf')
        @include('buyer.partials.icon',['name'=>'file','size'=>28])
       @else
        <img src="{{ $adminProofUrl }}" alt="{{ $adminProofLabel }}" loading="lazy">
       @endif
       <div><span>{{ $adminProofLabel }}{{ ($adminProof['mime']??'') === 'application/pdf' ? '.pdf' : '' }}</span><time datetime="{{ \Illuminate\Support\Carbon::parse($adminEvidence->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($adminEvidence->created_at)->format('M j, Y · g:i A') }}</time></div>
      </div>
      <button type="button" class="order-payment-file-action order-payment-file-retake" data-private-receipt data-private-receipt-url="{{ $adminProofUrl }}" data-submitted-receipt-mime="{{ $adminProof['mime']??'' }}" data-submitted="{{ \Illuminate\Support\Carbon::parse($adminEvidence->created_at)->format('M j, Y g:i A') }}" aria-label="Enlarge {{ strtolower($adminProofLabel) }}" title="Enlarge"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7M21 3l-8 8M10 21H3v-7M3 21l8-8"/></svg></button>
     </fieldset>
   @endforeach
   @if($canReview && $platformFlow && $p->payment_state === 'underpaid')
    <form method="POST" action="{{ route('gcash.remindUnderpayment',$p->id) }}">@csrf<button class="gcash-button" type="submit">Resend balance notice to buyer</button></form>
   @endif
   @if($canReview && $live && $p->payment_state === 'proof_submitted')
    <section class="gcash-card" id="seller-review" aria-labelledby="review-heading">
      @unless($platformFlow)<h3 id="review-heading">Verify actual receipt</h3><p class="gcash-notice">Check the seller GCash account directly. Verify the exact amount and transaction reference before confirming.</p>@endunless
     <form class="gcash-form {{ $platformFlow ? 'gcash-admin-verify-form' : '' }}" data-action="verify" data-confirm="Have you checked your GCash account and confirmed that this exact payment was received? A screenshot alone is not confirmation." method="post" enctype="multipart/form-data" action="{{ route('gcash.decide', $p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}"><input type="hidden" name="accept" value="1">
       @if($platformFlow)@include('gcash.partials.admin-proof-upload',['inputId'=>'gcashAdminReceivedProof','required'=>true])@else<p><label>Actual amount received <input type="number" step="0.01" min="0" inputmode="decimal" name="amount" required></label></p><p><label>Actual GCash transaction reference <input name="reference" required maxlength="64" autocomplete="off"></label></p>@endif
      <button class="gcash-button {{ $platformFlow ? 'gcash-admin-verify-submit' : '' }}" type="submit" aria-label="Verify payment" title="Verify payment">@if($platformFlow)<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.9 8.1 7 10 4.1-1.9 7-5.5 7-10V6l-7-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>@else Verify Payment @endif</button>
     </form>
       <details class="gcash-admin-rejection" open style="margin-top:20px"><summary aria-label="Show rejection choices"></summary>
      <form class="gcash-form" data-action="reject" data-confirm="Notify the buyer about this payment proof? Payment will not be marked verified. The buyer will see your message." method="post" enctype="multipart/form-data" action="{{ route('gcash.decide', $p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}"><input type="hidden" name="accept" value="0">
        @if($platformFlow)
         <p><label>Review outcome <select name="decision" data-rejection-choice required><option value="not_received">Payment not received</option><option value="underpaid">Payment is underpaid</option><option value="other">Other</option></select></label></p>
         <div data-underpayment-fields hidden>@include('gcash.partials.admin-proof-upload',['inputId'=>'gcashAdminReceivedProofForRejection','required'=>false])<small class="gcash-admin-proof-note">This proof is shared with the buyer so they can see the amount received.</small></div>
         <div data-returned-payment-choice hidden>
          <label class="gcash-admin-returned-checkbox"><input type="checkbox" name="payment_returned" value="1" data-returned-payment-toggle> Payment has been returned to the buyer</label>
          <div data-return-proof-fields hidden>@include('gcash.partials.admin-proof-upload',['inputId'=>'gcashAdminReturnedPaymentProof','inputName'=>'refund_proof','legend'=>'Proof of returned payment','ariaLabel'=>'Proof of returned payment','required'=>false])</div>
         </div>
         <p><label>Message to buyer <textarea name="reason" maxlength="1000"></textarea></label></p>
        @else
         <p><label>Message to buyer <textarea name="reason" required maxlength="1000"></textarea></label></p>
        @endif
        <button class="gcash-button gcash-button-danger" type="submit">Notify buyer</button>
      </form>
     </details>
    </section>
   @endif
   @if($buyer && $placement['eligible'])
    <section class="gcash-card" id="place-order" aria-labelledby="place-heading"><h3 id="place-heading">Ready to place your order</h3><p>The seller verified payment for these exact items and amount. Review the summary before continuing.</p>
     <form class="gcash-form" data-action="place" data-confirm="Confirming submits your order for these exact verified items and amount. This is your explicit order placement." method="post" action="{{ route('gcash.place', $p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}"><button class="gcash-button" type="submit">Place Order</button></form>
    </section>
   @endif
   @if((!$reviewer || ($platformFlow && $canReview)) && !$ordered && !($reviewer && $embeddedPayment))
    <section class="gcash-card" id="payment-help"><details @if($inactive || $p->payment_state === 'manual_resolution') open @endif><summary>Payment help &amp; reservation options</summary>
     <p class="gcash-muted">If money was sent or payment is uncertain, request manual resolution instead of paying again.</p>
     @if($p->reservation_state !== 'initializing' && !$reviewer)
     <form class="gcash-form" method="post" action="{{ route('gcash.requestResolution', $p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}">
      <p><label>Payment problem / money already sent <textarea name="reason" required maxlength="1000"></textarea></label></p><button class="gcash-button gcash-button-secondary" type="submit">Request manual resolution</button>
     </form>
     @endif
     @if($canReview && $p->payment_state === 'manual_resolution' && $inactive)
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
   @unless($embeddedPayment)
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
      @if($buyer)<a class="gcash-button gcash-button-secondary" href="{{ $conversation }}">Message seller</a>@endif
      @if($linkedOrder)<a class="gcash-button" href="{{ route($buyer ? 'buyer.orders' : 'seller.orders') }}">View order</a>@endif
      @if($buyer && $inactive)<a class="gcash-button gcash-button-secondary" href="{{ route('buyer.cart') }}">Back to cart</a>@endif
     </div>
    @endunless
   </section>
   @endunless
   @unless($embeddedPayment)
   <section class="gcash-card"><details><summary>Payment history</summary>
    <ol class="gcash-history">@foreach($history as $event)<li><strong>{{ ucfirst(str_replace('_', ' ', $event->event)) }}</strong><time datetime="{{ \Illuminate\Support\Carbon::parse($event->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('M j, Y g:i A') }}</time><span class="gcash-muted">{{ $event->actor_name ?? 'System' }}</span>@if($event->reason)<p>{{ $event->reason }}</p>@endif</li>@endforeach</ol>
    {{ $history->withQueryString()->links() }}
   </details></section>
   @endunless
  </aside>
 </div>
 @include('gcash.dialogs')
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/gcash.js') }}?v={{ filemtime(public_path('js/gcash.js')) }}" defer></script>
@endpush
