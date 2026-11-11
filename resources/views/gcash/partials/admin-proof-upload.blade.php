@php
 $proofLegend = $legend ?? 'Proof of actual amount received';
 $proofInputName = $inputName ?? 'received_proof';
 $proofAriaLabel = $ariaLabel ?? $proofLegend;
@endphp
<fieldset class="order-payment-upload-fieldset gcash-admin-proof-upload">
 <legend>{{ $proofLegend }}</legend>
 <label class="order-payment-file-drop" for="{{ $inputId }}">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 16V4m0 0L7 9m5-5 5 5"/><path d="M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></svg>
  <span data-payment-file-name>Upload Document</span>
  <img class="order-payment-file-preview" data-payment-file-preview alt="Selected payment receipt preview" hidden>
  <svg class="order-payment-file-pdf-icon" data-payment-file-pdf-icon hidden viewBox="0 0 24 28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 1h10l6 6v19H4zM14 1v7h6M7 13h10M7 17h7"/><text x="6" y="23" fill="currentColor" stroke="none" font-size="6" font-family="sans-serif" font-weight="700">PDF</text></svg>
  <span class="order-payment-file-pdf" data-payment-file-pdf hidden></span>
  <input id="{{ $inputId }}" type="file" name="{{ $proofInputName }}" accept="image/jpeg,image/png,image/webp,application/pdf" aria-label="{{ $proofAriaLabel }}" data-admin-received-proof @if($required) required @endif>
 </label>
 <button type="button" class="order-payment-file-action order-payment-file-preview-button" data-admin-proof-preview aria-label="Enlarge receipt" title="Preview" hidden><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7M21 3l-8 8M10 21H3v-7M3 21l8-8"/></svg></button>
 <button type="button" class="order-payment-file-action order-payment-file-retake" data-admin-proof-retake aria-label="Retake receipt" title="Retake" hidden><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5M6.1 7a7 7 0 0 1 11.6-2L20 8M4 16l2.3 3A7 7 0 0 0 17.9 17"/></svg></button>
</fieldset>
