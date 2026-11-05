<div class="logistics-document-fields">
  @foreach(\App\Support\LogisticsDocuments::LABELS as $field => $label)
    <div class="auth-field" style="margin-top:12px">
      <label class="auth-label" for="{{ $field }}">{{ $label }} <span class="auth-required">*</span></label>
      <input class="auth-input" id="{{ $field }}" name="{{ $field }}" type="file" accept=".jpg,.jpeg,.png,.pdf" required>
      <span class="field-hint">JPG, PNG or PDF · maximum 5 MB</span>
    </div>
  @endforeach
</div>
@once<script src="{{ asset('js/logistics-documents.js') }}?v={{ filemtime(public_path('js/logistics-documents.js')) }}" defer></script>@endonce
