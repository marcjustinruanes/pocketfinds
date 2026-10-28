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
</style>
@endpush

@section('content')
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

<div class="dash-grid">
  <div class="stack">

    {{-- Profile --}}
    <div class="card">
      <div class="card-head"><div><h2>Profile Information</h2><p>Your personal details on file</p></div></div>
      <div class="card-pad">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px">
          @if($user->profile_picture)
          <img src="{{ \Illuminate\Support\Facades\Storage::disk('profile_images')->url($user->profile_picture) }}" alt="Profile picture"
               style="width:64px;height:64px;border-radius:50%;object-fit:cover;flex:none">
          @else
          <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);display:grid;place-items:center;color:#fff;font-weight:700;font-size:22px;flex:none">
            {{ strtoupper(substr($user->given_names, 0, 1)) }}
          </div>
          @endif
          <div>
            <div style="font-size:16px;font-weight:700">{{ $user->given_names }} {{ $user->last_name }}</div>
            <div style="font-size:12px;color:var(--muted)">{{ $user->email }}</div>
            <span class="stamp stamp-approved" style="margin-top:4px">Courier</span>
          </div>
        </div>
        @if(session('profile_success'))
        <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('profile_success') }}</div>
        @endif
        <form method="POST" action="{{ route('rider.account.update') }}" enctype="multipart/form-data" {{ $pendingRequest ? 'style=opacity:.5;pointer-events:none' : '' }}>
          @csrf
          <div class="form-row">
            <label>Profile Picture</label>
            <input type="file" name="profile_picture" accept="image/png,image/jpeg,image/webp" style="padding:6px">
            <span style="font-size:11px;color:var(--muted)">JPG, PNG, or WEBP. Max 2MB.</span>
            @error('profile_picture')<div style="color:var(--danger);font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
          </div>
          <div class="form-grid-2">
            <div class="form-row"><label>Given Names (First Name)</label><input type="text" name="given_names" value="{{ old('given_names', $user->given_names) }}" required></div>
            <div class="form-row"><label>Last Name</label><input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" required></div>
          </div>
          <div class="form-grid-2">
            <div class="form-row">
              <label>Middle Name <span style="color:var(--muted);font-weight:400">(optional)</span></label>
              <input type="text" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}" maxlength="1" pattern="[A-Za-z]" placeholder="e.g. D" style="text-transform:uppercase">
              @error('middle_name')<div style="color:var(--danger);font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
            </div>
            <div class="form-row">
              <label>Contact No.</label>
              <input type="tel" name="contact_no" value="{{ old('contact_no', $user->contact_no) }}" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX">
              @error('contact_no')<div style="color:var(--danger);font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="form-row">
            <label>Sex</label>
            <select name="sex">
              <option value="" {{ blank(old('sex', $user->sex)) ? 'selected' : '' }}>Select sex</option>
              <option value="male" {{ old('sex', $user->sex) === 'male' ? 'selected' : '' }}>Male</option>
              <option value="female" {{ old('sex', $user->sex) === 'female' ? 'selected' : '' }}>Female</option>
              <option value="other" {{ old('sex', $user->sex) === 'other' ? 'selected' : '' }}>Other</option>
            </select>
          </div>
          <div class="form-row"><label>Email Address</label><input type="email" value="{{ $user->email }}" disabled style="background:var(--paper);color:var(--muted);cursor:not-allowed"></div>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </form>
      </div>
    </div>

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
      <div class="card-head"><h2>Account Overview</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column">
        @foreach([
          ['Account Type', 'Courier'],
          ['Logistics Company', $user->business_name ?: 'Not provided'],
          ['Status',       filled($user->status) ? ucfirst($user->status) : 'Not provided'],
          ['Member Since', $user->created_at?->format('M d, Y') ?? 'Not provided'],
          ['Email',        $user->email ?: 'Not provided'],
          ['Contact No.',  $user->contact_no ?: 'Not provided'],
          ['Full Address', collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter()->implode(', ') ?: 'Not provided'],
        ] as [$label, $val])
        <div style="display:flex;justify-content:space-between;gap:10px;font-size:13px;padding:8px 0;border-bottom:1px solid var(--border)">
          <span style="color:var(--muted);flex:none">{{ $label }}</span>
          <span style="font-weight:600;text-align:right;word-break:break-word">{{ $val }}</span>
        </div>
        @endforeach
      </div>
    </div>

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
      </div>
    </div>
  </div>
</div>
@include('account.partials.email-change')
@endsection

@push('scripts')
<script src="{{ asset('js/role-account-details.js') }}?v={{ filemtime(public_path('js/role-account-details.js')) }}"></script>
@endpush
