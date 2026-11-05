<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Correct Seller Application — PocketFinds</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/seller-application.css') }}?v={{ filemtime(public_path('css/seller-application.css')) }}">
</head>
<body class="seller-application-page">
<main class="seller-application">
  <a class="application-brand" href="{{ url('/') }}">PocketFinds</a>
  <h1>Correct your seller application</h1>
  <p>Your application was rejected. Update the details or documents below and resubmit for review.</p>
  <div class="application-reason"><strong>Reason for rejection</strong><p>{{ $applicant->status_reason ?: 'Please review your application details and documents.' }}</p></div>
  <p class="application-identity">{{ $applicant->email }} · {{ $applicant->username }}</p>
  @if($errors->any())<div class="application-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <form data-registration-form data-registration-dirty="{{ $errors->any() ? 'true' : 'false' }}" action="{{ route('seller.application.update') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <fieldset><legend>Personal details</legend><div class="application-fields">
      @foreach(['given_names' => 'Given names', 'middle_name' => 'Middle name', 'last_name' => 'Last name', 'contact_no' => 'Mobile number'] as $field => $label)
      <label>{{ $label }}<input name="{{ $field }}" value="{{ old($field, $applicant->{$field}) }}" maxlength="{{ $field === 'contact_no' ? 14 : 50 }}" @if($field !== 'middle_name') required @endif @if($field === 'contact_no') type="tel" @else type="text" @endif></label>
      @endforeach
      <label>Sex<select name="sex" required>@foreach(\App\Enums\Sex::cases() as $sexOption)<option value="{{ $sexOption->value }}" @selected(old('sex', $applicant->sex) === $sexOption->value)>{{ $sexOption->label() }}</option>@endforeach</select></label>
      <label>Birthday<input type="date" name="birthday" value="{{ old('birthday', $applicant->birthday?->toDateString()) }}" max="{{ now()->subYears(16)->toDateString() }}" required></label>
    </div></fieldset>
    <fieldset><legend>Shop details</legend><div class="application-fields">
      <label>Shop name<input name="business_name" value="{{ old('business_name', $applicant->business_name) }}" minlength="3" maxlength="30" required></label>
      <label>Seller type<select name="seller_type" required>@foreach(\App\Enums\SellerType::cases() as $type)<option value="{{ $type->value }}" @selected(old('seller_type', $applicant->seller_type?->value) === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
      <label>TIN<input name="tin" data-tin inputmode="numeric" maxlength="15" pattern="[0-9]{3}-?[0-9]{3}-?[0-9]{3}-?[0-9]{3}" placeholder="123-456-789-000" value="{{ old('tin', $applicant->tin) }}" required></label>
      <label>Category<select name="category_id"><option value="">Custom category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id', $applicant->category_id) === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></label>
      <label>Custom category (only if selected above)<input name="category_other" maxlength="100" value="{{ old('category_other', $applicant->category_other) }}"></label>
    </div></fieldset>
    <fieldset><legend>Pickup address</legend><div class="application-fields">
      @foreach(['region' => 'Region', 'province' => 'Province', 'municipality' => 'City / municipality', 'barangay' => 'Barangay', 'house_no' => 'House / unit', 'street' => 'Street', 'postal_code' => 'Postal code'] as $field => $label)
      <label>{{ $label }}<input name="{{ $field }}" value="{{ old($field, $applicant->{$field}) }}" @if(!in_array($field, ['house_no', 'street'])) required @endif @if($field === 'postal_code') inputmode="numeric" pattern="[0-9]{4}" maxlength="4" @else maxlength="{{ $field === 'house_no' ? 50 : ($field === 'street' ? 100 : 255) }}" @endif></label>
      @endforeach
    </div></fieldset>
    <fieldset><legend>Documents</legend>
      <p>Replace the documents that need correcting. Existing documents are kept when no replacement is uploaded.</p>
      <label>ID type<select name="id_type_id" required><option value="">Select ID type</option>@foreach($idTypes as $type)<option value="{{ $type->id }}" @selected((string) old('id_type_id', $applicant->id_type_id) === (string) $type->id)>{{ $type->name }}</option>@endforeach</select></label>
      <div class="application-fields">
      @foreach($documents as $field => $document)
      <label @if($document['types']) data-seller-document="{{ $document['types'] }}" data-document-on-file="{{ $document['exists'] ? 'true' : 'false' }}" @endif @if(!$document['required']) hidden @endif>{{ $document['label'] }}<small>{{ $document['exists'] ? 'Document on file · replacement optional' : 'Upload required' }}</small><input type="file" name="{{ $field }}" accept="{{ $field === 'selfie_file' ? '.jpg,.jpeg,.png' : '.jpg,.jpeg,.png,.pdf' }}" @required($document['required'] && !$document['exists']) @disabled(!$document['required'])><small>{{ $field === 'selfie_file' ? 'JPG or PNG' : 'JPG, PNG or PDF' }} · maximum 5 MB</small></label>
      @endforeach
      </div>
    </fieldset>
    <div class="application-actions"><a href="{{ route('login') }}">Back to sign in</a><button type="submit">Resubmit application</button></div>
  </form>
</main>
<script src="{{ asset('js/tin-input.js') }}?v={{ filemtime(public_path('js/tin-input.js')) }}" defer></script>
<script src="{{ asset('js/seller-documents.js') }}?v={{ filemtime(public_path('js/seller-documents.js')) }}" defer></script>
<script src="{{ asset('js/registration-unsaved.js') }}?v={{ filemtime(public_path('js/registration-unsaved.js')) }}"></script>
</body>
</html>
