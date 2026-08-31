@extends('rider.layout')
@section('title', 'Profit')
@section('page-title', 'Profit Dashboard')
@section('page-sub', 'Your delivery earnings')

@section('content')
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr)">
  <div class="kpi"><div class="label">Total Earnings</div><div class="value">₱{{ number_format($total, 2) }}</div></div>
  <div class="kpi"><div class="label">This Month</div><div class="value">₱{{ number_format($thisMonth, 2) }}</div></div>
  <div class="kpi"><div class="label">Deliveries Completed</div><div class="value">{{ $deliveryCount }}</div></div>
</div>

<div class="card">
  <div class="card-head"><h2>Earnings Breakdown</h2><p style="font-size:11.5px;color:var(--muted)">Earnings shown per delivery are the shipping fee collected on that order.</p></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Tracking #</th><th>Buyer</th><th>Delivered On</th><th>Earning</th></tr></thead>
      <tbody>
        @forelse($completed as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td class="mono">{{ $s->delivered_at?->format('M d, Y') ?? '—' }}</td>
          <td class="mono">₱{{ number_format($s->order?->shipping_amount ?? 0, 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="4"><div class="empty"><h3>No earnings yet</h3><p>Complete a delivery to start earning.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
