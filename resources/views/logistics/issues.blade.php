@extends('logistics.layout')
@section('title', 'Issues')
@section('page-title', 'Issues')
@section('page-sub', 'Failed and cancelled shipments')

@section('content')
<div class="card">
  <div class="card-head"><h2>Problem Shipments</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Shipment</th><th>Buyer</th><th>Courier</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">#{{ $s->id }}</td>
          <td>{{ optional(optional($s->order)->buyer)->first_name }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td>{{ optional($s->courier)->first_name }} {{ optional($s->courier)->last_name ?? '—' }}</td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst($s->shipping_status) }}</span></td>
          <td class="mono">{{ $s->created_at?->format('Y-m-d') }}</td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty"><h3>No issues found</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
