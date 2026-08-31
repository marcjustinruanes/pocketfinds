@extends('rider.layout')
@section('title', 'My Deliveries')
@section('page-title', 'My Deliveries')
@section('page-sub', 'Deliveries you have accepted, in progress')

@php
  $nextLabel = [
    'accepted'         => 'Confirm Item Pickup',
    'picked_up'        => 'Mark Out for Delivery',
    'out_for_delivery' => 'Complete Delivery',
  ];
@endphp

@section('content')
<div class="card">
  <div class="card-head"><h2>Active Deliveries</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Seller</th>
          <th>Buyer</th>
          <th>Status</th>
          <th>Updated</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>{{ optional(optional($s->order)->seller)->business_name ?? optional(optional($s->order)->seller)->given_names }}</td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr(optional(optional($s->order)->buyer)->given_names ?? '?', 0, 1)) }}</div>
              <div><strong>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</strong></div>
            </div>
          </td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_', ' ', $s->shipping_status)) }}</span></td>
          <td class="mono" style="font-size:11.5px">{{ $s->updated_at?->format('M d, Y H:i') }}</td>
          <td>
            <a href="{{ route('rider.deliveries.show', $s->id) }}" class="btn btn-sm btn-primary">
              {{ $nextLabel[$s->shipping_status] ?? 'Open' }}
            </a>
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="empty"><h3>No active deliveries</h3><p>Accept a pickup request to see it here.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
