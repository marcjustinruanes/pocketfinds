@extends('rider.layout')
@section('title', 'Pickup Requests')
@section('page-title', 'Pickup Requests')
@section('page-sub', 'Available deliveries — first come, first served')

@section('content')
<div class="card">
  <div class="card-head"><h2>Available Requests</h2><span class="stamp stamp-available">{{ $shipments->count() }} available</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Seller Location</th>
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
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr(optional(optional($s->order)->seller)->given_names ?? '?', 0, 1)) }}</div>
              <div>
                <strong>{{ optional(optional($s->order)->seller)->business_name ?? optional(optional($s->order)->seller)->given_names }}</strong>
                <span>{{ collect([optional($s->order)->seller?->municipality, optional($s->order)->seller?->province])->filter()->implode(', ') ?: '—' }}</span>
              </div>
            </div>
          </td>
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
        <tr><td colspan="7"><div class="empty"><h3>No pickup requests right now</h3><p>New delivery requests will appear here as soon as logistics releases them.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
