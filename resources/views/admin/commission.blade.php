@extends('admin.layout')
@section('title', 'Commission')
@section('page-title', 'Commission')
@section('page-sub', 'Platform commission ledger')

@section('content')
<div class="card" style="margin-bottom:18px">
  <div class="card-pad">
    <div class="ledger-hero">
      <div>
        <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px">Total Commission Collected</div>
        <div class="ledger-num">₱{{ number_format($totalAmount, 2) }} <small>PHP</small></div>
      </div>
    </div>
  </div>
</div>

<div class="kpi-grid" style="margin-bottom:18px">
  <div class="kpi"><div class="label">Total Records</div><div class="value">{{ $commissions->count() }}</div></div>
  <div class="kpi"><div class="label">Total Commission</div><div class="value">₱{{ number_format($totalAmount, 2) }}</div></div>
  <div class="kpi"><div class="label">Active Sellers</div><div class="value">{{ $sellers }}</div></div>
  <div class="kpi"><div class="label">Avg Rate</div><div class="value">{{ $commissions->avg('commission_rate') ? number_format($commissions->avg('commission_rate'), 1).'%' : '—' }}</div></div>
</div>

<div class="card">
  <div class="card-head"><h2>Commission Ledger</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Seller</th><th>Order ID</th><th>Sale Amount</th><th>Rate</th><th>Commission</th><th>Seller Earnings</th><th>Date</th></tr></thead>
      <tbody>
        @forelse($commissions as $c)
        <tr class="rail-row rail-approved">
          <td>
            @if($c->seller)
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr($c->seller->first_name,0,1).substr($c->seller->last_name,0,1)) }}</div>
              <div><strong>{{ $c->seller->first_name }} {{ $c->seller->last_name }}</strong></div>
            </div>
            @else
            <span style="color:var(--muted)">Unknown</span>
            @endif
          </td>
          <td class="mono">{{ strtoupper(substr($c->order_id ?? $c->id, 0, 8)) }}</td>
          <td class="mono">₱{{ number_format($c->order_amount, 2) }}</td>
          <td class="mono">{{ $c->commission_rate }}%</td>
          <td class="mono">₱{{ number_format($c->commission_amount, 2) }}</td>
          <td class="mono">₱{{ number_format($c->seller_earnings, 2) }}</td>
          <td class="mono">{{ $c->created_at?->format('Y-m-d') }}</td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="empty"><div class="ic">₱</div><h3>No commission records yet</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
