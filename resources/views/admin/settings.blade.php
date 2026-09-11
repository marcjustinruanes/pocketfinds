@extends('admin.layout')
@section('title', 'Settings')
@section('page-title', 'Settings')
@section('page-sub', 'Manage platform configuration and your preferences')

@section('content')
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:18px">
  {{ session('success') }}
</div>
@endif

@php
  $me = auth()->user();
  $languages = ['en' => 'English', 'fil' => 'Filipino'];
@endphp

<div class="settings-shell">
  <nav class="settings-nav" id="settingsNav">
    <a class="settings-nav-item active" data-settings-tab="general"><span class="ic"><x-admin-icon name="settings" /></span> General</a>
    <a class="settings-nav-item" data-settings-tab="policies"><span class="ic"><x-admin-icon name="file" /></span> Platform Policies</a>
    <a class="settings-nav-item" data-settings-tab="appearance"><span class="ic"><x-admin-icon name="chart" /></span> Appearance</a>
    <a class="settings-nav-item" data-settings-tab="danger"><span class="ic"><x-admin-icon name="flag" /></span> Danger Zone</a>
  </nav>

  <div>
    {{-- ── General ── --}}
    <div data-settings-panel="general">
      <h2 class="settings-section-title"><span class="ic"><x-admin-icon name="settings" /></span> General</h2>
      <p class="settings-section-sub">Platform-wide identity and defaults.</p>

      <div class="card" style="margin-bottom:16px">
        <div class="card-pad">
          <form method="POST" action="{{ route('admin.settings.general.update') }}">
            @csrf
            <div class="form-row"><label>Platform Name</label><input type="text" name="platform_name" value="{{ old('platform_name', $setting->platform_name) }}" required></div>
            <div class="form-row"><label>Support Email</label><input type="email" name="support_email" value="{{ old('support_email', $setting->support_email) }}" required></div>
            <div class="form-row"><label>Commission Rate (%)</label><input type="number" name="commission_rate" value="{{ old('commission_rate', $setting->commission_rate) }}" min="0" max="100" step="0.01" required></div>
            @if($setting->editor)
              <p style="font-size:11.5px;color:var(--muted);margin:0 0 12px">Last updated by {{ $setting->editor->given_names }} {{ $setting->editor->last_name }} · {{ $setting->updated_at->diffForHumans() }}</p>
            @endif
            <button class="btn btn-primary" type="submit">Save Changes</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><div><h2>Feature Toggles</h2><p>Turn platform features on or off</p></div></div>
        <div class="card-pad">
          <form method="POST" action="{{ route('admin.settings.toggles.update') }}">
            @csrf
            <div class="switch-row">
              <div><strong>Google Sign-In</strong><span>Allow users to register via Google</span></div>
              <label class="switch"><input type="checkbox" name="google_signin_enabled" value="1" {{ $setting->google_signin_enabled ? 'checked' : '' }}><span class="track"></span></label>
            </div>
            <div class="switch-row">
              <div><strong>New Registrations</strong><span>Accept new account applications</span></div>
              <label class="switch"><input type="checkbox" name="new_registrations_enabled" value="1" {{ $setting->new_registrations_enabled ? 'checked' : '' }}><span class="track"></span></label>
            </div>
            <div class="switch-row">
              <div><strong>Maintenance Mode</strong><span>Take the platform offline</span></div>
              <label class="switch"><input type="checkbox" name="maintenance_mode" value="1" {{ $setting->maintenance_mode ? 'checked' : '' }}><span class="track"></span></label>
            </div>
            <div class="switch-row">
              <div><strong>Email Notifications</strong><span>Send system emails to users</span></div>
              <label class="switch"><input type="checkbox" name="email_notifications_enabled" value="1" {{ $setting->email_notifications_enabled ? 'checked' : '' }}><span class="track"></span></label>
            </div>
            <div style="margin-top:14px"><button class="btn btn-primary" type="submit">Save Toggles</button></div>
          </form>
        </div>
      </div>
    </div>

    {{-- ── Platform Policies ── --}}
    {{-- Terms & Conditions editing lives on its own dedicated Policies page — it needs
         room for per-role documents, revision history, and logistics companies' own
         submitted documents with an approval queue. This panel just points there. --}}
    <div data-settings-panel="policies" style="display:none">
      <h2 class="settings-section-title"><span class="ic"><x-admin-icon name="file" /></span> Platform Policies</h2>
      <p class="settings-section-sub">Terms & Conditions and other policy documents shown to users.</p>

      <div class="card">
        <div class="card-pad" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 4px;font-size:14px">Terms & Conditions</h2>
            <p style="margin:0;font-size:12.5px;color:var(--muted)">Edit each role's document, review revision history, and approve logistics companies' own submitted policies.</p>
          </div>
          <a href="{{ route('admin.policies') }}" class="btn btn-primary">Open Policies Page</a>
        </div>
      </div>
    </div>

    {{-- ── Appearance ── --}}
    <div data-settings-panel="appearance" style="display:none">
      <h2 class="settings-section-title"><span class="ic"><x-admin-icon name="chart" /></span> Appearance</h2>
      <p class="settings-section-sub">Your personal display and language preferences — only affects your own account.</p>

      <div class="card">
        <div class="card-pad">
          <form method="POST" action="{{ route('admin.settings.preferences.update') }}" id="preferencesForm">
            @csrf
            <div class="form-row">
              <label>Theme</label>
              <div class="theme-picker">
                <label class="theme-option">
                  <input type="radio" name="theme" value="light" {{ $me->theme === 'light' ? 'checked' : '' }}>
                  <div class="theme-swatch theme-swatch-light">
                    <div class="theme-swatch-preview"><span class="side"></span><span class="main"></span></div>
                    <div class="theme-swatch-label">Light <span class="check">✓</span></div>
                  </div>
                </label>
                <label class="theme-option">
                  <input type="radio" name="theme" value="dark" {{ $me->theme === 'dark' ? 'checked' : '' }}>
                  <div class="theme-swatch theme-swatch-dark">
                    <div class="theme-swatch-preview"><span class="side"></span><span class="main"></span></div>
                    <div class="theme-swatch-label">Dark <span class="check">✓</span></div>
                  </div>
                </label>
                <label class="theme-option">
                  <input type="radio" name="theme" value="system" {{ $me->theme === 'system' || !$me->theme ? 'checked' : '' }}>
                  <div class="theme-swatch theme-swatch-system">
                    <div class="theme-swatch-preview"><span class="side"></span><span class="main"></span></div>
                    <div class="theme-swatch-label">System <span class="check">✓</span></div>
                  </div>
                </label>
              </div>
            </div>

            <div class="form-row" style="margin-top:16px">
              <label>Language</label>
              <select name="preferred_language">
                @foreach($languages as $code => $label)
                <option value="{{ $code }}" {{ $me->preferred_language === $code ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
              <span class="hint">Saved to your account now — full interface translation is on the roadmap.</span>
            </div>

            <div style="margin-top:16px"><button class="btn btn-primary" type="submit">Save Preferences</button></div>
          </form>
        </div>
      </div>
    </div>

    {{-- ── Danger Zone ── --}}
    <div data-settings-panel="danger" style="display:none">
      <h2 class="settings-section-title"><span class="ic"><x-admin-icon name="flag" /></span> Danger Zone</h2>
      <p class="settings-section-sub">Irreversible or platform-wide maintenance actions.</p>

      <div class="card">
        <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
          <form method="POST" action="{{ route('admin.settings.cache.clear') }}">
            @csrf
            <button class="btn btn-danger" type="submit" style="width:100%">Clear Application Cache</button>
          </form>
          <form method="POST" action="{{ route('admin.settings.sessions.clear') }}" onsubmit="return confirm('This signs out every other logged-in user immediately. Continue?')">
            @csrf
            <button class="btn btn-danger" type="submit" style="width:100%">Clear All Other Sessions</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('#settingsNav [data-settings-tab]').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('#settingsNav [data-settings-tab]').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    document.querySelectorAll('[data-settings-panel]').forEach(p => p.style.display = 'none');
    document.querySelector('[data-settings-panel="' + tab.dataset.settingsTab + '"]').style.display = '';
  });
});

// Live preview: apply the chosen theme instantly, before the form even saves.
document.querySelectorAll('#preferencesForm input[name="theme"]').forEach(input => {
  input.addEventListener('change', () => {
    if (input.value === 'system') {
      document.documentElement.removeAttribute('data-theme');
    } else {
      document.documentElement.setAttribute('data-theme', input.value);
    }
  });
});
</script>

@endsection
