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
    @include('buyer.partials.email-change', ['emailRole'=>'admin'])
    @include('account.partials.login-security', ['securityRole'=>'admin'])

    
  </div>
</div>

</x-account-workspace>
@include('account.partials.profile-photo-manager')
@endsection

@push('scripts')<script src="{{ asset('js/role-account-details.js') }}?v={{ filemtime(public_path('js/role-account-details.js')) }}"></script>@endpush
