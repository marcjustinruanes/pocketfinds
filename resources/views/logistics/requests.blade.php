@extends('logistics.layout')
@section('title', 'Delivery Requests')
@section('page-title', 'Delivery Requests')
@section('page-sub', 'Pending shipments awaiting approval')

@section('content')
<div class="card">
  <div class="card-head"><h2>Pending Requests</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Shipment</th><th>Buyer</th><th>Items</th><th>Date</th><th></th></tr></thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">#{{ $s->id }}</td>
          <td>{{ optional(optional($s->order)->buyer)->first_name }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td class="mono">{{ optional($s->order)->items?->count() ?? 0 }}</td>
          <td class="mono">{{ $s->created_at?->format('Y-m-d') }}</td>
          <td>
            <div class="row-actions">
              <form method="POST" action="{{ route('logistics.requests.approve', $s->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-success">Approve</button></form>
              <form method="POST" action="{{ route('logistics.requests.reject', $s->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-danger">Reject</button></form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty"><h3>No pending requests</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
