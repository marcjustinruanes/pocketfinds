@extends('admin.layout')
@section('title', 'Settings')
@section('page-title', 'Platform Settings')
@section('page-sub', 'Manage platform preferences')

@section('content')
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:18px">
  {{ session('success') }}
</div>
@endif

<div data-tab-panel="general">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">
    <div class="card">
      <div class="card-head"><div><h2>General</h2><p>Platform-wide defaults</p></div></div>
      <div class="card-pad">
        <div class="form-row"><label>Platform Name</label><input type="text" value="PocketFinds"></div>
        <div class="form-row"><label>Support Email</label><input type="email" value="support@pocketfinds.com"></div>
        <div class="form-row"><label>Commission Rate (%)</label><input type="number" value="10" min="0" max="100"></div>
        <button class="btn btn-primary" data-toast="Settings saved!">Save Changes</button>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div><h2>Feature Toggles</h2><p>Turn platform features on or off</p></div></div>
      <div class="card-pad">
        <div class="switch-row">
          <div><strong>Google Sign-In</strong><span>Allow users to register via Google</span></div>
          <label class="switch"><input type="checkbox" checked><span class="track"></span></label>
        </div>
        <div class="switch-row">
          <div><strong>New Registrations</strong><span>Accept new account applications</span></div>
          <label class="switch"><input type="checkbox" checked><span class="track"></span></label>
        </div>
        <div class="switch-row">
          <div><strong>Maintenance Mode</strong><span>Take the platform offline</span></div>
          <label class="switch"><input type="checkbox"><span class="track"></span></label>
        </div>
        <div class="switch-row">
          <div><strong>Email Notifications</strong><span>Send system emails to users</span></div>
          <label class="switch"><input type="checkbox" checked><span class="track"></span></label>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div><h2>Danger Zone</h2><p>Irreversible maintenance actions</p></div></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        <button class="btn btn-danger" data-toast="Cache cleared!">Clear Application Cache</button>
        <button class="btn btn-danger" data-toast="Sessions cleared!">Clear All Sessions</button>
      </div>
    </div>
  </div>
</div>

@endsection
