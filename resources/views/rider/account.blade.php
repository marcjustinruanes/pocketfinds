@extends('rider.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your profile, address, and password')

@push('head')
<style>
  .addr-combo{position:relative}
  .addr-combo input{width:100%}
  .addr-combo-list{position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:220px;overflow-y:auto;background:var(--surface,#fff);border:1px solid var(--border);border-radius:9px;box-shadow:0 8px 24px rgba(27,22,32,.12);z-index:20}
  .addr-combo-option{padding:9px 12px;font-size:13px;cursor:pointer}
  .addr-combo-option:hover,.addr-combo-option.active{background:var(--paper)}
  .addr-combo-empty{padding:9px 12px;font-size:12.5px;color:var(--muted)}
  .rider-account-page>.stack>.card{overflow:hidden;border:1px solid var(--border);border-radius:12px;background:#fff;box-shadow:0 2px 8px rgba(42,28,40,.035)}
  .rider-account-page>.stack>.card .card-head{min-height:52px;padding:14px 17px;border-bottom:1px solid var(--border)}
  .rider-account-page>.stack>.card .card-head h2{color:var(--text);font-size:13px;font-weight:700}
  .rider-account-page>.stack>.card .card-pad{padding:16px 17px}
  .rider-account-page form .form-row{gap:6px;margin-bottom:12px}
  .rider-account-page form .form-row>label{color:var(--text);font-size:10.5px;font-weight:700}
  .rider-account-page form :is(input:not([type=file]),select,textarea){min-height:38px;border:1px solid var(--border);border-radius:8px;background:#fff}
  .rider-account-page form input[type=file]{border:1px solid var(--border);border-radius:8px;background:#fff}
  @media(max-width:600px){.rider-account-page>.stack>.card :is(.card-head,.card-pad){padding:13px}.rider-account-page form .form-grid-2{grid-template-columns:minmax(0,1fr)}}
</style>
@endpush

@include('account.partials.role-workspace-assets')

@section('content')
<x-account-workspace role="rider">
@include('commerce.messages')
@php($user = auth()->user())

@if($pendingRequest)
<div style="background:var(--warning-soft);border:1px solid var(--warning-line);color:var(--warning);padding:12px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">
  <div style="font-weight:700;margin-bottom:2px">Update Request Pending</div>
  <div>Your requested changes are awaiting admin review — the forms below are locked until this request is resolved.</div>
</div>
@elseif($lastRequest && $lastRequest->status === 'rejected')
<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:12px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">
  <div style="font-weight:700;margin-bottom:2px">Last Request Rejected</div>
  <div>{{ $lastRequest->note ?? 'Your previous update request was rejected. You may submit a new one.' }}</div>
</div>
@endif

<div class="dash-grid role-account-content">
  <div class="stack">

    <x-account-profile-card role="rider" :action="route('rider.account.update')" :pending="$pendingRequest !== null" />

    {{-- Address --}}
    <div class="card">
      <div class="card-head"><div><h2>Address Information</h2><p>Where you're based</p></div></div>
      <div class="card-pad">
        @if(session('address_success'))
        <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('address_success') }}</div>
        @endif
        @error('province')
        <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ $message }}</div>
        @enderror
        <form method="POST" action="{{ route('rider.account.address') }}" id="addressForm" {{ $pendingRequest ? 'style=opacity:.5;pointer-events:none' : '' }}>
          @csrf
@include('account.partials.address-region')
          <div class="form-row">
            <label>Province</label>
            <div class="addr-combo">
              <input type="text" name="province" id="province_input" autocomplete="off" required
                     value="{{ old('province', $user->province) }}" placeholder="Type to search a province…">
              <div class="addr-combo-list" id="province_list" hidden></div>
            </div>
          </div>
          <div class="form-row">
            <label>Municipality / City</label>
            <div class="addr-combo">
              <input type="text" name="municipality" id="municipality_input" autocomplete="off" required
                     value="{{ old('municipality', $user->municipality) }}" placeholder="Type to search a city / municipality…">
              <div class="addr-combo-list" id="municipality_list" hidden></div>
            </div>
          </div>
          <div class="form-row">
            <label>Barangay</label>
            <div class="addr-combo">
              <input type="text" name="barangay" id="barangay_input" autocomplete="off" required
                     value="{{ old('barangay', $user->barangay) }}" placeholder="Type to search a barangay…">
              <div class="addr-combo-list" id="barangay_list" hidden></div>
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-row"><label>House No. / Unit</label><input type="text" name="house_no" value="{{ old('house_no', $user->house_no) }}" placeholder="e.g. 123"></div>
            <div class="form-row"><label>Street</label><input type="text" name="street" value="{{ old('street', $user->street) }}" placeholder="e.g. Rizal St."></div>
          </div>
          <button class="btn btn-primary" type="submit">Update Address</button>
        </form>
      </div>
    </div>

    {{-- Password --}}
    <div class="card">
      <div class="card-head"><h2>Change Password</h2></div>
      <div class="card-pad">
        @if(session('password_success'))
        <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('password_success') }}</div>
        @endif
        @error('current_password')
        <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ $message }}</div>
        @enderror
        <form method="POST" action="{{ route('rider.account.password') }}">
          @csrf
          <div class="form-row"><label>Current Password</label><input type="password" name="current_password" required></div>
          <div class="form-row"><label>New Password</label><input type="password" name="password" required></div>
          <div class="form-row"><label>Confirm New Password</label><input type="password" name="password_confirmation" required></div>
          <button class="btn btn-primary" type="submit">Update Password</button>
        </form>
      </div>
    </div>

  </div>

  <div class="stack">
    

    <div class="card">
      <div class="card-head"><h2>Vehicle & Documents</h2><p style="font-size:11.5px;color:var(--muted)">On file from your registration</p></div>
      <div class="card-pad" style="display:flex;flex-direction:column">
        @foreach(array_filter([
          ['Vehicle Type', $user->vehicle_type ? \Illuminate\Support\Str::title(str_replace('_', ' ', $user->vehicle_type)) : null],
          ['Ownership', $user->vehicle_ownership === 'own' ? 'Own Vehicle' : ($user->vehicle_ownership === 'company' ? 'Company Vehicle' : null)],
          ['Vehicle', $user->vehicle_ownership === 'own' ? (collect([$user->vehicle_brand, $user->vehicle_model])->filter()->implode(' ') ?: null) : null],
          ['Plate Number', $user->vehicle_ownership === 'own' ? $user->plate_number : null],
          ['License Number', $user->license_number],
          ['License Expiry', $user->license_expiry?->format('M d, Y')],
        ]) as [$label, $val])
        <div style="display:flex;justify-content:space-between;gap:10px;font-size:13px;padding:8px 0;border-bottom:1px solid var(--border)">
          <span style="color:var(--muted);flex:none">{{ $label }}</span>
          <span style="font-weight:600;text-align:right;word-break:break-word">{{ $val ?: 'Not provided' }}</span>
        </div>
        @endforeach
        @if($user->nbi_clearance_file)
          <a href="{{ app(\App\Services\SensitiveDocuments::class)->url($user->nbi_clearance_file) }}" target="_blank" rel="noopener" style="font-size:13px;padding:8px 0">View NBI clearance</a>
        @endif
        @if(session('vehicle_success'))<div style="margin-top:12px;color:var(--success);font-size:13px">{{ session('vehicle_success') }}</div>@endif
        <form method="POST" action="{{ route('rider.account.vehicle') }}" enctype="multipart/form-data" style="margin-top:14px" {{ $pendingRequest ? 'style=opacity:.5;pointer-events:none' : '' }}>
          @csrf
          <div class="form-grid-2"><div class="form-row"><label>Vehicle type</label><select name="vehicle_type" required><option value="two_wheels" @selected($user->vehicle_type === 'two_wheels')>Two wheels</option><option value="four_wheels" @selected($user->vehicle_type === 'four_wheels')>Four wheels</option></select></div><div class="form-row"><label>Ownership</label><select name="vehicle_ownership" required><option value="own" @selected($user->vehicle_ownership === 'own')>Own vehicle</option><option value="company" @selected($user->vehicle_ownership === 'company')>Company vehicle</option></select></div></div>
          <div class="form-grid-2"><div class="form-row"><label>Brand</label><input name="vehicle_brand" value="{{ old('vehicle_brand', $user->vehicle_brand) }}"></div><div class="form-row"><label>Model</label><input name="vehicle_model" value="{{ old('vehicle_model', $user->vehicle_model) }}"></div></div>
          <div class="form-row"><label>Plate number</label><input name="plate_number" value="{{ old('plate_number', $user->plate_number) }}"></div>
          <div class="form-grid-2"><div class="form-row"><label>License number</label><input name="license_number" value="{{ old('license_number', $user->license_number) }}" required></div><div class="form-row"><label>License expiry</label><input type="date" name="license_expiry" value="{{ old('license_expiry', $user->license_expiry?->format('Y-m-d')) }}" required></div></div>
          <div class="form-row"><label>Updated documents</label><input type="file" name="license_file" accept="image/jpeg,image/png,application/pdf"><input type="file" name="or_file" accept="image/jpeg,image/png,application/pdf" style="margin-top:7px"><input type="file" name="cr_file" accept="image/jpeg,image/png,application/pdf" style="margin-top:7px"><span style="font-size:11px;color:var(--muted)">License, OR, and CR: JPG, PNG, or PDF, maximum 5 MB each.</span></div>
          <button class="btn btn-outline" type="submit">Request vehicle update</button>
        </form>
      </div>
    </div>
  </div>
</div>
@include('account.partials.email-change')
</x-account-workspace>
@include('account.partials.profile-photo-manager')
@endsection

@push('scripts')
<script src="{{ asset('js/role-account-details.js') }}?v={{ filemtime(public_path('js/role-account-details.js')) }}"></script>
@endpush
