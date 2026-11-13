@extends('logistics.layout')
@section('title', 'Hub Transfers')
@section('page-title', 'Hub Transfers')
@section('page-sub', auth()->user()->isHubStaff() ? 'Outgoing and incoming transfers for your assigned hub' : 'Company transfer requests, decisions, dispatches and arrivals')
@section('content')
<section class="hub-transfer-page">
@if($errors->any())<div class="card card-pad">{{ $errors->first() }}</div>@endif
<div class="card card-pad hub-transfer-action-panel"><div class="hub-transfer-action-copy"><span class="hub-transfer-action-icon"><x-admin-icon name="truck" /></span><div><strong>Transfer operations</strong><small>Move parcels between company hubs.</small></div></div><a href="{{ route('logistics.transfers.create') }}" class="btn btn-primary">Request a transfer</a></div>
<nav class="tabs" aria-label="Transfer stages" style="margin:16px 0">
@if(auth()->user()->isLogisticsAdmin())<a class="tab {{ request('queue') === 'awaiting' ? 'active' : '' }}" href="{{ route('logistics.transfers', ['queue'=>'awaiting']) }}">Awaiting request</a>@endif
@foreach([''=>'All transfers', 'pending'=>'Pending approval', 'approved'=>'Approved', 'in_transit'=>'In transit', 'completed'=>'Received'] as $key=>$label)
<a class="tab {{ !request('queue') && (string)request('status', '') === $key ? 'active' : '' }}" href="{{ route('logistics.transfers', ['status'=>$key]) }}">{{ $label }}</a>
@endforeach
</nav>
@if(request('queue') === 'awaiting')
@include('logistics.partials.transfer-queue')
@else
<form method="GET" class="card card-pad logistics-form-grid hub-transfer-filters" style="margin-top:16px" aria-label="Filter hub transfers">
  <label>Search<input name="search" value="{{ request('search') }}" placeholder="Tracking, batch, or municipality"></label>
  <label>Status<select name="status"><option value="">All statuses</option>@foreach(['pending'=>'Pending approval','approved'=>'Approved','rejected'=>'Rejected','in_transit'=>'Dispatched / in transit','completed'=>'Received','cancelled'=>'Cancelled'] as $key=>$label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></label>
  @if(auth()->user()->isHubStaff())
  <label>Direction<select name="direction"><option value="">Both</option><option value="outgoing" @selected(request('direction') === 'outgoing')>Outgoing</option><option value="incoming" @selected(request('direction') === 'incoming')>Incoming</option></select></label>
  @else
  <label>Hub<select name="hub_id"><option value="">All hubs</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected(request('hub_id') == $hub->id)>{{ $hub->municipality }}, {{ $hub->province }}</option>@endforeach</select></label>
  @endif
  <label>Requested from<input type="date" name="from" value="{{ request('from') }}"></label><label>To<input type="date" name="to" value="{{ request('to') }}"></label>
  <button class="btn btn-outline">Filter</button>
</form>
<div class="card hub-transfer-records"><div class="card-head"><h2><span class="hub-transfer-record-icon"><x-admin-icon name="layers" /></span>Transfer requests</h2><span class="hub-transfer-record-count">{{ $batches->total() }} {{ \Illuminate\Support\Str::plural('transfer', $batches->total()) }}</span></div><div class="table-wrap"><table class="dtable">
<thead><tr><th>Batch</th><th>Origin</th><th>Destination</th><th>Parcels</th><th>Status</th><th>Requester / requested</th><th>Approval</th><th>Dispatch</th><th>Arrival</th><th></th></tr></thead>
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
      <a class="btn btn-outline" id="transferDetailsFullPage" href="#">Open full page</a>
      <button type="button" class="btn btn-primary" data-modal-close>Close</button>
    </footer>
  </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics-transfer-details-modal.js') }}?v={{ filemtime(public_path('js/logistics-transfer-details-modal.js')) }}" defer></script>
@endpush
