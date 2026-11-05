<div class="auth-field registration-postal-field" id="registrationPostalField" data-lookup-url="{{ route('register.postal-code') }}">
    <label for="postal_code">Postal code</label>
    <input class="auth-input registration-floating-native" type="text" id="postal_code" name="postal_code" value="" inputmode="numeric" maxlength="4" readonly placeholder="Unavailable">
    <input type="hidden" id="municipality_psgc_code" name="municipality_psgc_code" value="">
    <span id="registrationPostalCapsule" class="registration-postal-status" role="status" aria-live="polite"></span>
</div>
@once<script defer src="{{ asset('js/registration-postal-code.js') }}?v={{ filemtime(public_path('js/registration-postal-code.js')) }}"></script>@endonce
