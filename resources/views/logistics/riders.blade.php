@extends('logistics.layout')
@section('title', 'Rider Management')
@section('page-title', 'Rider Management')
@section('page-sub', 'Review courier applications, manage rider access and coverage')

@section('workspace-actions')
<div class="rider-workspace-actions">
  <form method="GET" class="rider-search-form" role="search">
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5"/></svg>
    <input name="search" value="{{ request('search') }}" placeholder="Search name, username, or email" aria-label="Search riders" oninput="clearTimeout(this._riderSearchTimer); this._riderSearchTimer = setTimeout(() => this.form.requestSubmit(), 350)">
    <input type="hidden" name="status" value="{{ $status }}">
  </form>
  <button type="button" class="btn btn-primary rider-create-button" data-modal-open="createRiderModal"><span aria-hidden="true">+</span>Create rider account</button>
</div>
@endsection

@section('content')
<section class="personnel-page rider-management-page">
@if($errors->has('activation_email'))<div class="acf-alert" role="alert">{{ $errors->first('activation_email') }}</div>@endif
@include('logistics.partials.personnel-tabs')
@if(session('success'))<div class="rider-success-alert" role="status"><x-admin-icon name="check-circle" />{{ session('success') }}</div>@endif

<div class="modal-overlay {{ $errors->any() ? 'open' : '' }}" id="createRiderModal" aria-hidden="{{ $errors->any() ? 'false' : 'true' }}">
  <section class="modal modal-xl rider-create-modal" role="dialog" aria-modal="true" aria-labelledby="createRiderTitle">
    <header class="modal-head">
      <div class="modal-head-main"><span class="modal-icon"><x-admin-icon name="account" /></span><div class="modal-head-copy"><h3 id="createRiderTitle">Create rider account</h3><p>Add a courier and assign their hub, coverage, and application details.</p></div></div>
      <button type="button" class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
    </header>
    <div class="modal-body rider-create-body">
      @if($errors->any())<div class="acf-alert" role="alert">{{ $errors->first() }}</div>@endif
      <form data-registration-form data-registration-dirty="{{ $errors->any() ? 'true' : 'false' }}" method="POST" enctype="multipart/form-data" action="{{ route('logistics.riders.accounts.store') }}">@csrf
        @include('logistics.partials.account-form', ['account' => null, 'creationRole' => 'rider'])
      </form>
    </div>
  </section>
</div>

<div class="card rider-applications-card">
  <div class="card-head rider-applications-heading">
    <div class="rider-applications-title"><span class="rider-heading-icon"><x-admin-icon name="users" /></span><div><h2>Courier Applications</h2><p>Review rider applications and manage their status.</p></div></div>
    <span class="rider-total-count">{{ $riders->total() }} in this view</span>
  </div>
  <div class="card-pad rider-applications-body">
    <div data-tabs id="riderTabs" class="rider-status-tabs" aria-label="Filter riders by status">
      <a class="tab {{ $status === 'all' ? 'active' : '' }}" href="{{ route('logistics.riders', ['search'=>request('search'), 'status'=>'all']) }}">All <span class="tab-count">{{ $statusCounts->sum() }}</span></a>
      <a class="tab {{ $status === 'pending' ? 'active' : '' }}" href="{{ route('logistics.riders', ['search' => request('search'), 'status' => 'pending']) }}">Pending <span class="tab-count">{{ $statusCounts['pending'] ?? 0 }}</span></a>
      <a class="tab {{ $status === 'approved' ? 'active' : '' }}" href="{{ route('logistics.riders', ['search' => request('search'), 'status' => 'approved']) }}">Approved <span class="tab-count">{{ $statusCounts['approved'] ?? 0 }}</span></a>
      <a class="tab {{ $status === 'rejected' ? 'active' : '' }}" href="{{ route('logistics.riders', ['search' => request('search'), 'status' => 'rejected']) }}">Rejected <span class="tab-count">{{ $statusCounts['rejected'] ?? 0 }}</span></a>
      <a class="tab {{ $status === 'suspended' ? 'active' : '' }}" href="{{ route('logistics.riders', ['search' => request('search'), 'status' => 'suspended']) }}">Suspended <span class="tab-count">{{ $statusCounts['suspended'] ?? 0 }}</span></a>
    </div>

    <div class="table-wrap">
      <table class="dtable">
        <thead>
          <tr><th>Applicant</th><th>Vehicle &amp; plate</th><th>Coverage</th><th>Submitted</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          @forelse($riders as $rider)
          @php($activationInvitation = app(\App\Services\RiderActivation::class)->invitation($rider))
          @php($activationPending = $activationInvitation && !$activationInvitation->activated_at)
          <tr>
            <td>
              <div class="cell-user">
                <x-user-avatar :user="$rider" size="30" class="avatar-sm" />
                <div><strong>{{ $rider->given_names }} {{ $rider->last_name }}</strong><span>{{ $rider->email }}</span></div>
              </div>
            </td>
            <td>{{ $rider->vehicle_type ? \Illuminate\Support\Str::title(str_replace('_', ' ', $rider->vehicle_type)) : '—' }}<div class="mono" style="font-size:11px;color:var(--muted)">{{ $rider->vehicle_ownership === 'company' ? 'Company vehicle' : ($rider->plate_number ?: 'Plate not provided') }}</div></td>
            <td>@if($coverageInstalled)<span>{{ $rider->barangayRoutes->where('hub_id', $rider->logistics_hub_id)->count() }} barangay routes</span><br><a href="{{ route('logistics.riders.coverage.edit', $rider->id) }}">Manage coverage</a>@else Coverage migration pending @endif</td>
            <td class="mono">{{ $rider->created_at?->format('M d, Y') ?? '—' }}</td>
            <td><span class="stamp stamp-{{ $rider->status }}">{{ $activationPending && $rider->status === 'pending' ? 'Pending activation' : ucfirst($rider->status) }}</span></td>
            <td>
              <div class="row-actions">
                <a class="btn btn-sm btn-outline" href="{{ route('logistics.accounts.edit', $rider->id) }}">Edit / hub assignment</a>
                <button class="btn btn-sm btn-outline" data-modal-open="riderModal-{{ $rider->id }}">Review</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="riderModal-{{ $rider->id }}">
            <div class="modal modal-lg rider-application-modal">
              <div class="modal-head">
                <div class="modal-head-main">
                  <span class="modal-icon"><x-admin-icon name="truck" /></span>
                  <div class="modal-head-copy">
                    <h3>Review Courier Application</h3>
                    <p>{{ $rider->given_names }} {{ $rider->last_name }}</p>
                  </div>
                </div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="account" /></span><span>Personal Information</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Full Name</div><div class="field-value">{{ $rider->given_names }} {{ $rider->middle_name ? $rider->middle_name.' ' : '' }}{{ $rider->last_name }}</div></div>
                    <div><div class="field-label">Username</div><div class="field-value mono">{{ $rider->username ?? '—' }}</div></div>
                    <div><div class="field-label">Sex</div><div class="field-value">{{ ucfirst($rider->sex ?? '—') }}</div></div>
                    <div><div class="field-label">Birthday</div><div class="field-value mono">{{ $rider->birthday?->format('M d, Y') ?? '—' }}</div></div>
                    <div><div class="field-label">Age</div><div class="field-value">{{ $rider->age ?? '—' }}</div></div>
                    <div><div class="field-label">Contact No.</div><div class="field-value mono">{{ $rider->contact_no }}</div></div>
                  </div>
                </div>

                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="pin" /></span><span>Address</span></div>
                  <div class="detail-grid">
                    <div class="full"><div class="field-label">Full Address</div><div class="field-value">{{ collect([$rider->house_no, $rider->street, $rider->barangay, $rider->municipality, $rider->province])->filter()->implode(', ') ?: '—' }}</div></div>
                  </div>
                </div>

                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="truck" /></span><span>Vehicle Information</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Vehicle Type</div><div class="field-value">{{ $rider->vehicle_type ? \Illuminate\Support\Str::title(str_replace('_',' ',$rider->vehicle_type)) : '—' }}</div></div>
                    <div><div class="field-label">Ownership</div><div class="field-value">{{ $rider->vehicle_ownership === 'own' ? 'Own Vehicle' : ($rider->vehicle_ownership === 'company' ? 'Company Vehicle' : '—') }}</div></div>
                    @if($rider->vehicle_brand)
                    <div><div class="field-label">Brand / Model</div><div class="field-value">{{ $rider->vehicle_brand }} {{ $rider->vehicle_model }}</div></div>
                    @endif
                    @if($rider->plate_number)
                    <div><div class="field-label">Plate Number</div><div class="field-value mono">{{ $rider->plate_number }}</div></div>
                    @endif
                    @if($rider->license_number)
                    <div><div class="field-label">License No.</div><div class="field-value mono">{{ $rider->license_number }}</div></div>
                    <div><div class="field-label">License Expiry</div><div class="field-value mono">{{ $rider->license_expiry?->format('M d, Y') }}</div></div>
                    @endif
                  </div>
                </div>

                @if($coverageInstalled)
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="pin" /></span><span>Barangay Coverage</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Assigned Hub</div><div class="field-value">{{ $rider->logisticsHub?->municipality ?? '—' }}, {{ $rider->logisticsHub?->province ?? '' }}</div></div>
                    <div><div class="field-label">Covered Barangays</div><div class="field-value">{{ $rider->barangayRoutes->where('hub_id', $rider->logistics_hub_id)->pluck('barangay_name')->implode(', ') ?: 'No barangay coverage assigned' }}</div></div>
                    <div class="full"><a class="btn btn-sm btn-outline" href="{{ route('logistics.riders.coverage.edit', $rider->id) }}">Manage barangay routes</a></div>
                  </div>
                </div>
                @endif

                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="shield" /></span><span>Verification Documents</span></div>
                  <div class="doc-grid">
                    <x-admin-doc-thumb :path="$rider->selfie_file" label="Selfie with License" />
                    <x-admin-doc-thumb :path="$rider->license_file" label="Driver's License" />
                    @if($rider->vehicle_ownership === 'own')
                    <x-admin-doc-thumb :path="$rider->or_file" label="OR" />
                    <x-admin-doc-thumb :path="$rider->cr_file" label="CR" />
                    @endif
                  </div>
                </div>
              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Close</button>
                @if($rider->status === 'pending')
                  <button type="button" class="btn btn-outline-danger" onclick="openReasonModal('reject', '{{ route('logistics.riders.reject', $rider->id) }}')">Reject</button>
                  @if($activationPending)
                  <div role="status"><p>Activation recipient: {{ $rider->email }}</p><p>{{ $activationInvitation->delivery_status === 'sent' ? 'Accepted by the mail server. Check Inbox and Spam; use the newest email.' : ($activationInvitation->delivery_status === 'failed' ? 'Email sending failed. Verify the address and resend.' : 'Email has not been sent yet.') }}</p></div>
                  <form method="POST" action="{{ route('logistics.riders.activation.resend', $rider->id) }}">@csrf<button class="btn btn-primary" type="submit">Resend activation email</button></form>
                  @else
                  <form method="POST" enctype="multipart/form-data" action="{{ route('logistics.riders.approve', $rider->id) }}" style="display:inline" data-confirm data-confirm-title="Approve this Rider?" data-confirm-message="This will approve the Rider account and enable the next activation step." data-confirm-action="Approve Rider" data-confirm-tone="success">
                    @csrf @method('PATCH')
                    <button class="btn btn-success" type="submit">Approve</button>
                  </form>
                  @endif
                @elseif($rider->status === 'rejected')
                  <span>{{ $rider->can_resubmit ? 'Resubmission allowed' : 'Final rejection' }}</span>
                  <button type="button" class="btn btn-outline" onclick="openReasonModal('reject', '{{ route('logistics.riders.reject', $rider->id) }}')">Update rejection decision</button>
                  @if($activationPending && $rider->can_resubmit)<form method="POST" action="{{ route('logistics.riders.activation.resend', $rider->id) }}">@csrf<button class="btn btn-primary" type="submit">Resend correction link</button></form>@endif
                @elseif($rider->status === 'approved')
                  <button type="button" class="btn btn-outline-danger" onclick="openReasonModal('suspend', '{{ route('logistics.riders.suspend', $rider->id) }}')">Suspend</button>
                @elseif($rider->status === 'suspended')
                  <button type="button" class="btn btn-success" onclick="openReasonModal('activate', '{{ route('logistics.riders.activate', $rider->id) }}')">Reactivate</button>
                @endif
              </div>
            </div>
          </div>
          @empty
          <tr><td colspan="6"><div class="empty"><div class="ic"><x-admin-icon name="truck" /></div><h3>No {{ $status }} courier applications</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@include('admin.partials.doc-lightbox')
@include('admin.partials.reason-modals', ['reasonModalTypes'=>['reject','suspend','activate']])
{{ $riders->links() }}
<script src="{{ asset('js/registration-unsaved.js') }}?v={{ filemtime(public_path('js/registration-unsaved.js')) }}"></script>
<script src="{{ asset('js/license-number-input.js') }}?v={{ filemtime(public_path('js/license-number-input.js')) }}"></script>
</section>
@endsection
