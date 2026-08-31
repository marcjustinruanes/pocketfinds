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

    @php
      $usersByType = $users->countBy('account_type');
    @endphp
    <div data-tabs>
      <a class="tab active" data-tab="all">All <span class="tab-count">{{ $users->count() }}</span></a>
      <a class="tab" data-tab="buyer">Buyers <span class="tab-count">{{ $usersByType->get('buyer', 0) }}</span></a>
      <a class="tab" data-tab="seller">Sellers <span class="tab-count">{{ $usersByType->get('seller', 0) }}</span></a>
      <a class="tab" data-tab="rider">Riders <span class="tab-count">{{ $usersByType->get('rider', 0) }}</span></a>
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
            <td><span class="stamp stamp-{{ $user->account_type }}">{{ ucfirst($user->account_type) }}</span></td>
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
                <div class="modal-head-main">
                  <span class="modal-icon"><x-admin-icon name="users" /></span>
                  <div class="modal-head-copy">
                    <h3>Manage User</h3>
                    <p>{{ $user->given_names }} {{ $user->last_name }} — {{ ucfirst($user->account_type) }}</p>
                  </div>
                </div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">

                {{-- Personal --}}
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="account" /></span><span>Personal Information</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Full Name</div><div class="field-value">{{ $user->given_names }} {{ $user->middle_name ? $user->middle_name.' ' : '' }}{{ $user->last_name }}</div></div>
                    <div><div class="field-label">Username</div><div class="field-value mono">{{ $user->username ?? '—' }}</div></div>
                    <div><div class="field-label">Sex</div><div class="field-value">{{ ucfirst($user->sex ?? '—') }}</div></div>
                    <div><div class="field-label">Birthday</div><div class="field-value mono">{{ $user->birthday?->format('M d, Y') ?? '—' }}</div></div>
                    <div><div class="field-label">Age</div><div class="field-value">{{ $user->age ?? '—' }}</div></div>
                    <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></div></div>
                  </div>
                </div>

                {{-- Contact --}}
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="mail" /></span><span>Contact &amp; Auth</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Email</div><div class="field-value">{{ $user->email }}</div></div>
                    <div><div class="field-label">Contact No.</div><div class="field-value mono">{{ $user->contact_no }}</div></div>
                    <div><div class="field-label">Auth Method</div><div class="field-value">{{ ucfirst($user->auth_method) }}</div></div>
                    <div><div class="field-label">Joined</div><div class="field-value mono">{{ $user->created_at->format('M d, Y') }}</div></div>
                  </div>
                </div>

                {{-- Address --}}
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="pin" /></span><span>Address</span></div>
                  <div class="detail-grid">
                    <div class="full"><div class="field-label">Full Address</div><div class="field-value">{{ collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter()->implode(', ') ?: '—' }}</div></div>
                  </div>
                </div>

                {{-- Verification Documents --}}
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="shield" /></span><span>Verification Documents</span></div>
                  <div class="doc-grid">
                    <x-admin-doc-thumb :path="$user->id_file" label="Government ID" />
                    <x-admin-doc-thumb :path="$user->selfie_file" label="Selfie with ID" />
                    @if($user->account_type === 'seller')
                    <x-admin-doc-thumb :path="$user->business_permit_file" label="Business Permit" />
                    @endif
                  </div>
                </div>

                {{-- Seller-specific --}}
                @if($user->account_type === 'seller')
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="bag" /></span><span>Seller Details</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Business Name</div><div class="field-value">{{ $user->business_name ?? '—' }}</div></div>
                    <div class="full"><div class="field-label">Category</div><div class="field-value">{{ collect([$user->category?->name, $user->category_other])->filter()->implode(', ') ?: '—' }}</div></div>
                  </div>
                </div>
                @endif

                {{-- Rider-specific --}}
                @if($user->account_type === 'rider' && $user->vehicle_type)
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="truck" /></span><span>Vehicle Information</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Vehicle Type</div><div class="field-value">{{ ucfirst(str_replace('_',' ',$user->vehicle_type)) }}</div></div>
                    <div><div class="field-label">Brand / Model</div><div class="field-value">{{ $user->vehicle_brand }} {{ $user->vehicle_model }}</div></div>
                    @if($user->plate_number)
                    <div><div class="field-label">Plate Number</div><div class="field-value mono">{{ $user->plate_number }}</div></div>
                    @endif
                    @if($user->license_number)
                    <div><div class="field-label">License No.</div><div class="field-value mono">{{ $user->license_number }}</div></div>
                    <div><div class="field-label">License Expiry</div><div class="field-value mono">{{ $user->license_expiry?->format('M d, Y') }}</div></div>
                    @endif
                  </div>
                  <div class="doc-grid" style="margin-top:10px">
                    <x-admin-doc-thumb :path="$user->or_file" label="OR" />
                    <x-admin-doc-thumb :path="$user->cr_file" label="CR" />
                    <x-admin-doc-thumb :path="$user->license_file" label="Driver's License" />
                  </div>
                </div>
                @endif

              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Close</button>
                @if($user->status !== 'suspended')
                <form method="POST" action="{{ route('admin.users.suspend', $user->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-outline" type="submit">Suspend</button>
                </form>
                @endif
                @if($user->status !== 'rejected')
                <form method="POST" action="{{ route('admin.registrations.reject', $user->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-outline-danger" type="submit">Reject</button>
                </form>
                @endif
                @if($user->status !== 'approved')
                <form method="POST" action="{{ route('admin.users.approve', $user->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-success" type="submit">Activate</button>
                </form>
                @endif
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

@include('admin.partials.doc-lightbox')
@endsection
