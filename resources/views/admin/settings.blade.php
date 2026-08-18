@extends('admin.layout')
@section('title', 'Settings')
@section('page-title', 'Platform Settings')
@section('page-sub', 'Configure platform-wide options')

@section('content')
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
  <div class="card">
    <div class="card-head"><h2>General</h2></div>
    <div class="card-pad">
      <div class="form-row"><label>Platform Name</label><input type="text" value="PocketFinds"></div>
      <div class="form-row"><label>Support Email</label><input type="email" value="support@pocketfinds.com"></div>
      <div class="form-row"><label>Commission Rate (%)</label><input type="number" value="5" min="0" max="100"></div>
      <button class="btn btn-primary" data-toast="Settings saved!">Save Changes</button>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Feature Toggles</h2></div>
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
        <div><strong>Maintenance Mode</strong><span>Take the platform offline for maintenance</span></div>
        <label class="switch"><input type="checkbox"><span class="track"></span></label>
      </div>
      <div class="switch-row">
        <div><strong>Email Notifications</strong><span>Send system emails to users</span></div>
        <label class="switch"><input type="checkbox" checked><span class="track"></span></label>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Registration Requirements</h2></div>
    <div class="card-pad">
      <div class="checklist">
        <label><input type="checkbox" checked> Require government ID for sellers</label>
        <label><input type="checkbox" checked> Require business permit for sellers</label>
        <label><input type="checkbox"> Require ID for buyers</label>
        <label><input type="checkbox" checked> Manual admin approval for sellers</label>
        <label><input type="checkbox"> Manual admin approval for buyers</label>
      </div>
      <button class="btn btn-primary" data-toast="Requirements saved!">Save</button>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Danger Zone</h2></div>
    <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
      <button class="btn btn-danger" data-toast="Cache cleared!">Clear Application Cache</button>
      <button class="btn btn-danger" data-toast="Sessions cleared!">Clear All Sessions</button>
    </div>
  </div>
</div>
@endsection
