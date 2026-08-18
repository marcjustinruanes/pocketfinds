@extends('buyer.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your profile and settings')

@section('content')
<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Profile Information</h2></div>
      <div class="card-pad">
        @if(session('success'))
        <div class="auth-success" style="margin-bottom:16px">{{ session('success') }}</div>
        @endif
        <form method="POST" action="#">
          @csrf
          <div class="form-grid-2">
            <div class="form-row">
              <label>First Name</label>
              <input type="text" name="first_name" value="{{ auth()->user()->first_name }}">
            </div>
            <div class="form-row">
              <label>Last Name</label>
              <input type="text" name="last_name" value="{{ auth()->user()->last_name }}">
            </div>
            <div class="form-row">
              <label>Email</label>
              <input type="email" value="{{ auth()->user()->email }}" disabled style="background:var(--paper)">
            </div>
            <div class="form-row">
              <label>Contact No.</label>
              <input type="text" name="contact_no" value="{{ auth()->user()->contact_no }}">
            </div>
          </div>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Change Password</h2></div>
      <div class="card-pad">
        <form method="POST" action="#">
          @csrf
          <div class="form-row">
            <label>Current Password</label>
            <input type="password" name="current_password" placeholder="••••••••">
          </div>
          <div class="form-row">
            <label>New Password</label>
            <input type="password" name="password" placeholder="••••••••">
          </div>
          <div class="form-row">
            <label>Confirm New Password</label>
            <input type="password" name="password_confirmation" placeholder="••••••••">
          </div>
          <button class="btn btn-primary" type="submit">Update Password</button>
        </form>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Account Details</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:14px">
        <div>
          <div class="field-label">Account Type</div>
          <div class="field-value"><span class="stamp stamp-approved">Buyer</span></div>
        </div>
        <div>
          <div class="field-label">Status</div>
          <div class="field-value"><span class="stamp stamp-{{ auth()->user()->status }}">{{ ucfirst(auth()->user()->status) }}</span></div>
        </div>
        <div>
          <div class="field-label">Member Since</div>
          <div class="field-value mono">{{ auth()->user()->created_at->format('M d, Y') }}</div>
        </div>
        <div>
          <div class="field-label">Address</div>
          <div class="field-value" style="font-size:13px">
            {{ auth()->user()->barangay }}, {{ auth()->user()->municipality }}, {{ auth()->user()->province }}
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Delivery Address</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        <div class="address-card active-address">
          <div style="font-size:13px;font-weight:600">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</div>
          <div style="font-size:12.5px;color:var(--muted);margin-top:4px">
            {{ auth()->user()->house_no ? auth()->user()->house_no . ', ' : '' }}
            {{ auth()->user()->street ? auth()->user()->street . ', ' : '' }}
            {{ auth()->user()->barangay }}, {{ auth()->user()->municipality }}, {{ auth()->user()->province }}
          </div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">{{ auth()->user()->contact_no }}</div>
          <span class="stamp stamp-approved" style="margin-top:8px;display:inline-flex">Default</span>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
