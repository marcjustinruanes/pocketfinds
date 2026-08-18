@extends('logistics.layout')
@section('title', 'Logistics Reports')
@section('page-title', 'Logistics Reports')
@section('page-sub', 'Delivery statistics and courier performance')

@section('content')
<div class="kpi-grid" style="margin-bottom:18px">
  <div class="kpi"><div class="label">Total Deliveries</div><div class="value">{{ number_format($total) }}</div></div>
  <div class="kpi"><div class="label">Completed</div><div class="value">{{ number_format($completed) }}</div><div class="delta up">{{ $total > 0 ? round($completed/$total*100) : 0 }}% success rate</div></div>
  <div class="kpi"><div class="label">Cancelled</div><div class="value">{{ number_format($cancelled) }}</div><div class="delta down">{{ $total > 0 ? round($cancelled/$total*100) : 0 }}%</div></div>
  <div class="kpi"><div class="label">Failed</div><div class="value">{{ number_format($failed) }}</div><div class="delta down">{{ $total > 0 ? round($failed/$total*100) : 0 }}%</div></div>
</div>

<div class="dash-grid">
  <div class="card">
    <div class="card-head"><h2>Courier Performance</h2><p>Top couriers by completed deliveries</p></div>
    <div class="table-wrap">
      <table class="dtable">
        <thead><tr><th>Courier</th><th>Contact</th><th>Completed</th></tr></thead>
        <tbody>
          @forelse($courierStats as $courier)
          <tr class="rail-row rail-active">
            <td>
              <div class="cell-user">
                <div class="avatar-sm">{{ strtoupper(substr($courier->first_name,0,1).substr($courier->last_name,0,1)) }}</div>
                <div><strong>{{ $courier->first_name }} {{ $courier->last_name }}</strong><span>{{ $courier->email }}</span></div>
              </div>
            </td>
            <td class="mono">{{ $courier->contact_no }}</td>
            <td class="mono">{{ $courier->delivered_count }}</td>
          </tr>
          @empty
          <tr><td colspan="3"><div class="empty"><div class="ic">👤</div><h3>No courier data yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Delivery Statistics</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        <div style="display:flex;justify-content:space-between;align-items:center"><span class="stamp stamp-approved">Completed</span><span class="mono">{{ $completed }}</span></div>
        <div style="display:flex;justify-content:space-between;align-items:center"><span class="stamp stamp-rejected">Cancelled</span><span class="mono">{{ $cancelled }}</span></div>
        <div style="display:flex;justify-content:space-between;align-items:center"><span class="stamp stamp-flagged">Failed</span><span class="mono">{{ $failed }}</span></div>
        <div style="display:flex;justify-content:space-between;align-items:center"><span class="stamp stamp-active">Active Couriers</span><span class="mono">{{ $couriers }}</span></div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Export</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        <button class="btn btn-outline" data-toast="Downloading delivery report…">📦 Delivery Report (CSV)</button>
        <button class="btn btn-outline" data-toast="Downloading courier report…">👤 Courier Report (CSV)</button>
      </div>
    </div>
  </div>
</div>
@endsection
