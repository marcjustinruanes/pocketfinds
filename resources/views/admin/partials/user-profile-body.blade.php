{{-- Shared, view-only profile detail sections for a user. Expects: $user (User model). --}}
<div class="section-card">
  <div class="section-head"><span class="ic"><x-admin-icon name="account" /></span><span>Personal Information</span></div>
  <div class="detail-grid">
    <div><div class="field-label">Full Name</div><div class="field-value">{{ $user->given_names }} {{ $user->middle_name ? $user->middle_name.' ' : '' }}{{ $user->last_name }}</div></div>
    <div><div class="field-label">Username</div><div class="field-value mono">{{ $user->username ?? '—' }}</div></div>
    <div><div class="field-label">Sex</div><div class="field-value">{{ ucfirst($user->sex ?? '—') }}</div></div>
    <div><div class="field-label">Birthday</div><div class="field-value mono">{{ $user->birthday?->format('M d, Y') ?? '—' }}</div></div>
    <div><div class="field-label">Age</div><div class="field-value">{{ $user->age ?? '—' }}</div></div>
    <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></div></div>
  </div>
</div>

<div class="section-card">
  <div class="section-head"><span class="ic"><x-admin-icon name="mail" /></span><span>Contact &amp; Auth</span></div>
  <div class="detail-grid">
    <div><div class="field-label">Email</div><div class="field-value">{{ $user->email }}</div></div>
    <div><div class="field-label">Contact No.</div><div class="field-value mono">{{ $user->contact_no }}</div></div>
    <div><div class="field-label">Auth Method</div><div class="field-value">{{ ucfirst($user->auth_method) }}</div></div>
    <div><div class="field-label">Joined</div><div class="field-value mono">{{ $user->created_at?->format('M d, Y') ?? '—' }}</div></div>
  </div>
</div>

<div class="section-card">
  <div class="section-head"><span class="ic"><x-admin-icon name="pin" /></span><span>Address</span></div>
  <div class="detail-grid">
    <div class="full"><div class="field-label">Full Address</div><div class="field-value">{{ collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter()->implode(', ') ?: '—' }}</div></div>
  </div>
</div>

<div class="section-card">
  <div class="section-head"><span class="ic"><x-admin-icon name="shield" /></span><span>Verification Documents</span></div>
  <div class="doc-grid">
    {{-- Riders have no separate government ID upload — their driver's license
         (shown below) doubles as ID, so the generic thumb would just repeat it. --}}
    @unless($user->account_type === 'rider')
    <x-admin-doc-thumb :path="$user->id_file" label="Government ID" />
    @endunless
    <x-admin-doc-thumb :path="$user->selfie_file" label="Selfie with ID" />
    @if($user->account_type === 'seller' || $user->account_type === 'logistics')
    <x-admin-doc-thumb :path="$user->business_permit_file" label="Business Permit" />
    @endif
    @if($user->account_type === 'logistics' && $user->company_logo)
    <x-admin-doc-thumb :path="$user->company_logo" label="Company Logo" />
    @endif
    @if($user->resume_file)
    <x-admin-doc-thumb :path="$user->resume_file" label="Resume / CV" />
    @endif
  </div>
</div>

@if($user->account_type === 'seller')
<div class="section-card">
  <div class="section-head"><span class="ic"><x-admin-icon name="bag" /></span><span>Seller Details</span></div>
  <div class="detail-grid">
    <div><div class="field-label">Business Name</div><div class="field-value">{{ $user->business_name ?? '—' }}</div></div>
    <div class="full"><div class="field-label">Category</div><div class="field-value">{{ collect([$user->category?->name, $user->category_other])->filter()->implode(', ') ?: '—' }}</div></div>
  </div>
</div>
@endif

@if($user->account_type === 'logistics' && $user->business_name)
<div class="section-card">
  <div class="section-head"><span class="ic"><x-admin-icon name="truck" /></span><span>Logistics Company</span></div>
  <div class="detail-grid">
    <div class="full"><div class="field-label">Company Name</div><div class="field-value">{{ $user->business_name }}</div></div>
    @if($user->logistics_role)<div><div class="field-label">Role</div><div class="field-value">{{ ucfirst($user->logistics_role) }}</div></div>@endif
  </div>
</div>
@endif

@if($user->account_type === 'rider' && $user->vehicle_type)
<div class="section-card">
  <div class="section-head"><span class="ic"><x-admin-icon name="truck" /></span><span>Vehicle Information</span></div>
  <div class="detail-grid">
    <div class="full"><div class="field-label">Logistics Company</div><div class="field-value">{{ $user->business_name ?: '—' }}</div></div>
    <div><div class="field-label">Vehicle Type</div><div class="field-value">{{ \Illuminate\Support\Str::title(str_replace('_',' ',$user->vehicle_type)) }}</div></div>
    <div><div class="field-label">Ownership</div><div class="field-value">{{ $user->vehicle_ownership === 'own' ? 'Own Vehicle' : ($user->vehicle_ownership === 'company' ? 'Company Vehicle' : '—') }}</div></div>
    @if($user->vehicle_brand)<div><div class="field-label">Brand / Model</div><div class="field-value">{{ $user->vehicle_brand }} {{ $user->vehicle_model }}</div></div>@endif
    @if($user->plate_number)
    <div><div class="field-label">Plate Number</div><div class="field-value mono">{{ $user->plate_number }}</div></div>
    @endif
    @if($user->license_number)
    <div><div class="field-label">License No.</div><div class="field-value mono">{{ $user->license_number }}</div></div>
    <div><div class="field-label">License Expiry</div><div class="field-value mono">{{ $user->license_expiry?->format('M d, Y') }}</div></div>
    @endif
  </div>
  <div class="doc-grid" style="margin-top:10px">
    <x-admin-doc-thumb :path="$user->license_file" label="Driver's License" />
    @if($user->vehicle_ownership === 'own')
    <x-admin-doc-thumb :path="$user->or_file" label="OR" />
    <x-admin-doc-thumb :path="$user->cr_file" label="CR" />
    @endif
  </div>
</div>
@endif
