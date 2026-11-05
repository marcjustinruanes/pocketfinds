<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Correct Application — PocketFinds</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/seller-application.css') }}?v={{ filemtime(public_path('css/seller-application.css')) }}">
</head>
<body class="seller-application-page">
<main class="seller-application">
  <a class="application-brand" href="{{ url('/') }}">PocketFinds</a>
  <h1>Correct your {{ $personnelRole === 'hub_staff' ? 'hub staff' : ($isFounder ? 'logistics company' : $applicant->account_type) }} application</h1>
  <p>Update your details or replace documents, then resubmit for review.</p>
  <div class="application-reason"><strong>Reason for rejection</strong><p>{{ $applicant->status_reason ?: 'Please review your application details and documents.' }}</p></div>
  <p class="application-identity">{{ $applicant->email }} · {{ $applicant->username }}</p>
  @if($errors->any())<div class="application-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <form data-registration-form data-registration-dirty="{{ $errors->any() ? 'true' : 'false' }}" action="{{ route('application.correction.update') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <fieldset><legend>Personal details</legend><div class="application-fields">
      @foreach(['given_names'=>'Given names','middle_name'=>'Middle name','last_name'=>'Last name','contact_no'=>'Mobile number'] as $field=>$label)
        <label>{{ $label }}<input name="{{ $field }}" value="{{ old($field,$applicant->{$field}) }}" maxlength="{{ $field==='contact_no' ? 14 : 50 }}" @required($field!=='middle_name') type="{{ $field==='contact_no' ? 'tel' : 'text' }}"></label>
      @endforeach
      <label>Suffix<select name="suffix"><option value="">None</option>@foreach(\App\Enums\Suffix::cases() as $suffix)<option value="{{ $suffix->value }}" @selected(old('suffix',$applicant->suffix?->value)===$suffix->value)>{{ $suffix->value }}</option>@endforeach</select></label>
      <label>Sex<select name="sex" required>@foreach(\App\Enums\Sex::cases() as $sex)<option value="{{ $sex->value }}" @selected(old('sex',$applicant->sex)===$sex->value)>{{ $sex->label() }}</option>@endforeach</select></label>
      <label>Birthday<input type="date" name="birthday" value="{{ old('birthday',$applicant->birthday?->toDateString()) }}" max="{{ now()->subYears(in_array($applicant->account_type,['buyer','rider'])?18:16)->toDateString() }}" required></label>
    </div></fieldset>
    @if($isFounder)
    <fieldset><legend>Company details</legend>
      <p><strong>{{ $applicant->business_name }}</strong></p>
      <div class="application-fields">
        <label>Position<input name="position" value="{{ old('position',$applicant->position) }}" maxlength="100" required></label>
        <label>SEC registration number<input name="sec_registration_no" value="{{ old('sec_registration_no',$applicant->sec_registration_no) }}" maxlength="11" required></label>
        <label>TIN<input name="tin" data-tin value="{{ old('tin',$applicant->tin) }}" inputmode="numeric" maxlength="15" required></label>
      </div>
    </fieldset>
    @elseif($personnelRole)
    <fieldset><legend>Company and hub</legend><p>{{ $applicant->business_name }} · {{ $hub?->municipality }}, {{ $hub?->province }}</p></fieldset>
    @endif
    <fieldset><legend>Address</legend><div class="application-fields">
      @foreach(['region'=>'Region','province'=>'Province','municipality'=>'City / municipality','barangay'=>'Barangay','house_no'=>'House / unit','street'=>'Street'] as $field=>$label)
        <label>{{ $label }}<input name="{{ $field }}" value="{{ old($field,$applicant->{$field}) }}" maxlength="{{ $field==='house_no' ? 50 : ($field==='street' ? 100 : 255) }}" @required(!in_array($field,['house_no','street']))></label>
      @endforeach
      @if($isFounder)<label>Postal code<input name="postal_code" value="{{ old('postal_code',$applicant->postal_code) }}" pattern="[0-9]{4}" maxlength="4" inputmode="numeric" required></label>@endif
    </div></fieldset>
    @if($personnelRole==='rider')
    <fieldset><legend>Vehicle and license</legend><div class="application-fields">
      <label>Vehicle type<select name="vehicle_type" required>@foreach(\Illuminate\Support\Facades\DB::table('vehicle_types')->orderBy('id')->get() as $type)<option value="{{ $type->slug }}" @selected(old('vehicle_type',$applicant->vehicle_type)===$type->slug)>{{ $type->name }}</option>@endforeach</select></label>
      <label>Vehicle ownership<select name="vehicle_ownership" required>@foreach(\App\Enums\VehicleOwnership::cases() as $ownership)<option value="{{ $ownership->value }}" data-own-vehicle="{{ $ownership===\App\Enums\VehicleOwnership::Own ? '1' : '0' }}" @selected(old('vehicle_ownership',$applicant->vehicle_ownership)===$ownership->value)>{{ $ownership->label() }}</option>@endforeach</select></label>
      <label>License number<input name="license_number" value="{{ old('license_number',$applicant->license_number) }}" required></label>
      <label>License expiry<input name="license_expiry" type="date" min="{{ today()->addMonthNoOverflow()->toDateString() }}" value="{{ old('license_expiry',$applicant->license_expiry?->toDateString()) }}" required></label>
      @foreach(['vehicle_brand'=>'Vehicle brand','vehicle_model'=>'Vehicle model','plate_number'=>'Plate number'] as $field=>$label)
        <label data-own-vehicle-correction>{{ $label }}<input name="{{ $field }}" value="{{ old($field,$applicant->{$field}) }}" data-required="true"></label>
      @endforeach
    </div></fieldset>
    @endif
    <fieldset><legend>Documents</legend>
      <p>Existing documents are kept unless you upload replacements. Missing documents must be uploaded.</p>
      @if($personnelRole!=='rider')<label>ID type<select name="id_type_id" required>@foreach($idTypes as $type)<option value="{{ $type->id }}" @selected((string)old('id_type_id',$applicant->id_type_id)===(string)$type->id)>{{ $type->name }}</option>@endforeach</select></label>@endif
      <div class="application-fields">
        @foreach($documents as $field=>$document)
          <label @if(in_array($field,['or_file','cr_file'])) data-own-vehicle-correction @endif>{{ $document['label'] }}<small>{{ $document['exists'] ? 'Document on file · replacement optional' : 'Upload required' }}</small>
            <input type="file" name="{{ $field }}" accept="{{ $field==='selfie_file' ? '.jpg,.jpeg,.png' : '.jpg,.jpeg,.png,.pdf' }}" data-required="{{ $document['exists'] ? 'false' : 'true' }}" @required(!$document['exists'])>
            <small>{{ $field==='selfie_file' ? 'JPG or PNG' : 'JPG, PNG or PDF' }} · maximum 5 MB</small>
          </label>
        @endforeach
        @if($personnelRole)
          <label>Resume / CV (optional replacement)<small>{{ $applicant->resume_file ? 'Document on file' : 'Optional' }}</small><input type="file" name="resume_file" accept=".pdf,application/pdf"><small>PDF · maximum 5 MB</small></label>
        @elseif($isFounder)
          <label>Company logo (optional replacement)<input type="file" name="company_logo" accept=".jpg,.jpeg,.png,.webp" data-max-kb="5120"><small>Maximum 5 MB</small></label>
        @endif
      </div>
    </fieldset>
    <div class="application-actions"><a href="{{ route('login') }}">Back to sign in</a><button type="submit">Resubmit application</button></div>
  </form>
</main>
<script src="{{ asset('js/registration-unsaved.js') }}?v={{ filemtime(public_path('js/registration-unsaved.js')) }}"></script>
<script src="{{ asset('js/license-number-input.js') }}?v={{ filemtime(public_path('js/license-number-input.js')) }}"></script>
<script src="{{ asset('js/application-correction.js') }}?v={{ filemtime(public_path('js/application-correction.js')) }}"></script>
<script src="{{ asset('js/tin-input.js') }}?v={{ filemtime(public_path('js/tin-input.js')) }}" defer></script>
</body>
</html>
