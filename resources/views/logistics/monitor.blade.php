@extends('logistics.layout')
@section('title', 'Monitor Deliveries')
@section('page-title', 'Monitor Deliveries')
@section('page-sub', 'Track and manage ongoing delivery statuses')

@section('content')
<section class="live-monitor-page">
<form method="GET" action="{{ route('logistics.monitor') }}" class="monitor-search-toolbar" id="parcel-lookup">
  <label class="monitor-search-field"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg><input type="search" id="monitor-search" name="search" maxlength="100" value="{{ $search }}" placeholder="Search parcels" aria-label="Search by tracking number, order number, or buyer"></label>
  @if(request('stage'))<input type="hidden" name="stage" value="{{ request('stage') }}">@endif
  <button type="submit" class="btn btn-primary">Search</button>
  @if($search!=='')<a class="monitor-clear-search" href="{{ route('logistics.monitor',request()->only('stage')) }}">Clear</a>@endif
</form>
@if($errors->has('status'))
<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ $errors->first('status') }}</div>
@endif
<div class="card live-monitor-table">
  <div class="card-head monitor-table-heading"><div><h2>{{ $search !== '' ? 'Parcel lookup results' : match($stage) { 'pickup' => 'Approved pickups', 'transit' => 'Parcels in transit', 'delivery' => 'Deliveries in progress', default => 'Active Deliveries' } }}</h2></div><span class="monitor-result-count">{{ number_format($shipments->total()) }} {{ \Illuminate\Support\Str::plural('parcel', $shipments->total()) }}</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Assigned rider</th>
          <th>Current Status</th>
          <th>Last Update</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>
            <div class="cell-user">
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
            <button type="button" class="monitor-update-action" data-modal-open="monitor-status-{{ $s->id }}" aria-haspopup="dialog" aria-controls="monitor-status-{{ $s->id }}" aria-label="Update status for {{ $s->tracking_number ?? $s->id }}">Update status <span aria-hidden="true">&nearr;</span></button>
            @push('monitor-status-modals')@include('logistics.partials.monitor-status-modal')@endpush
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
@stack('monitor-status-modals')
@endsection
