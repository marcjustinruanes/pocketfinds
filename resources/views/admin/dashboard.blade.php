@extends('admin.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->given_names)

@section('content')
@php
  $buyers   = \App\Models\User::where('account_type','buyer')->where('is_admin',false)->count();
  $sellers  = \App\Models\User::where('account_type','seller')->where('is_admin',false)->count();
  $riders   = \App\Models\User::where('account_type','rider')->where('is_admin',false)->count();
  $approved = \App\Models\User::where('status','approved')->where('is_admin',false)->count();
@endphp

<div class="kpi-grid">
  <div class="kpi">
    <div class="label">Total Users</div>
    <div class="value">{{ number_format($totalUsers) }}</div>
    <div class="delta up">All accounts</div>
  </div>
  <div class="kpi">
    <div class="label">Pending Registrations</div>
    <div class="value">{{ $pendingCount }}</div>
    <div class="delta {{ $pendingCount > 0 ? 'down' : 'up' }}">Awaiting review</div>
  </div>
  <div class="kpi">
    <div class="label">Open Disputes</div>
    <div class="value">{{ $openDisputes }}</div>
    <div class="delta">Active cases</div>
  </div>
  <div class="kpi">
    <div class="label">Approved Users</div>
    <div class="value">{{ $approved }}</div>
    <div class="delta up">Active accounts</div>
  </div>
</div>

<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head">
        <div><h2>Recent Registrations</h2><p>Latest 5 submissions</p></div>
        <a href="{{ route('admin.registrations') }}" class="btn btn-sm btn-outline">View all</a>
      </div>
      <div class="table-wrap">
        <table class="dtable">
          <thead>
            <tr><th>Applicant</th><th>Type</th><th>Auth</th><th>Date</th><th>Status</th></tr>
          </thead>
          <tbody>
            @forelse($recentUsers as $user)
            <tr class="rail-row rail-{{ $user->status }}">
              <td>
                <div class="cell-user">
                  <x-user-avatar :user="$user" size="30" class="avatar-sm" />
                  <div><strong>{{ $user->given_names }} {{ $user->last_name }}</strong><span>{{ $user->email }}</span></div>
                </div>
              </td>
              <td><span class="stamp stamp-{{ $user->account_type }}" style="text-transform:capitalize">{{ ucfirst($user->account_type) }}</span></td>
              <td class="mono" style="font-size:11px">{{ ucfirst($user->auth_method) }}</td>
              <td class="mono">{{ $user->created_at->format('M d, Y') }}</td>
              <td><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></td>
            </tr>
            @empty
            <tr><td colspan="5"><div class="empty"><div class="ic"><x-admin-icon name="users" /></div><h3>No users yet</h3></div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>User Breakdown</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:14px">
        @foreach([['Buyers','buyer',$buyers,'#3457c2'],['Sellers','seller',$sellers,'#1a7f5a'],['Riders','rider',$riders,'#a8670a']] as [$label,$type,$count,$color])
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px">
          <div style="display:flex;align-items:center;gap:8px">
            <span style="width:8px;height:8px;border-radius:50%;background:{{ $color }};display:inline-block"></span>
            <span>{{ $label }}</span>
          </div>
          <div style="display:flex;align-items:center;gap:10px">
            <span class="mono">{{ $count }}</span>
            @if($totalUsers > 0)
            <span style="font-size:11px;color:var(--muted)">{{ round($count / $totalUsers * 100) }}%</span>
            @endif
          </div>
        </div>
        @if($totalUsers > 0)
        <div style="height:4px;background:var(--border);border-radius:99px;margin-top:-8px">
          <div style="height:100%;width:{{ round($count / $totalUsers * 100) }}%;background:{{ $color }};border-radius:99px"></div>
        </div>
        @endif
        @endforeach
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <a href="{{ route('admin.registrations') }}" class="btn btn-outline">
          Review Pending Registrations
          @if($pendingCount > 0)<span style="margin-left:auto;background:var(--pink);color:#fff;font-size:10px;padding:1px 7px;border-radius:20px">{{ $pendingCount }}</span>@endif
        </a>
        <a href="{{ route('admin.complaints') }}" class="btn btn-outline">
          Open Disputes
          @if($openDisputes > 0)<span style="margin-left:auto;background:var(--danger);color:#fff;font-size:10px;padding:1px 7px;border-radius:20px">{{ $openDisputes }}</span>@endif
        </a>
        <a href="{{ route('admin.compliance') }}" class="btn btn-outline">Seller Compliance Queue</a>
        <a href="{{ route('admin.reports') }}" class="btn btn-outline">View Reports</a>
      </div>
    </div>
  </div>
</div>
@endsection
