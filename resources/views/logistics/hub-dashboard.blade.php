@extends('logistics.layout')
@section('title', 'Hub Dashboard')
@section('page-title', $hub->municipality . ' Hub')
@section('page-sub', $hub->is_regional_hub ? "{$hub->province}'s regional hub — also relays parcels for other municipality hubs in the province" : "Local hub for {$hub->municipality}, {$hub->province}")

@section('content')
@if(session('success'))<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>@endif
@if(session('error'))<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('error') }}</div>@endif
@if($errors->any())<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ $errors->first() }}</div>@endif

<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <h2>Pickup Requests</h2>
    <span class="stamp stamp-pending">{{ $pickupRequests->count() }} awaiting a rider</span>
  </div>
  <div class="card-pad">
    @forelse($pickupRequests as $shipment)
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
      <div>
        <span class="mono">#{{ $shipment->order->order_number ?? substr($shipment->id, 0, 8) }}</span>
        <div style="font-size:11.5px;color:var(--muted);margin-top:2px">
          {{ $shipment->order->seller->business_name ?? 'Seller' }} &rarr; {{ $shipment->destination_hub ?? 'buyer' }}
          — approved {{ optional($shipment->pickup_approved_at)->diffForHumans() }}
        </div>
      </div>
      <form method="POST" action="{{ route('logistics.requests.assign-pickup', $shipment->id) }}" style="display:flex;gap:6px">
        @csrf @method('PATCH')
        <select name="courier_id" class="select" required>
          <option value="" selected disabled>Select rider…</option>
          @foreach($pickupRiders as $rider)
          <option value="{{ $rider->id }}">{{ $rider->given_names }} {{ $rider->last_name }}</option>
          @endforeach
        </select>
        <button type="submit" class="btn btn-sm btn-primary">Assign</button>
      </form>
    </div>
    @empty
    <p style="color:var(--muted);font-size:13px;margin:0">No approved pickups waiting at this hub right now.</p>
    @endforelse
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <h2>Incoming Pickups</h2>
    <span class="stamp stamp-active">{{ $incomingPickups->count() }} en route</span>
  </div>
  <div class="card-pad">
    @forelse($incomingPickups as $shipment)
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px">
      <span>
        <span class="mono">#{{ $shipment->order->order_number ?? substr($shipment->id, 0, 8) }}</span>
        from {{ $shipment->order->seller->business_name ?? 'seller' }}
        — {{ $shipment->pickupRider->given_names ?? 'rider' }} {{ $shipment->pickupRider->last_name ?? '' }}
      </span>
      <span style="color:var(--muted)">confirmed {{ optional($shipment->picked_up_at)->diffForHumans() }}</span>
    </div>
    @empty
    <p style="color:var(--muted);font-size:13px;margin:0">Nothing on its way to this hub right now — scan a parcel in once its rider arrives.</p>
    @endforelse
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <h2>Ready to Request</h2>
    <span class="stamp stamp-pending">{{ $requestable->flatten()->count() }} parcel(s)</span>
  </div>
  <div class="card-pad">
    @forelse($requestable as $toHub => $legs)
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
      <div>
        <strong>&rarr; {{ $toHub }}</strong>
        <div style="font-size:11.5px;color:var(--muted);margin-top:2px">
          {{ $legs->count() }} parcel(s) —
          @foreach($legs as $leg)<span class="mono">#{{ optional($leg->shipment->order)->order_number ?? substr($leg->shipment_id, 0, 8) }}</span>@if(!$loop->last), @endif @endforeach
        </div>
      </div>
      <form method="POST" action="{{ route('logistics.hub.request-transfer') }}">
        @csrf
        <input type="hidden" name="to_hub" value="{{ $toHub }}">
        <button type="submit" class="btn btn-sm btn-primary">Request Transfer</button>
      </form>
    </div>
    @empty
    <p style="color:var(--muted);font-size:13px;margin:0">Nothing waiting to move on right now.</p>
    @endforelse
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <h2>Waiting on Admin Approval</h2>
    <span class="stamp stamp-pending">{{ $pendingApproval->flatten()->count() }} parcel(s)</span>
  </div>
  <div class="card-pad">
    @forelse($pendingApproval as $toHub => $legs)
    <div style="padding:10px 0;border-bottom:1px solid var(--border)">
      <strong>&rarr; {{ $toHub }}</strong>
      <span style="color:var(--muted);font-size:11.5px">— {{ $legs->count() }} parcel(s), requested {{ optional($legs->first()->requested_at)?->diffForHumans() }}</span>
    </div>
    @empty
    <p style="color:var(--muted);font-size:13px;margin:0">No requests are pending approval.</p>
    @endforelse
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <h2>Approved — Ready to Assign a Rider</h2>
    <span class="stamp stamp-active">{{ $readyToAssign->count() }} parcel(s)</span>
  </div>
  <div class="card-pad">
    @forelse($readyToAssign as $leg)
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px">
      <span><span class="mono">#{{ optional($leg->shipment->order)->order_number ?? substr($leg->shipment_id, 0, 8) }}</span> &rarr; {{ $leg->to_hub }}</span>
      <a href="{{ route('logistics.scan') }}" class="btn btn-sm btn-outline">Assign on Scan Page</a>
    </div>
    @empty
    <p style="color:var(--muted);font-size:13px;margin:0">Nothing approved yet.</p>
    @endforelse
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Incoming</h2>
    <span class="stamp stamp-active">{{ $incoming->count() }} en route</span>
  </div>
  <div class="card-pad">
    @forelse($incoming as $leg)
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px">
      <span><span class="mono">#{{ optional($leg->shipment->order)->order_number ?? substr($leg->shipment_id, 0, 8) }}</span> from {{ $leg->from_hub }}</span>
      <span style="color:var(--muted)">{{ optional($leg->rider)->given_names }} {{ optional($leg->rider)->last_name }}</span>
    </div>
    @empty
    <p style="color:var(--muted);font-size:13px;margin:0">Nothing en route to this hub right now.</p>
    @endforelse
  </div>
</div>
@endsection
