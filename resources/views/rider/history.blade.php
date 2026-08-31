@extends('rider.layout')
@section('title', 'Delivery History')
@section('page-title', 'Delivery History')
@section('page-sub', 'Deliveries you have completed')

@section('content')
<div class="card">
  <div class="card-head"><h2>Completed Deliveries</h2><span class="stamp stamp-approved">{{ $shipments->count() }} total</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Seller</th>
          <th>Buyer</th>
          <th>Order Amount</th>
          <th>Delivered On</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>{{ optional(optional($s->order)->seller)->business_name ?? optional(optional($s->order)->seller)->given_names }}</td>
          <td>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td class="mono">₱{{ number_format(optional($s->order)->total ?? 0, 2) }}</td>
          <td class="mono">{{ $s->delivered_at?->format('M d, Y H:i') ?? '—' }}</td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_', ' ', $s->shipping_status)) }}</span></td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="empty"><h3>No completed deliveries yet</h3><p>Deliveries you finish will show up here.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
