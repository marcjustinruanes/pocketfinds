@extends('admin.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your admin profile')

@include('account.partials.role-workspace-assets')

@section('content')
<x-account-workspace role="admin">
@include('commerce.messages')
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

<div class="account-grid role-account-content">
  <x-account-profile-card role="admin" :action="route('admin.account.update')" />
  @include('account.partials.admin-address')

  <div class="stack">
    <div class="card">
      <div class="card-head"><div><h2>Change Password</h2><p>Update your login credentials</p></div></div>
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
</div>
@include('account.partials.email-change')
</x-account-workspace>
@include('account.partials.profile-photo-manager')
@endsection

@push('scripts')<script src="{{ asset('js/role-account-details.js') }}?v={{ filemtime(public_path('js/role-account-details.js')) }}"></script>@endpush
