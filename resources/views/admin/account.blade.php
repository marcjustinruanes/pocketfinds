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
  .profile-photo { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; display: block; }
  .profile-photo-fallback { width:72px;height:72px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);display:grid;place-items:center;color:#fff;font-weight:700;font-size:24px;flex:none }
  .profile-upload { border:1px dashed var(--line); border-radius:8px; padding:12px; background:var(--panel-soft); }
  .profile-upload input[type=file] { width:100%; border:0; padding:0; background:transparent; }
</style>
@endpush

@section('content')
@php($admin = auth()->user())
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

<div class="account-grid">
  <div class="card">
    <div class="card-head"><h2>Account Information</h2></div>
    <div class="card-pad">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
        @if($admin->profile_picture)
          <img class="profile-photo" src="{{ asset('storage/'.$admin->profile_picture) }}" alt="{{ $admin->first_name }} {{ $admin->last_name }}">
        @else
          <div class="profile-photo-fallback">
            {{ strtoupper(substr($admin->first_name, 0, 1)) }}
          </div>
        @endif
        <div>
          <div style="font-weight:700;font-size:15px">{{ $admin->first_name }} {{ $admin->last_name }}</div>
          <div style="font-size:12px;color:var(--muted)">{{ $admin->email }}</div>
          <span class="stamp stamp-active" style="margin-top:4px">{{ ucfirst($admin->account_type) }}</span>
        </div>
      </div>
      <form method="POST" action="{{ route('admin.account.update') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-row">
          <label>Profile Picture <span class="hint">JPG, PNG or WEBP · max 2MB</span></label>
          <div class="profile-upload">
            <input type="file" name="profile_picture" accept="image/png,image/jpeg,image/webp">
          </div>
          @error('profile_picture')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
          <label>First Name</label>
          <input type="text" name="first_name" value="{{ old('first_name', $admin->first_name) }}" required>
          @error('first_name')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
          <label>Last Name</label>
          <input type="text" name="last_name" value="{{ old('last_name', $admin->last_name) }}" required>
          @error('last_name')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
          <label>Email</label>
          <input type="email" name="email" value="{{ old('email', $admin->email) }}" required>
          @error('email')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
          <label>Contact Number</label>
          <input type="text" name="contact_no" value="{{ old('contact_no', $admin->contact_no) }}" maxlength="11" required>
          @error('contact_no')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
        </div>
        <div class="form-grid-2">
          <div class="form-row">
            <label>Province</label>
            <input type="text" name="province" value="{{ old('province', $admin->province) }}" required>
            @error('province')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
          </div>
          <div class="form-row">
            <label>Municipality</label>
            <input type="text" name="municipality" value="{{ old('municipality', $admin->municipality) }}" required>
            @error('municipality')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="form-row">
          <label>Barangay</label>
          <input type="text" name="barangay" value="{{ old('barangay', $admin->barangay) }}" required>
          @error('barangay')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
        </div>
        <div class="form-grid-2">
          <div class="form-row">
            <label>House No. <span class="hint">Optional</span></label>
            <input type="text" name="house_no" value="{{ old('house_no', $admin->house_no) }}">
            @error('house_no')<div class="hint" style="color:var(--danger);margin-top:5px">{{ $message }}</div>@enderror
          </div>
          <div class="form-row">
            <label>Street <span class="hint">Optional</span></label>
            <input type="text" name="street" value="{{ old('street', $admin->street) }}">
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
