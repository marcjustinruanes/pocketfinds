@extends('seller.layout')
@section('title', 'Confirm Delivery')
@section('page-title', 'Confirm Delivery')
@section('page-sub', 'Orders confirmed received by customers')

@section('content')
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr)">
  <div class="kpi"><div class="label">Delivered Today</div><div class="value">0</div><div class="delta up">Confirmed</div></div>
  <div class="kpi"><div class="label">This Week</div><div class="value">0</div><div class="delta up">Delivered</div></div>
  <div class="kpi"><div class="label">Pending Confirmation</div><div class="value">1</div><div class="delta">Awaiting buyer</div></div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Delivery Status</h2><p>You will be notified once the customer confirms receipt</p></div>
  </div>
  <div class="tabs" style="padding:0 20px;margin-bottom:0" data-tabs>
    <button class="tab active" data-tab="pending">Pending Confirmation</button>
    <button class="tab" data-tab="confirmed">Confirmed</button>
  </div>

  <div data-panel="pending">
    <table class="tbl">
      <thead><tr>
        <th>Order ID</th><th>Customer</th><th>Delivered On</th><th>Courier</th><th>Amount</th><th>Status</th>
      </tr></thead>
      <tbody>
        <tr>
          <td class="mono">#00001</td>
          <td>Sample Customer</td>
          <td style="font-size:12px;color:var(--muted)">{{ now()->format('M d, Y') }}</td>
          <td>J&T Express</td>
          <td class="mono">₱299.00</td>
          <td><span class="stamp stamp-pending">Awaiting Buyer</span></td>
        </tr>
        <tr><td colspan="6"><div class="empty" style="padding:30px 20px"><h3>No more pending</h3></div></td></tr>
      </tbody>
    </table>
  </div>

  <div data-panel="confirmed" style="display:none">
    <div class="empty" style="padding:40px 20px">
      <div class="ic">@include('seller.partials.icon', ['name' => 'check-circle', 'size' => 28])</div>
      <h3>No confirmed deliveries yet</h3>
      <p>Confirmed deliveries will appear here.</p>
    </div>
  </div>
</div>
@endsection
