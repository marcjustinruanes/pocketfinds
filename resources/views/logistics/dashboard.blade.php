@extends('logistics.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->first_name)

@section('content')
<div class="kpi-grid">
  <div class="kpi"><div class="label">Total Shipments</div><div class="value">{{ $total }}</div></div>
  <div class="kpi"><div class="label">Pending</div><div class="value">{{ $pending }}</div><div class="delta {{ $pending > 0 ? 'down' : 'up' }}">Awaiting assignment</div></div>
  <div class="kpi"><div class="label">Active</div><div class="value">{{ $active }}</div><div class="delta up">In transit</div></div>
  <div class="kpi"><div class="label">Delivered</div><div class="value">{{ $completed }}</div><div class="delta up">Completed</div></div>
</div>

<div class="card">
  <div class="card-head"><h2>Recent Shipments</h2><a href="{{ route('logistics.monitor') }}" class="btn btn-sm btn-outline">View active</a></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Shipment</th><th>Buyer</th><th>Courier</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        @forelse($recent as $s)
        <tr>
          <td class="mono">#{{ $s->id }}</td>
          <td>{{ optional(optional($s->order)->buyer)->first_name }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td>{{ optional($s->courier)->first_name }} {{ optional($s->courier)->last_name ?? '—' }}</td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_', ' ', $s->shipping_status)) }}</span></td>
          <td class="mono">{{ $s->created_at?->format('Y-m-d') }}</td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty"><h3>No shipments yet</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
