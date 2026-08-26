@extends('admin.layout')
@section('title', 'Seller Compliance')
@section('page-title', 'Seller Compliance')
@section('page-sub', 'Review seller documents and listings')
@php use Illuminate\Support\Facades\Storage; @endphp

@section('content')
<div class="card">
  <div class="card-head">
    <div><h2>Seller Accounts</h2><p>{{ $sellers->count() }} sellers</p></div>
  </div>
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
        <thead>
          <tr><th>Seller</th><th>Business</th><th>Contact</th><th>Registered</th><th>Documents</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          @forelse($sellers as $seller)
          <tr class="rail-row rail-{{ $seller->status }}" data-type="{{ $seller->status }}">
            <td>
              <div class="cell-user">
                <x-user-avatar :user="$seller" size="30" class="avatar-sm" />
                <div><strong>{{ $seller->given_names }} {{ $seller->last_name }}</strong><span>{{ $seller->email }}</span></div>
              </div>
            </td>
            <td style="font-size:12.5px">{{ $seller->business_name ?? '—' }}</td>
            <td class="mono">{{ $seller->contact_no }}</td>
            <td class="mono">{{ $seller->created_at->format('M d, Y') }}</td>
            <td>
              <div style="display:flex;gap:5px;flex-wrap:wrap">
                @if($seller->id_file)
                  <a href="{{ Storage::url($seller->id_file) }}" target="_blank" class="doc-chip" style="padding:4px 8px;font-size:11px">📄 ID</a>
                @endif
                @if($seller->selfie_file)
                  <a href="{{ Storage::url($seller->selfie_file) }}" target="_blank" class="doc-chip" style="padding:4px 8px;font-size:11px">🤳 Selfie</a>
                @endif
                @if($seller->business_permit_file)
                  <a href="{{ Storage::url($seller->business_permit_file) }}" target="_blank" class="doc-chip" style="padding:4px 8px;font-size:11px">📋 Permit</a>
                @endif
                @if(!$seller->id_file && !$seller->selfie_file && !$seller->business_permit_file)
                  <span style="color:var(--muted);font-size:12px">None</span>
                @endif
              </div>
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
                <div><h3>Compliance Review</h3><p>{{ $seller->given_names }} {{ $seller->last_name }} — {{ $seller->business_name ?? 'No business name' }}</p></div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">

                {{-- Personal --}}
                <p class="crumb" style="margin-bottom:10px">Personal Information</p>
                <div class="detail-grid">
                  <div><div class="field-label">Full Name</div><div class="field-value">{{ $seller->given_names }} {{ $seller->middle_name ? $seller->middle_name.' ' : '' }}{{ $seller->last_name }}</div></div>
                  <div><div class="field-label">Username</div><div class="field-value mono">{{ $seller->username ?? '—' }}</div></div>
                  <div><div class="field-label">Email</div><div class="field-value">{{ $seller->email }}</div></div>
                  <div><div class="field-label">Contact No.</div><div class="field-value mono">{{ $seller->contact_no }}</div></div>
                  <div><div class="field-label">Sex</div><div class="field-value">{{ ucfirst($seller->sex ?? '—') }}</div></div>
                  <div><div class="field-label">Birthday</div><div class="field-value mono">{{ $seller->birthday?->format('M d, Y') ?? '—' }}</div></div>
                  <div><div class="field-label">Age</div><div class="field-value">{{ $seller->age ?? '—' }}</div></div>
                  <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-{{ $seller->status }}">{{ ucfirst($seller->status) }}</span></div></div>
                  <div class="full"><div class="field-label">Address</div><div class="field-value">{{ collect([$seller->house_no, $seller->street, $seller->barangay, $seller->municipality, $seller->province])->filter()->implode(', ') ?: '—' }}</div></div>
                </div>

                {{-- Seller details --}}
                <p class="crumb" style="margin:14px 0 10px">Seller Details</p>
                <div class="detail-grid">
                  <div><div class="field-label">Business Name</div><div class="field-value">{{ $seller->business_name ?? '—' }}</div></div>
                  <div><div class="field-label">Category</div><div class="field-value">{{ \App\Models\Category::find($seller->category_id)?->name ?? '—' }}</div></div>
                  <div><div class="field-label">Auth Method</div><div class="field-value">{{ ucfirst($seller->auth_method) }}</div></div>
                  <div><div class="field-label">Registered</div><div class="field-value mono">{{ $seller->created_at->format('M d, Y') }}</div></div>
                </div>

                {{-- Documents --}}
                <p class="crumb" style="margin:14px 0 10px">Submitted Documents</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                  @if($seller->id_file)
                  <a href="{{ Storage::url($seller->id_file) }}" target="_blank" class="doc-chip">📄 View ID</a>
                  @endif
                  @if($seller->selfie_file)
                  <a href="{{ Storage::url($seller->selfie_file) }}" target="_blank" class="doc-chip">🤳 View Selfie</a>
                  @endif
                  @if($seller->business_permit_file)
                  <a href="{{ Storage::url($seller->business_permit_file) }}" target="_blank" class="doc-chip">📋 Business Permit</a>
                  @endif
                  @if(!$seller->id_file && !$seller->selfie_file && !$seller->business_permit_file)
                  <span style="font-size:12px;color:var(--muted)">No documents uploaded.</span>
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
          <tr><td colspan="7"><div class="empty"><div class="ic"><x-admin-icon name="shield" /></div><h3>No sellers yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
