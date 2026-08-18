@extends('admin.layout')
@section('title', 'User Accounts')
@section('page-title', 'User Accounts')
@section('page-sub', 'Manage all platform accounts')

@section('content')
<div class="card">
  <div class="card-head">
    <div><h2>All Users</h2><p>{{ $users->count() }} total accounts</p></div>
  </div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic"><x-admin-icon name="search" /></span>
        <input type="text" placeholder="Search..." data-table-search="usersTable">
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
        <thead><tr><th>User</th><th>Type</th><th>Contact</th><th>Joined</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse($users as $user)
          <tr class="rail-row rail-{{ $user->status }}" data-type="{{ $user->account_type }}">
            <td>
              <div class="cell-user">
                <div class="avatar-sm">{{ strtoupper(substr($user->first_name,0,1).substr($user->last_name,0,1)) }}</div>
                <div><strong>{{ $user->first_name }} {{ $user->last_name }}</strong><span>{{ $user->email }}</span></div>
              </div>
            </td>
            <td>{{ ucfirst($user->account_type) }}</td>
            <td class="mono">{{ $user->contact_no }}</td>
            <td class="mono">{{ $user->created_at->format('Y-m-d') }}</td>
            <td><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="userModal-{{ $user->id }}">Manage</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="userModal-{{ $user->id }}">
            <div class="modal">
              <div class="modal-head">
                <div><h3>Manage User</h3><p>{{ $user->first_name }} {{ $user->last_name }} — {{ ucfirst($user->account_type) }}</p></div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">
                <div class="detail-grid">
                  <div><div class="field-label">Name</div><div class="field-value">{{ $user->first_name }} {{ $user->last_name }}</div></div>
                  <div><div class="field-label">Email</div><div class="field-value">{{ $user->email }}</div></div>
                  <div><div class="field-label">Type</div><div class="field-value">{{ ucfirst($user->account_type) }}</div></div>
                  <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></div></div>
                  <div><div class="field-label">Contact</div><div class="field-value">{{ $user->contact_no }}</div></div>
                  <div><div class="field-label">Joined</div><div class="field-value mono">{{ $user->created_at->format('Y-m-d') }}</div></div>
                </div>
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
          <tr><td colspan="6"><div class="empty"><div class="ic"><x-admin-icon name="users" /></div><h3>No users yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
