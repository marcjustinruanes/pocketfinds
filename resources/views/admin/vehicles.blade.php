@extends('admin.layout')
@section('title', 'Company Vehicles')
@section('page-title', 'Company Vehicles')
@section('page-sub', 'Review vehicles submitted by logistics companies — approved vehicles become available for rider assignment at their assigned hub')

@section('content')
@if(session('success'))
<div class="modal-overlay open" id="vehicleActionResultModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-body" style="text-align:center;padding:30px 26px 20px">
      <span style="width:48px;height:48px;border-radius:50%;background:var(--success-soft);color:var(--success);display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
        <x-admin-icon name="check-circle" />
      </span>
      <h3 style="font-family:var(--font-display);font-size:16px;margin:0 0 6px">Done</h3>
      <p style="font-size:13px;color:var(--muted);margin:0">{{ session('success') }}</p>
    </div>
    <div class="modal-foot" style="justify-content:center">
      <button class="btn btn-primary" type="button" data-modal-close>OK</button>
    </div>
  </div>
</div>
@endif

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr)">
  <button type="button" class="kpi kpi-filter active" data-status-kpi="">
    <div class="label">Total Vehicles</div>
    <div class="value">{{ $vehicles->count() }}</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="pending">
    <div class="label">Awaiting Review</div>
    <div class="value">{{ $platformCounts['pending'] ?? 0 }}</div>
    <div class="delta {{ ($platformCounts['pending'] ?? 0) > 0 ? 'down' : 'up' }}">Needs attention</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="approved">
    <div class="label">Approved</div>
    <div class="value">{{ $platformCounts['approved'] ?? 0 }}</div>
    <div class="delta up">Available to riders</div>
  </button>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Vehicle Submissions</h2><p>{{ $vehicles->count() }} total</p></div>
  </div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic"><x-admin-icon name="search" /></span>
        <input type="text" placeholder="Search vehicle, company or hub..." data-table-search="vehiclesTable">
      </div>
      <select class="select" id="vehicleStatusFilter">
        <option value="">All Status</option>
        <option value="pending">Awaiting Review</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
    </div>

    <div class="table-wrap">
      <table class="dtable" id="vehiclesTable">
        <thead>
          <tr><th>Vehicle</th><th>Type</th><th>Company</th><th>Hub</th><th>Submitted By</th><th>Status</th><th>Submitted</th><th></th></tr>
        </thead>
        <tbody>
          @forelse($vehicles as $vehicle)
          <tr data-status="{{ $vehicle->platform_status }}">
            <td>
              <strong>{{ $vehicle->brand }} {{ $vehicle->model }}</strong>
              <div class="mono" style="font-size:11.5px;color:var(--muted)">{{ $vehicle->plate_number }}</div>
            </td>
            <td style="font-size:12.5px">{{ str_replace('_', ' ', ucfirst($vehicle->vehicle_type)) }}</td>
            <td style="font-size:12.5px">{{ $vehicle->company_name }}</td>
            <td style="font-size:12.5px">
              {{ $vehicle->hub->municipality ?? '—' }}
              @if($vehicle->hub?->province)
                <span style="color:var(--muted)">· {{ $vehicle->hub->province }}</span>
              @endif
            </td>
            <td>
              <div class="cell-user">
                <div class="avatar-sm">{{ strtoupper(substr($vehicle->submitter->given_names ?? '?', 0, 1)) }}</div>
                <div>
                  <strong>{{ $vehicle->submitter->given_names ?? '—' }} {{ $vehicle->submitter->last_name ?? '' }}</strong>
                  <span>{{ $vehicle->company_name }}</span>
                </div>
              </div>
            </td>
            <td>
              @if($vehicle->platform_status === 'pending')
                <span class="stamp stamp-pending">Pending</span>
              @elseif($vehicle->platform_status === 'approved')
                <span class="stamp stamp-active">Approved</span>
                <span class="stamp {{ $vehicle->is_available ? 'stamp-active' : 'stamp-suspended' }}" style="margin-left:4px">
                  {{ $vehicle->is_available ? 'Available' : 'Maintenance' }}
                </span>
              @elseif($vehicle->platform_status === 'rejected')
                <span class="stamp stamp-rejected">Rejected</span>
              @endif
            </td>
            <td class="mono" style="font-size:12px">{{ $vehicle->created_at?->format('M d, Y') ?? '—' }}</td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="vehicleModal-{{ $vehicle->id }}">
                  <x-admin-icon name="eye" /> Review
                </button>
              </div>
            </td>
          </tr>
          @empty
          <tr><td colspan="8">
            <div class="empty">
              <div class="ic"><x-admin-icon name="shield" /></div>
              <h3>No vehicle submissions yet</h3>
              <p>Logistics companies submit vehicles from their fleet page — they'll appear here for review.</p>
            </div>
          </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Per-vehicle review modal --}}
@foreach($vehicles as $vehicle)
<div class="modal-overlay" id="vehicleModal-{{ $vehicle->id }}">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-head-main">
        <span class="modal-icon"><x-admin-icon name="shield" /></span>
        <div class="modal-head-copy">
          <h3>{{ $vehicle->brand }} {{ $vehicle->model }}
            @if($vehicle->platform_status === 'pending')<span class="stamp stamp-pending">Pending</span>
            @elseif($vehicle->platform_status === 'approved')<span class="stamp stamp-active">Approved</span>
            @elseif($vehicle->platform_status === 'rejected')<span class="stamp stamp-rejected">Rejected</span>@endif
          </h3>
          <p>{{ $vehicle->company_name }} · Submitted {{ $vehicle->created_at?->format('M d, Y g:i A') ?? '—' }}</p>
        </div>
      </div>
      <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
    </div>

    <div class="modal-body">
      <div class="section-card">
        <div class="section-head"><span class="ic"><x-admin-icon name="shield" /></span><span>Vehicle Details</span></div>
        <div class="detail-grid">
          <div><div class="field-label">Brand</div><div class="field-value">{{ $vehicle->brand }}</div></div>
          <div><div class="field-label">Model</div><div class="field-value">{{ $vehicle->model }}</div></div>
          <div><div class="field-label">Plate Number</div><div class="field-value mono">{{ $vehicle->plate_number }}</div></div>
          <div><div class="field-label">Vehicle Type</div><div class="field-value">{{ str_replace('_', ' ', ucfirst($vehicle->vehicle_type)) }}</div></div>
          <div><div class="field-label">Assigned Hub</div><div class="field-value">{{ $vehicle->hub->municipality ?? '—' }}, {{ $vehicle->hub->province ?? '—' }}</div></div>
          <div><div class="field-label">Company</div><div class="field-value">{{ $vehicle->company_name }}</div></div>
          <div><div class="field-label">Submitted By</div><div class="field-value">{{ $vehicle->submitter->given_names ?? '—' }} {{ $vehicle->submitter->last_name ?? '' }}</div></div>
          <div><div class="field-label">Date Submitted</div><div class="field-value mono">{{ $vehicle->created_at?->format('M d, Y g:i A') ?? '—' }}</div></div>
        </div>
      </div>

      @if($vehicle->platform_status === 'approved')
      <div class="section-card" style="background:var(--success-soft);border-color:var(--success-line)">
        <div class="section-head"><span class="ic" style="color:var(--success)"><x-admin-icon name="check-circle" /></span><span style="color:var(--success)">Approved</span></div>
        <p style="margin:0;font-size:12.5px">
          Approved by {{ $vehicle->platformReviewer?->given_names }} {{ $vehicle->platformReviewer?->last_name ?? 'Admin' }}
          on {{ $vehicle->platform_reviewed_at?->format('M d, Y') ?? '—' }}.
          Currently <strong>{{ $vehicle->is_available ? 'available' : 'in maintenance' }}</strong> at the hub.
        </p>
      </div>
      @endif

      @if($vehicle->platform_status === 'rejected' && $vehicle->platform_status_reason)
      <div class="section-card" style="background:var(--danger-soft);border-color:var(--danger-line)">
        <div class="section-head"><span class="ic" style="color:var(--danger)"><x-admin-icon name="flag" /></span><span style="color:var(--danger)">Rejection Reason</span></div>
        <p style="margin:0;font-size:12.5px;color:var(--danger)">{{ $vehicle->platform_status_reason }}</p>
      </div>
      @endif
    </div>

    <div class="modal-foot">
      <button type="button" class="btn btn-outline" data-modal-close>Close</button>
      @if($vehicle->platform_status === 'pending')
        <button type="button" class="btn btn-outline-danger"
          onclick="openReasonModal('reject', '{{ route('admin.vehicles.reject', $vehicle->id) }}')">
          <x-admin-icon name="close" /> Reject
        </button>
        <form method="POST" action="{{ route('admin.vehicles.approve', $vehicle->id) }}" style="display:inline">
          @csrf @method('PATCH')
          <button class="btn btn-success" type="submit"><x-admin-icon name="edit" /> Approve Vehicle</button>
        </form>
      @elseif($vehicle->platform_status === 'approved')
        <span style="font-size:12.5px;color:var(--muted);display:inline-flex;align-items:center;gap:6px">
          <x-admin-icon name="check-circle" /> Already approved — hub staff manages availability
        </span>
      @elseif($vehicle->platform_status === 'rejected')
        <form method="POST" action="{{ route('admin.vehicles.approve', $vehicle->id) }}" style="display:inline">
          @csrf @method('PATCH')
          <button class="btn btn-success" type="submit"><x-admin-icon name="edit" /> Approve Anyway</button>
        </form>
      @endif
    </div>
  </div>
</div>
@endforeach

@include('admin.partials.reason-modals', ['reasonModalTypes' => ['reject']])

<script>
// Wire the status dropdown to filter the table rows
document.getElementById('vehicleStatusFilter')?.addEventListener('change', function () {
  const val = this.value;
  document.querySelectorAll('#vehiclesTable tbody tr').forEach(row => {
    row.style.display = (!val || row.dataset.status === val) ? '' : 'none';
  });
});
</script>
@endsection
