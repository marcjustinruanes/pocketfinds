@if(in_array($regType ?? request('type', 'buyer'), ['seller', 'logistics'], true))
<div class="auth-field">
    <label class="auth-label" for="postal_code">Postal code <span class="auth-required">*</span></label>
    <input class="auth-input" id="postal_code" name="postal_code" type="text" inputmode="numeric" minlength="4" maxlength="4" pattern="[0-9]{4}" placeholder="1603" value="{{ old('postal_code') }}" required>
</div>
@endif
