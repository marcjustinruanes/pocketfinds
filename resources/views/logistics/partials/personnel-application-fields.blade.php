@php($application = app(\App\Services\PersonnelApplication::class))
@php($ownership = old('vehicle_ownership', $account?->vehicle_ownership ?? 'own'))
@php($sectionNum = ($formRole === 'rider') ? 5 : 4)

<div class="acf-section acf-section-last" data-application-fields>
  <div class="acf-section-head">
    <span class="acf-section-num">{{ $sectionNum }}</span>
    <div>
      <strong>Application Documents</strong>
      <span>Private documents — not publicly accessible. Existing files are kept unless replaced.</span>
    </div>
  </div>

  <div class="acf-grid">
    @if($formRole === 'rider')
      <div class="acf-doc-note acf-span">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M10 9v5M10 7h.01"/></svg>
        The driver's licence serves as the identity document. Do not upload a separate ID.
      </div>
      <label class="acf-label">Licence number <span class="acf-req" aria-hidden="true">*</span>
        <input class="acf-input" name="license_number" maxlength="50" required
          value="{{ old('license_number', $account?->license_number ?? '') }}"
          placeholder="e.g. N01-23-456789">
      </label>
      <div data-own-vehicle-fields style="display:contents">
        <label class="acf-label">Vehicle brand <span class="acf-req" aria-hidden="true">*</span>
          <input class="acf-input" name="vehicle_brand" maxlength="100"
            value="{{ old('vehicle_brand', $account?->vehicle_brand ?? '') }}"
            @required($ownership === 'own')>
        </label>
        <label class="acf-label">Vehicle model <span class="acf-req" aria-hidden="true">*</span>
          <input class="acf-input" name="vehicle_model" maxlength="100"
            value="{{ old('vehicle_model', $account?->vehicle_model ?? '') }}"
            @required($ownership === 'own')>
        </label>
        <label class="acf-label">Plate number <span class="acf-req" aria-hidden="true">*</span>
          <input class="acf-input" name="plate_number" maxlength="20"
            value="{{ old('plate_number', $account?->plate_number ?? '') }}"
            @required($ownership === 'own')>
        </label>
      </div>
    @else
      <label class="acf-label">Identity type <span class="acf-req" aria-hidden="true">*</span>
        <select class="acf-input" name="id_type_id" required>
          <option value="">Select identity type</option>
          @foreach(\Illuminate\Support\Facades\DB::table('id_types')->get() as $type)
            <option value="{{ $type->id }}"
              @selected((string)old('id_type_id', $account?->id_type_id ?? '') === (string)$type->id)>
              {{ $type->name }}
            </option>
          @endforeach
        </select>
      </label>
    @endif
  </div>

  {{-- File uploads --}}
  @php($fileFields = $formRole === 'rider'
    ? ['license_file' => ['label' => "Driver's licence", 'accept' => 'image/jpeg,image/png,application/pdf', 'hint' => 'JPG, PNG or PDF'],
       'selfie_file'  => ['label' => 'Selfie with licence', 'accept' => 'image/jpeg,image/png', 'hint' => 'JPG or PNG — face clearly visible'],
       'or_file'      => ['label' => 'Official receipt (OR)', 'accept' => 'image/jpeg,image/png,application/pdf', 'hint' => 'JPG, PNG or PDF', 'own' => true],
       'cr_file'      => ['label' => 'Certificate of registration (CR)', 'accept' => 'image/jpeg,image/png,application/pdf', 'hint' => 'JPG, PNG or PDF', 'own' => true]]
    : ['id_file'      => ['label' => 'Identity document', 'accept' => 'image/jpeg,image/png,application/pdf', 'hint' => 'JPG, PNG or PDF'],
       'selfie_file'  => ['label' => 'Selfie with identity document', 'accept' => 'image/jpeg,image/png', 'hint' => 'JPG or PNG']])

  <div class="acf-file-grid">
    @foreach($fileFields as $field => $meta)
      @php($readable = $application->readable($account?->{$field}))
      @php($isOwn = $meta['own'] ?? false)
      <div class="acf-file-item{{ $isOwn ? ' acf-file-own' : '' }}" @if($isOwn) data-own-vehicle-fields @endif>
        <div class="acf-file-label-row">
          <label for="application-{{ $field }}" class="acf-label">
            {{ $meta['label'] }}
            @if(!$readable && !$isOwn)<span class="acf-req" aria-hidden="true">*</span>@endif
            @if($isOwn)<span class="acf-optional">own vehicle only</span>@endif
          </label>
          <span class="acf-file-hint">{{ $meta['hint'] }} · max 5 MB</span>
        </div>
        <label class="acf-file-drop" for="application-{{ $field }}">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M10 13V7m0 0L7.5 9.5M10 7l2.5 2.5"/><rect x="3" y="3" width="14" height="14" rx="3"/></svg>
          <span class="acf-file-drop-text">
            <strong>Choose file</strong> or drag and drop
          </span>
          <span class="acf-file-name" data-file-name>No file chosen</span>
          <input id="application-{{ $field }}" name="{{ $field }}" type="file"
            accept="{{ $meta['accept'] }}"
            data-file-required="{{ $readable ? '0' : '1' }}"
            @required(!$readable && (!$isOwn || $ownership === 'own'))>
        </label>
        @if($readable)
          <p class="acf-hint acf-file-status acf-file-status--ok">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 8l3.5 3.5L13 5"/></svg>
            Document on file — replacement is optional.
          </p>
          <x-admin-doc-thumb :path="$account->{$field}" :label="$meta['label']" />
        @elseif($editing)
          <p class="acf-hint acf-file-status acf-file-status--warn">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M8 3v5M8 11h.01"/><circle cx="8" cy="8" r="6.5"/></svg>
            Missing or unreadable — a replacement is required.
          </p>
        @endif
      </div>
    @endforeach
  </div>
</div>

<script>
(() => {
  const form = document.currentScript.closest('form');
  const ownership = form.querySelector('[name="vehicle_ownership"]');

  const updateOwn = () => {
    const own = ownership?.value === 'own';
    form.querySelectorAll('[data-own-vehicle-fields]').forEach(group => {
      group.hidden = !own;
      group.querySelectorAll('input').forEach(input => {
        input.disabled = !own;
        input.required = own && (input.type !== 'file' || input.dataset.fileRequired === '1');
      });
    });
  };
  ownership?.addEventListener('change', updateOwn);
  updateOwn();

  // Show selected filename
  form.querySelectorAll('.acf-file-drop input[type="file"]').forEach(input => {
    const nameEl = input.closest('.acf-file-drop')?.querySelector('[data-file-name]');
    if (!nameEl) return;
    input.addEventListener('change', () => {
      nameEl.textContent = input.files[0]?.name ?? 'No file chosen';
    });
  });
})();
</script>
