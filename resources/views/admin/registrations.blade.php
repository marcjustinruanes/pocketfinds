@extends('admin.layout')
@section('title', 'Registrations')
@section('page-title', 'Registrations')
@section('page-sub', 'Review and approve new account applications')
@php use Illuminate\Support\Facades\Storage; @endphp

@section('content')
<div class="card">
  <div class="card-head">
    <div><h2>Applications</h2><p>{{ $users->count() }} total submissions</p></div>
  </div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic"><x-admin-icon name="search" /></span>
        <input type="text" placeholder="Search name or email..." data-table-search="regTable">
      </div>
      <select class="select" id="regTypeFilter">
        <option value="all">All Types</option>
        <option value="buyer">Buyer</option>
        <option value="seller">Seller</option>
        <option value="rider">Rider</option>
      </select>
    </div>

    @php
      $regByStatus = $users->countBy('status');
    @endphp
    <div data-tabs id="regTabs">
      <a class="tab active" data-tab="all">All <span class="tab-count">{{ $users->count() }}</span></a>
      <a class="tab" data-tab="pending">Pending <span class="tab-count">{{ $regByStatus->get('pending', 0) }}</span></a>
      <a class="tab" data-tab="approved">Approved <span class="tab-count">{{ $regByStatus->get('approved', 0) }}</span></a>
      <a class="tab" data-tab="rejected">Rejected <span class="tab-count">{{ $regByStatus->get('rejected', 0) }}</span></a>
      <a class="tab" data-tab="suspended">Suspended <span class="tab-count">{{ $regByStatus->get('suspended', 0) }}</span></a>
    </div>

    <div class="table-wrap">
      <table class="dtable" id="regTable">
        <thead>
          <tr><th>Applicant</th><th>Type</th><th>Username</th><th>Method</th><th>Submitted</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          @forelse($users as $user)
          <tr class="rail-row rail-{{ $user->status }}" data-type="{{ $user->status }}" data-account-type="{{ $user->account_type }}">
            <td>
              <div class="cell-user">
                <x-user-avatar :user="$user" size="30" class="avatar-sm" />
                <div><strong>{{ $user->given_names }} {{ $user->last_name }}</strong><span>{{ $user->email }}</span></div>
              </div>
            </td>
            <td><span class="stamp stamp-{{ $user->account_type }}">{{ ucfirst($user->account_type) }}</span></td>
            <td class="mono" style="font-size:12px">{{ $user->username ?? '—' }}</td>
            <td>{{ ucfirst($user->auth_method) }}</td>
            <td class="mono">{{ $user->created_at->format('M d, Y') }}</td>
            <td><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="reviewModal-{{ $user->id }}">Review</button>
              </div>
            </td>
          </tr>

          {{-- Per-user review modal --}}
          <div class="modal-overlay" id="reviewModal-{{ $user->id }}">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div class="modal-head-main">
                  <span class="modal-icon"><x-admin-icon name="edit" /></span>
                  <div class="modal-head-copy">
                    <h3>Review Application</h3>
                    <p>{{ $user->given_names }} {{ $user->last_name }} — {{ ucfirst($user->account_type) }}</p>
                  </div>
                </div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">

                {{-- Personal --}}
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="account" /></span><span>Personal Information</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Full Name</div><div class="field-value">{{ $user->given_names }} {{ $user->middle_name ? $user->middle_name.' ' : '' }}{{ $user->last_name }}</div></div>
                    <div><div class="field-label">Username</div><div class="field-value mono">{{ $user->username ?? '—' }}</div></div>
                    <div><div class="field-label">Sex</div><div class="field-value">{{ ucfirst($user->sex ?? '—') }}</div></div>
                    <div><div class="field-label">Birthday</div><div class="field-value mono">{{ $user->birthday?->format('M d, Y') ?? '—' }}</div></div>
                    <div><div class="field-label">Age</div><div class="field-value">{{ $user->age ?? '—' }}</div></div>
                    <div><div class="field-label">Account Type</div><div class="field-value"><span class="stamp stamp-{{ $user->account_type }}">{{ ucfirst($user->account_type) }}</span></div></div>
                  </div>
                </div>

                {{-- Contact --}}
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="mail" /></span><span>Contact &amp; Auth</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Email</div><div class="field-value">{{ $user->email }}</div></div>
                    <div><div class="field-label">Contact No.</div><div class="field-value mono">{{ $user->contact_no }}</div></div>
                    <div><div class="field-label">Auth Method</div><div class="field-value">{{ ucfirst($user->auth_method) }}</div></div>
                    <div><div class="field-label">Submitted</div><div class="field-value mono">{{ $user->created_at->format('M d, Y') }}</div></div>
                  </div>
                </div>

                {{-- Address --}}
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="pin" /></span><span>Address</span></div>
                  <div class="detail-grid">
                    <div class="full"><div class="field-label">Full Address</div><div class="field-value">@php
                      $mun  = is_numeric(trim($user->municipality ?? ''))  ? null : $user->municipality;
                      $prov = is_numeric(trim($user->province ?? '')) ? null : $user->province;
                    @endphp{{ collect([$user->house_no, $user->street, $user->barangay, $mun, $prov])->filter()->implode(', ') ?: '—' }}</div></div>
                  </div>
                </div>

                {{-- Verification Documents --}}
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="shield" /></span><span>Verification Documents</span></div>
                  <div class="doc-grid">
                    <x-admin-doc-thumb :path="$user->id_file" label="Government ID" />
                    <x-admin-doc-thumb :path="$user->selfie_file" label="Selfie with ID" />
                    @if($user->account_type === 'seller')
                    <x-admin-doc-thumb :path="$user->business_permit_file" label="Business Permit" />
                    @endif
                    @if($user->account_type === 'rider')
                      @php $regRiderProfile = \App\Models\RiderProfile::where('user_id', $user->id)->first(); @endphp
                      @if($regRiderProfile)
                      <x-admin-doc-thumb :path="$regRiderProfile->or_file" label="OR" />
                      <x-admin-doc-thumb :path="$regRiderProfile->cr_file" label="CR" />
                      <x-admin-doc-thumb :path="$regRiderProfile->license_file" label="Driver's License" />
                      @endif
                    @endif
                  </div>
                </div>

                {{-- Seller-specific --}}
                @if($user->account_type === 'seller' && $user->business_name)
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="bag" /></span><span>Seller Details</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Business Name</div><div class="field-value">{{ $user->business_name }}</div></div>
                    <div class="full"><div class="field-label">Categories</div><div class="field-value">{{ $user->categories->pluck('name')->push($user->category_other)->filter()->implode(', ') ?: '—' }}</div></div>
                  </div>
                </div>
                @endif

              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Cancel</button>
                @if($user->status !== 'suspended')
                <form method="POST" action="{{ route('admin.users.suspend', $user->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-outline" type="submit">Suspend</button>
                </form>
                @endif
                @if($user->status !== 'rejected')
                <form method="POST" action="{{ route('admin.registrations.reject', $user->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-outline-danger" type="submit">Reject</button>
                </form>
                @endif
                @if($user->status !== 'approved')
                <form method="POST" action="{{ route('admin.registrations.approve', $user->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-success" type="submit">Approve</button>
                </form>
                @endif
              </div>
            </div>
          </div>
          @empty
          <tr><td colspan="7"><div class="empty"><div class="ic"><x-admin-icon name="edit" /></div><h3>No registrations yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function applyRegFilters() {
  const statusTab = document.querySelector('#regTabs .tab.active')?.dataset.tab || 'all';
  const typeVal = document.getElementById('regTypeFilter').value;
  document.querySelectorAll('#regTable tbody tr[data-type]').forEach(row => {
    const statusOk = statusTab === 'all' || row.dataset.type === statusTab;
    const typeOk = typeVal === 'all' || row.dataset.accountType === typeVal;
    row.hidden = !(statusOk && typeOk);
  });
}
document.querySelectorAll('#regTabs .tab').forEach(tab => {
  // run after admin.js's own tab-click handler has applied its status-only filter
  tab.addEventListener('click', () => setTimeout(applyRegFilters, 0));
});
document.getElementById('regTypeFilter').addEventListener('change', applyRegFilters);
</script>

@include('admin.partials.doc-lightbox')
@endsection
