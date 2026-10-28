@if(($regType ?? request('type', 'buyer')) === 'seller')
    <div class="auth-field">
        <label class="auth-label" for="seller_type">Seller type <span class="auth-required">*</span></label>
        <select class="auth-input auth-select" id="seller_type" name="seller_type" required>
            <option value="" disabled selected>Select seller type</option>
            @foreach(\App\Enums\SellerType::cases() as $sellerType)
                <option value="{{ $sellerType->value }}" @selected(old('seller_type') === $sellerType->value)>{{ $sellerType->label() }}</option>
            @endforeach
        </select>
    </div>
@endif
@if(in_array($regType ?? request('type', 'buyer'), ['seller', 'logistics'], true) && !request()->is('register/logistics-staff'))
    <div class="auth-field">
        <label class="auth-label" for="tin">TIN <span class="auth-required">*</span></label>
        <input class="auth-input" id="tin" name="tin" type="text" inputmode="numeric" minlength="12" maxlength="12" pattern="[0-9]{12}" placeholder="123456789000" value="{{ old('tin') }}" required>
        <span class="field-hint">Exactly 12 digits.</span>
    </div>
@endif
