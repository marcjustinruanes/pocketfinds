@extends('buyer.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your profile and settings')

@section('content')
@if($errors->any())
<div class="auth-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
@endif
@if(session('success'))
<div class="auth-success" style="margin-bottom:16px">{{ session('success') }}</div>
@endif

{{-- Profile summary --}}
<div class="profile-summary">
  <x-user-avatar :user="auth()->user()" size="64" class="profile-summary-avatar" />
  <div class="profile-summary-info">
    <h1>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</h1>
    <p>{{ auth()->user()->email }}</p>
    <div class="profile-summary-badges">
      <span class="stamp stamp-active">Buyer</span>
      <span class="profile-summary-since">Member since {{ auth()->user()->created_at->format('M Y') }}</span>
    </div>
  </div>
</div>

<div class="dash-grid">
  <div class="stack">
    @if($pendingRequest)
    <div class="card">
      <div class="card-pad" style="background:var(--warning-soft);border:1px solid var(--warning-line);color:var(--warning);border-radius:9px;font-size:13px;display:flex;align-items:flex-start;gap:10px">
        <div>
          <div style="font-weight:700;margin-bottom:2px">Update Request Pending</div>
          <div>Your requested changes are awaiting admin review and won't appear on your account until approved.</div>
        </div>
      </div>
    </div>
    @elseif($lastRequest && $lastRequest->status === 'rejected')
    <div class="card">
      <div class="card-pad" style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);border-radius:9px;font-size:13px">
        <div style="font-weight:700;margin-bottom:2px">Last Request Rejected</div>
        <div>{{ $lastRequest->note ?? 'Your previous update request was rejected. You may submit a new one.' }}</div>
      </div>
    </div>
    @endif

    <div class="card">
      <div class="card-head"><div><h2>Profile Information</h2><p>Update your name and profile photo</p></div></div>
      <div class="card-pad">
        @if(session('profile_success'))
        <div class="auth-success" style="margin-bottom:16px">{{ session('profile_success') }}</div>
        @endif
        <form method="POST" action="{{ route('buyer.account.profile') }}" enctype="multipart/form-data">
          @csrf
          <div class="form-grid-2">
            <div class="form-row">
              <label>First Name</label>
              <input type="text" name="given_names" value="{{ old('given_names', auth()->user()->given_names) }}" minlength="2" maxlength="50" required>
            </div>
            <div class="form-row">
              <label>Last Name</label>
              <input type="text" name="last_name" value="{{ old('last_name', auth()->user()->last_name) }}" minlength="2" maxlength="50" required>
            </div>
            <div class="form-row">
              <label>Email</label>
              <input type="email" value="{{ auth()->user()->email }}" disabled style="background:var(--paper)">
            </div>
            <div class="form-row">
              <label>Contact No.</label>
              <input type="text" value="{{ auth()->user()->contact_no }}" disabled>
            </div>
            <div class="form-row"><label>Profile photo (JPG or PNG, up to 5 MB)</label><input type="file" name="profile_picture" accept="image/jpeg,image/png"></div>
          </div>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Change email or phone</h2></div>
      <div class="card-pad">
        @if(session('contact_success'))<p class="auth-success" role="status">{{ session('contact_success') }}</p>@endif
        @foreach(['email' => 'Email address', 'contact_no' => 'Phone number'] as $field => $label)
          <form method="POST" action="{{ route('buyer.account.contact.send', $field) }}">
            @csrf
            <div class="form-row"><label for="change-{{ $field }}">New {{ strtolower($label) }}</label><input id="change-{{ $field }}" name="value" type="{{ $field === 'email' ? 'email' : 'tel' }}" required></div>
            <button class="btn btn-outline" type="submit">Send {{ $field === 'email' ? 'email' : 'phone' }} verification code</button>
          </form>
          <p style="font-size:12px">{{ $field === 'email' ? 'The code is sent to your new email.' : 'Confirm the phone change using a code sent to your registered email.' }} Codes expire after 5 minutes.</p>
          <form method="POST" action="{{ route('buyer.account.contact.verify', $field) }}">
            @csrf
            <div class="form-row"><label for="verify-{{ $field }}">{{ $label }} verification code</label><input id="verify-{{ $field }}" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required></div>
            <button class="btn btn-primary" type="submit">Verify {{ $field === 'email' ? 'email' : 'phone' }} change</button>
          </form>
          <hr>
        @endforeach