@extends('logistics.layout')
@section('title', 'Delivery History')
@section('page-title', 'Delivery History')
@section('page-sub', 'Completed delivery records')

@section('content')
<div class="card">
  <div class="card-head"><h2>Completed Deliveries</h2><span class="stamp stamp-approved">{{ $shipments->count() }} records</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Courier</th>
          <th>Status</th>
          <th>Picked Up</th>
          <th>Out for Delivery</th>
          <th>Delivered At</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr(optional(optional($s->order)->buyer)->given_names ?? '?', 0, 1)) }}</div>
              <div>
                <strong>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</strong>
              </div>
            </div>
          </td>
          <td>{{ optional($s->courier)->given_names ? optional($s->courier)->given_names . ' ' . optional($s->courier)->last_name : '—' }}</td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</span></td>
          <td class="mono">{{ $s->picked_up_at ? \Carbon\Carbon::parse($s->picked_up_at)->format('M d, Y H:i') : '—' }}</td>
          <td class="mono">{{ $s->out_for_delivery_at ? \Carbon\Carbon::parse($s->out_for_delivery_at)->format('M d, Y H:i') : '—' }}</td>
          <td class="mono">{{ $s->delivered_at ? \Carbon\Carbon::parse($s->delivered_at)->format('M d, Y H:i') : '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="empty"><h3>No completed deliveries yet</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
