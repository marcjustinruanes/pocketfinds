@extends('rider.layout')
@section('title', 'Pickup Offers')
@section('page-title', 'Pickup Offers')
@section('page-sub', 'Confirm a personal pickup offer within 60 seconds. Maximum three active pickup and delivery jobs combined.')
@section('content')
<div class="card"><div class="card-head"><h2>Pickup Offers</h2><span class="stamp stamp-approved">{{ $shipments->count() }} available</span></div><div class="table-wrap"><table class="dtable"><thead><tr><th>Tracking #</th><th>Seller Location</th><th>Order Amount</th><th>Pickup pay</th><th>Expires</th><th>Action</th></tr></thead><tbody>
@forelse($shipments as $s)
<tr><td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td><td>{{ optional(optional($s->order)->seller)->business_name ?? optional(optional($s->order)->seller)->given_names }}<br><small>{{ collect([optional($s->order)->seller?->municipality, optional($s->order)->seller?->province])->filter()->implode(', ') }}</small></td><td class="mono">₱{{ number_format(optional($s->order)->total ?? 0, 2) }}</td><td class="mono">₱30.00</td><td class="mono" data-offer-expiry="{{ optional($s->pickupAssignment)->expires_at?->toIso8601String() }}">60s</td><td><div style="display:flex;gap:6px"><form method="POST" action="{{ route('rider.pickup-requests.accept', $s->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-primary">Accept</button></form><form method="POST" action="{{ route('rider.pickup-requests.decline', $s->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline">Decline</button></form></div></td></tr>
@empty
<tr><td colspan="6"><div class="empty"><h3>No pickup offers right now</h3><p>Go online to receive approved pickup offers.</p></div></td></tr>
@endforelse
</tbody></table></div></div>
@push('scripts')<script>document.querySelectorAll('[data-offer-expiry]').forEach((element)=>{const tick=()=>{const seconds=Math.max(0,Math.ceil((new Date(element.dataset.offerExpiry)-new Date())/1000));element.textContent=seconds?`${seconds}s`:'Expired';};tick();setInterval(tick,1000);});</script>@endpush
@endsection
