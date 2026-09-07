@extends('rider.layout')
@section('title', 'Delivery Requests')
@section('page-title', 'Delivery Requests')
@section('page-sub', 'Sorted parcels ready for a delivery rider — first come, first served')

@section('content')
<div class="card">
  <div class="card-head"><h2>Available Requests</h2><span class="stamp stamp-sorted">{{ $shipments->count() }} available</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Sorting Area</th>
          <th>Buyer</th>
          <th>Delivery Address</th>
          <th>Order Amount</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        @php($addr = optional($s->order)->shipping_address ?? [])
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>{{ $s->sorted_area ?? '—' }}</td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr(optional(optional($s->order)->buyer)->given_names ?? '?', 0, 1)) }}</div>
              <div><strong>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</strong></div>
            </div>
          </td>
          <td style="font-size:12px;max-width:220px">{{ implode(', ', array_filter([$addr['barangay'] ?? null, $addr['municipality'] ?? null, $addr['province'] ?? null])) ?: '—' }}</td>
          <td class="mono">₱{{ number_format(optional($s->order)->total ?? 0, 2) }}</td>
          <td class="mono">{{ $s->created_at?->format('M d, Y') }}</td>
          <td>
            <form method="POST" action="{{ route('rider.requests.accept', $s->id) }}">@csrf @method('PATCH')
              <button class="btn btn-sm btn-primary">Accept Request</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="empty"><h3>No delivery requests right now</h3><p>Sorted parcels will appear here as soon as logistics releases them.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
