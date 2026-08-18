@extends('admin.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->first_name)

@section('content')
<div class="kpi-grid">
  <div class="kpi"><div class="label">Total Users</div><div class="value">{{ number_format($totalUsers) }}</div><div class="delta up">All accounts</div></div>
  <div class="kpi"><div class="label">Pending Registrations</div><div class="value">{{ $pendingCount }}</div><div class="delta {{ $pendingCount > 0 ? 'down' : 'up' }}">Awaiting review</div></div>
  <div class="kpi"><div class="label">Open Disputes</div><div class="value">{{ $openDisputes }}</div><div class="delta">Active cases</div></div>
  <div class="kpi"><div class="label">Approved Users</div><div class="value">{{ \App\Models\User::where('status','approved')->where('is_admin',false)->count() }}</div><div class="delta up">Active accounts</div></div>
</div>

<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head"><div><h2>Recent Registrations</h2></div><a href="{{ route('admin.registrations') }}" class="btn btn-sm btn-outline">View all</a></div>
      <div class="table-wrap">
        <table class="dtable">
          <thead><tr><th>Name</th><th>Type</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>
            @forelse($recentUsers as $user)
            <tr class="rail-row rail-{{ $user->status }}">
              <td>
                <div class="cell-user">
                  <x-user-avatar :user="$user" size="30" class="avatar-sm" />
                  <div><strong>{{ $user->first_name }} {{ $user->last_name }}</strong><span>{{ $user->email }}</span></div>
                </div>
              </td>
              <td>{{ ucfirst($user->account_type) }}</td>
              <td class="mono">{{ $user->created_at->format('Y-m-d') }}</td>
              <td><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></td>
            </tr>
            @empty
            <tr><td colspan="4"><div class="empty"><div class="ic"><x-admin-icon name="users" /></div><h3>No users yet</h3></div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>User Breakdown</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        @php
          $buyers  = \App\Models\User::where('account_type','buyer')->count();
          $sellers = \App\Models\User::where('account_type','seller')->count();
          $riders  = \App\Models\User::where('account_type','rider')->count();
        @endphp
        <div style="display:flex;justify-content:space-between;font-size:13px"><span>Buyers</span><span class="mono">{{ $buyers }}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:13px"><span>Sellers</span><span class="mono">{{ $sellers }}</span></div>
        <div style="display:flex;justify-content:space-between;font-size:13px"><span>Riders</span><span class="mono">{{ $riders }}</span></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <a href="{{ route('admin.registrations') }}" class="btn btn-outline">Review Pending Registrations</a>
        <a href="{{ route('admin.complaints') }}" class="btn btn-outline">Open Disputes</a>
        <a href="{{ route('admin.compliance') }}" class="btn btn-outline">Seller Compliance Queue</a>
        <a href="{{ route('admin.reports') }}" class="btn btn-outline">View Reports</a>
      </div>
    </div>
  </div>
</div>
@endsection
