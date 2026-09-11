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
    <div data-settings-panel="policies" style="display:none">
      <h2 class="settings-section-title"><span class="ic"><x-admin-icon name="file" /></span> Platform Policies</h2>
      <p class="settings-section-sub">Terms, privacy, and other policy documents shown to users.</p>

      <div class="dash-grid">
        <div class="card">
          <div class="card-head"><div><h2>Add New Policy</h2><p>Create a platform policy document</p></div></div>
          <div class="card-pad">
            <form method="POST" action="{{ route('admin.settings.policies.store') }}">
              @csrf
              <div class="form-row">
                <label>Policy Title</label>
                <input type="text" name="title" placeholder="e.g. Terms of Service" required value="{{ old('title') }}">
              </div>
              <div class="form-row">
                <label>Slug <span class="hint">(unique identifier, e.g. terms-of-service)</span></label>
                <input type="text" name="slug" placeholder="terms-of-service" required value="{{ old('slug') }}">
              </div>
              <div class="form-row">
                <label>Content</label>
                <textarea name="content" rows="6" placeholder="Write the policy content here..." required>{{ old('content') }}</textarea>
              </div>
              <button class="btn btn-primary" type="submit">Save Policy</button>
            </form>
          </div>
        </div>

        <div class="stack">
          @forelse($policies as $policy)
          <div class="card">
            <div class="card-head">
              <div><h2>{{ $policy->title }}</h2><p>Last updated {{ $policy->updated_at?->format('Y-m-d') }}</p></div>
              <div style="display:flex;gap:8px">
                <button class="btn btn-sm btn-outline" data-modal-open="policyModal-{{ $policy->id }}">Edit</button>
                <form method="POST" action="{{ route('admin.settings.policies.destroy', $policy->id) }}">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-danger icon-only" type="submit" onclick="return confirm('Delete this policy?')" aria-label="Delete policy"><x-admin-icon name="trash" /></button>
                </form>
              </div>
            </div>
            <div class="card-pad">
              <p style="font-size:13px;color:var(--muted);margin:0;line-height:1.6">{{ Str::limit($policy->content, 160) }}</p>
            </div>
          </div>

          <div class="modal-overlay" id="policyModal-{{ $policy->id }}">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div class="modal-head-main">
                  <span class="modal-icon"><x-admin-icon name="file" /></span>
                  <div class="modal-head-copy">
                    <h3>Edit Policy</h3>
                    <p>{{ $policy->title }}</p>
                  </div>
                </div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <form method="POST" action="{{ route('admin.settings.policies.update', $policy->id) }}">
                @csrf @method('PATCH')
                <div class="modal-body">
                  <div class="form-row">
                    <label>Title</label>
                    <input type="text" name="title" value="{{ $policy->title }}" required>
                  </div>
                  <div class="form-row">
                    <label>Content</label>
                    <textarea name="content" rows="8" required>{{ $policy->content }}</textarea>
                  </div>
                </div>
                <div class="modal-foot">
                  <button class="btn btn-outline" type="button" data-modal-close>Cancel</button>
                  <button class="btn btn-primary" type="submit">Update Policy</button>
                </div>
              </form>
            </div>
          </div>
          @empty
          <div class="card"><div class="empty"><div class="ic"><x-admin-icon name="file" /></div><h3>No policies yet</h3><p>Add your first platform policy.</p></div></div>
          @endforelse
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

@push('scripts')
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
@endpush
@endsection
