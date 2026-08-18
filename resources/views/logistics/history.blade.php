@extends('logistics.layout')
@section('title', 'Delivery History')
@section('page-title', 'Delivery History')
@section('page-sub', 'Completed deliveries')

@section('content')
<div class="card">
  <div class="card-head"><h2>Delivered Shipments</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Shipment</th><th>Buyer</th><th>Courier</th><th>Delivered At</th></tr></thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">#{{ $s->id }}</td>
          <td>{{ optional(optional($s->order)->buyer)->first_name }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td>{{ optional($s->courier)->first_name }} {{ optional($s->courier)->last_name ?? '—' }}</td>
          <td class="mono">{{ $s->delivered_at ? \Carbon\Carbon::parse($s->delivered_at)->format('Y-m-d') : '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="4"><div class="empty"><h3>No deliveries yet</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
