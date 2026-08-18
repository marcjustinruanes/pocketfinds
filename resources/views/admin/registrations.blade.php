@extends('admin.layout')
@section('title', 'Registrations')
@section('page-title', 'Registrations')
@section('page-sub', 'Review and approve new account applications')

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
      <select class="select" onchange="filterType(this.value)">
        <option value="all">All Types</option>
        <option value="buyer">Buyer</option>
        <option value="seller">Seller</option>
        <option value="rider">Rider</option>
      </select>
    </div>

    <div data-tabs>
      <a class="tab active" data-tab="all">All</a>
      <a class="tab" data-tab="pending">Pending</a>
      <a class="tab" data-tab="approved">Approved</a>
      <a class="tab" data-tab="rejected">Rejected</a>
    </div>

    <div class="table-wrap">
      <table class="dtable" id="regTable">
        <thead><tr><th>Applicant</th><th>Type</th><th>Method</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse($users as $user)
          <tr class="rail-row rail-{{ $user->status }}" data-type="{{ $user->status }}">
            <td>
              <div class="cell-user">
                <div class="avatar-sm">{{ strtoupper(substr($user->first_name,0,1).substr($user->last_name,0,1)) }}</div>
                <div><strong>{{ $user->first_name }} {{ $user->last_name }}</strong><span>{{ $user->email }}</span></div>
              </div>
            </td>
            <td>{{ ucfirst($user->account_type) }}</td>
            <td>{{ ucfirst($user->auth_method) }}</td>
            <td class="mono">{{ $user->created_at->format('Y-m-d') }}</td>
            <td><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline"
                  data-modal-open="reviewModal-{{ $user->id }}">Review</button>
              </div>
            </td>
          </tr>

          {{-- Per-user modal --}}
          <div class="modal-overlay" id="reviewModal-{{ $user->id }}">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div><h3>Review Application</h3><p>{{ $user->first_name }} {{ $user->last_name }} — {{ ucfirst($user->account_type) }}</p></div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">
                <div class="detail-grid">
                  <div><div class="field-label">Full Name</div><div class="field-value">{{ $user->first_name }} {{ $user->middle_initial ? $user->middle_initial.'. ' : '' }}{{ $user->last_name }}</div></div>
                  <div><div class="field-label">Email</div><div class="field-value">{{ $user->email }}</div></div>
                  <div><div class="field-label">Account Type</div><div class="field-value">{{ ucfirst($user->account_type) }}</div></div>
                  <div><div class="field-label">Contact</div><div class="field-value">{{ $user->contact_no }}</div></div>
                  <div><div class="field-label">Sex</div><div class="field-value">{{ ucfirst($user->sex) }}</div></div>
                  <div><div class="field-label">Birthday</div><div class="field-value mono">{{ $user->birthday?->format('Y-m-d') }}</div></div>
                  <div class="full"><div class="field-label">Address</div><div class="field-value">{{ $user->house_no }} {{ $user->street }}, {{ $user->barangay }}, {{ $user->municipality }}, {{ $user->province }}</div></div>
                  <div><div class="field-label">Submitted</div><div class="field-value mono">{{ $user->created_at->format('Y-m-d') }}</div></div>
                  <div><div class="field-label">Auth Method</div><div class="field-value">{{ ucfirst($user->auth_method) }}</div></div>
                  @if($user->id_file)
                  <div class="full"><div class="field-label">Submitted ID</div><div class="field-value"><span class="doc-chip"><x-admin-icon name="file" /> {{ basename($user->id_file) }}</span></div></div>
                  @endif
                </div>
              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Cancel</button>
                <form method="POST" action="{{ route('admin.registrations.reject', $user->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-danger" type="submit">Reject</button>
                </form>
                <form method="POST" action="{{ route('admin.registrations.approve', $user->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-success" type="submit">Approve</button>
                </form>
              </div>
            </div>
          </div>
          @empty
          <tr><td colspan="6"><div class="empty"><div class="ic"><x-admin-icon name="edit" /></div><h3>No registrations yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
