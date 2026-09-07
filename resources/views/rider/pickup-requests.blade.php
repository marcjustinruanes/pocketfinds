@extends('rider.layout')
@section('title', 'Pickup Requests')
@section('page-title', 'Pickup Requests')
@section('page-sub', 'Approved seller parcels ready for pickup — first come, first served')

@section('content')
<div class="card">
  <div class="card-head"><h2>Available Requests</h2><span class="stamp stamp-approved">{{ $shipments->count() }} available</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Seller Location</th>
          <th>Order Amount</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr(optional(optional($s->order)->seller)->given_names ?? '?', 0, 1)) }}</div>
              <div>
                <strong>{{ optional(optional($s->order)->seller)->business_name ?? optional(optional($s->order)->seller)->given_names }}</strong>
                <span>{{ collect([optional($s->order)->seller?->municipality, optional($s->order)->seller?->province])->filter()->implode(', ') ?: '—' }}</span>
              </div>
            </div>
          </td>
          <td class="mono">₱{{ number_format(optional($s->order)->total ?? 0, 2) }}</td>
          <td class="mono">{{ $s->created_at?->format('M d, Y') }}</td>
          <td>
            <form method="POST" action="{{ route('rider.pickup-requests.accept', $s->id) }}">@csrf @method('PATCH')
              <button class="btn btn-sm btn-primary">Accept Request</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty"><h3>No pickup requests right now</h3><p>New pickup requests will appear here once Logistics approves them.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
