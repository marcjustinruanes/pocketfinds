@extends('rider.layout')
@section('title', 'Delivery Offers')
@section('page-title', 'Delivery Offers')
@section('page-sub', 'Personal delivery offers expire after 60 seconds')

@section('content')
<div class="card">
  <div class="card-head"><h2>Delivery Offers</h2><span class="stamp stamp-sorted">{{ $shipments->count() }} available</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Tracking #</th><th>Sorting Area</th><th>Buyer</th><th>Delivery Address</th><th>Order Amount</th><th>Expires</th><th>Action</th></tr></thead>
      <tbody>
        @forelse($shipments as $s)
        @php($addr = optional($s->order)->shipping_address ?? [])
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td><td>{{ $s->sorted_area ?? '—' }}</td>
          <td>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td style="font-size:12px;max-width:220px">{{ implode(', ', array_filter([$addr['barangay'] ?? null, $addr['municipality'] ?? null, $addr['province'] ?? null])) ?: '—' }}</td>
          <td class="mono">₱{{ number_format(optional($s->order)->total ?? 0, 2) }}</td>
          <td class="mono" data-offer-expiry="{{ optional($s->deliveryAssignment)->expires_at?->toIso8601String() }}">60s</td>
          <td><div style="display:flex;gap:6px"><form method="POST" action="{{ route('rider.requests.accept', $s->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-primary">Accept</button></form><form method="POST" action="{{ route('rider.requests.decline', $s->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline">Decline</button></form></div></td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="empty"><h3>No delivery offers right now</h3><p>Go online to receive the next available offer.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@push('scripts')
<script>document.querySelectorAll('[data-offer-expiry]').forEach((element)=>{const tick=()=>{const seconds=Math.max(0,Math.ceil((new Date(element.dataset.offerExpiry)-new Date())/1000));element.textContent=seconds?`${seconds}s`:'Expired';};tick();setInterval(tick,1000);});</script>
@endpush
@endsection
