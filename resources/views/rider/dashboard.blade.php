@extends('rider.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->given_names)

@section('content')
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="kpi"><div class="label">Pickup Requests</div><div class="value">{{ $availableRequests }}</div><div class="delta {{ $availableRequests > 0 ? 'up' : '' }}">Available now</div></div>
  <div class="kpi"><div class="label">Active Deliveries</div><div class="value">{{ $activeDeliveries }}</div><div class="delta {{ $activeDeliveries > 0 ? 'up' : '' }}">In your hands</div></div>
  <div class="kpi"><div class="label">Delivered Today</div><div class="value">{{ $completedToday }}</div></div>
  <div class="kpi"><div class="label">Total Delivered</div><div class="value">{{ $totalCompleted }}</div></div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Available Pickup Requests</h2>
    <a href="{{ route('rider.requests') }}" class="btn btn-sm btn-outline">View all</a>
  </div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Tracking #</th><th>Seller</th><th>Buyer</th><th>Order Amount</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
        @forelse($available as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>{{ optional(optional($s->order)->seller)->business_name ?? optional(optional($s->order)->seller)->given_names }}</td>
          <td>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td class="mono">₱{{ number_format(optional($s->order)->total ?? 0, 2) }}</td>
          <td class="mono">{{ $s->created_at?->format('M d, Y') }}</td>
          <td>
            <form method="POST" action="{{ route('rider.requests.accept', $s->id) }}">@csrf @method('PATCH')
              <button class="btn btn-sm btn-primary">Accept</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="empty"><h3>No pickup requests right now</h3><p>New delivery requests will appear here — first to accept gets it.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>My Active Deliveries</h2>
    <a href="{{ route('rider.deliveries') }}" class="btn btn-sm btn-outline">View all</a>
  </div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Tracking #</th><th>Buyer</th><th>Status</th><th>Updated</th><th></th></tr></thead>
      <tbody>
        @forelse($active as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_', ' ', $s->shipping_status)) }}</span></td>
          <td class="mono" style="font-size:11.5px">{{ $s->updated_at?->format('M d, Y H:i') }}</td>
          <td><a href="{{ route('rider.deliveries.show', $s->id) }}" class="btn btn-sm btn-outline">Open</a></td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty"><h3>No active deliveries</h3><p>Accept a pickup request to get started.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
