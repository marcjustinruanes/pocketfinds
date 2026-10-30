@php($editing = isset($account) && $account)
@php($formRole = $editing ? ($account->account_type === 'rider' ? 'rider' : 'hub_staff') : ($creationRole ?? null))
@php($isRider = $formRole === 'rider')

{{-- ① Hub Assignment --}}
<div class="acf-section acf-section-first">
  <div class="acf-section-head">
    <span class="acf-section-num">1</span>
    <div>
      <strong>Hub Assignment</strong>
      <span>Assign to an operational hub — barangay and region load automatically</span>
    </div>
  </div>
  <div class="acf-grid" data-personnel-address>
    @if(!$editing)
      <input type="hidden" name="role" value="{{ $formRole ?? 'rider' }}">
    @endif
    <label class="acf-label">Assigned hub <span class="acf-req" aria-hidden="true">*</span>
      <select name="logistics_hub_id" required data-personnel-hub class="acf-input">
        <option value="">Select an operational hub</option>
        @foreach($hubs as $hub)
          <option value="{{ $hub->id }}"
            data-province="{{ $hub->province }}"
            data-municipality="{{ $hub->municipality }}"
            data-region="{{ $hub->region ?? '' }}"
            @selected((string)old('logistics_hub_id', $account->logistics_hub_id ?? '') === (string)$hub->id)>
            {{ $hub->municipality }}, {{ $hub->province }}
          </option>
        @endforeach
      </select>
    </label>
    <label class="acf-label">Barangay <span class="acf-req" aria-hidden="true">*</span>
      <select name="barangay" required disabled data-personnel-barangay data-current="{{ old('barangay', $account?->barangay ?? '') }}" class="acf-input">
        <option value="">Select a hub first</option>
      </select>
    </label>
    <p class="acf-hint acf-span" data-personnel-address-status role="status" aria-live="polite">Select a hub to load its barangays.</p>
    <button type="button" class="btn btn-outline acf-retry" data-personnel-address-retry hidden>Retry loading barangays</button>

    {{-- Region: derived from hub. Read-only when hub has a stored region; editable fallback otherwise. --}}
    <div class="acf-span" data-personnel-region-wrap>
      <div class="acf-label" id="acf-region-label">
        Region
        <div class="acf-region-display" data-personnel-region-display aria-live="polite">
          @php($existingRegion = old('region', $account?->region ?? ''))
          @if($existingRegion)
            <span class="acf-region-chip">{{ $existingRegion }}</span>
            <input type="hidden" name="region" value="{{ $existingRegion }}" data-personnel-region-hidden>
          @else
            <span class="acf-region-placeholder">Populated automatically when a hub is selected</span>
          @endif
        </div>
        {{-- Editable fallback: shown only when the selected hub has no stored region --}}
        <input class="acf-input" type="text" name="region" maxlength="255"
          placeholder="Enter region (hub has no region on record)"
          value="{{ $existingRegion }}"
          data-personnel-region-input
          style="display:none"
          aria-labelledby="acf-region-label">
      </div>
    </div>
  </div>
</div>

{{-- ② Personal Information --}}
<div class="acf-section">
  <div class="acf-section-head">
    <span class="acf-section-num">2</span>
    <div>
      <strong>Personal Information</strong>
      <span>Legal name, contact details and demographics</span>
    </div>
  </div>
  <div class="acf-grid">
    <label class="acf-label">First name <span class="acf-req" aria-hidden="true">*</span>
      <input class="acf-input" name="given_names" type="text" value="{{ old('given_names', $account?->given_names ?? '') }}" required minlength="2" maxlength="50" autocomplete="given-name">
    </label>
    <label class="acf-label">Last name <span class="acf-req" aria-hidden="true">*</span>
      <input class="acf-input" name="last_name" type="text" value="{{ old('last_name', $account?->last_name ?? '') }}" required minlength="2" maxlength="50" autocomplete="family-name">
    </label>
    <label class="acf-label">Middle name <span class="acf-optional">optional</span>
      <input class="acf-input" name="middle_name" type="text" value="{{ old('middle_name', $account?->middle_name ?? '') }}" minlength="2" maxlength="50" autocomplete="additional-name">
    </label>
    <label class="acf-label">Suffix <span class="acf-optional">optional</span>
      <select class="acf-input" name="suffix">
        <option value="">None</option>
        @foreach(\App\Enums\Suffix::cases() as $s)
          <option value="{{ $s->value }}" @selected(old('suffix', $account?->suffix?->value ?? '') === $s->value)>{{ $s->value }}</option>
        @endforeach
      </select>
    </label>
    <label class="acf-label">Contact number <span class="acf-req" aria-hidden="true">*</span>
      <span class="acf-phone-wrap">
        <span class="acf-phone-prefix">+63</span>
        <input class="acf-input" name="contact_no" type="tel" inputmode="numeric" autocomplete="tel-national"
          placeholder="9XXXXXXXXX" required minlength="10" maxlength="10" pattern="9[0-9]{9}"
          value="{{ substr(\App\Support\PhilippineMobile::normalize(old('contact_no', $account?->contact_no ?? '')), 1) }}">
      </span>
    </label>
    <label class="acf-label">Sex <span class="acf-req" aria-hidden="true">*</span>
      <select class="acf-input" name="sex" required>
        <option value="" disabled @selected(!old('sex', $account->sex ?? ''))>Select sex</option>
        @foreach(['male' => 'Male', 'female' => 'Female'] as $val => $lbl)
          <option value="{{ $val }}" @selected(old('sex', $account->sex ?? '') === $val)>{{ $lbl }}</option>
        @endforeach
      </select>
    </label>
    <label class="acf-label">Birthday <span class="acf-req" aria-hidden="true">*</span>
      <input class="acf-input" type="date" name="birthday" required
        max="{{ now()->subYears($isRider ? 18 : 16)->toDateString() }}"
        value="{{ old('birthday', $account?->birthday?->format('Y-m-d') ?? '') }}">
      <span class="acf-field-hint">{{ $isRider ? 'Rider must be at least 18 years old.' : 'Must be at least 16 years old.' }}</span>
    </label>
    @if($editing)
      <p class="acf-hint acf-span">Status: <strong>{{ ucfirst($account->status) }}</strong>. Review in the Riders or Hub Staff tab.</p>
    @endif
  </div>
</div>

{{-- ③ Account Credentials --}}
<div class="acf-section">
  <div class="acf-section-head">
    <span class="acf-section-num">3</span>
    <div>
      <strong>Account Credentials</strong>
      <span>Login details for the {{ $isRider ? 'rider' : 'hub staff' }}'s account</span>
    </div>
  </div>
  <div class="acf-grid">
    <label class="acf-label">Username <span class="acf-req" aria-hidden="true">*</span>
      <input class="acf-input" name="username" type="text" value="{{ old('username', $account?->username ?? '') }}" required maxlength="255" autocomplete="username">
    </label>
    <label class="acf-label">Email address <span class="acf-req" aria-hidden="true">*</span>
      <input class="acf-input" name="email" type="email" value="{{ old('email', $account?->email ?? '') }}" required maxlength="255" autocomplete="email">
    </label>
    <label class="acf-label">{{ $editing ? 'New password' : 'Password' }} @if($editing)<span class="acf-optional">optional</span>@else<span class="acf-req" aria-hidden="true">*</span>@endif
      <span class="acf-pw-wrap">
        <input class="acf-input" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="128" @required(!$editing) id="acf-pw-1">
        <button type="button" class="acf-pw-toggle" aria-label="Show password" data-pw-target="acf-pw-1">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 10s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6z"/><circle cx="10" cy="10" r="2.5"/></svg>
        </button>
      </span>
    </label>
    <label class="acf-label">Confirm password @if(!$editing)<span class="acf-req" aria-hidden="true">*</span>@endif
      <span class="acf-pw-wrap">
        <input class="acf-input" type="password" name="password_confirmation" autocomplete="new-password" @required(!$editing) id="acf-pw-2">
        <button type="button" class="acf-pw-toggle" aria-label="Show confirm password" data-pw-target="acf-pw-2">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 10s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6z"/><circle cx="10" cy="10" r="2.5"/></svg>
        </button>
      </span>
    </label>
    <p class="acf-hint acf-span">Minimum 12 characters — must include uppercase, lowercase, numbers and symbols.</p>
  </div>
</div>

@if($isRider)
{{-- ④ Vehicle Information --}}
<div class="acf-section">
  <div class="acf-section-head">
    <span class="acf-section-num">4</span>
    <div>
      <strong>Vehicle Information</strong>
      <span>Vehicle type, ownership and licence details</span>
    </div>
  </div>
  <div class="acf-grid">
    <label class="acf-label">Vehicle type <span class="acf-req" aria-hidden="true">*</span>
      <select class="acf-input" name="vehicle_type" required>
        <option value="">Select vehicle type</option>
        <option value="two_wheels" @selected(old('vehicle_type', $account->vehicle_type ?? '') === 'two_wheels')>Two wheels (motorcycle)</option>
        <option value="four_wheels" @selected(old('vehicle_type', $account->vehicle_type ?? '') === 'four_wheels')>Four wheels (car/van)</option>
      </select>
    </label>
    <label class="acf-label">Vehicle ownership <span class="acf-req" aria-hidden="true">*</span>
      <select class="acf-input" name="vehicle_ownership" required id="acf-ownership">
        <option value="">Select ownership</option>
        <option value="own" @selected(old('vehicle_ownership', $account->vehicle_ownership ?? '') === 'own')>Own vehicle</option>
        <option value="company" @selected(old('vehicle_ownership', $account->vehicle_ownership ?? '') === 'company')>Company vehicle</option>
      </select>
    </label>
    <label class="acf-label" data-rider-license>Licence expiry date <span class="acf-req" aria-hidden="true">*</span>
      <input class="acf-input" type="date" name="license_expiry" min="{{ today()->toDateString() }}"
        value="{{ old('license_expiry', $account?->license_expiry?->toDateString() ?? '') }}" required>
    </label>
  </div>
</div>
@endif

{{-- ⑤ Application Documents --}}
@if(!$editing || in_array($account->status, ['pending', 'interview']))
  @include('logistics.partials.personnel-application-fields')
@endif

{{-- Submit --}}
<div class="acf-footer">
  @if(!$editing)
    <p class="acf-submit-note">The {{ $isRider ? 'rider' : 'hub staff' }} application will remain pending until reviewed and approved.</p>
  @endif
  <button type="submit" class="btn btn-primary acf-submit">
    {{ $editing ? 'Save Account' : ($isRider ? 'Create Rider Application' : ($formRole === 'hub_staff' ? 'Create Hub Staff Application' : 'Create Account')) }}
  </button>
</div>

<script>
(() => {
  const form = document.currentScript.closest('form');

  // Hub → region auto-population
  const hubSelect = form.querySelector('[data-personnel-hub]');
  const regionDisplay = form.querySelector('[data-personnel-region-display]');
  const regionInput = form.querySelector('[data-personnel-region-input]');
  const updateRegion = () => {
    if (!regionDisplay || !regionInput) return;
    const opt = hubSelect?.selectedOptions[0];
    const hubRegion = opt?.dataset.region?.trim() || '';
    if (hubRegion) {
      // Hub has a stored region — show it read-only, submit via hidden input
      regionDisplay.innerHTML = '<span class="acf-region-chip">' + hubRegion.replace(/</g,'&lt;') + '</span><input type="hidden" name="region" value="' + hubRegion.replace(/"/g,'&quot;') + '" data-personnel-region-hidden>';
      regionInput.style.display = 'none';
      regionInput.removeAttribute('required');
      regionInput.name = '';
    } else if (opt?.value) {
      // Hub selected but has no stored region — show editable fallback
      regionDisplay.innerHTML = '<span class="acf-region-placeholder">No region on record for this hub — enter it below</span>';
      regionInput.style.display = '';
      regionInput.setAttribute('required', '');
      regionInput.name = 'region';
    } else {
      // No hub selected
      regionDisplay.innerHTML = '<span class="acf-region-placeholder">Populated automatically when a hub is selected</span>';
      regionInput.style.display = 'none';
      regionInput.removeAttribute('required');
      regionInput.name = '';
    }
  };
  hubSelect?.addEventListener('change', updateRegion);
  updateRegion();

  // Phone digit filter
  const phone = form.querySelector('[name="contact_no"]');
  const digits = v => v.replace(/\D/g, '').replace(/^(?:63|0)(?=9)/, '').slice(0, 10);
  phone.addEventListener('input', () => { phone.value = digits(phone.value); });
  phone.addEventListener('paste', e => { e.preventDefault(); phone.value = digits(e.clipboardData.getData('text')); });

  // Birthday max by role
  const role = form.querySelector('[name="role"]');
  const updateRole = () => {
    const rider = role ? role.value === 'rider' : @json($isRider);
    const bd = form.querySelector('[name="birthday"]');
    if (bd) bd.max = rider ? @json(now()->subYears(18)->toDateString()) : @json(now()->subYears(16)->toDateString());
    const expiry = form.querySelector('[name="license_expiry"]');
    if (expiry) { expiry.disabled = !rider; expiry.required = rider; expiry.closest('label').hidden = !rider; }
  };
  role?.addEventListener('change', updateRole); updateRole();

  // Password visibility toggles
  form.querySelectorAll('.acf-pw-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = form.querySelector('#' + btn.dataset.pwTarget);
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.classList.toggle('acf-pw-visible', show);
    });
  });

  // Prevent double-submit
  form.addEventListener('submit', () => {
    const btn = form.querySelector('.acf-submit');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
  });
})();
</script>
