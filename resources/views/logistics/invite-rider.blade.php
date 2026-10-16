@extends('layouts.auth')
@section('title', 'Add rider — PocketFinds')
@section('content')
<main class="auth-card" style="max-width:640px;margin:32px auto;padding:28px">
  <h1>Add rider</h1><p>The rider will receive an email invitation to set their password. The invitation expires in 24 hours.</p>
  @if($errors->any())<ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
  <form method="POST" action="{{ route('logistics.riders.store') }}" enctype="multipart/form-data" id="inviteRiderForm">
    @csrf
    <div class="auth-form-grid">
      @foreach(['given_names' => 'First name', 'last_name' => 'Last name', 'email' => 'Email', 'contact_no' => 'Mobile number', 'license_number' => "Driver’s license number", 'plate_number' => 'Plate number'] as $field => $label)
        <div class="auth-field"><label class="auth-label" for="{{ $field }}">{{ $label }} *</label><input class="auth-input" id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" required value="{{ old($field) }}"></div>
      @endforeach
      <div class="auth-field"><label class="auth-label" for="birthday">Date of birth *</label><input class="auth-input" type="date" id="birthday" name="birthday" required max="{{ now()->subYears(18)->toDateString() }}" value="{{ old('birthday') }}"></div>
      <div class="auth-field"><label class="auth-label" for="license_expiry">License expiry date *</label><input class="auth-input" type="date" id="license_expiry" name="license_expiry" required min="{{ now()->toDateString() }}" value="{{ old('license_expiry') }}"></div>
      <div class="auth-field"><label class="auth-label" for="vehicle_type">Vehicle type *</label><select class="auth-input" id="vehicle_type" name="vehicle_type" required><option value="">Select vehicle type</option><option value="two_wheels" @selected(old('vehicle_type') === 'two_wheels')>Motorcycle</option><option value="four_wheels" @selected(old('vehicle_type') === 'four_wheels')>Car / Van</option></select></div>
      <div class="auth-field"><label class="auth-label" for="logistics_hub_id">Hub *</label><select class="auth-input" id="logistics_hub_id" name="logistics_hub_id" required><option value="">Select hub</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected(old('logistics_hub_id') == $hub->id)>{{ $hub->municipality }}, {{ $hub->province }}</option>@endforeach</select></div>
      @foreach(['license_file' => "Driver’s license", 'or_cr_file' => 'Vehicle OR/CR'] as $field => $label)
        <div class="auth-field full"><label class="auth-label" for="{{ $field }}">{{ $label }} *</label><input class="auth-input" type="file" id="{{ $field }}" name="{{ $field }}" accept="image/jpeg,image/png,application/pdf" required><span class="field-hint">JPG, PNG or PDF; up to 5 MB.</span></div>
      @endforeach
    </div>
    <button class="auth-btn" type="submit">Save and send invitation</button>
  </form>
  <p><a href="{{ route('logistics.riders') }}">Back to Rider Management</a></p>
</main>
<script>
document.getElementById('inviteRiderForm').addEventListener('submit', function (event) {
  if (this.dataset.submitting) { event.preventDefault(); return; }
  this.dataset.submitting = 'true'; this.querySelector('button[type=submit]').disabled = true;
});
window.addEventListener('pageshow', () => {
  const form = document.getElementById('inviteRiderForm'); delete form.dataset.submitting; form.querySelector('button[type=submit]').disabled = false;
});
</script>
@endsection
