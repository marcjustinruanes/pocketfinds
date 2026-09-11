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
            <td class="mono">{{ $user->created_at?->format('M d, Y') ?? '—' }}</td>
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
                @include('admin.partials.user-profile-body', [
                  'user' => $user,
                  'riderProfile' => $user->account_type === 'rider' ? \App\Models\RiderProfile::where('user_id', $user->id)->first() : null,
                ])
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
