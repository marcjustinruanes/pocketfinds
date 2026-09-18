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
    <a class="settings-nav-item" data-settings-tab="hero"><span class="ic"><x-admin-icon name="bag" /></span> Hero Banner</a>
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

    {{-- ── Hero Banner ── --}}
    <div data-settings-panel="hero" style="display:none">
      <h2 class="settings-section-title"><span class="ic"><x-admin-icon name="bag" /></span> Hero Banner</h2>
      <p class="settings-section-sub">Control the landing page hero image, tagline, and seasonal theme. Changes go live immediately.</p>

      <div class="card">
        <div class="card-pad">
          <form method="POST" action="{{ route('admin.settings.hero.update') }}" enctype="multipart/form-data">
            @csrf

            {{-- Current image preview --}}
            @if($setting->hero_image)
            <div style="margin-bottom:18px">
              <div class="field-label" style="margin-bottom:8px">Current Banner Image</div>
              <div style="position:relative;display:inline-block;border-radius:12px;overflow:hidden;max-width:100%">
                <img src="{{ \Illuminate\Support\Facades\Storage::url($setting->hero_image) }}" alt="Hero banner" style="display:block;width:100%;max-height:240px;object-fit:cover;border-radius:12px;border:1px solid var(--border)">
              </div>
              <div style="margin-top:10px;display:flex;align-items:center;gap:8px">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--danger);cursor:pointer">
                  <input type="checkbox" name="hero_image_clear" value="1" style="accent-color:var(--danger)">
                  Remove current image
                </label>
              </div>
            </div>
            @endif

            {{-- Upload new image --}}
            <div class="form-row">
              <label>{{ $setting->hero_image ? 'Replace' : 'Upload' }} Banner Image</label>
              <input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp" style="padding:6px">
              <span style="font-size:11.5px;color:var(--muted)">Recommended: 1920×800px or wider. JPG, PNG, or WEBP · max 4 MB. Use a high-quality editorial photo that represents the current season or campaign theme.</span>
            </div>

            <div style="height:1px;background:var(--border);margin:20px 0"></div>

            {{-- Season / Theme label --}}
            <div class="form-row">
              <label>Season / Theme Label</label>
              <input type="text" name="hero_label" value="{{ old('hero_label', $setting->hero_label) }}" placeholder="e.g. Summer Sale · Philippines" maxlength="100">
              <span style="font-size:11.5px;color:var(--muted)">Shown as a small pill above the headline. Keep it short — 30 chars or less works best.</span>
            </div>

            {{-- Tagline (main headline) --}}
            <div class="form-row">
              <label>Tagline <span style="color:var(--danger)">*</span></label>
              <input type="text" name="hero_tagline" value="{{ old('hero_tagline', $setting->hero_tagline) }}" placeholder="e.g. Fresh Finds. Summer Feels." maxlength="200" required>
              <span style="font-size:11.5px;color:var(--muted)">The big headline on the hero. Short, punchy, on-brand.</span>
            </div>

            {{-- Subtitle --}}
            <div class="form-row">
              <label>Subtitle</label>
              <textarea name="hero_subtitle" rows="2" maxlength="500" placeholder="e.g. Discover summer essentials from verified local sellers." style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;resize:vertical;font-family:inherit">{{ old('hero_subtitle', $setting->hero_subtitle) }}</textarea>
              <span style="font-size:11.5px;color:var(--muted)">Supporting text shown below the tagline. One or two sentences max.</span>
            </div>

            {{-- CTA button text --}}
            <div class="form-row">
              <label>CTA Button Text</label>
              <input type="text" name="hero_cta_text" value="{{ old('hero_cta_text', $setting->hero_cta_text) }}" placeholder="Browse Products" maxlength="80">
              <span style="font-size:11.5px;color:var(--muted)">Text on the "Browse Products" button. Defaults to "Browse Products" if left blank.</span>
            </div>

            {{-- Overlay darkness --}}
            <div class="form-row">
              <label>Text Overlay</label>
              <select name="hero_overlay" style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px">
                <option value="dark"  {{ ($setting->hero_overlay ?? 'dark') === 'dark'  ? 'selected' : '' }}>Dark overlay (white text — for bright/light images)</option>
                <option value="light" {{ ($setting->hero_overlay ?? 'dark') === 'light' ? 'selected' : '' }}>Light overlay (dark text — for dark/moody images)</option>
                <option value="none"  {{ ($setting->hero_overlay ?? 'dark') === 'none'  ? 'selected' : '' }}>No overlay (image only, text floats above)</option>
              </select>
              <span style="font-size:11.5px;color:var(--muted)">Controls the gradient overlay that makes the text readable against the banner image.</span>
            </div>

            <div style="margin-top:18px;display:flex;align-items:center;gap:10px">
              <button class="btn btn-primary" type="submit">Save Hero Banner</button>
              @if($setting->hero_image)
              <span style="font-size:12px;color:var(--muted)">Live immediately — no cache clear needed.</span>
              @endif
            </div>
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
