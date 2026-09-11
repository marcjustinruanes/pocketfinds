{{-- Shared, view-only profile detail sections for a user.
     Expects: $user (User model). Optional: $riderProfile (pre-fetched
     RiderProfile, to avoid an extra query when the caller already has it). --}}
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
    <x-admin-doc-thumb :path="$user->id_file" label="Government ID" />
    <x-admin-doc-thumb :path="$user->selfie_file" label="Selfie with ID" />
    @if(in_array($user->account_type, ['seller', 'logistics']))
    <x-admin-doc-thumb :path="$user->business_permit_file" label="Business Permit" />
    @endif
  </div>
</div>

@if($user->account_type === 'seller')
<div class="section-card">
  <div class="section-head"><span class="ic"><x-admin-icon name="bag" /></span><span>Seller Details</span></div>
  <div class="detail-grid">
    <div><div class="field-label">Business Name</div><div class="field-value">{{ $user->business_name ?? '—' }}</div></div>
    <div class="full"><div class="field-label">Categories</div><div class="field-value">{{ $user->categories->pluck('name')->push($user->category_other)->filter()->implode(', ') ?: '—' }}</div></div>
  </div>
</div>
@endif

@if($user->account_type === 'logistics')
<div class="section-card">
  <div class="section-head"><span class="ic"><x-admin-icon name="truck" /></span><span>Logistics Details</span></div>
  <div class="detail-grid">
    <div><div class="field-label">Company Name</div><div class="field-value">{{ $user->business_name ?? '—' }}</div></div>
    <div><div class="field-label">Role</div><div class="field-value">{{ $user->logistics_role ? ucfirst($user->logistics_role) : '—' }}</div></div>
  </div>
</div>
@endif

@if($user->account_type === 'rider' && ($riderProfile ?? null))
<div class="section-card">
  <div class="section-head"><span class="ic"><x-admin-icon name="truck" /></span><span>Vehicle Information</span></div>
  <div class="detail-grid">
    <div><div class="field-label">Vehicle Type</div><div class="field-value">{{ ucfirst(str_replace('_',' ',$riderProfile->vehicle_type)) }}</div></div>
    <div><div class="field-label">Brand / Model</div><div class="field-value">{{ $riderProfile->vehicle_brand }} {{ $riderProfile->vehicle_model }}</div></div>
    @if($riderProfile->plate_number)
    <div><div class="field-label">Plate Number</div><div class="field-value mono">{{ $riderProfile->plate_number }}</div></div>
    @endif
    @if($riderProfile->license_number)
    <div><div class="field-label">License No.</div><div class="field-value mono">{{ $riderProfile->license_number }}</div></div>
    <div><div class="field-label">License Expiry</div><div class="field-value mono">{{ $riderProfile->license_expiry?->format('M d, Y') }}</div></div>
    @endif
  </div>
  <div class="doc-grid" style="margin-top:10px">
    <x-admin-doc-thumb :path="$riderProfile->or_file" label="OR" />
    <x-admin-doc-thumb :path="$riderProfile->cr_file" label="CR" />
    <x-admin-doc-thumb :path="$riderProfile->license_file" label="Driver's License" />
  </div>
</div>
@endif
