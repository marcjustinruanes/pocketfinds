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

{{-- Terms & Conditions now lives on its own Policies page (see the sidebar) — it
     needed room for logistics companies' own documents and their approval queue. --}}
<div data-tab-panel="general" class="active">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">
    <div class="card">
      <div class="card-head"><div><h2>General</h2><p>Platform-wide defaults</p></div></div>
      <div class="card-pad">
        <form method="POST" action="{{ route('admin.settings.update') }}">
          @csrf
          <div class="form-row"><label>Platform Name</label><input type="text" name="platform_name" value="{{ old('platform_name', $settings->platform_name) }}" required></div>
          <div class="form-row"><label>Support Email</label><input type="email" name="support_email" value="{{ old('support_email', $settings->support_email) }}" required></div>
          <div class="form-row"><label>Commission Rate (%)</label><input type="number" name="commission_rate" value="{{ old('commission_rate', $settings->commission_rate) }}" min="0" max="100" step="0.01" required></div>
          @if($settings->editor)
            <p style="font-size:11.5px;color:var(--muted);margin:0 0 12px">Last updated by {{ $settings->editor->given_names }} {{ $settings->editor->last_name }} · {{ $settings->updated_at->diffForHumans() }}</p>
          @endif
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div><h2>Feature Toggles</h2><p>Turn platform features on or off — saved immediately</p></div></div>
      <div class="card-pad">
        <div class="switch-row">
          <div><strong>Google Sign-In</strong><span>Allow users to register via Google</span></div>
          <label class="switch"><input type="checkbox" data-setting-toggle="google_signin_enabled" {{ $settings->google_signin_enabled ? 'checked' : '' }}><span class="track"></span></label>
        </div>
        <div class="switch-row">
          <div><strong>New Registrations</strong><span>Accept new account applications</span></div>
          <label class="switch"><input type="checkbox" data-setting-toggle="new_registrations_enabled" {{ $settings->new_registrations_enabled ? 'checked' : '' }}><span class="track"></span></label>
        </div>
        <div class="switch-row">
          <div><strong>Maintenance Mode</strong><span>Take the platform offline</span></div>
          <label class="switch"><input type="checkbox" data-setting-toggle="maintenance_mode" {{ $settings->maintenance_mode ? 'checked' : '' }}><span class="track"></span></label>
        </div>
        <div class="switch-row">
          <div><strong>Email Notifications</strong><span>Send system emails to users</span></div>
          <label class="switch"><input type="checkbox" data-setting-toggle="email_notifications_enabled" {{ $settings->email_notifications_enabled ? 'checked' : '' }}><span class="track"></span></label>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div><h2>Danger Zone</h2><p>Irreversible maintenance actions</p></div></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        <button class="btn btn-danger" data-toast="Cache cleared!">Clear Application Cache</button>
        <button class="btn btn-danger" data-toast="Sessions cleared!">Clear All Sessions</button>
        <p style="font-size:11px;color:var(--muted);margin:4px 0 0">These two are placeholders — no live cache/session store is wired up on this server yet.</p>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('[data-setting-toggle]').forEach(function (input) {
  input.addEventListener('change', function () {
    fetch('{{ route('admin.settings.toggle') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
      body: JSON.stringify({ key: input.dataset.settingToggle, value: input.checked }),
    })
    .then(r => r.json())
    .then(data => {
      if (!data.success) { input.checked = !input.checked; }
    })
    .catch(() => { input.checked = !input.checked; });
  });
});
</script>

@endsection
