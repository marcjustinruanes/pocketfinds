@extends('admin.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your admin profile')

@push('head')
<style>
  .card-head h2, .card-head h3 { font-family: var(--font-body); font-weight: 600; font-size: 14px; }
  .form-row label { font-family: var(--font-body); font-size: 13px; }
  .form-row input { font-family: var(--font-body); font-size: 13px; }
  .stamp { font-family: var(--font-body); font-size: 11px; letter-spacing: 0; text-transform: none; }
</style>
@endpush

@section('content')
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">
  {{ session('success') }}
</div>
@endif
@if($errors->any())
<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">
  Please check the highlighted account details and try again.
</div>
@endif

<div style="display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:18px;max-width:980px">
  <div class="card">
    <div class="card-head"><h2>Account Information</h2></div>
    <div class="card-pad">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
        <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);display:grid;place-items:center;color:#fff;font-weight:700;font-size:20px">
          {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
        </div>
        <div>
          <div style="font-weight:700;font-size:15px">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</div>
          <div style="font-size:12px;color:var(--muted)">{{ auth()->user()->email }}</div>
          <span class="stamp stamp-active" style="margin-top:4px">{{ ucfirst(auth()->user()->account_type) }}</span>
        </div>
      </div>
      <form method="POST" action="{{ route('admin.account.update') }}">
        @csrf
        <div class="form-row">
          <label>First Name</label>
          <input type="text" name="first_name" value="{{ old('first_name', auth()->user()->first_name) }}" required>
          @error('first_name')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
          <label>Last Name</label>
          <input type="text" name="last_name" value="{{ old('last_name', auth()->user()->last_name) }}" required>
          @error('last_name')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
          <label>Email</label>
          <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required>
          @error('email')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
          <label>Contact Number</label>
          <input type="text" name="contact_no" value="{{ old('contact_no', auth()->user()->contact_no) }}" maxlength="11" required>
          @error('contact_no')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
        </div>
        <div class="form-grid-2">
          <div class="form-row">
            <label>Province</label>
            <input type="text" name="province" value="{{ old('province', auth()->user()->province) }}" required>
            @error('province')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
          </div>
          <div class="form-row">
            <label>Municipality</label>
            <input type="text" name="municipality" value="{{ old('municipality', auth()->user()->municipality) }}" required>
            @error('municipality')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="form-row">
          <label>Barangay</label>
          <input type="text" name="barangay" value="{{ old('barangay', auth()->user()->barangay) }}" required>
          @error('barangay')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
        </div>
        <div class="form-grid-2">
          <div class="form-row">
            <label>House No. <span class="hint">Optional</span></label>
            <input type="text" name="house_no" value="{{ old('house_no', auth()->user()->house_no) }}">
            @error('house_no')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
          </div>
          <div class="form-row">
            <label>Street <span class="hint">Optional</span></label>
            <input type="text" name="street" value="{{ old('street', auth()->user()->street) }}">
            @error('street')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
          </div>
        </div>
        <button class="btn btn-primary" type="submit">Save Changes</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Change Password</h2></div>
    <div class="card-pad">
      @error('current_password')
      <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ $message }}</div>
      @enderror
      <form method="POST" action="{{ route('admin.account.password') }}">
        @csrf
        <div class="form-row">
          <label>Current Password</label>
          <input type="password" name="current_password" required>
        </div>
        <div class="form-row">
          <label>New Password</label>
          <input type="password" name="password" required>
          @error('password')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
          <label>Confirm New Password</label>
          <input type="password" name="password_confirmation" required>
        </div>
        <button class="btn btn-primary" type="submit">Update Password</button>
      </form>
    </div>
  </div>
</div>
@endsection
