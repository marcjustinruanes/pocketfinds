@extends('logistics.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, {{ auth()->user()->first_name }}')

@section('content')
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="kpi"><div class="label">Total Shipments</div><div class="value">{{ $total }}</div></div>
  <div class="kpi"><div class="label">Pending</div><div class="value">{{ $pending }}</div><div class="delta {{ $pending > 0 ? 'down' : 'up' }}">Awaiting review</div></div>
  <div class="kpi"><div class="label">For Verification</div><div class="value">{{ $forVerify }}</div><div class="delta {{ $forVerify > 0 ? 'down' : 'up' }}">Needs checking</div></div>
  <div class="kpi"><div class="label">Available</div><div class="value">{{ $available }}</div><div class="delta up">Ready for pickup</div></div>
</div>
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-top:0">
  <div class="kpi"><div class="label">Active</div><div class="value">{{ $active }}</div><div class="delta up">In transit</div></div>
  <div class="kpi"><div class="label">Completed</div><div class="value">{{ $completed }}</div><div class="delta up">Delivered</div></div>
  <div class="kpi"><div class="label">Cancelled / Failed</div><div class="value">{{ $cancelled }}</div><div class="delta {{ $cancelled > 0 ? 'down' : 'up' }}"></div></div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Recent Shipments</h2>
    <a href="{{ route('logistics.monitor') }}" class="btn btn-sm btn-outline">View all</a>
  </div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Tracking #</th><th>Buyer</th><th>Courier</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        @forelse($recent as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>{{ optional(optional($s->order)->buyer)->first_name }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td>{{ optional($s->courier)->first_name ? optional($s->courier)->first_name . ' ' . optional($s->courier)->last_name : '—' }}</td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_', ' ', $s->shipping_status)) }}</span></td>
          <td class="mono">{{ $s->created_at?->format('M d, Y') }}</td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty"><h3>No shipments yet</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
