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
        <span class="ic">🔍</span>
        <input type="text" placeholder="Search name or email…" data-table-search="regTable">
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
        <thead>
          <tr><th>Applicant</th><th>Type</th><th>Username</th><th>Method</th><th>Submitted</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          @forelse($users as $user)
          <tr class="rail-row rail-{{ $user->status }}" data-type="{{ $user->status }}">
            <td>
              <div class="cell-user">
                <div class="avatar-sm">{{ strtoupper(substr($user->given_names,0,1).substr($user->last_name,0,1)) }}</div>
                <div>
                  <strong>{{ $user->given_names }} {{ $user->last_name }}</strong>
                  <span>{{ $user->email }}</span>
                </div>
              </div>
            </td>
            <td>{{ ucfirst($user->account_type) }}</td>
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
                <div>
                  <h3>Review Application</h3>
                  <p>{{ $user->given_names }} {{ $user->last_name }} — {{ ucfirst($user->account_type) }}</p>
                </div>
                <button class="modal-close" data-modal-close>✕</button>
              </div>
              <div class="modal-body">

                {{-- Personal --}}
                <p class="crumb" style="margin-bottom:10px">Personal Information</p>
                <div class="detail-grid">
                  <div><div class="field-label">Full Name</div><div class="field-value">{{ $user->given_names }} {{ $user->middle_name ? $user->middle_name.' ' : '' }}{{ $user->last_name }}</div></div>
                  <div><div class="field-label">Username</div><div class="field-value mono">{{ $user->username ?? '—' }}</div></div>
                  <div><div class="field-label">Sex</div><div class="field-value">{{ ucfirst($user->sex ?? '—') }}</div></div>
                  <div><div class="field-label">Birthday</div><div class="field-value mono">{{ $user->birthday?->format('M d, Y') ?? '—' }}</div></div>
                  <div><div class="field-label">Age</div><div class="field-value">{{ $user->age ?? '—' }}</div></div>
                  <div><div class="field-label">Account Type</div><div class="field-value">{{ ucfirst($user->account_type) }}</div></div>
                </div>

                {{-- Contact --}}
                <p class="crumb" style="margin:14px 0 10px">Contact & Auth</p>
                <div class="detail-grid">
                  <div><div class="field-label">Email</div><div class="field-value">{{ $user->email }}</div></div>
                  <div><div class="field-label">Contact No.</div><div class="field-value mono">{{ $user->contact_no }}</div></div>
                  <div><div class="field-label">Auth Method</div><div class="field-value">{{ ucfirst($user->auth_method) }}</div></div>
                  <div><div class="field-label">Submitted</div><div class="field-value mono">{{ $user->created_at->format('M d, Y') }}</div></div>
                </div>

                {{-- Address --}}
                <p class="crumb" style="margin:14px 0 10px">Address</p>
                <div class="detail-grid">
                  <div class="full"><div class="field-label">Full Address</div><div class="field-value">{{ collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter()->implode(', ') ?: '—' }}</div></div>
                </div>

                {{-- Documents --}}
                <p class="crumb" style="margin:14px 0 10px">Submitted Documents</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                  @if($user->id_file)
                  <a href="{{ Storage::url($user->id_file) }}" target="_blank" class="doc-chip">📄 View ID</a>
                  @endif
                  @if($user->selfie_file)
                  <a href="{{ Storage::url($user->selfie_file) }}" target="_blank" class="doc-chip">🤳 View Selfie</a>
                  @endif
                  @if($user->account_type === 'seller' && $user->business_permit_file)
                  <a href="{{ Storage::url($user->business_permit_file) }}" target="_blank" class="doc-chip">📋 Business Permit</a>
                  @endif
                  @if(!$user->id_file && !$user->selfie_file)
                  <span style="font-size:12px;color:var(--muted)">No documents uploaded.</span>
                  @endif
                </div>

                {{-- Seller-specific --}}
                @if($user->account_type === 'seller' && $user->business_name)
                <p class="crumb" style="margin:14px 0 10px">Seller Details</p>
                <div class="detail-grid">
                  <div><div class="field-label">Business Name</div><div class="field-value">{{ $user->business_name }}</div></div>
                  @if($user->category_id)
                  <div><div class="field-label">Category</div><div class="field-value">{{ \App\Models\Category::find($user->category_id)?->name ?? '—' }}</div></div>
                  @endif
                </div>
                @endif

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
          <tr><td colspan="7"><div class="empty"><div class="ic">✎</div><h3>No registrations yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
