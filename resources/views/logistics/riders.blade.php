@extends('logistics.layout')
@section('title', 'Rider Management')
@section('page-title', 'Rider Management')
@section('page-sub', "Approve/disapprove courier applications, activate or suspend riders")

@section('content')
@if(session('success'))<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>@endif

<div class="card">
  <div class="card-head">
    <div><h2>Courier Applications</h2><p>{{ $riders->count() }} in this view</p></div>
  </div>
  <div class="card-pad">
    <div data-tabs id="riderTabs">
      <a class="tab {{ $status === 'pending' ? 'active' : '' }}" href="{{ route('logistics.riders', ['status' => 'pending']) }}">Pending <span class="tab-count">{{ $statusCounts['pending'] ?? 0 }}</span></a>
      <a class="tab {{ $status === 'approved' ? 'active' : '' }}" href="{{ route('logistics.riders', ['status' => 'approved']) }}">Approved <span class="tab-count">{{ $statusCounts['approved'] ?? 0 }}</span></a>
      <a class="tab {{ $status === 'rejected' ? 'active' : '' }}" href="{{ route('logistics.riders', ['status' => 'rejected']) }}">Rejected <span class="tab-count">{{ $statusCounts['rejected'] ?? 0 }}</span></a>
      <a class="tab {{ $status === 'suspended' ? 'active' : '' }}" href="{{ route('logistics.riders', ['status' => 'suspended']) }}">Suspended <span class="tab-count">{{ $statusCounts['suspended'] ?? 0 }}</span></a>
    </div>

    <div class="table-wrap">
      <table class="dtable">
        <thead>
          <tr><th>Applicant</th><th>Vehicle</th><th>Plate No.</th><th>Submitted</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          @forelse($riders as $rider)
          <tr>
            <td>
              <div class="cell-user">
                <x-user-avatar :user="$rider" size="30" class="avatar-sm" />
                <div><strong>{{ $rider->given_names }} {{ $rider->last_name }}</strong><span>{{ $rider->email }}</span></div>
              </div>
            </td>
            <td>{{ $rider->vehicle_type ? \Illuminate\Support\Str::title(str_replace('_', ' ', $rider->vehicle_type)) : '—' }}</td>
            <td class="mono">{{ $rider->vehicle_ownership === 'company' ? 'Company Vehicle' : ($rider->plate_number ?: '—') }}</td>
            <td class="mono">{{ $rider->created_at?->format('M d, Y') ?? '—' }}</td>
            <td><span class="stamp stamp-{{ $rider->status }}">{{ ucfirst($rider->status) }}</span></td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="riderModal-{{ $rider->id }}">Review</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="riderModal-{{ $rider->id }}">
            <div class="modal modal-lg">
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
                  <form method="POST" action="{{ route('logistics.riders.reject', $rider->id) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <button class="btn btn-outline-danger" type="submit">Reject</button>
                  </form>
                  <form method="POST" action="{{ route('logistics.riders.approve', $rider->id) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <button class="btn btn-success" type="submit">Approve</button>
                  </form>
                @elseif($rider->status === 'approved')
                  <form method="POST" action="{{ route('logistics.riders.suspend', $rider->id) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <button class="btn btn-outline-danger" type="submit">Suspend</button>
                  </form>
                @elseif($rider->status === 'suspended')
                  <form method="POST" action="{{ route('logistics.riders.activate', $rider->id) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <button class="btn btn-success" type="submit">Activate</button>
                  </form>
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
@endsection
