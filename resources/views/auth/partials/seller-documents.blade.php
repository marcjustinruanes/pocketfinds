<div class="seller-document-fields">
  @foreach(\App\Support\SellerDocuments::LABELS as $field => $label)
  @php($types = $field === 'dti_certificate_file' ? 'sole_proprietorship' : ($field === 'sec_certificate_file' ? 'corporation' : 'sole_proprietorship,corporation'))
  <div class="auth-field" data-seller-document="{{ $types }}" hidden>
    <fieldset class="seller-upload-box">
      <legend>{{ $label }}</legend>
      <input id="{{ $field }}" name="{{ $field }}" type="file" accept=".jpg,.jpeg,.png,.pdf" aria-label="{{ $label }}" disabled>
      <span class="seller-upload-idle" aria-hidden="true">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V3m-5 5 5-5 5 5M3 15v5a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-5"/></svg>
        <span>Upload Document</span>
      </span>
      <img class="seller-upload-preview" id="{{ $field }}_preview" alt="{{ $label }} preview" hidden>
      <svg class="seller-upload-pdf-icon" hidden width="30" height="34" viewBox="0 0 24 28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 1h10l6 6v19H4zM14 1v7h6M7 13h10M7 17h7"/><text x="6" y="23" fill="currentColor" stroke="none" font-size="6" font-family="sans-serif" font-weight="700">PDF</text></svg>
      <span class="seller-upload-pdf" hidden></span>
      <button type="button" class="seller-upload-enlarge" aria-label="Enlarge {{ $label }}" hidden><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7M21 3l-8 8M10 21H3v-7M3 21l8-8"/></svg></button>
      <button type="button" class="seller-upload-replace" aria-label="Reupload document" title="Reupload" hidden><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5M6.1 7a7 7 0 0 1 11.6-2L20 8M4 16l2.3 3A7 7 0 0 0 17.9 17"/></svg></button>
    </fieldset>
    <span class="field-hint seller-upload-note">JPG, PNG or PDF · maximum 5 MB</span>
  </div>
  @endforeach
</div>
@once<script src="{{ asset('js/seller-documents.js') }}?v={{ filemtime(public_path('js/seller-documents.js')) }}" defer></script>@endonce
