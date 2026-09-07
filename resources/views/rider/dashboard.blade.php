@extends('rider.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->given_names)

@section('content')
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="kpi"><div class="label">Pickup Requests</div><div class="value">{{ $pickupRequests }}</div><div class="delta {{ $pickupRequests > 0 ? 'up' : '' }}">Awaiting a rider</div></div>
  <div class="kpi"><div class="label">Delivery Requests</div><div class="value">{{ $deliveryRequests }}</div><div class="delta {{ $deliveryRequests > 0 ? 'up' : '' }}">Sorted, awaiting a rider</div></div>
  <div class="kpi"><div class="label">Active Deliveries</div><div class="value">{{ $activeDeliveries }}</div><div class="delta {{ $activeDeliveries > 0 ? 'up' : '' }}">In your hands</div></div>
  <div class="kpi"><div class="label">Delivered Today</div><div class="value">{{ $completedToday }}</div></div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>My Pickups</h2><p style="font-size:11.5px;color:var(--muted)">Accepted — proceed to the seller's location</p></div>
    <a href="{{ route('rider.my-pickups') }}" class="btn btn-sm btn-outline">View all</a>
  </div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Tracking #</th><th>Seller</th><th>Status</th><th></th></tr></thead>
      <tbody>
        @forelse($myPickupsList as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>{{ optional(optional($s->order)->seller)->business_name ?? optional(optional($s->order)->seller)->given_names }}</td>
          <td><span class="stamp stamp-pending">Awaiting seller confirmation</span></td>
          <td class="mono" style="font-size:11.5px">{{ $s->updated_at?->format('M d, g:i A') }}</td>
        </tr>
        @empty
        <tr><td colspan="4"><div class="empty"><h3>No active pickups</h3><p>Accept a pickup request to see it here.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Open Pickup Requests</h2>
    <a href="{{ route('rider.pickup-requests') }}" class="btn btn-sm btn-outline">View all</a>
  </div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Tracking #</th><th>Seller</th><th>Order Amount</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
        @forelse($pickupsOpen as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>{{ optional(optional($s->order)->seller)->business_name ?? optional(optional($s->order)->seller)->given_names }}</td>
          <td class="mono">₱{{ number_format(optional($s->order)->total ?? 0, 2) }}</td>
          <td class="mono">{{ $s->created_at?->format('M d, Y') }}</td>
          <td>
            <form method="POST" action="{{ route('rider.pickup-requests.accept', $s->id) }}">@csrf @method('PATCH')
              <button class="btn btn-sm btn-primary">Accept</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty"><h3>No pickup requests right now</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Open Delivery Requests</h2><p style="font-size:11.5px;color:var(--muted)">Sorted parcels ready for a delivery rider</p></div>
    <a href="{{ route('rider.requests') }}" class="btn btn-sm btn-outline">View all</a>
  </div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Tracking #</th><th>Buyer</th><th>Order Amount</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
        @forelse($deliveriesOpen as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
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
        <tr><td colspan="5"><div class="empty"><h3>No delivery requests right now</h3></div></td></tr>
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
        <tr><td colspan="5"><div class="empty"><h3>No active deliveries</h3><p>Accept a delivery request to get started.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
