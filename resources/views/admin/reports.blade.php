@extends('admin.layout')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-sub', 'Sales, recorded payments and account statistics')

@section('content')
@include('admin.partials.ai-link', ['aiMode' => 'chat', 'aiLabel' => 'Ask dashboard'])
<div class="kpi-grid" style="margin-bottom:18px">
  <div class="kpi"><div class="label">Total Users</div><div class="value">{{ number_format($totalUsers) }}</div><div class="delta up">All accounts</div></div>
  <div class="kpi tone-info"><div class="label">Buyers</div><div class="value">{{ number_format($buyerCount) }}</div></div>
  <div class="kpi"><div class="label">Sellers</div><div class="value">{{ number_format($sellerCount) }}</div></div>
  <div class="kpi tone-warning"><div class="label">Riders</div><div class="value">{{ number_format($riderCount) }}</div></div>
</div>

<div class="kpi-grid" style="margin-bottom:24px">
  <div class="kpi tone-success"><div class="label">Approved Users</div><div class="value">{{ number_format($approved) }}</div><div class="delta up">Active accounts</div></div>
  <div class="kpi tone-warning"><div class="label">Pending</div><div class="value">{{ number_format($pending) }}</div><div class="delta {{ $pending > 0 ? 'down' : '' }}">Awaiting review</div></div>
</div>

<div class="dash-grid payment-report-layout">
  <div class="stack">
    @include('reports.payment-summary',['report'=>$paymentReport,'tableClass'=>'dtable'])
  </div>
  <div class="stack">
    <div class="card"><div class="card-head"><h2>Sales and payment exports</h2></div><div class="card-pad">
      <p>No platform commission is charged. Sales are completed order values, not profit or verified receipts. Payment summaries use recorded receipts and payouts without adding treasury mirrors.</p>
      <p><a class="btn btn-outline" href="{{ route('admin.reports.export.sales') }}">Sales CSV</a> <a class="btn btn-outline" href="{{ route('admin.reports.export.sales-pdf') }}">Sales PDF</a></p>
      <p><a class="btn btn-primary" href="{{ route('admin.reports.export.commission') }}">Payment summary CSV</a></p>
      <p><a href="{{ route('admin.commission') }}">Historical commission archive</a></p>
    </div></div>
    {{-- User Registrations by Status --}}
    <div class="card">
      <div class="card-head"><div><h2>User Registrations by Status</h2><p>Approval pipeline snapshot</p></div></div>
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
