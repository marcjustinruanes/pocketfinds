@extends('admin.layout')
@section('title', 'Document Requests')
@section('page-title', 'Document Requests')
@section('page-sub', 'Review seller document update requests')

@section('content')
@if(session('success'))
  <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>
@endif

@php
  $pendingDocRequests  = $requests->where('status', 'pending')->count();
  $approvedDocRequests = $requests->where('status', 'approved')->count();
  $rejectedDocRequests = $requests->where('status', 'rejected')->count();
@endphp
<div class="kpi-grid">
  <button type="button" class="kpi kpi-filter" data-status-kpi="">
    <div class="label">Total Requests</div>
    <div class="value">{{ $requests->count() }}</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="pending">
    <div class="label">Pending Review</div>
    <div class="value">{{ $pendingDocRequests }}</div>
    <div class="delta {{ $pendingDocRequests > 0 ? 'down' : 'up' }}">Needs attention</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="approved">
    <div class="label">Approved</div>
    <div class="value">{{ $approvedDocRequests }}</div>
    <div class="delta up">Applied to account</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="rejected">
    <div class="label">Rejected</div>
    <div class="value">{{ $rejectedDocRequests }}</div>
  </button>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Document Update Requests</h2><p>{{ $requests->count() }} total</p></div>
  </div>
  <div class="card-pad">
    <div class="table-wrap">
      <table class="dtable" id="docRequestsTable">
        <thead>
          <tr><th>Seller</th><th>Submitted</th><th>ID Type</th><th>Files</th><th>Status</th><th></th></tr>
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
            <td class="mono" style="font-size:12px">{{ $req->created_at->format('M d, Y h:i A') }}</td>
            <td>{{ $req->id_type_id ? ($idTypes[$req->id_type_id]->name ?? '—') : '—' }}</td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                @if($req->id_file)
                  <button type="button" class="btn btn-sm btn-outline" data-doc-trigger
                    data-src="{{ asset('storage/'.$req->id_file) }}"
                    data-type="{{ in_array(strtolower(pathinfo($req->id_file,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf' }}"
                    data-title="ID File"><x-admin-icon name="eye" /> ID File</button>
                @endif
                @if($req->business_permit_file)
                  <button type="button" class="btn btn-sm btn-outline" data-doc-trigger
                    data-src="{{ asset('storage/'.$req->business_permit_file) }}"
                    data-type="{{ in_array(strtolower(pathinfo($req->business_permit_file,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf' }}"
                    data-title="Business Permit"><x-admin-icon name="eye" /> Permit</button>
                @endif
                @if(!$req->id_file && !$req->business_permit_file) <span style="color:var(--muted);font-size:12px">None</span> @endif
              </div>
            </td>
            <td><span class="stamp stamp-{{ $req->status }}">{{ ucfirst($req->status) }}</span></td>
            <td>
              @if($req->status === 'pending')
                <div style="display:flex;gap:6px">
                  <form method="POST" action="{{ route('admin.doc-requests.approve', $req->id) }}">
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
          <tr><td colspan="6"><div class="empty"><div class="ic"><x-admin-icon name="file" /></div><h3>No document requests</h3></div></td></tr>
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
          <p>Let the seller know why their document update was declined.</p>
        </div>
      </div>
      <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
    </div>
    <form id="rejectForm" method="POST">
      @csrf @method('PATCH')
      <div class="modal-body">
        <div class="form-row"><label>Reason (optional)</label><textarea name="note" rows="3" placeholder="Tell the seller why their request was rejected…"></textarea></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger">Reject</button>
      </div>
    </form>
  </div>
</div>

<script>
function applyDocRequestFilter(val) {
  document.querySelectorAll('#docRequestsTable tbody tr[data-status]').forEach(row => {
    row.style.display = (!val || row.dataset.status === val) ? '' : 'none';
  });
  document.querySelectorAll('.kpi-filter').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.statusKpi === val);
  });
}
document.querySelectorAll('.kpi-filter').forEach(btn => {
  btn.addEventListener('click', () => applyDocRequestFilter(btn.dataset.statusKpi));
});
applyDocRequestFilter('');

function openReject(id) {
  document.getElementById('rejectForm').action = `/admin/doc-requests/${id}/reject`;
  document.getElementById('rejectModal').classList.add('open');
}
</script>

@include('admin.partials.doc-lightbox')
@endsection
