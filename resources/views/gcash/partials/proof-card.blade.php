<fieldset class="order-payment-upload-fieldset order-submitted-receipt gcash-admin-submitted-receipt">
 <legend>{{ $proofLabel }}</legend>
 <div class="order-submitted-receipt-content">
  @if($proofMime === 'application/pdf')
   @include('buyer.partials.icon',['name'=>'file','size'=>28])
  @else
   <img src="{{ $proofUrl }}" alt="{{ $proofLabel }}" loading="lazy">
  @endif
  <div><span>{{ $proofLabel }}{{ $proofMime === 'application/pdf' ? '.pdf' : '' }}</span><time datetime="{{ \Illuminate\Support\Carbon::parse($proofDate)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($proofDate)->format('M j, Y · g:i A') }}</time></div>
 </div>
 <button type="button" class="order-payment-file-action order-payment-file-retake" @if($buyerPreview ?? false) data-submitted-receipt-url="{{ $proofUrl }}" @else data-private-receipt data-private-receipt-url="{{ $proofUrl }}" @endif data-submitted-receipt-mime="{{ $proofMime }}" aria-label="Enlarge {{ strtolower($proofLabel) }}" title="Enlarge"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7M21 3l-8 8M10 21H3v-7M3 21l8-8"/></svg></button>
</fieldset>
