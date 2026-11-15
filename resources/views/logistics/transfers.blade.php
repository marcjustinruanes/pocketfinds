@extends('logistics.layout')
@section('title', 'Hub Transfers')
@section('page-title', 'Hub Transfers')
@section('page-sub', auth()->user()->isHubStaff() ? 'Outgoing and incoming transfers for your assigned hub' : 'Company transfer requests, decisions, dispatches and arrivals')
@section('content')
<section class="hub-transfer-page">
@if($errors->any())<div class="card card-pad">{{ $errors->first() }}</div>@endif
<div class="hub-transfer-toolbar">
<nav class="tabs" aria-label="Transfer queues">
@if(auth()->user()->isLogisticsAdmin())<a class="tab {{ request('queue')==='awaiting' ? 'active' : '' }}" href="{{ route('logistics.transfers',['queue'=>'awaiting']) }}">Awaiting request</a>@endif
<a class="tab {{ !request('queue') ? 'active' : '' }}" href="{{ route('logistics.transfers',request()->except(['queue','page'])) }}">All transfers</a>
</nav>
@if(request('queue')!=='awaiting')<label class="hub-transfer-search"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg><input type="search" name="search" form="hubTransferFilters" value="{{ request('search') }}" placeholder="Search transfers" aria-label="Search tracking, batch, or municipality"></label>@endif
<a href="{{ route('logistics.transfers.create') }}" class="btn btn-primary">Request a transfer</a>
</div>
@if(request('queue') === 'awaiting')
@include('logistics.partials.transfer-queue')
@else
<form method="GET" id="hubTransferFilters" class="logistics-form-grid hub-transfer-filters" aria-label="Filter hub transfers">
  @if(auth()->user()->isHubStaff())
  <label class="hub-transfer-hub-filter">Direction<select name="direction"><option value="">Both</option><option value="outgoing" @selected(request('direction') === 'outgoing')>Outgoing</option><option value="incoming" @selected(request('direction') === 'incoming')>Incoming</option></select></label>
  @else
  <label class="hub-transfer-hub-filter">Hub<select name="hub_id"><option value="">All hubs</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected(request('hub_id') == $hub->id)>{{ $hub->municipality }}, {{ $hub->province }}</option>@endforeach</select></label>
  @endif
  <div class="hub-transfer-date-filter"><span>Requested date</span><div class="hub-transfer-date-range"><label><span class="hub-transfer-sr-only">Requested from</span><input type="date" name="from" value="{{ request('from') }}" aria-label="Requested from"></label><span aria-hidden="true">&ndash;</span><label><span class="hub-transfer-sr-only">Requested to</span><input type="date" name="to" value="{{ request('to') }}" aria-label="Requested to"></label></div></div>

</form>
<div class="card hub-transfer-records"><div class="card-head"><h2><span class="hub-transfer-record-icon"><x-admin-icon name="layers" /></span>Transfer requests</h2><span class="hub-transfer-record-count">{{ $batches->total() }} {{ \Illuminate\Support\Str::plural('transfer', $batches->total()) }}</span></div><div class="table-wrap"><table class="dtable">
<thead><tr><th>Batch</th><th>Origin</th><th>Destination</th><th>Parcels</th><th><label class="logistics-table-status-filter"><span aria-hidden="true">Status</span><select name="status" form="hubTransferFilters" aria-label="Filter transfers by status"><option value="">Status</option>@foreach(['pending'=>'Pending approval','approved'=>'Approved','rejected'=>'Rejected','in_transit'=>'In transit','completed'=>'Received','cancelled'=>'Cancelled'] as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></label></th><th>Requester / requested</th><th>Approval</th><th>Dispatch</th><th>Arrival</th><th>Actions</th></tr></thead>
<tbody>@forelse($batches as $batch)<tr>
<td class="mono">{{ substr($batch->id,0,8) }}</td><td>{{ $batch->from_hub }}, {{ $batch->from_province }}</td><td>{{ $batch->to_hub }}, {{ $batch->to_province }}</td><td>{{ $batch->members_count }}</td><td>{{ $batch->label() }}</td>
<td>{{ $batch->requester?->username ?? 'Not recorded' }}<br>{{ $batch->requested_at?->format('Y-m-d g:i A') }}</td>
<td>{{ $batch->approver?->username ?? '—' }}<br>{{ $batch->approved_at?->format('Y-m-d g:i A') }}</td>
<td>{{ $batch->dispatcher?->username ?? '—' }}<br>{{ $batch->dispatched_at?->format('Y-m-d g:i A') }}</td>
<td>{{ $batch->receiver?->username ?? '—' }}<br>{{ $batch->received_at?->format('Y-m-d g:i A') }}</td>
<td><a class="btn btn-sm btn-outline" href="{{ route('logistics.transfers.show',$batch->id) }}" data-modal-open="transferDetailsModal" data-transfer-details-url="{{ route('logistics.transfers.show',$batch->id) }}" aria-haspopup="dialog">Details</a></td>
</tr>@empty<tr><td colspan="10"><div class="hub-transfer-empty"><span aria-hidden="true">—</span><strong>No transfers match these filters</strong><small>Adjust the search, status, or date range and try again.</small></div></td></tr>@endforelse</tbody></table></div></div>
{{ $batches->links() }}
@endif
</section>
<div class="modal-overlay" id="transferDetailsModal" aria-hidden="true">
  <section class="modal modal-xl logistics-transfer-details-modal" role="dialog" aria-modal="true" aria-labelledby="transferDetailsTitle" aria-describedby="transferDetailsDescription">
    <header class="modal-head">
      <div class="modal-head-copy"><h3 id="transferDetailsTitle">Transfer details</h3><p id="transferDetailsDescription">Review the transfer timeline, parcels, and available actions.</p></div>
      <button type="button" class="modal-close" data-modal-close aria-label="Close transfer details"><x-admin-icon name="close" /></button>
    </header>
    <div class="modal-body logistics-transfer-details-content" id="transferDetailsContent" aria-live="polite">
      <p class="logistics-transfer-details-loading">Loading transfer details…</p>
    </div>
    <footer class="modal-foot">
      <button type="button" class="btn btn-primary" data-modal-close>Close</button>
    </footer>
  </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics-transfer-filters.js') }}?v={{ filemtime(public_path('js/logistics-transfer-filters.js')) }}" defer></script>
<script src="{{ asset('js/logistics-transfer-details-modal.js') }}?v={{ filemtime(public_path('js/logistics-transfer-details-modal.js')) }}" defer></script>
@endpush
