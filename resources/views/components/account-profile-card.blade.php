@props(['role', 'action', 'pending' => false])
@php($accountUser = auth()->user())
<div class="card" data-account-panel="profile-information">
  <div class="card-pad">
    @if(session('profile_success'))<div class="auth-success" role="status">{{ session('profile_success') }}</div>@endif
    @if(session('profile_photo_success'))<div class="auth-success" role="status">{{ session('profile_photo_success') }}</div>@endif
    <div class="profile-personal-heading-row" id="profile-information">
      <div class="profile-form-section-heading"><h3>Personal details</h3><p>{{ $role === 'admin' ? 'Manage the personal details on your account.' : 'Changes to your personal details are reviewed by an admin before approval.' }}</p></div>
      <button class="buyer-profile-photo-trigger" type="button" id="openBuyerProfilePhotoModal" aria-label="Manage profile photo">
        <span class="buyer-profile-photo-preview" id="buyerProfilePhotoPreview"><x-user-avatar :user="$accountUser" size="82" /></span>
      </button>
    </div>
    <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
      @csrf
      @if($role === 'admin')
        @foreach(['region','postal_code','province','municipality','barangay','house_no','street'] as $field)
          <input type="hidden" name="{{ $field }}" value="{{ old($field, $accountUser->$field) }}">
        @endforeach
      @endif
      <fieldset class="account-profile-fields" @disabled($pending)>
        <section class="profile-form-section">
          <div class="profile-name-grid">
            <div class="form-row profile-name-given"><label for="profileGivenNames">Given Names</label><input id="profileGivenNames" name="given_names" value="{{ old('given_names', $accountUser->given_names) }}" minlength="2" maxlength="50" required>@error('given_names')<small class="profile-field-error">{{ $message }}</small>@enderror</div>
            <div class="form-row profile-name-username"><label for="profileUsername">Username</label><input id="profileUsername" value="{{ $accountUser->username }}" readonly></div>
            <div class="form-row"><label for="profileMiddleName">Middle Name</label><input id="profileMiddleName" @if($role !== 'admin') name="middle_name" @endif value="{{ old('middle_name', $accountUser->middle_name) }}" maxlength="50" @readonly($role === 'admin')>@error('middle_name')<small class="profile-field-error">{{ $message }}</small>@enderror</div>
            <div class="form-row"><label for="profileLastName">Last Name</label><input id="profileLastName" name="last_name" value="{{ old('last_name', $accountUser->last_name) }}" minlength="2" maxlength="50" required>@error('last_name')<small class="profile-field-error">{{ $message }}</small>@enderror</div>
            <div class="form-row"><label for="profileSuffix">Suffix</label><input id="profileSuffix" value="{{ $accountUser->suffix?->value ?? 'None' }}" readonly></div>
          </div>
          <div class="form-grid-2 profile-birthday-row">
            <div class="form-row"><label for="profileSex">Sex</label><select id="profileSex" @if($role !== 'admin') name="sex" @endif @disabled($role === 'admin') @required($role === 'seller')><option value="">Select sex</option>@foreach(['male'=>'Male','female'=>'Female',...($role !== 'seller' ? ['other'=>'Other'] : [])] as $value=>$label)<option value="{{ $value }}" @selected(old('sex', $accountUser->sex) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="form-row"><label for="profileBirthday">Birthday</label><input id="profileBirthday" type="date" @if($role === 'seller') name="birthday" @endif value="{{ old('birthday', $accountUser->birthday?->format('Y-m-d')) }}" @readonly($role !== 'seller')></div>
            <div class="form-row profile-age-field"><div class="account-floating-field always-floating"><output class="account-age-output">{{ $accountUser->birthday ? $accountUser->birthday->age.' years old' : '—' }}</output><label>Age</label><fieldset aria-hidden="true"><legend><span>Age</span></legend></fieldset></div></div>
          </div>        </section>
        <section class="profile-form-section">
          <div class="profile-form-section-heading"><h3>Contact information</h3><p>Your email and mobile number help us keep you updated.</p></div>
          <div class="form-grid-2">
            <div class="form-row"><label for="profileEmail">Email</label><input id="profileEmail" type="email" @if($role === 'admin') name="email" @endif value="{{ $accountUser->email }}" readonly></div>
            <div class="form-row"><label for="profileContact">Contact No.</label><input id="profileContact" type="tel" name="contact_no" value="{{ old('contact_no', $accountUser->contact_no) }}" inputmode="numeric" @required($role === 'admin')>@error('contact_no')<small class="profile-field-error">{{ $message }}</small>@enderror</div>
          </div>
        </section>
        <div class="profile-submit-row"><button class="btn btn-primary" type="submit">{{ $role === 'admin' ? 'Save changes' : 'Submit profile update for review' }}</button>
          <div class="account-details-grid profile-account-details" aria-label="Account details"><div class="account-detail-item"><span class="field-label">Account Type</span><span class="field-value"><span class="account-detail-badge">{{ $role === 'admin' ? 'Administrator' : ($accountUser->isHubStaff() ? 'Hub Staff' : ucfirst($role)) }}</span></span></div><div class="account-detail-item"><span class="field-label">Status</span><span class="field-value"><span class="account-detail-badge account-status-badge">{{ ucfirst($accountUser->status) }} on {{ $accountUser->created_at?->format('M d, Y') ?? '—' }}</span></span></div></div>
        </div>
      </fieldset>
    </form>
  </div>
</div>
