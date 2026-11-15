<div class="card" data-account-panel="address-information" id="address-information">
  <div class="card-head"><div><h2>Registered address</h2><p>Keep your account address up to date.</p></div></div>
  <div class="card-pad">
    <form method="POST" action="{{ route('admin.account.update') }}">
      @csrf
      @foreach(['given_names','last_name','email','contact_no'] as $field)<input type="hidden" name="{{ $field }}" value="{{ auth()->user()->$field }}">@endforeach
      @include('account.partials.address-region')
      <div class="form-grid-2">
        @foreach(['province'=>'Province','municipality'=>'Municipality','barangay'=>'Barangay','house_no'=>'House No. / Unit','street'=>'Street'] as $field=>$label)
          <div class="form-row"><label for="adminAddress{{ ucfirst($field) }}">{{ $label }}</label><input id="adminAddress{{ ucfirst($field) }}" name="{{ $field }}" value="{{ old($field, auth()->user()->$field) }}" @required(in_array($field, ['province','municipality','barangay']))>@error($field)<small class="profile-field-error">{{ $message }}</small>@enderror</div>
        @endforeach
      </div>
      <button class="btn btn-primary" type="submit">Update address</button>
    </form>
  </div>
</div>
