@extends('logistics.layout')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-sub', 'Delivery performance and statistics')

@section('content')
<div class="kpi-grid">
  <div class="kpi"><div class="label">Total Shipments</div><div class="value">{{ $total }}</div></div>
  <div class="kpi"><div class="label">Completed</div><div class="value">{{ $completed }}</div><div class="delta up">{{ $total > 0 ? round($completed/$total*100,1) : 0 }}% success rate</div></div>
  <div class="kpi"><div class="label">Cancelled</div><div class="value">{{ $cancelled }}</div><div class="delta {{ $cancelled > 0 ? 'down' : 'up' }}"></div></div>
  <div class="kpi"><div class="label">Failed</div><div class="value">{{ $failed }}</div><div class="delta {{ $failed > 0 ? 'down' : 'up' }}"></div></div>
</div>

<div class="dash-grid">
  <div class="card">
    <div class="card-head"><h2>Courier Performance</h2><span style="font-size:12px;color:var(--muted)">Top 10 by deliveries</span></div>
    <div class="table-wrap">
      <table class="dtable">
        <thead><tr><th>#</th><th>Courier</th><th>Deliveries</th></tr></thead>
        <tbody>
          @forelse($courierStats as $c)
          <tr>
            <td class="mono">{{ $loop->iteration }}</td>
            <td>
              <div class="cell-user">
                <div class="avatar-sm">{{ strtoupper(substr($c->first_name,0,1)) }}</div>
                <div><strong>{{ $c->first_name }} {{ $c->last_name }}</strong></div>
              </div>
            </td>
            <td class="mono">{{ $c->delivered_count }}</td>
          </tr>
          @empty
          <tr><td colspan="3"><div class="empty"><h3>No courier data yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="stack">
    <div class="card card-pad">
      <div class="field-label">Active Couriers</div>
      <div class="value" style="font-family:var(--font-display);font-size:28px;font-weight:600;margin:6px 0">{{ $couriers }}</div>
    </div>
    <div class="card card-pad">
      <div class="field-label">Success Rate</div>
      <div class="value" style="font-family:var(--font-display);font-size:28px;font-weight:600;margin:6px 0">{{ $total > 0 ? round($completed/$total*100,1) : 0 }}%</div>
    </div>
    <div class="card card-pad">
      <div class="field-label">Issue Rate</div>
      <div class="value" style="font-family:var(--font-display);font-size:28px;font-weight:600;margin:6px 0">{{ $total > 0 ? round(($cancelled+$failed)/$total*100,1) : 0 }}%</div>
    </div>
  </div>
</div>
@endsection
