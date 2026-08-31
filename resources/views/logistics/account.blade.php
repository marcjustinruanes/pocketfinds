@extends('logistics.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your profile, address, and password')

@push('head')
<style>
  .addr-combo{position:relative}
  .addr-combo input{width:100%}
  .addr-combo-list{position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:220px;overflow-y:auto;background:var(--surface,#fff);border:1px solid var(--border);border-radius:9px;box-shadow:0 8px 24px rgba(27,22,32,.12);z-index:20}
  .addr-combo-option{padding:9px 12px;font-size:13px;cursor:pointer}
  .addr-combo-option:hover,.addr-combo-option.active{background:var(--paper)}
  .addr-combo-empty{padding:9px 12px;font-size:12.5px;color:var(--muted)}
</style>
@endpush

@section('content')
@php($user = auth()->user())

<div class="dash-grid">
  <div class="stack">

    {{-- Profile --}}
    <div class="card">
      <div class="card-head"><div><h2>Profile Information</h2><p>Your personal details on file</p></div></div>
      <div class="card-pad">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px">
          @if($user->profile_picture)
          <img src="{{ \Illuminate\Support\Facades\Storage::disk('profile_images')->url($user->profile_picture) }}" alt="Profile picture"
               style="width:64px;height:64px;border-radius:50%;object-fit:cover;flex:none">
          @else
          <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);display:grid;place-items:center;color:#fff;font-weight:700;font-size:22px;flex:none">
            {{ strtoupper(substr($user->given_names, 0, 1)) }}
          </div>
          @endif
          <div>
            <div style="font-size:16px;font-weight:700">{{ $user->given_names }} {{ $user->last_name }}</div>
            <div style="font-size:12px;color:var(--muted)">{{ $user->email }}</div>
            <span class="stamp stamp-approved" style="margin-top:4px">Logistics</span>
          </div>
        </div>
        @if(session('profile_success'))
        <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('profile_success') }}</div>
        @endif
        <form method="POST" action="{{ route('logistics.account.update') }}" enctype="multipart/form-data">
          @csrf
          <div class="form-row">
            <label>Profile Picture</label>
            <input type="file" name="profile_picture" accept="image/png,image/jpeg,image/webp" style="padding:6px">
            <span style="font-size:11px;color:var(--muted)">JPG, PNG, or WEBP. Max 2MB.</span>
            @error('profile_picture')<div style="color:var(--danger);font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
          </div>
          <div class="form-grid-2">
            <div class="form-row"><label>Given Names (First Name)</label><input type="text" name="given_names" value="{{ old('given_names', $user->given_names) }}" required></div>
            <div class="form-row"><label>Last Name</label><input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" required></div>
          </div>
          <div class="form-grid-2">
            <div class="form-row">
              <label>Middle Initial <span style="color:var(--muted);font-weight:400">(optional)</span></label>
              <input type="text" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}" maxlength="1" pattern="[A-Za-z]" placeholder="e.g. D" style="text-transform:uppercase">
              @error('middle_name')<div style="color:var(--danger);font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
            </div>
            <div class="form-row">
              <label>Contact No.</label>
              <input type="tel" name="contact_no" value="{{ old('contact_no', $user->contact_no) }}" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX">
              @error('contact_no')<div style="color:var(--danger);font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="form-row">
            <label>Sex</label>
            <select name="sex">
              <option value="" {{ blank(old('sex', $user->sex)) ? 'selected' : '' }}>Select sex</option>
              <option value="male" {{ old('sex', $user->sex) === 'male' ? 'selected' : '' }}>Male</option>
              <option value="female" {{ old('sex', $user->sex) === 'female' ? 'selected' : '' }}>Female</option>
              <option value="other" {{ old('sex', $user->sex) === 'other' ? 'selected' : '' }}>Other</option>
            </select>
          </div>
          <div class="form-row"><label>Email Address</label><input type="email" value="{{ $user->email }}" disabled style="background:var(--paper);color:var(--muted);cursor:not-allowed"></div>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </form>
      </div>
    </div>

    {{-- Address --}}
    <div class="card">
      <div class="card-head"><div><h2>Address Information</h2><p>Where you're based</p></div></div>
      <div class="card-pad">
        @if(session('address_success'))
        <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('address_success') }}</div>
        @endif
        @error('province')
        <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ $message }}</div>
        @enderror
        <form method="POST" action="{{ route('logistics.account.address') }}" id="addressForm">
          @csrf
          <div class="form-row">
            <label>Province</label>
            <div class="addr-combo">
              <input type="text" name="province" id="province_input" autocomplete="off" required
                     value="{{ old('province', $user->province) }}" placeholder="Type to search a province…">
              <div class="addr-combo-list" id="province_list" hidden></div>
            </div>
          </div>
          <div class="form-row">
            <label>Municipality / City</label>
            <div class="addr-combo">
              <input type="text" name="municipality" id="municipality_input" autocomplete="off" required
                     value="{{ old('municipality', $user->municipality) }}" placeholder="Type to search a city / municipality…">
              <div class="addr-combo-list" id="municipality_list" hidden></div>
            </div>
          </div>
          <div class="form-row">
            <label>Barangay</label>
            <div class="addr-combo">
              <input type="text" name="barangay" id="barangay_input" autocomplete="off" required
                     value="{{ old('barangay', $user->barangay) }}" placeholder="Type to search a barangay…">
              <div class="addr-combo-list" id="barangay_list" hidden></div>
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-row"><label>House No. / Unit</label><input type="text" name="house_no" value="{{ old('house_no', $user->house_no) }}" placeholder="e.g. 123"></div>
            <div class="form-row"><label>Street</label><input type="text" name="street" value="{{ old('street', $user->street) }}" placeholder="e.g. Rizal St."></div>
          </div>
          <button class="btn btn-primary" type="submit">Update Address</button>
        </form>
      </div>
    </div>

    {{-- Password --}}
    <div class="card">
      <div class="card-head"><h2>Change Password</h2></div>
      <div class="card-pad">
        @if(session('password_success'))
        <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('password_success') }}</div>
        @endif
        @error('current_password')
        <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ $message }}</div>
        @enderror
        <form method="POST" action="{{ route('logistics.account.password') }}">
          @csrf
          <div class="form-row"><label>Current Password</label><input type="password" name="current_password" required></div>
          <div class="form-row"><label>New Password</label><input type="password" name="password" required></div>
          <div class="form-row"><label>Confirm New Password</label><input type="password" name="password_confirmation" required></div>
          <button class="btn btn-primary" type="submit">Update Password</button>
        </form>
      </div>
    </div>

  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Account Overview</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column">
        @foreach([
          ['Account Type', 'Logistics'],
          ['Status',       filled($user->status) ? ucfirst($user->status) : 'Not provided'],
          ['Member Since', $user->created_at?->format('M d, Y') ?? 'Not provided'],
          ['Email',        $user->email ?: 'Not provided'],
          ['Contact No.',  $user->contact_no ?: 'Not provided'],
          ['Full Address', collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter()->implode(', ') ?: 'Not provided'],
        ] as [$label, $val])
        <div style="display:flex;justify-content:space-between;gap:10px;font-size:13px;padding:8px 0;border-bottom:1px solid var(--border)">
          <span style="color:var(--muted);flex:none">{{ $label }}</span>
          <span style="font-weight:600;text-align:right;word-break:break-word">{{ $val }}</span>
        </div>
        @endforeach
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
// Middle initial: letters only, single character, auto-uppercase.
document.querySelector('input[name="middle_name"]')?.addEventListener('input', function () {
  this.value = this.value.replace(/[^A-Za-z]/g, '').slice(0, 1).toUpperCase();
});

// Contact number: digits only, forced to start with 09, capped at 11 digits.
document.querySelector('input[name="contact_no"]')?.addEventListener('input', function () {
  let v = this.value.replace(/\D/g, '').slice(0, 11);
  this.value = v;
});

// Searchable, cascading Province → City/Municipality → Barangay combobox, backed by the
// same PSGC API already used at registration (public/js/register.js), but with type-to-filter
// instead of a plain <select> so it works well against thousands of barangays.
(function () {
  const PSGC = 'https://psgc.gitlab.io/api';

  async function fetchJSON(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error('network');
    return res.json();
  }

  function field(prefix) {
    return {
      input: document.getElementById(prefix + '_input'),
      list: document.getElementById(prefix + '_list'),
    };
  }

  const province = field('province');
  const municipality = field('municipality');
  const barangay = field('barangay');

  let provinces = [];
  let municipalities = [];
  let barangays = [];

  function closeList(f) { f.list.hidden = true; f.list.innerHTML = ''; }

  function showOptions(f, items, onPick) {
    if (!items.length) {
      f.list.innerHTML = '<div class="addr-combo-empty">No matches — you can still type your own.</div>';
      f.list.hidden = false;
      return;
    }
    f.list.innerHTML = '';
    items.slice(0, 50).forEach(item => {
      const opt = document.createElement('div');
      opt.className = 'addr-combo-option';
      opt.textContent = item.name;
      opt.addEventListener('mousedown', e => { e.preventDefault(); onPick(item); });
      f.list.appendChild(opt);
    });
    f.list.hidden = false;
  }

  function filtered(source, query) {
    const q = query.trim().toLowerCase();
    return q ? source.filter(i => i.name.toLowerCase().includes(q)) : source;
  }

  function wire(f, getSource, onPick) {
    f.input.addEventListener('input', () => showOptions(f, filtered(getSource(), f.input.value), onPick));
    f.input.addEventListener('focus', () => showOptions(f, filtered(getSource(), f.input.value), onPick));
    f.input.addEventListener('blur', () => setTimeout(() => closeList(f), 120));
  }

  function pickProvince(item) {
    province.input.value = item.name;
    closeList(province);
    municipality.input.placeholder = 'Loading cities / municipalities…';
    fetchJSON(`${PSGC}/provinces/${item.code}/cities-municipalities/`)
      .then(data => { municipalities = data; municipality.input.placeholder = 'Type to search a city / municipality…'; })
      .catch(() => { municipality.input.placeholder = 'Could not load suggestions — type manually'; });
  }

  function pickMunicipality(item) {
    municipality.input.value = item.name;
    closeList(municipality);
    barangay.input.placeholder = 'Loading barangays…';
    fetchJSON(`${PSGC}/cities-municipalities/${item.code}/barangays/`)
      .then(data => { barangays = data; barangay.input.placeholder = 'Type to search a barangay…'; })
      .catch(() => { barangay.input.placeholder = 'Could not load suggestions — type manually'; });
  }

  function pickBarangay(item) {
    barangay.input.value = item.name;
    closeList(barangay);
  }

  wire(province, () => provinces, pickProvince);
  wire(municipality, () => municipalities, pickMunicipality);
  wire(barangay, () => barangays, pickBarangay);

  fetchJSON(`${PSGC}/provinces/`)
    .then(async data => {
      provinces = data;
      const currentProvince = province.input.value.trim();
      if (!currentProvince) return;

      const pMatch = provinces.find(p => p.name.toLowerCase() === currentProvince.toLowerCase());
      if (!pMatch) return;
      try {
        municipalities = await fetchJSON(`${PSGC}/provinces/${pMatch.code}/cities-municipalities/`);
        const currentMuni = municipality.input.value.trim();
        if (!currentMuni) return;

        const mMatch = municipalities.find(m => m.name.toLowerCase() === currentMuni.toLowerCase());
        if (!mMatch) return;
        barangays = await fetchJSON(`${PSGC}/cities-municipalities/${mMatch.code}/barangays/`);
      } catch (e) { /* leave fields as free text if lookups fail */ }
    })
    .catch(() => { province.input.placeholder = 'Could not load suggestions — type manually'; });
})();
</script>
@endpush
