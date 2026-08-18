@extends('admin.layout')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-sub', 'Sales summary and commission reports')

@section('content')
<div class="kpi-grid" style="margin-bottom:18px">
  <div class="kpi"><div class="label">Total Users</div><div class="value">{{ number_format($totalUsers) }}</div><div class="delta up">All accounts</div></div>
  <div class="kpi"><div class="label">Buyers</div><div class="value">{{ number_format($buyerCount) }}</div></div>
  <div class="kpi"><div class="label">Sellers</div><div class="value">{{ number_format($sellerCount) }}</div></div>
  <div class="kpi"><div class="label">Riders</div><div class="value">{{ number_format($riderCount) }}</div></div>
</div>

<div class="kpi-grid" style="margin-bottom:24px">
  <div class="kpi"><div class="label">Total Commission</div><div class="value">₱{{ number_format($totalCommission, 2) }}</div><div class="delta up">Platform earnings</div></div>
  <div class="kpi"><div class="label">Commission Records</div><div class="value">{{ number_format($commissionCount) }}</div></div>
  <div class="kpi"><div class="label">Approved Users</div><div class="value">{{ number_format($approved) }}</div><div class="delta up">Active accounts</div></div>
  <div class="kpi"><div class="label">Pending</div><div class="value">{{ number_format($pending) }}</div><div class="delta {{ $pending > 0 ? 'down' : '' }}">Awaiting review</div></div>
</div>

<div class="dash-grid">
  {{-- Sales Summary Report --}}
  <div class="card">
    <div class="card-head">
      <div><h2>Sales Summary Report</h2><p>Overview of platform transactions and commissions</p></div>
      <a href="{{ route('admin.reports.export.sales') }}" class="btn btn-primary btn-sm">⬇ Export CSV</a>
    </div>
    <div class="card-pad" style="display:flex;flex-direction:column;gap:14px">
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">
        <span style="font-size:13px;font-weight:600">Total Commission Collected</span>
        <span class="mono" style="font-size:15px;font-weight:700;color:var(--pink-dark)">₱{{ number_format($totalCommission, 2) }}</span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">
        <span style="font-size:13px">Total Transactions</span>
        <span class="mono">{{ number_format($commissionCount) }}</span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">
        <span style="font-size:13px">Active Sellers</span>
        <span class="mono">{{ number_format($sellerCount) }}</span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0">
        <span style="font-size:13px">Commission Rate</span>
        <span class="mono">10%</span>
      </div>
    </div>
  </div>

  <div class="stack">
    {{-- Commission Report --}}
    <div class="card">
      <div class="card-head">
        <div><h2>Commission Report</h2><p>Per-seller commission breakdown</p></div>
        <a href="{{ route('admin.reports.export.commission') }}" class="btn btn-primary btn-sm">⬇ Export CSV</a>
      </div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        <p style="font-size:12.5px;color:var(--muted);margin:0">Download the full commission report as CSV to view per-seller earnings and platform deductions.</p>
        <a href="{{ route('admin.reports.export.commission') }}" class="btn btn-outline btn-block">📊 Download Commission Report (CSV)</a>
        <a href="{{ route('admin.reports.export.sales') }}" class="btn btn-outline btn-block">📋 Download Sales Summary (CSV)</a>
      </div>
    </div>

    {{-- User Registrations by Status --}}
    <div class="card">
      <div class="card-head"><h2>User Registrations by Status</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span class="stamp stamp-pending">Pending</span><span class="mono">{{ $pending }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span class="stamp stamp-approved">Approved</span><span class="mono">{{ $approved }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span class="stamp stamp-rejected">Rejected</span><span class="mono">{{ $rejected }}</span>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
