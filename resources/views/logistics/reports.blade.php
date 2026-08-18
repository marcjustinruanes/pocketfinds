@extends('logistics.layout')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-sub', 'Delivery performance overview')

@section('content')
<div class="kpi-grid">
  <div class="kpi"><div class="label">Total Shipments</div><div class="value">{{ $total }}</div></div>
  <div class="kpi"><div class="label">Delivered</div><div class="value">{{ $completed }}</div><div class="delta up">Completed</div></div>
  <div class="kpi"><div class="label">Cancelled</div><div class="value">{{ $cancelled }}</div><div class="delta down"></div></div>
  <div class="kpi"><div class="label">Failed</div><div class="value">{{ $failed }}</div><div class="delta down"></div></div>
</div>
<div class="card">
  <div class="card-head"><h2>Summary</h2></div>
  <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
    <div style="display:flex;justify-content:space-between;font-size:13px"><span>Active Couriers</span><span class="mono">{{ $couriers }}</span></div>
    <div style="display:flex;justify-content:space-between;font-size:13px"><span>Success Rate</span><span class="mono">{{ $total > 0 ? round($completed / $total * 100, 1) : 0 }}%</span></div>
  </div>
</div>
@endsection
