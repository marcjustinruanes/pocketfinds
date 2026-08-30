@extends('admin.layout')
@section('title', 'Settings')
@section('page-title', 'Platform Settings')
@section('page-sub', 'Manage platform policies and preferences')

@section('content')
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:18px">
  {{ session('success') }}
</div>
@endif

<div data-tabs style="margin-bottom:20px">
  <a class="tab active" data-tab="policies"><x-admin-icon name="file" /> Platform Policies</a>
  <a class="tab" data-tab="general"><x-admin-icon name="settings" /> General</a>
</div>

<div data-tab-panel="policies">
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

<div data-tab-panel="general" style="display:none">
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

@push('scripts')
<script>
document.querySelectorAll('[data-tabs] .tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('[data-tabs] .tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    document.querySelectorAll('[data-tab-panel]').forEach(p => p.style.display = 'none');
    document.querySelector('[data-tab-panel="' + tab.dataset.tab + '"]').style.display = '';
  });
});
</script>
@endpush
@endsection
