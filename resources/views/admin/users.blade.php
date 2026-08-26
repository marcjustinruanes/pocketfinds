@extends('admin.layout')
@section('title', 'User Accounts')
@section('page-title', 'User Accounts')
@section('page-sub', 'Manage all platform accounts')
@php use Illuminate\Support\Facades\Storage; @endphp

@section('content')
<div class="card">
  <div class="card-head">
    <div><h2>All Users</h2><p>{{ $users->count() }} total accounts</p></div>
  </div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic"><x-admin-icon name="search" /></span>
        <input type="text" placeholder="Search name, email or username…" data-table-search="usersTable">
      </div>
    </div>

    <div data-tabs>
      <a class="tab active" data-tab="all">All</a>
      <a class="tab" data-tab="buyer">Buyers</a>
      <a class="tab" data-tab="seller">Sellers</a>
      <a class="tab" data-tab="rider">Riders</a>
    </div>

    <div class="table-wrap">
      <table class="dtable" id="usersTable">
        <thead>
          <tr><th>User</th><th>Type</th><th>Username</th><th>Contact</th><th>Joined</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          @forelse($users as $user)
          <tr class="rail-row rail-{{ $user->status }}" data-type="{{ $user->account_type }}">
            <td>
              <div class="cell-user">
                <x-user-avatar :user="$user" size="30" class="avatar-sm" />
                <div><strong>{{ $user->given_names }} {{ $user->last_name }}</strong><span>{{ $user->email }}</span></div>
              </div>
            </td>
            <td>{{ ucfirst($user->account_type) }}</td>
            <td class="mono" style="font-size:12px">{{ $user->username ?? '—' }}</td>
            <td class="mono">{{ $user->contact_no }}</td>
            <td class="mono">{{ $user->created_at->format('M d, Y') }}</td>
            <td><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="userModal-{{ $user->id }}">Manage</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="userModal-{{ $user->id }}">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div><h3>Manage User</h3><p>{{ $user->given_names }} {{ $user->last_name }} — {{ ucfirst($user->account_type) }}</p></div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
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
                  <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></div></div>
                </div>

                {{-- Contact --}}
                <p class="crumb" style="margin:14px 0 10px">Contact & Auth</p>
                <div class="detail-grid">
                  <div><div class="field-label">Email</div><div class="field-value">{{ $user->email }}</div></div>
                  <div><div class="field-label">Contact No.</div><div class="field-value mono">{{ $user->contact_no }}</div></div>
                  <div><div class="field-label">Auth Method</div><div class="field-value">{{ ucfirst($user->auth_method) }}</div></div>
                  <div><div class="field-label">Joined</div><div class="field-value mono">{{ $user->created_at->format('M d, Y') }}</div></div>
                </div>

                {{-- Address --}}
                <p class="crumb" style="margin:14px 0 10px">Address</p>
                <div class="detail-grid">
                  <div class="full"><div class="field-label">Full Address</div><div class="field-value">{{ collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter()->implode(', ') ?: '—' }}</div></div>
                </div>

                {{-- Documents --}}
                <p class="crumb" style="margin:14px 0 10px">Documents</p>
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
                @if($user->account_type === 'seller')
                <p class="crumb" style="margin:14px 0 10px">Seller Details</p>
                <div class="detail-grid">
                  <div><div class="field-label">Business Name</div><div class="field-value">{{ $user->business_name ?? '—' }}</div></div>
                  <div><div class="field-label">Category</div><div class="field-value">{{ \App\Models\Category::find($user->category_id)?->name ?? '—' }}</div></div>
                </div>
                @endif

                {{-- Rider-specific --}}
                @if($user->account_type === 'rider')
                @php $riderProfile = \App\Models\RiderProfile::where('user_id', $user->id)->first(); @endphp
                @if($riderProfile)
                <p class="crumb" style="margin:14px 0 10px">Vehicle Information</p>
                <div class="detail-grid">
                  <div><div class="field-label">Vehicle Type</div><div class="field-value">{{ ucfirst(str_replace('_',' ',$riderProfile->vehicle_type)) }}</div></div>
                  <div><div class="field-label">Brand / Model</div><div class="field-value">{{ $riderProfile->vehicle_brand }} {{ $riderProfile->vehicle_model }}</div></div>
                  @if($riderProfile->plate_number)
                  <div><div class="field-label">Plate Number</div><div class="field-value mono">{{ $riderProfile->plate_number }}</div></div>
                  @endif
                  @if($riderProfile->license_number)
                  <div><div class="field-label">License No.</div><div class="field-value mono">{{ $riderProfile->license_number }}</div></div>
                  <div><div class="field-label">License Expiry</div><div class="field-value mono">{{ $riderProfile->license_expiry?->format('M d, Y') }}</div></div>
                  @endif
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">
                  @if($riderProfile->or_file)<a href="{{ Storage::url($riderProfile->or_file) }}" target="_blank" class="doc-chip">📄 OR</a>@endif
                  @if($riderProfile->cr_file)<a href="{{ Storage::url($riderProfile->cr_file) }}" target="_blank" class="doc-chip">📄 CR</a>@endif
                  @if($riderProfile->license_file)<a href="{{ Storage::url($riderProfile->license_file) }}" target="_blank" class="doc-chip">🪪 License</a>@endif
                </div>
                @endif
                @endif

              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Close</button>
                <form method="POST" action="{{ route('admin.users.suspend', $user->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-danger" type="submit">Suspend</button>
                </form>
                <form method="POST" action="{{ route('admin.users.approve', $user->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-success" type="submit">Activate</button>
                </form>
              </div>
            </div>
          </div>
          @empty
          <tr><td colspan="7"><div class="empty"><div class="ic"><x-admin-icon name="users" /></div><h3>No users yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
