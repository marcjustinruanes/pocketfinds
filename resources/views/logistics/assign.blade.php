@extends('logistics.layout')
@section('title','Assignments')
@section('page-title','Assignments')
@push('head')
<link rel="stylesheet" href="{{ asset('css/assignment-review.css') }}">
<link rel="stylesheet" href="{{ asset('css/assignment-workspace.css') }}?v={{ filemtime(public_path('css/assignment-workspace.css')) }}">
@endpush
@section('content')
<section class="assignment-workspace">

<div class="assignment-workspace-toolbar">
  <div>
    <h2>Assignments &amp; tracking</h2>
    <span>{{ $assignmentRows->total() }} records</span>
  </div>
  @if(auth()->user()->isHubStaff())
  <button type="button" class="btn btn-primary" data-modal-open="requestTransferModal">Request a transfer</button>
  @endif
</div>

<div class="card assignment-workspace-table">
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr><th>Reference</th><th>Type</th><th>Buyer / Route</th><th>Rider</th><th>Status</th><th>Last update</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($assignmentRows as $row)
        @php $item=$row->item; @endphp
        @if($row->assignment_type==='transfer')
        <tr>
          <td class="mono">{{ substr($item->id,0,8) }}<small>{{ $item->members_count }} parcels</small></td>
          <td>Hub transfer</td>
          <td>{{ $item->from_hub }}<small>&rarr; {{ $item->to_hub }}</small></td>
          <td>{{ $item->rider?->given_names ?? 'Unassigned' }} {{ $item->rider?->last_name }}</td>
          <td><span class="stamp stamp-{{ $item->status }}">{{ $item->label() }}</span></td>
          <td>{{ $item->updated_at?->format('M d, Y g:i A') }}</td>
          <td><button type="button" class="assignment-details-link" data-assignment-details-url="{{ route('logistics.transfers.show',$item->id,false) }}" aria-label="Details for transfer {{ substr($item->id,0,8) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M17 7H7M17 7v10"/></svg></button></td>
        </tr>
        @else
        @php
          $currentRider=match($item->shipping_status){'ready_for_pickup','picked_up'=>$item->pickupRider,'hub_transfer'=>$item->hubTransferRider,default=>$item->courier};
          $leg=match($item->shipping_status){'ready_for_pickup','picked_up'=>'Pickup','at_sorting_center'=>'At hub','hub_transfer'=>'Hub transfer',default=>'Delivery'};
        @endphp
        <tr>
          <td class="mono">{{ $item->tracking_number ?? substr($item->id,0,8) }}<small>{{ $item->order?->order_number }}</small></td>
          <td>{{ $leg }}</td>
          <td>{{ $item->order?->buyer?->given_names }} {{ $item->order?->buyer?->last_name }}<small>{{ $item->destination_hub }}</small></td>
          <td>{{ $currentRider?->given_names ?? 'Unassigned' }} {{ $currentRider?->last_name }}</td>
          <td><span class="stamp stamp-{{ $item->shipping_status }}">{{ ucwords(str_replace('_',' ',$item->shipping_status)) }}</span></td>
          <td>{{ $item->updated_at?->format('M d, Y g:i A') }}</td>
          <td><button type="button" class="assignment-details-link" data-assignment-details-url="{{ route('logistics.assignments.parcel',$item->id,false) }}" aria-label="Details for {{ $item->tracking_number ?? $item->id }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M17 7H7M17 7v10"/></svg></button></td>
        </tr>
        @endif
        @empty
        <tr><td colspan="7"><div class="empty"><h3>No assignments yet</h3><p>Parcels and transfer requests will appear here.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
{{ $assignmentRows->links() }}
</section>

{{-- Assignment details modal --}}
<div class="modal-overlay" id="assignmentDetailsModal" data-reopen="{{ $errors->any() ? 'true' : 'false' }}">
  <section class="modal assignment-details-dialog" role="dialog" aria-modal="true" aria-labelledby="assignmentDetailsTitle">
    <header class="modal-head">
      <div class="modal-head-main">
        <div class="assignment-modal-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18M9 21V9"/></svg>
        </div>
        <div>
          <p class="assignment-detail-kicker" id="assignmentDetailsKicker">Assignment details</p>
          <h3 id="assignmentDetailsTitle">Loading&hellip;</h3>
        </div>
      </div>
      <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </header>
    <div class="modal-body" id="assignmentDetailsContent" aria-live="polite">
      <div class="assignment-details-loading">
        <span></span><p>Loading details&hellip;</p>
      </div>
    </div>
    <footer class="modal-foot">
      <button type="button" class="btn btn-outline" data-assignment-details-back hidden>&#8592; Back</button>
      <button type="button" class="btn btn-primary" data-modal-close>Close</button>
    </footer>
  </section>
</div>

{{-- Request a transfer modal — hub staff only --}}
@if(auth()->user()->isHubStaff())
<div class="modal-overlay" id="requestTransferModal">
  <section class="modal assignment-details-dialog" role="dialog" aria-modal="true" aria-labelledby="requestTransferTitle">
    <header class="modal-head">
      <div class="modal-head-main">
        <div class="assignment-modal-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </div>
        <div>
          <p class="assignment-detail-kicker">Hub operations</p>
          <h3 id="requestTransferTitle">Request a transfer</h3>
        </div>
      </div>
      <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </header>
    <div class="modal-body">
      <p class="assignment-transfer-intro">Select a destination hub for the parcels sitting at <strong>{{ auth()->user()->logisticsHub?->municipality }}, {{ auth()->user()->logisticsHub?->province }}</strong>. The Logistics Admin will review and approve before dispatch.</p>
      <form method="GET" action="{{ route('logistics.transfers.create', [], false) }}" class="assignment-transfer-route-form" id="transferRouteForm">
        <input type="hidden" name="origin_hub_id" value="{{ auth()->user()->logistics_hub_id }}">
        <div class="assignment-transfer-fields" style="grid-template-columns:minmax(0,1fr)">
          <label class="assignment-transfer-field">
            <span class="assignment-transfer-label">Destination hub</span>
            <select name="destination_hub_id" required class="assignment-transfer-select">
              <option value="">Choose destination</option>
              @foreach($hubs ?? [] as $hub)
              @if($hub->id !== auth()->user()->logistics_hub_id)
              <option value="{{ $hub->id }}">{{ $hub->municipality }}, {{ $hub->province }}</option>
              @endif
              @endforeach
            </select>
          </label>
        </div>
      </form>
    </div>
    <footer class="modal-foot">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="submit" form="transferRouteForm" class="btn btn-primary">Find eligible parcels</button>
    </footer>
  </section>
</div>
@endif

@endsection
@push('scripts')
<script src="{{ asset('js/assignment-workspace.js') }}?v={{ filemtime(public_path('js/assignment-workspace.js')) }}" defer></script>
@endpush
