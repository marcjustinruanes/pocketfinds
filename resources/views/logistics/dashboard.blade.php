@extends('logistics.layout')
@section('title', 'Logistics Dashboard')
@section('page-title', 'Logistics Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->first_name)

@section('content')
<div class="kpi-grid">
  <div class="kpi"><div class="label">Total Deliveries</div><div class="value">{{ number_format($total) }}</div><div class="delta">All time</div></div>
  <div class="kpi"><div class="label">Pending</div><div class="value">{{ $pending }}</div><div class="delta {{ $pending > 0 ? 'down' : 'up' }}">Awaiting action</div></div>
  <div class="kpi"><div class="label">Active</div><div class="value">{{ $active }}</div><div class="delta up">In progress</div></div>
  <div class="kpi"><div class="label">Completed</div><div class="value">{{ $completed }}</div><div class="delta up">Delivered</div></div>
</div>

<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head"><div><h2>Recent Deliveries</h2></div><a href="{{ route('logistics.monitor') }}" class="btn btn-sm btn-outline">View all</a></div>
      <div class="table-wrap">
        <table class="dtable">
          <thead><tr><th>Tracking #</th><th>Buyer</th><th>Courier</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            @forelse($recent as $s)
            <tr class="rail-row rail-{{ $s->shipping_status }}">
              <td class="mono">{{ strtoupper(substr($s->tracking_number ?? $s->id, 0, 10)) }}</td>
              <td>{{ $s->order?->buyer ? $s->order->buyer->first_name.' '.$s->order->buyer->last_name : '—' }}</td>
              <td>{!! $s->courier ? $s->courier->first_name.' '.$s->courier->last_name : '<span style="color:var(--muted)">Unassigned</span>' !!}</td>
              <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</span></td>
              <td class="mono">{{ $s->created_at?->format('Y-m-d') }}</td>
            </tr>
            @empty
            <tr><td colspan="5"><div class="empty"><div class="ic">📦</div><h3>No deliveries yet</h3></div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Delivery Breakdown</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px"><span class="stamp stamp-pending">Pending</span><span class="mono">{{ $pending }}</span></div>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px"><span class="stamp stamp-review">Active</span><span class="mono">{{ $active }}</span></div>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px"><span class="stamp stamp-approved">Completed</span><span class="mono">{{ $completed }}</span></div>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px"><span class="stamp stamp-rejected">Cancelled/Failed</span><span class="mono">{{ $cancelled }}</span></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <a href="{{ route('logistics.requests') }}" class="btn btn-outline">Review Delivery Requests</a>
        <a href="{{ route('logistics.assign') }}" class="btn btn-outline">Assign Couriers</a>
        <a href="{{ route('logistics.monitor') }}" class="btn btn-outline">Monitor Active Deliveries</a>
        <a href="{{ route('logistics.issues') }}" class="btn btn-outline">Handle Delivery Issues</a>
      </div>
    </div>
  </div>
</div>
@endsection
