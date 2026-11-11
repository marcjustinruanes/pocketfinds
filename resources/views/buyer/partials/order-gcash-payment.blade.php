@php
  $purchaseState = $purchase->payment_state;
  $detailsEvent = $paymentHistory->firstWhere('event', 'platform_instructions');
  $proofEvent = $paymentHistory->where('event', 'proof_submitted')->last();
  $reviewEvent = $paymentHistory->whereIn('event', ['admin_verified', 'admin_rejected', 'admin_short_payment', 'order_placed'])->last();
  $shortPaymentEvent = $paymentHistory->where('event', 'admin_short_payment')->last();
  $returnedPaymentProofs = $paymentHistory->whereIn('event', ['admin_refund_proof', 'admin_rejection_refund_proof']);
  $showUnderpaidRow = $shortPaymentEvent !== null || $returnedPaymentProofs->isNotEmpty() || $paymentHistory->contains('event','admin_rejected');
  $paymentRejected = $purchaseState === 'rejected';
  $paymentApproved = $purchaseState === 'order_placed';
  $paymentInReview = $purchaseState === 'proof_submitted';
  $paymentHasProof = $paymentInReview || $paymentRejected || $paymentApproved || $purchaseState === 'underpaid';
  $canUploadPaymentProof = $order->status !== 'cancelled' && in_array($purchaseState, ['awaiting_payment', 'rejected', 'underpaid'], true);
  $remainingAmount = max(0, (float) $purchase->amount_due - (float) ($purchase->verified_amount ?? 0));
  $hasPartialPayment = (float) ($purchase->verified_amount ?? 0) > 0 && ! $paymentApproved;
  $asTime = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value) : null;
  $timeline = [
    ['title' => 'Order placed', 'icon' => 'bag', 'state' => 'completed', 'time' => $asTime($order->created_at)],
    ['title' => 'Payment details', 'icon' => 'wallet', 'state' => 'completed', 'time' => $asTime($detailsEvent?->created_at ?? $purchase->created_at)],
    ['title' => 'Submit proof', 'icon' => 'receipt', 'state' => in_array($purchaseState, ['awaiting_payment', 'rejected', 'underpaid'], true) ? 'current' : ($paymentHasProof ? 'completed' : 'future'), 'time' => $asTime($proofEvent?->created_at)],
    ['title' => 'Admin review', 'icon' => 'shield', 'state' => $paymentApproved || $paymentRejected || $purchaseState === 'underpaid' ? 'completed' : ($paymentInReview || $purchaseState === 'manual_resolution' ? 'current' : 'future'), 'time' => ($paymentApproved || $paymentRejected || $purchaseState === 'underpaid') ? $asTime($reviewEvent?->created_at) : null],
  ];
  $rejectionEvent = $paymentHistory->where('event', 'admin_rejected')->last();
@endphp
  <ol class="order-payment-steps" aria-label="Payment progress" @if($paymentApproved) hidden @endif>
    @foreach($timeline as $step)
      <li class="is-{{ $step['state'] }}">
        <span class="order-payment-step-icon" aria-hidden="true">@include('buyer.partials.icon', ['name' => $step['icon'], 'size' => 17])</span>
        <strong>{{ $step['title'] }}</strong>
        @if($loop->last && $purchaseState === 'underpaid')
          <span class="order-payment-short-note">Payment short</span>
          @if($shortPaymentEvent?->reason)<small class="order-payment-short-reason">{{ $shortPaymentEvent->reason }}</small>@endif
        @endif
        @if($step['time'])<time datetime="{{ $step['time']->toIso8601String() }}">{{ $step['time']->format('M j, Y · g:i A') }}</time>@endif
      </li>
    @endforeach
  </ol>
<section class="order-payment-panel order-payment-panel-flat" data-payment-details-panel aria-label="GCash payment details" @if($paymentApproved) hidden @endif>
  @include('gcash.partials.cancellation-refund',['refundOrder'=>$order,'refundPurchase'=>$purchase,'refundReviewer'=>false])
  @php $excessPayment = $paymentHistory->whereIn('event', ['overpayment_pending', 'overpayment_refunded'])->last(); @endphp
  @if($excessPayment)
    <p class="order-payment-guidance" role="status"><strong>{{ $excessPayment->event === 'overpayment_refunded' ? 'Excess payment refunded' : 'Excess payment refund pending' }}</strong><br>{{ $excessPayment->reason }}</p>
    @php $refundDetailsSent = $paymentHistory->firstWhere('event','refund_details_submitted'); @endphp
    @if($excessPayment->event === 'overpayment_pending' && !$refundDetailsSent)
    <form method="POST" action="{{ route('gcash.refundDetails',$purchase->id) }}" class="order-refund-details-form">@csrf
      <p>Enter the GCash account that sent the payment so admin can return the excess.</p>
      <label>GCash account name<input name="name" maxlength="150" required></label>
       <label>GCash number<span class="gcash-mobile-input"><b>+63</b><input name="number" type="tel" pattern="9[0-9]{9}" maxlength="10" placeholder="9XXXXXXXXX" required></span></label>
      <button type="submit" class="btn btn-primary">Send refund details to admin</button>
    </form>
    @elseif($excessPayment->event === 'overpayment_pending')<p class="order-payment-guidance">Your refund GCash details have been sent. Waiting for admin to return the excess payment.</p>@endif
  @endif
  @foreach($paymentHistory->whereIn('event',$showUnderpaidRow ? [] : ['admin_received_proof','admin_refund_proof']) as $evidence)
    @php $evidenceMeta = json_decode($evidence->before,true); @endphp
    <button type="button" class="btn btn-outline btn-sm" data-submitted-receipt-url="{{ route('gcash.adminEvidence',[$purchase->id,$evidence->id]) }}" data-submitted-receipt-mime="{{ $evidenceMeta['mime'] }}">{{ $evidence->event === 'admin_refund_proof' ? 'View proof of excess payment returned' : 'View admin proof of amount received' }}</button>
  @endforeach
  @unless($paymentRejected)
  <p class="order-payment-guidance{{ $paymentApproved ? ' order-payment-approved-banner' : '' }}">{{ match ($purchaseState) {
    'underpaid' => 'The platform confirmed a partial payment. Pay the remaining amount below and upload proof so admin can review it.',
    'awaiting_payment' => 'Pay the amount below via GCash and upload your receipt. The seller can prepare your order after payment approval.',
    'proof_submitted' => $hasPartialPayment ? 'Your GCash top-up receipt is under review. The confirmed partial payment is recorded; the seller can prepare after the remaining balance is approved.' : 'Your GCash receipt is under review. Seller preparation starts after payment approval.',
    'manual_resolution' => 'Payment needs manual attention. Do not send another payment while it is being reviewed.',
    'order_placed' => 'PocketFinds approved your payment. The seller can now confirm and prepare your order.',
    default => 'Check the payment details and progress below.',
  } }}</p>
  @endunless



  <div class="order-payment-columns{{ $canUploadPaymentProof || $paymentProofList->isNotEmpty() ? ' has-proof-form' : '' }}">
    <div class="order-payment-account-column">
      @if($purchase->instructions)
        <dl class="order-payment-account">
          <div><dt>Account name</dt><dd>{{ $purchase->instructions['name'] ?? 'PocketFinds' }}</dd></div>
          <div><dt>Number</dt><dd><span>{{ preg_replace('/^0(?=9[0-9]{9}$)/', '+63', (string) ($purchase->instructions['number'] ?? '')) }}</span><button type="button" class="order-payment-copy-number" data-copy-payment-number="{{ $purchase->id }}" aria-label="Copy GCash number" title="Copy number"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h3"/></svg></button></dd></div>
        </dl>
      @endif
      @if($purchase->payment_reference)
        <p class="order-payment-reference">GCash transaction reference: <strong>{{ $purchase->payment_reference }}</strong></p>
      @endif
    </div>

    @if($canUploadPaymentProof || $paymentProofList->isNotEmpty())
      <div class="order-payment-upload-column">
        @unless($showUnderpaidRow)
          @include('buyer.partials.order-payment-review-note')
        @endunless
        @include('buyer.partials.order-submitted-receipts', ['receiptRows' => $showUnderpaidRow ? $paymentProofList->take(1) : $paymentProofList])
        @if(!$showUnderpaidRow && $canUploadPaymentProof)
          @include('buyer.partials.order-payment-upload')
        @endif
      </div>
    @endif
  </div>
  @if($showUnderpaidRow)
    @include('gcash.partials.related-proofs', ['p' => $purchase, 'proofs' => $paymentProofList, 'buyerPreview' => true, 'pendingUpload' => $canUploadPaymentProof])
  @endif
</section>
