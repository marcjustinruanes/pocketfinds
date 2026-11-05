@if(($regType ?? request('type', 'buyer')) === 'seller')
    <div class="auth-field full">
        <label class="auth-label" for="shop_logo">Shop logo</label>
        <input id="shop_logo" name="shop_logo" type="file" accept="image/jpeg,image/png,image/webp">
    </div>
    <div class="auth-field">
        <label class="auth-label" for="seller_type">Seller type <span class="auth-required">*</span></label>
        <select class="auth-input auth-select" id="seller_type" name="seller_type" required>
            <option value="" disabled selected>Select seller type</option>
            @foreach(\App\Enums\SellerType::cases() as $sellerType)
                <option value="{{ $sellerType->value }}" @selected(old('seller_type') === $sellerType->value)>{{ $sellerType->label() }}</option>
            @endforeach
        </select>
        <span class="field-hint">Documents depend on your seller type.</span>
    </div>
@endif
@if(in_array($regType ?? request('type', 'buyer'), ['seller', 'logistics'], true) && !request()->is('register/logistics-staff'))
    <div class="auth-field">
        <label class="auth-label" for="tin">TIN <span class="auth-required">*</span></label>
        <input class="auth-input" id="tin" name="tin" data-tin type="text" inputmode="numeric" minlength="12" maxlength="15" pattern="[0-9]{3}-?[0-9]{3}-?[0-9]{3}-?[0-9]{3}" placeholder="123-456-789-000" value="{{ old('tin') }}" required>
        <span class="field-hint">12 digits. Hyphens are added automatically.</span>
    </div>
@endif
@once<script src="{{ asset('js/tin-input.js') }}?v={{ filemtime(public_path('js/tin-input.js')) }}" defer></script>@endonce
@once<script src="{{ asset('js/registration-business-numbers.js') }}?v={{ filemtime(public_path('js/registration-business-numbers.js')) }}" defer></script>@endonce
