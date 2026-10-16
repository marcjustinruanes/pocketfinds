@extends('layouts.auth')
@section('title', ucfirst($role).' registration — PocketFinds')
@section('content')
<div class="auth-card" style="max-width:680px;margin:32px auto;padding:28px">
  <h1 class="auth-title">Create your {{ $role }} account</h1>
  <p class="auth-subtitle">{{ $role === 'buyer' ? 'Verify your email to start shopping.' : 'Verify your email, then an admin will review your application.' }}</p>
  @if($errors->any())
    <div role="alert" class="auth-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif
  <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" id="registrationForm">
    @csrf
    <input type="hidden" name="account_type" value="{{ $role }}">
    <div class="auth-form-grid">
      @foreach(['given_names' => ($role === 'logistics' ? 'Contact person first name' : 'First name'), 'last_name' => ($role === 'logistics' ? 'Contact person last name' : 'Last name')] as $field => $label)
        <div class="auth-field"><label class="auth-label" for="{{ $field }}">{{ $label }} *</label><input class="auth-input" id="{{ $field }}" name="{{ $field }}" required minlength="2" maxlength="50" value="{{ old($field) }}">@error($field)<span class="field-error">{{ $message }}</span>@enderror</div>
      @endforeach
      <div class="auth-field full"><label class="auth-label" for="email">Email *</label><input class="auth-input" type="email" id="email" name="email" required maxlength="255" value="{{ old('email', session('google_email')) }}">@error('email')<span class="field-error">{{ $message }}</span>@enderror</div>
      <div class="auth-field"><label class="auth-label" for="contact_no">Mobile number *</label><input class="auth-input" type="tel" id="contact_no" name="contact_no" required pattern="(09[0-9]{9}|\+639[0-9]{9})" maxlength="13" placeholder="09XXXXXXXXX or +639XXXXXXXXX" value="{{ old('contact_no') }}">@error('contact_no')<span class="field-error">{{ $message }}</span>@enderror</div>
      @if($role === 'buyer')
        <div class="auth-field"><label class="auth-label" for="birthday">Date of birth *</label><input class="auth-input" type="date" id="birthday" name="birthday" required max="{{ now()->subYears(18)->toDateString() }}" value="{{ old('birthday') }}">@error('birthday')<span class="field-error">{{ $message }}</span>@enderror</div>
      @else
        <div class="auth-field"><label class="auth-label" for="business_name">{{ $role === 'seller' ? 'Shop name' : 'Company name' }} *</label><input class="auth-input" id="business_name" name="business_name" required minlength="3" maxlength="{{ $role === 'seller' ? 30 : 150 }}" value="{{ old('business_name') }}">@error('business_name')<span class="field-error">{{ $message }}</span>@enderror</div>
        @if($role === 'seller')
          <div class="auth-field"><label class="auth-label" for="category_id">Shop category *</label><select class="auth-input" id="category_id" name="category_id" required><option value="">Select a category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
          <div class="auth-field"><label class="auth-label" for="seller_type">Seller type *</label><select class="auth-input" id="seller_type" name="seller_type" required><option value="">Select seller type</option>@foreach(['individual' => 'Individual', 'sole_proprietorship' => 'Sole Proprietorship', 'corporation' => 'Corporation'] as $value => $label)<option value="{{ $value }}" @selected(old('seller_type') === $value)>{{ $label }}</option>@endforeach</select></div>
        @else
          <div class="auth-field"><label class="auth-label" for="sec_registration_no">SEC registration number *</label><input class="auth-input" id="sec_registration_no" name="sec_registration_no" required pattern="[A-Za-z]{2}[0-9]{9}" maxlength="11" value="{{ old('sec_registration_no') }}"></div>
          <div class="auth-field"><label class="auth-label" for="position">Contact person position *</label><input class="auth-input" id="position" name="position" required maxlength="255" value="{{ old('position') }}"></div>
        @endif
        <div class="auth-field"><label class="auth-label" for="tin">TIN *</label><input class="auth-input" id="tin" name="tin" required pattern="[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}" placeholder="000-000-000-000" value="{{ old('tin') }}"></div>
        <div class="auth-field"><label class="auth-label" for="region">Region *</label><select class="auth-input" id="region" name="region" data-initial="{{ old('region') }}" required><option value="">Loading regions…</option></select></div>
        <div class="auth-field"><label class="auth-label" for="province">Province *</label><select class="auth-input" id="province" name="province" data-initial="{{ old('province') }}" required disabled><option value="">Select region first</option></select></div>
        <div class="auth-field"><label class="auth-label" for="municipality">City / municipality *</label><select class="auth-input" id="municipality" name="municipality" data-initial="{{ old('municipality') }}" required disabled><option value="">Select province first</option></select></div>
        <div class="auth-field"><label class="auth-label" for="barangay">Barangay *</label><select class="auth-input" id="barangay" name="barangay" data-initial="{{ old('barangay') }}" required disabled><option value="">Select city first</option></select></div>
        <div class="auth-field full"><label class="auth-label" for="street">Street address *</label><input class="auth-input" id="street" name="street" required maxlength="255" value="{{ old('street') }}"></div>
        <div class="auth-field"><label class="auth-label" for="postal_code">Postal code *</label><input class="auth-input" id="postal_code" name="postal_code" required inputmode="numeric" pattern="[0-9]{4}" maxlength="4" value="{{ old('postal_code') }}"></div>
        @if($role === 'logistics')
          <div class="auth-field full"><label class="auth-label" for="service_areas">Service areas * (select all that apply)</label><select class="auth-input" id="service_areas" name="service_areas[]" multiple required></select></div>
        @endif
        @foreach(['id_file' => 'Government-issued ID', 'dti_file' => 'DTI Certificate', 'sec_file' => 'SEC Certificate', 'bir_file' => 'BIR Form 2303', 'business_permit_file' => "Mayor’s / Business Permit"] as $field => $label)
          @if(($role === 'seller' && $field !== 'business_permit_file') || ($role === 'logistics' && $field !== 'dti_file'))
            <div class="auth-field full" data-document="{{ $field }}"><label class="auth-label" for="{{ $field }}">{{ $label }} <span data-required-mark>*</span></label><input class="auth-input" type="file" id="{{ $field }}" name="{{ $field }}" accept="image/jpeg,image/png,application/pdf" @required($role === 'logistics' || $field === 'id_file')><span class="field-hint">JPG, PNG or PDF; up to 5 MB.</span>@error($field)<span class="field-error">{{ $message }}</span>@enderror</div>
          @endif
        @endforeach
      @endif
      @foreach(['password' => 'Password', 'password_confirmation' => 'Confirm password'] as $field => $label)
        <div class="auth-field"><label class="auth-label" for="{{ $field }}">{{ $label }} *</label><input class="auth-input" type="password" id="{{ $field }}" name="{{ $field }}" required minlength="8" autocomplete="new-password">@error($field)<span class="field-error">{{ $message }}</span>@enderror</div>
      @endforeach
      <p class="field-hint full">Use at least 8 characters, including uppercase and lowercase letters, a number and a special character.</p>
      <label class="auth-field full" style="display:block"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))> I accept the <a href="{{ route('registration.terms', $role) }}" target="_blank">Terms and Privacy Policy</a>.</label>
    </div>
    <button class="auth-btn" type="submit" id="registerSubmit">Register</button>
  </form>
  <p style="margin-top:16px"><a href="{{ route('login') }}">Already have an account? Sign in</a></p>
</div>
<script src="{{ asset('js/account-registration.js') }}" defer></script>
@endsection
