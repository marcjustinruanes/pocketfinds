@extends('logistics.layout')
@section('title', 'Delivery Requests')
@section('page-title', 'Delivery Requests')
@section('page-sub', 'Incoming delivery requests awaiting review')

@section('content')
<div class="card">
  <div class="card-head"><h2>Pending Requests</h2><span class="stamp stamp-pending">{{ $shipments->count() }} pending</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Items</th>
          <th>Order Amount</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr(optional(optional($s->order)->buyer)->first_name ?? '?', 0, 1)) }}</div>
              <div>
                <strong>{{ optional(optional($s->order)->buyer)->first_name }} {{ optional(optional($s->order)->buyer)->last_name }}</strong>
                <span>{{ optional(optional($s->order)->buyer)->email }}</span>
              </div>
            </div>
          </td>
          <td class="mono">{{ optional($s->order)->items?->count() ?? 0 }} item(s)</td>
          <td class="mono">₱{{ number_format(optional($s->order)->total_amount ?? 0, 2) }}</td>
          <td class="mono">{{ $s->created_at?->format('M d, Y') }}</td>
          <td>
            <div class="row-actions">
              <form method="POST" action="{{ route('logistics.requests.approve', $s->id) }}">@csrf @method('PATCH')
                <button class="btn btn-sm btn-success">Approve</button>
              </form>
              <form method="POST" action="{{ route('logistics.requests.reject', $s->id) }}">@csrf @method('PATCH')
                <button class="btn btn-sm btn-danger">Reject</button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="empty"><h3>No pending requests</h3><p>All delivery requests have been processed.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
