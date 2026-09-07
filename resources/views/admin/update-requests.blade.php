@extends('admin.layout')
@section('title', 'Update Requests')
@section('page-title', 'Update Requests')
@section('page-sub', 'Review account changes submitted by buyers, sellers, riders, and logistics staff')

@section('content')
@if(session('success'))
  <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>
@endif

@php
  $pendingCount  = $requests->where('status', 'pending')->count();
  $approvedCount = $requests->where('status', 'approved')->count();
  $rejectedCount = $requests->where('status', 'rejected')->count();

  // Human-friendly labels for every field this system can carry.
  $fieldLabels = [
    'given_names' => 'First Name', 'last_name' => 'Last Name', 'middle_name' => 'Middle Name',
    'contact_no' => 'Contact No.', 'sex' => 'Sex', 'birthday' => 'Birthday',
    'province' => 'Province', 'municipality' => 'Municipality / City', 'barangay' => 'Barangay',
    'house_no' => 'House No.', 'street' => 'Street',
    'business_name' => 'Business Name', 'username' => 'Username', 'category_id' => 'Shop Category', 'shipping_fee' => 'Shipping Fee',
    'id_type_id' => 'ID Type',
  ];
  $documentLabels = ['id_file' => 'ID File', 'business_permit_file' => 'Business Permit'];
@endphp

<div class="kpi-grid">
  <button type="button" class="kpi kpi-filter" data-status-kpi="">
    <div class="label">Total Requests</div>
    <div class="value">{{ $requests->count() }}</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="pending">
    <div class="label">Pending Review</div>
    <div class="value">{{ $pendingCount }}</div>
    <div class="delta {{ $pendingCount > 0 ? 'down' : 'up' }}">Needs attention</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="approved">
    <div class="label">Approved</div>
    <div class="value">{{ $approvedCount }}</div>
    <div class="delta up">Applied to account</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="rejected">
    <div class="label">Rejected</div>
    <div class="value">{{ $rejectedCount }}</div>
  </button>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Account Update Requests</h2><p>{{ $requests->count() }} total</p></div>
  </div>
  <div class="card-pad">
    <div class="table-wrap">
      <table class="dtable" id="updateRequestsTable">
        <thead>
          <tr><th>User</th><th>Role</th><th>Submitted</th><th>Requested Changes</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          @forelse($requests as $req)
          <tr class="rail-row rail-{{ $req->status }}" data-status="{{ $req->status }}">
            <td>
              <div class="cell-user">
                <div class="avatar-sm">{{ strtoupper(substr($req->user->given_names,0,1).substr($req->user->last_name,0,1)) }}</div>
                <div>
                  <strong>{{ $req->user->given_names }} {{ $req->user->last_name }}</strong>
                  <span>{{ $req->user->email }}</span>
                </div>
              </div>
            </td>
            <td><span class="stamp stamp-approved">{{ ucfirst($req->user->account_type) }}</span></td>
            <td class="mono" style="font-size:12px">{{ $req->created_at?->format('M d, Y h:i A') ?? '—' }}</td>
            <td>
              <div style="display:flex;flex-direction:column;gap:6px;max-width:340px">
                @forelse($req->requested_changes ?? [] as $field => $value)
                  <div style="font-size:12.5px">
                    <strong>{{ $fieldLabels[$field] ?? \Illuminate\Support\Str::headline($field) }}:</strong>
                    <span style="color:var(--muted)">was</span> {{ $req->user->{$field} ?? '—' }}
                    <span style="color:var(--muted)">→</span>
                    <span style="font-weight:600">{{ $field === 'id_type_id' ? ($idTypes[$value]->name ?? $value) : $value }}</span>
                  </div>
                @empty
                @endforelse
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  @foreach($req->requested_documents ?? [] as $docKey => $docPath)
                    <button type="button" class="btn btn-sm btn-outline" data-doc-trigger
                      data-src="{{ asset('storage/'.$docPath) }}"
                      data-type="{{ in_array(strtolower(pathinfo($docPath,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf' }}"
                      data-title="{{ $documentLabels[$docKey] ?? \Illuminate\Support\Str::headline($docKey) }}">
                      <x-admin-icon name="eye" /> {{ $documentLabels[$docKey] ?? \Illuminate\Support\Str::headline($docKey) }}
                    </button>
                  @endforeach
                </div>
                @if(empty($req->requested_changes) && empty($req->requested_documents))
                  <span style="color:var(--muted);font-size:12px">None</span>
                @endif
              </div>
            </td>
            <td><span class="stamp stamp-{{ $req->status }}">{{ ucfirst($req->status) }}</span></td>
            <td>
              @if($req->status === 'pending')
                <div style="display:flex;gap:6px">
                  <form method="POST" action="{{ route('admin.update-requests.approve', $req->id) }}">
                    @csrf @method('PATCH')
                    <button class="btn btn-sm btn-success" type="submit">Approve</button>
                  </form>
                  <button class="btn btn-sm btn-outline-danger" onclick="openReject({{ $req->id }})">Reject</button>
                </div>
              @else
                <span style="font-size:12px;color:var(--muted)">{{ $req->reviewed_at?->format('M d, Y') ?? '—' }}</span>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="6"><div class="empty"><div class="ic"><x-admin-icon name="file" /></div><h3>No update requests</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Reject modal --}}
<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-head-main">
        <span class="modal-icon" style="background:var(--danger-soft);color:var(--danger)"><x-admin-icon name="close" /></span>
        <div class="modal-head-copy">
          <h3>Reject Request</h3>
          <p>Let the user know why their requested changes were declined.</p>
        </div>
      </div>
      <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
    </div>
    <form id="rejectForm" method="POST">
      @csrf @method('PATCH')
      <div class="modal-body">
        <div class="form-row"><label>Reason (optional)</label><textarea name="note" rows="3" placeholder="Tell the user why their request was rejected…"></textarea></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger">Reject</button>
      </div>
    </form>
  </div>
</div>

<script>
function applyUpdateRequestFilter(val) {
  document.querySelectorAll('#updateRequestsTable tbody tr[data-status]').forEach(row => {
    row.style.display = (!val || row.dataset.status === val) ? '' : 'none';
  });
  document.querySelectorAll('.kpi-filter').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.statusKpi === val);
  });
}
document.querySelectorAll('.kpi-filter').forEach(btn => {
  btn.addEventListener('click', () => applyUpdateRequestFilter(btn.dataset.statusKpi));
});
applyUpdateRequestFilter('');

function openReject(id) {
  document.getElementById('rejectForm').action = `/admin/update-requests/${id}/reject`;
  document.getElementById('rejectModal').classList.add('open');
}
</script>

@include('admin.partials.doc-lightbox')
@endsection
