@extends('seller.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your seller profile and settings')

@section('content')
@php $u = auth()->user(); @endphp

<div class="dash-grid">
  <div class="stack">

    {{-- Profile --}}
    <div class="card">
      <div class="card-head"><div><h2>Profile Information</h2><p>Update your personal details</p></div></div>
      <div class="card-pad">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px">
          <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);display:grid;place-items:center;color:#fff;font-weight:700;font-size:22px;flex:none">
            {{ strtoupper(substr($u->given_names, 0, 1)) }}
          </div>
          <div>
            <div style="font-size:16px;font-weight:700">{{ $u->given_names }} {{ $u->last_name }}</div>
            <div style="font-size:12px;color:var(--muted)">{{ $u->email }}</div>
            <span class="stamp stamp-active" style="margin-top:4px">Seller</span>
          </div>
        </div>
        @if(session('profile_success'))
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('profile_success') }}</div>
        @endif
        <form method="POST" action="{{ route('seller.account.profile') }}">
          @csrf
          <div class="form-grid-2">
            <div class="form-row"><label>Given Names</label><input type="text" name="given_names" value="{{ old('given_names', $u->given_names) }}" required></div>
            <div class="form-row"><label>Last Name</label><input type="text" name="last_name" value="{{ old('last_name', $u->last_name) }}" required></div>
          </div>
          <div class="form-grid-2">
            <div class="form-row"><label>Middle Name</label><input type="text" name="middle_name" value="{{ old('middle_name', $u->middle_name) }}"></div>
            <div class="form-row"><label>Phone Number</label><input type="text" name="contact_no" value="{{ old('contact_no', $u->contact_no) }}" placeholder="09XXXXXXXXX" maxlength="11"></div>
          </div>
          <div class="form-grid-2">
            <div class="form-row">
              <label>Sex</label>
              <select name="sex">
                <option value="male" {{ $u->sex === 'male' ? 'selected' : '' }}>Male</option>
                <option value="female" {{ $u->sex === 'female' ? 'selected' : '' }}>Female</option>
              </select>
            </div>
            <div class="form-row"><label>Birthday</label><input type="date" name="birthday" value="{{ old('birthday', $u->birthday?->format('Y-m-d')) }}"></div>
          </div>
          <div class="form-row"><label>Email Address</label><input type="email" value="{{ $u->email }}" disabled style="background:var(--paper);color:var(--muted);cursor:not-allowed"></div>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>

    {{-- Address --}}
    <div class="card">
      <div class="card-head"><div><h2>Address Information</h2><p>Your delivery and business address</p></div></div>
      <div class="card-pad">
        @if(session('address_success'))
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('address_success') }}</div>
        @endif
        <form method="POST" action="{{ route('seller.account.address') }}">
          @csrf
          <div class="form-row">
            <label>Province</label>
            <select name="province" id="acc-province" required>
              <option value="" disabled>Loading provinces…</option>
            </select>
          </div>
          <div class="form-row">
            <label>Municipality / City</label>
            <select name="municipality" id="acc-municipality" required disabled>
              <option value="" disabled>Select province first</option>
            </select>
          </div>
          <div class="form-row">
            <label>Barangay</label>
            <select name="barangay" id="acc-barangay" required disabled>
              <option value="" disabled>Select municipality first</option>
            </select>
          </div>
          <div class="form-grid-2">
            <div class="form-row"><label>House No. / Unit</label><input type="text" name="house_no" value="{{ old('house_no', $u->house_no) }}" placeholder="e.g. 123"></div>
            <div class="form-row"><label>Street</label><input type="text" name="street" value="{{ old('street', $u->street) }}" placeholder="e.g. Rizal St."></div>
          </div>
          <button type="submit" class="btn btn-primary">Update Address</button>
        </form>
      </div>
    </div>

    {{-- Shop --}}
    <div class="card">
      <div class="card-head"><div><h2>Shop Information</h2><p>Your store details visible to buyers</p></div></div>
      <div class="card-pad">
        @if(session('shop_success'))
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('shop_success') }}</div>
        @endif
        <form method="POST" action="{{ route('seller.account.shop') }}">
          @csrf
          <div class="form-grid-2">
            <div class="form-row"><label>Business Name</label><input type="text" name="business_name" value="{{ old('business_name', $u->business_name) }}" placeholder="Your Shop Name"></div>
            <div class="form-row"><label>Username</label><input type="text" name="username" value="{{ old('username', $u->username) }}" placeholder="@yourshop"></div>
          </div>
          <div class="form-row">
            <label>Shop Category</label>
            <select name="category_id">
              <option value="">— Select category —</option>
              @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ old('category_id', $u->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
              @endforeach
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Update Shop</button>
        </form>
      </div>
    </div>

    {{-- Documents --}}
    <div class="card">
      <div class="card-head"><div><h2>Documents & Verification</h2><p>Your ID and business permit on file</p></div></div>
      <div class="card-pad">

        {{-- Pending request banner --}}
        @if($pendingRequest)
          <div style="background:var(--warning-soft);border:1px solid var(--warning-line);color:var(--warning);padding:12px 14px;border-radius:9px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px">
            <span style="flex:none;margin-top:1px">@include('seller.partials.icon',['name'=>'clock','size'=>15])</span>
            <div>
              <div style="font-weight:700;margin-bottom:2px">Update Request Pending</div>
              <div>Your document update request is awaiting admin review. You cannot submit a new request until this one is resolved.</div>
              <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px">
                @if($pendingRequest->id_file)
                  <button type="button" class="doc-preview-btn btn btn-sm btn-outline"
                    data-src="{{ asset('storage/'.$pendingRequest->id_file) }}"
                    data-type="{{ in_array(strtolower(pathinfo($pendingRequest->id_file,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf' }}">
                    @include('seller.partials.icon',['name'=>'file','size'=>13]) Submitted ID
                  </button>
                @endif
                @if($pendingRequest->business_permit_file)
                  <button type="button" class="doc-preview-btn btn btn-sm btn-outline"
                    data-src="{{ asset('storage/'.$pendingRequest->business_permit_file) }}"
                    data-type="{{ in_array(strtolower(pathinfo($pendingRequest->business_permit_file,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf' }}">
                    @include('seller.partials.icon',['name'=>'file','size'=>13]) Submitted Permit
                  </button>
                @endif
              </div>
            </div>
          </div>
        @elseif($lastRequest && $lastRequest->status === 'rejected')
          <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:12px 14px;border-radius:9px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px">
            <span style="flex:none;margin-top:1px">@include('seller.partials.icon',['name'=>'x','size'=>15])</span>
            <div>
              <div style="font-weight:700;margin-bottom:2px">Last Request Rejected</div>
              <div>{{ $lastRequest->note ?? 'Your previous document update request was rejected. You may submit a new one.' }}</div>
            </div>
          </div>
        @endif

        @if(session('docs_success'))
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('docs_success') }}</div>
        @endif

        <form method="POST" action="{{ route('seller.account.documents') }}" enctype="multipart/form-data" {{ $pendingRequest ? 'style=opacity:.5;pointer-events:none' : '' }}>
          @csrf

          {{-- Valid ID --}}
          <div class="form-row">
            <label>ID Type</label>
            <select name="id_type_id">
              <option value="">— Select ID type —</option>
              @foreach($idTypes as $idType)
                <option value="{{ $idType->id }}" {{ old('id_type_id', $u->id_type_id) == $idType->id ? 'selected' : '' }}>{{ $idType->name }}</option>
              @endforeach
            </select>
          </div>
          @if($u->id_file)
            @php $ext = strtolower(pathinfo($u->id_file, PATHINFO_EXTENSION)); $isImg = in_array($ext, ['jpg','jpeg','png']); @endphp
            <div style="margin-bottom:12px">
              <div style="font-size:12px;font-weight:650;margin-bottom:6px">Current ID on File</div>
              <button type="button" class="doc-preview-btn" data-src="{{ asset('storage/'.$u->id_file) }}" data-type="{{ $isImg ? 'image' : 'pdf' }}"
                style="background:none;border:1px solid var(--border);border-radius:9px;padding:8px 12px;display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                @include('seller.partials.icon',['name'=>'file','size'=>14]) View Current ID
              </button>
            </div>
          @endif
          <div class="form-row"><label>Replace ID File <span style="color:var(--muted);font-weight:400">(optional)</span></label><input type="file" name="id_file" accept="image/*,.pdf" style="padding:6px"></div>

          {{-- Business Permit --}}
          @if($u->business_permit_file)
            @php $bext = strtolower(pathinfo($u->business_permit_file, PATHINFO_EXTENSION)); $bIsImg = in_array($bext, ['jpg','jpeg','png']); @endphp
            <div style="margin-bottom:12px">
              <div style="font-size:12px;font-weight:650;margin-bottom:6px">Current Business Permit</div>
              <button type="button" class="doc-preview-btn" data-src="{{ asset('storage/'.$u->business_permit_file) }}" data-type="{{ $bIsImg ? 'image' : 'pdf' }}"
                style="background:none;border:1px solid var(--border);border-radius:9px;padding:8px 12px;display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                @include('seller.partials.icon',['name'=>'file','size'=>14]) View Current Permit
              </button>
            </div>
          @endif
          <div class="form-row"><label>{{ $u->business_permit_file ? 'Replace' : 'Upload' }} Business Permit <span style="color:var(--muted);font-weight:400">(optional)</span></label><input type="file" name="business_permit_file" accept="image/*,.pdf" style="padding:6px"></div>

          <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:7px">
            @include('seller.partials.icon',['name'=>'send','size'=>14]) Submit for Review
          </button>
        </form>
      </div>
    </div>

  </div>

  <div class="stack">

    {{-- Change password --}}
    <div class="card">
      <div class="card-head"><div><h2>Change Password</h2></div></div>
      <div class="card-pad">
        @if(session('password_success'))
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('password_success') }}</div>
        @endif
        @error('current_password')
          <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ $message }}</div>
        @enderror
        <form method="POST" action="{{ route('seller.account.password') }}">
          @csrf
          <div class="form-row"><label>Current Password</label><input type="password" name="current_password" required></div>
          <div class="form-row"><label>New Password</label><input type="password" name="password" required></div>
          <div class="form-row"><label>Confirm New Password</label><input type="password" name="password_confirmation" required></div>
          <button type="submit" class="btn btn-outline">Update Password</button>
        </form>
      </div>
    </div>

    {{-- Account overview --}}
    <div class="card">
      <div class="card-head"><h2>Account Overview</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column">
        @foreach([
          ['Account Type',   ucfirst($u->account_type)],
          ['Status',         ucfirst($u->status)],
          ['Auth Method',    ucfirst($u->auth_method)],
          ['Member Since',   $u->created_at?->format('M d, Y') ?? '—'],
          ['Given Names',    $u->given_names],
          ['Last Name',      $u->last_name],
          ['Middle Name',    $u->middle_name ?? '—'],
          ['Sex',            ucfirst($u->sex)],
          ['Birthday',       $u->birthday?->format('M d, Y') ?? '—'],
          ['Age',            $u->age],
          ['Email',          $u->email],
          ['Contact No.',    $u->contact_no],
          ['Province',       '<span id="ov-province" data-code="'.e($u->province).'">—</span>'],
          ['Municipality',   '<span id="ov-municipality" data-code="'.e($u->municipality).'">—</span>'],
          ['Barangay',       '<span id="ov-barangay" data-code="'.e($u->barangay).'">—</span>'],
          ['House No.',      $u->house_no ?? '—'],
          ['Street',         $u->street ?? '—'],
          ['Business Name',  $u->business_name ?? '—'],
          ['Username',       $u->username ?? '—'],
          ['Shop Category',  $u->category_id ? ($categories->firstWhere('id', $u->category_id)->name ?? '—') : '—'],
          ['ID Type',        $u->id_type_id ? ($idTypes->firstWhere('id', $u->id_type_id)->name ?? '—') : '—'],
          ['ID File',        $u->id_file ? '<button type="button" class="doc-preview-btn" data-src="'.asset('storage/'.$u->id_file).'" data-type="'.(in_array(strtolower(pathinfo($u->id_file,PATHINFO_EXTENSION)),['jpg','jpeg','png'])?'image':'pdf').'" style="background:none;border:none;padding:0;color:var(--pink);font-weight:600;cursor:pointer;font-size:13px">View File</button>' : '—'],
          ['Business Permit',$u->business_permit_file ? '<button type="button" class="doc-preview-btn" data-src="'.asset('storage/'.$u->business_permit_file).'" data-type="'.(in_array(strtolower(pathinfo($u->business_permit_file,PATHINFO_EXTENSION)),['jpg','jpeg','png'])?'image':'pdf').'" style="background:none;border:none;padding:0;color:var(--pink);font-weight:600;cursor:pointer;font-size:13px">View File</button>' : '—'],
        ] as [$label, $val])
        <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid var(--border)">
          <span style="color:var(--muted)">{{ $label }}</span>
          <span style="font-weight:600;text-align:right;max-width:60%;word-break:break-word">{!! $val !!}</span>
        </div>
        @endforeach
      </div>
    </div>

    {{-- Danger zone --}}
    <div class="card" style="border-color:var(--danger-line)">
      <div class="card-head" style="border-color:var(--danger-line)"><div><h2 style="color:var(--danger)">Danger Zone</h2><p>Irreversible actions</p></div></div>
      <div class="card-pad">
        <button class="btn btn-danger btn-block" data-logout>@include('seller.partials.icon', ['name' => 'logout', 'size' => 14]) Sign Out</button>
      </div>
    </div>

  </div>
</div>

{{-- Document lightbox modal --}}
<div id="docModal" style="display:none;position:fixed;inset:0;background:rgba(27,22,32,.7);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;width:min(780px,100%);max-height:90vh;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(27,22,32,.3);overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
      <span id="docModalTitle" style="font-weight:700;font-size:14px">Document Preview</span>
      <button id="docModalClose" style="border:0;background:var(--paper);width:30px;height:30px;border-radius:50%;font-size:16px;cursor:pointer;display:grid;place-items:center">✕</button>
    </div>
    <div id="docModalBody" style="flex:1;overflow:auto;padding:20px;display:flex;align-items:center;justify-content:center;min-height:300px"></div>
  </div>
</div>

<script>
const PSGC = 'https://psgc.gitlab.io/api';
const savedProvince     = '{{ $u->province }}';
const savedMunicipality = '{{ $u->municipality }}';
const savedBarangay     = '{{ $u->barangay }}';

async function fetchJSON(url) {
  const r = await fetch(url);
  return r.json();
}

function populate(sel, items, saved, placeholder) {
  sel.innerHTML = `<option value="" disabled>${placeholder}</option>`;
  [...items].sort((a,b) => a.name.localeCompare(b.name)).forEach(item => {
    const o = document.createElement('option');
    o.value = item.code;
    o.textContent = item.name;
    if (item.code === saved) o.selected = true;
    sel.appendChild(o);
  });
  sel.disabled = false;
  // update overview label
  const selected = items.find(i => i.code === saved);
  return selected ? selected.name : null;
}

async function initAddress() {
  const provSel = document.getElementById('acc-province');
  const munSel  = document.getElementById('acc-municipality');
  const barSel  = document.getElementById('acc-barangay');

  // Load provinces
  const provinces = await fetchJSON(`${PSGC}/provinces/`);
  const provName = populate(provSel, provinces, savedProvince, 'Select province');
  if (provName) document.getElementById('ov-province').textContent = provName;

  if (!savedProvince) return;

  // Load municipalities
  munSel.innerHTML = '<option value="" disabled selected>Loading…</option>';
  const muns = await fetchJSON(`${PSGC}/provinces/${savedProvince}/cities-municipalities/`);
  const munName = populate(munSel, muns, savedMunicipality, 'Select municipality');
  if (munName) document.getElementById('ov-municipality').textContent = munName;

  if (!savedMunicipality) return;

  // Load barangays
  barSel.innerHTML = '<option value="" disabled selected>Loading…</option>';
  const bars = await fetchJSON(`${PSGC}/cities-municipalities/${savedMunicipality}/barangays/`);
  const barName = populate(barSel, bars, savedBarangay, 'Select barangay');
  if (barName) document.getElementById('ov-barangay').textContent = barName;
}

// Cascade on change
document.getElementById('acc-province').addEventListener('change', async function () {
  const munSel = document.getElementById('acc-municipality');
  const barSel = document.getElementById('acc-barangay');
  munSel.innerHTML = '<option value="" disabled selected>Loading…</option>';
  munSel.disabled = true;
  barSel.innerHTML = '<option value="" disabled selected>Select municipality first</option>';
  barSel.disabled = true;
  const muns = await fetchJSON(`${PSGC}/provinces/${this.value}/cities-municipalities/`);
  populate(munSel, muns, '', 'Select municipality');
});

document.getElementById('acc-municipality').addEventListener('change', async function () {
  const barSel = document.getElementById('acc-barangay');
  barSel.innerHTML = '<option value="" disabled selected>Loading…</option>';
  barSel.disabled = true;
  const bars = await fetchJSON(`${PSGC}/cities-municipalities/${this.value}/barangays/`);
  populate(barSel, bars, '', 'Select barangay');
});

initAddress();

// Document lightbox
const docModal      = document.getElementById('docModal');
const docModalBody  = document.getElementById('docModalBody');
const docModalTitle = document.getElementById('docModalTitle');

document.getElementById('docModalClose').addEventListener('click', closeDocModal);
docModal.addEventListener('click', e => { if (e.target === docModal) closeDocModal(); });

function closeDocModal() {
  docModal.style.display = 'none';
  docModalBody.innerHTML = '';
}

document.querySelectorAll('.doc-preview-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const src  = btn.dataset.src;
    const type = btn.dataset.type;
    docModalTitle.textContent = type === 'pdf' ? 'Document Preview' : 'Image Preview';
    docModalBody.innerHTML = type === 'image'
      ? `<img src="${src}" style="max-width:100%;max-height:70vh;border-radius:8px;object-fit:contain">`
      : `<iframe src="${src}" style="width:100%;height:70vh;border:0;border-radius:8px"></iframe>`;
    docModal.style.display = 'flex';
  });
});
</script>
@endsection
