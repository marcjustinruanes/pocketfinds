@php $receiptRows = $receiptRows ?? $paymentProofList; @endphp
@if($receiptRows->isNotEmpty())
<div class="order-submitted-receipts">
 @foreach($receiptRows as $proof)
 @php $receiptUrl = route('gcash.receipt', [$purchase->id, $proof->id]); @endphp
 <fieldset class="order-payment-upload-fieldset order-submitted-receipt">
  <legend>{{ ($updatedReceipts ?? false) ? 'Updated submitted receipt '.$loop->iteration : ($loop->first ? 'Original submitted receipt' : 'Updated submitted receipt '.$loop->index) }}</legend>
  <div class="order-submitted-receipt-content">
   @if($proof->mime === 'application/pdf')
    @include('buyer.partials.icon', ['name'=>'file', 'size'=>28])
   @else
    <img src="{{ $receiptUrl }}" alt="Submitted payment receipt" loading="lazy">
   @endif
   <div><span>Payment receipt{{ $proof->mime === 'application/pdf' ? '.pdf' : '' }}</span><time datetime="{{ \Illuminate\Support\Carbon::parse($proof->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($proof->created_at)->format('M j, Y · g:i A') }}</time></div>
  </div>
  <button type="button" class="order-payment-file-action order-payment-file-retake" data-submitted-receipt-url="{{ $receiptUrl }}" data-submitted-receipt-mime="{{ $proof->mime }}" aria-label="Enlarge submitted receipt" title="Enlarge"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7M21 3l-8 8M10 21H3v-7M3 21l8-8"/></svg></button>
 </fieldset>
 @endforeach
</div>
@endif
