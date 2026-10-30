<div class="form-grid-2" data-role-address-fields>
  <div class="form-row"><label>Region<select class="select" name="region" data-role-region @isset($addressApi) data-address-api="{{ $addressApi }}" @endisset data-current="{{ old('region',auth()->user()->region) }}" required><option value="{{ old('region',auth()->user()->region) }}">{{ old('region',auth()->user()->region)?:'Loading regions…' }}</option></select></label></div>
  <div class="form-row"><label>Postal code<input type="text" name="postal_code" value="{{ old('postal_code',auth()->user()->postal_code) }}" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="postal-code" required></label></div>
  <p data-role-address-error role="status" style="font-size:12px;color:var(--danger)"></p>
</div>
