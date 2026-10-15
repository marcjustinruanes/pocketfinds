@php($editing = isset($account) && $account)
@php($formRole = $editing ? ($account->account_type === 'rider' ? 'rider' : 'hub_staff') : ($creationRole ?? null))
<div class="logistics-form-grid">
@if(!$editing)
@if($formRole)
<input type="hidden" name="role" value="{{ $formRole }}">
<p>Account type: <strong>{{ $formRole === 'rider' ? 'Rider' : 'Hub staff' }}</strong></p>
@else
<label>Role<select name="role" required><option value="hub_staff" @selected(old('role') === 'hub_staff')>Hub staff</option><option value="rider" @selected(old('role') === 'rider')>Rider</option></select></label>
@endif
@endif
<label>Hub<select name="logistics_hub_id" required><option value="">Select an operational hub</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected((string)old('logistics_hub_id',$account->logistics_hub_id ?? '') === (string)$hub->id)>{{ $hub->municipality }}, {{ $hub->province }}</option>@endforeach</select></label>
@foreach(['username'=>'Username','email'=>'Email','given_names'=>'Given names','last_name'=>'Last name','contact_no'=>'Contact number (11 digits)','barangay'=>'Barangay'] as $field=>$label)
<label>{{ $label }}<input name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ old($field,$account?->{$field} ?? '') }}" required maxlength="{{ $field === 'contact_no' ? 11 : 255 }}"></label>
@endforeach
<label>Sex<select name="sex" required>@foreach(['male','female'] as $sex)<option value="{{ $sex }}" @selected(old('sex',$account->sex ?? '') === $sex)>{{ ucfirst($sex) }}</option>@endforeach</select></label>
<label>Birthday<input type="date" name="birthday" required value="{{ old('birthday',$account?->birthday?->format('Y-m-d') ?? '') }}"></label>
<p>Status: <strong>{{ ucfirst($account->status ?? 'pending') }}</strong>. Recruitment decisions are made in Rider Management or Hub Staff. New accounts start pending.</p>
@if($formRole !== 'hub_staff')
<label>Vehicle type (riders only)<select name="vehicle_type" @required($formRole === 'rider')><option value="">Select vehicle type</option>@foreach(['two_wheels','four_wheels'] as $type)<option value="{{ $type }}" @selected(old('vehicle_type',$account->vehicle_type ?? '') === $type)>{{ ucfirst(str_replace('_',' ',$type)) }}</option>@endforeach</select></label>
<label>Vehicle ownership (riders only)<select name="vehicle_ownership" @required($formRole === 'rider')><option value="">Select ownership</option>@foreach(['own','company'] as $ownership)<option value="{{ $ownership }}" @selected(old('vehicle_ownership',$account->vehicle_ownership ?? '') === $ownership)>{{ ucfirst($ownership) }}</option>@endforeach</select></label>
@endif
<label>{{ $editing ? 'New password (optional)' : 'Password' }}<input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="128" @required(!$editing)></label>
<label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" @required(!$editing)></label>
</div>
<p class="logistics-note">Passwords must be at least 12 characters. Riders require a vehicle type and ownership setting. Only riders and hub staff in your company can be managed here.</p>
<button type="submit" class="btn btn-primary">{{ $editing ? 'Save account' : ($formRole === 'rider' ? 'Create rider' : ($formRole === 'hub_staff' ? 'Create hub staff' : 'Create account')) }}</button>
