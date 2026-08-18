@extends('admin.layout')
@section('title', 'Seller Compliance')
@section('page-title', 'Seller Compliance')
@section('page-sub', 'Review seller documents and listings')

@section('content')
<div class="card">
  <div class="card-head"><div><h2>Seller Accounts</h2><p>{{ $sellers->count() }} sellers</p></div></div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic"><x-admin-icon name="search" /></span>
        <input type="text" placeholder="Search seller..." data-table-search="compTable">
      </div>
    </div>
    <div data-tabs>
      <a class="tab active" data-tab="all">All</a>
      <a class="tab" data-tab="pending">Pending</a>
      <a class="tab" data-tab="approved">Approved</a>
      <a class="tab" data-tab="rejected">Rejected</a>
    </div>
    <div class="table-wrap">
      <table class="dtable" id="compTable">
        <thead><tr><th>Seller</th><th>Contact</th><th>Registered</th><th>ID File</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse($sellers as $seller)
          <tr class="rail-row rail-{{ $seller->status }}" data-type="{{ $seller->status }}">
            <td>
              <div class="cell-user">
                <div class="avatar-sm">{{ strtoupper(substr($seller->first_name,0,1).substr($seller->last_name,0,1)) }}</div>
                <div><strong>{{ $seller->first_name }} {{ $seller->last_name }}</strong><span>{{ $seller->email }}</span></div>
              </div>
            </td>
            <td class="mono">{{ $seller->contact_no }}</td>
            <td class="mono">{{ $seller->created_at->format('Y-m-d') }}</td>
            <td>
              @if($seller->id_file)
                <span class="doc-chip"><x-admin-icon name="file" /> {{ basename($seller->id_file) }}</span>
              @else
                <span style="color:var(--muted);font-size:12px">None</span>
              @endif
            </td>
            <td><span class="stamp stamp-{{ $seller->status }}">{{ ucfirst($seller->status) }}</span></td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="compModal-{{ $seller->id }}">Review</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="compModal-{{ $seller->id }}">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div><h3>Compliance Review</h3><p>{{ $seller->first_name }} {{ $seller->last_name }}</p></div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">
                <div class="detail-grid">
                  <div><div class="field-label">Name</div><div class="field-value">{{ $seller->first_name }} {{ $seller->last_name }}</div></div>
                  <div><div class="field-label">Email</div><div class="field-value">{{ $seller->email }}</div></div>
                  <div><div class="field-label">Contact</div><div class="field-value">{{ $seller->contact_no }}</div></div>
                  <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-{{ $seller->status }}">{{ ucfirst($seller->status) }}</span></div></div>
                  <div class="full"><div class="field-label">Address</div><div class="field-value">{{ $seller->house_no }} {{ $seller->street }}, {{ $seller->barangay }}, {{ $seller->municipality }}, {{ $seller->province }}</div></div>
                  @if($seller->id_file)
                  <div class="full"><div class="field-label">Submitted ID</div><div><span class="doc-chip"><x-admin-icon name="file" /> {{ basename($seller->id_file) }}</span></div></div>
                  @endif
                </div>
              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Cancel</button>
                <form method="POST" action="{{ route('admin.registrations.reject', $seller->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-danger" type="submit">Reject</button>
                </form>
                <form method="POST" action="{{ route('admin.registrations.approve', $seller->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-success" type="submit">Approve</button>
                </form>
              </div>
            </div>
          </div>
          @empty
          <tr><td colspan="6"><div class="empty"><div class="ic"><x-admin-icon name="shield" /></div><h3>No sellers yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
