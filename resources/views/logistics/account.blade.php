@extends('logistics.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your profile')

@section('content')
@php($user = auth()->user())
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start">
  <div class="card">
    <div class="card-head"><h2>Profile</h2></div>
    <div class="card-pad">
      <form method="POST" action="{{ route('logistics.account.update') }}">
        @csrf
        <div class="form-row"><label>First Name</label><input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" required></div>
        <div class="form-row"><label>Last Name</label><input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" required></div>
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
      <form method="POST" action="{{ route('logistics.account.password') }}">
        @csrf
        <div class="form-row"><label>Current Password</label><input type="password" name="current_password" required></div>
        <div class="form-row"><label>New Password</label><input type="password" name="password" required></div>
        <div class="form-row"><label>Confirm New Password</label><input type="password" name="password_confirmation" required></div>
        <button class="btn btn-primary" type="submit">Update Password</button>
      </form>
    </div>
  </div>
</div>
@endsection
