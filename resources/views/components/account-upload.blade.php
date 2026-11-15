@props(['name','label','accept'=>'.jpg,.jpeg,.png,.pdf','actionLabel'=>'Upload Document'])
<div class="account-upload-field">
    <fieldset class="seller-upload-box" data-account-upload>
      <legend>{{ $label }}</legend>
      <input id="{{ $name }}" name="{{ $name }}" type="file" accept="{{ $accept }}" aria-label="{{ $label }}">
      <span class="seller-upload-idle" aria-hidden="true">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V3m-5 5 5-5 5 5M3 15v5a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-5"/></svg>
        <span>{{ $actionLabel }}</span>
      </span>
      <img class="seller-upload-preview" id="{{ $name }}_preview" alt="{{ $label }} preview" hidden>
      <svg class="seller-upload-pdf-icon" hidden width="30" height="34" viewBox="0 0 24 28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 1h10l6 6v19H4zM14 1v7h6M7 13h10M7 17h7"/><text x="6" y="23" fill="currentColor" stroke="none" font-size="6" font-family="sans-serif" font-weight="700">PDF</text></svg>
      <span class="seller-upload-pdf" hidden></span>
      <button type="button" class="seller-upload-enlarge" aria-label="Enlarge {{ $label }}" hidden><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7M21 3l-8 8M10 21H3v-7M3 21l8-8"/></svg></button>
      <button type="button" class="seller-upload-replace" aria-label="Reupload document" title="Reupload" hidden><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5M6.1 7a7 7 0 0 1 11.6-2L20 8M4 16l2.3 3A7 7 0 0 0 17.9 17"/></svg></button>
    </fieldset>
    <span class="field-hint seller-upload-note">{{ str_contains($accept,'pdf') ? 'JPG, PNG or PDF' : 'JPG or PNG' }} · maximum 5 MB</span>

</div>
