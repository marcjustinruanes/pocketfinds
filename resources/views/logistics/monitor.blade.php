@extends('logistics.layout')
@section('title', 'Monitor Deliveries')
@section('page-title', 'Monitor Deliveries')
@section('page-sub', 'Track and manage ongoing delivery statuses')

@section('content')
<section class="live-monitor-page">
<section class="card card-pad monitor-lookup" id="parcel-lookup">
  <h2 class="monitor-lookup-title"><span class="monitor-section-icon"><x-admin-icon name="search" /></span>Find a parcel</h2>
  <p>Search by tracking number, order number, or buyer name. Search includes historical parcels within your permitted scope. Hub Staff records physical receipt; Riders confirm their assigned work.</p>
  <form method="GET" action="{{ route('logistics.monitor') }}" class="parcel-lookup-form">
    <label for="monitor-search">Tracking, order, or buyer<input class="input" id="monitor-search" name="search" maxlength="100" value="{{ $search }}" placeholder="Enter tracking, order, or buyer"></label>
    @if(request('stage'))<input type="hidden" name="stage" value="{{ request('stage') }}">@endif
    <button class="btn btn-primary">Find parcel</button><a class="btn" href="{{ route('logistics.monitor') }}">Clear</a>
  </form>
  @if($search !== '')<p role="status">{{ $shipments->total() }} matching parcels. @if(request('stage'))The selected stage filter also applies.@endif</p>@endif
</section>
<div class="card card-pad monitor-guidance"><span class="monitor-guidance-icon"><x-admin-icon name="info" /></span><p>Hub Staff confirm physical hub arrivals. The assigned rider confirms delivery. This page provides oversight and the remaining operational actions.</p></div>
@if($errors->has('status'))
<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ $errors->first('status') }}</div>
@endif
<div class="card live-monitor-table">
  <div class="card-head monitor-table-heading"><div><h2><span class="monitor-section-icon"><x-admin-icon name="truck" /></span>{{ $search !== '' ? 'Parcel lookup results' : match($stage) { 'pickup' => 'Approved pickups', 'transit' => 'Parcels in transit', 'delivery' => 'Deliveries in progress', default => 'Active Deliveries' } }}</h2><p>{{ number_format($shipments->total()) }} {{ \Illuminate\Support\Str::plural('record', $shipments->total()) }} in this view</p></div><span class="monitor-result-count">{{ number_format($shipments->total()) }} {{ \Illuminate\Support\Str::plural('parcel', $shipments->total()) }}</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Assigned rider</th>
          <th>Current Status</th>
          <th>Last Update</th>
          <th>Update Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr(optional(optional($s->order)->buyer)->given_names ?? '?', 0, 1)) }}</div>
              <div>
                <strong>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</strong>
              </div>
            </div>
          </td>
          @php
            [$assignedRider, $riderLeg] = match($s->shipping_status) {
              'ready_for_pickup', 'picked_up' => [$s->pickupRider, 'Pickup'],
              'hub_transfer' => [$s->hubTransferRider, 'Hub transfer'],
              'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered', 'completed', 'delivery_failed', 'returned' => [$s->courier, 'Delivery'],
              default => [null, null],
            };
          @endphp
          <td>
            @if($assignedRider)
              {{ trim($assignedRider->given_names.' '.$assignedRider->last_name) }}<small class="dashboard-caption">{{ $riderLeg }} leg</small>
            @else
              —
            @endif
          </td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</span></td>
          <td class="mono" style="font-size:11.5px">{{ $s->updated_at?->format('M d, Y g:i A') ?? '—' }}</td>
          <td>
            @php
              $mayUpdate = auth()->user()->isLogisticsAdmin() || app(\App\Services\LogisticsAccess::class)->matches(auth()->user()->logisticsHub, $s->destination_hub, $s->destination_province);
              $allowedNext = $mayUpdate ? array_values(array_diff($transitions[$s->shipping_status] ?? [], ['returned','out_for_delivery'])) : [];
              if ($s->shipping_status === 'delivery_failed' && app(\App\Services\RiderDeliveryOffers::class)->retryExhausted($s)) $allowedNext = [];
            @endphp
            @if(count($allowedNext))
            <form method="POST" action="{{ route('logistics.status.update', $s->id) }}" class="monitor-status-form" data-confirm data-confirm-title="Update this parcel status?" data-confirm-message="The selected status and reason will be recorded in the shipment history." data-confirm-action="Update status" data-confirm-tone="success">
              @csrf @method('PATCH')
              <input type="hidden" name="attempt_token" value="{{ $s->failedDeliveryToken() }}">
              <label class="monitor-status-select"><span>New status</span><select name="status" class="select">
                @foreach($allowedNext as $st)
                <option value="{{ $st }}">{{ ucfirst(str_replace('_',' ',$st)) }}</option>
                @endforeach
              </select></label>
              <label>Reason<input name="reason" minlength="10" maxlength="300" required style="min-height:44px;max-width:180px"></label><button class="btn btn-sm btn-primary">Update</button>
            </form>
            @elseif($s->shipping_status === 'sorted' && $mayUpdate)
            <a href="{{ route('logistics.assignments') }}" style="color:var(--pink-dark);font-size:12px;font-weight:600">Assign a courier →</a>
            @elseif($s->shipping_status === 'ready_for_pickup')
            @if(auth()->user()->isLogisticsAdmin())
            @if(!$s->pickup_approved_at)
            <a href="{{ route('logistics.requests', ['queue'=>'review']) }}" style="color:var(--pink-dark);font-size:12px;font-weight:600">Review pickup request →</a>
            @elseif(!$s->pickupRider)
            <a href="{{ route('logistics.requests', ['queue'=>'assignment']) }}" style="color:var(--pink-dark);font-size:12px;font-weight:600">Assign pickup rider →</a>
            @else
            <span>Pickup rider accepted; awaiting collection.</span>
            @endif
            @else<span>Pickup approval is managed by Logistics Admin.</span>@endif
            @else
            <span style="color:var(--muted);font-size:12px">No action needed</span>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="empty monitor-empty-state"><h3>{{ $search !== '' ? 'No matching parcels' : ($stage === 'pickup' ? 'No approved pickups' : ($stage === 'transit' ? 'No parcels in transit' : ($stage === 'delivery' ? 'No deliveries in progress' : 'No active deliveries'))) }}</h3><p>{{ $search !== '' ? 'Check the tracking or order number, or clear the filters.' : ($stage === 'pickup' ? 'Approved pickups will appear here until collection is recorded.' : 'Deliveries in progress will appear here.') }}</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
{{ $shipments->links() }}
</section>
@endsection
@push('head')
<style>
.parcel-lookup-form{display:flex;flex-wrap:wrap;gap:12px;align-items:end}.parcel-lookup-form label{display:grid;gap:8px;flex:1 1 260px;min-width:0}.parcel-lookup-form input{width:100%;min-height:44px;font:inherit;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--paper);color:var(--text)}.parcel-lookup-form .btn{min-height:44px;display:inline-flex;align-items:center;justify-content:center}#parcel-lookup{scroll-margin-top:90px;margin-bottom:20px}#parcel-lookup h2{margin-top:0}#parcel-lookup p{color:var(--muted);line-height:1.6}
@media(max-width:600px){.parcel-lookup-form label{flex-basis:100%}.parcel-lookup-form .btn{flex:1}}
</style>
@endpush
