@extends('logistics.layout')
@section('title', 'Hub Transfers')
@section('page-title', 'Hub Transfers')
@section('page-sub', auth()->user()->isHubStaff() ? 'Outgoing and incoming transfers for your assigned hub' : 'Company transfer requests, decisions, dispatches and arrivals')
@section('content')
@if($errors->any())<div class="card card-pad">{{ $errors->first() }}</div>@endif
<a href="{{ route('logistics.transfers.create') }}" class="btn btn-primary">Request a transfer</a>
<nav class="tabs" aria-label="Transfer stages" style="margin:16px 0">
@if(auth()->user()->isLogisticsAdmin())<a class="tab {{ request('queue') === 'awaiting' ? 'active' : '' }}" href="{{ route('logistics.transfers', ['queue'=>'awaiting']) }}">Awaiting request</a>@endif
@foreach([''=>'All transfers', 'pending'=>'Pending approval', 'approved'=>'Approved', 'in_transit'=>'In transit', 'completed'=>'Received'] as $key=>$label)
<a class="tab {{ !request('queue') && (string)request('status', '') === $key ? 'active' : '' }}" href="{{ route('logistics.transfers', ['status'=>$key]) }}">{{ $label }}</a>
@endforeach
</nav>
@if(request('queue') === 'awaiting')
@include('logistics.partials.transfer-queue')
@else
<form method="GET" class="card card-pad logistics-form-grid" style="margin-top:16px">
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
<div class="card"><div class="card-head"><h2>{{ $batches->total() }} transfer(s)</h2></div><div class="table-wrap"><table class="dtable">
<thead><tr><th>Batch</th><th>Origin</th><th>Destination</th><th>Parcels</th><th>Status</th><th>Requester / requested</th><th>Approval</th><th>Dispatch</th><th>Arrival</th><th></th></tr></thead>
<tbody>@forelse($batches as $batch)<tr>
<td class="mono">{{ substr($batch->id,0,8) }}</td><td>{{ $batch->from_hub }}, {{ $batch->from_province }}</td><td>{{ $batch->to_hub }}, {{ $batch->to_province }}</td><td>{{ $batch->members_count }}</td><td>{{ $batch->label() }}</td>
<td>{{ $batch->requester?->username ?? 'Not recorded' }}<br>{{ $batch->requested_at?->format('Y-m-d H:i') }}</td>
<td>{{ $batch->approver?->username ?? '—' }}<br>{{ $batch->approved_at?->format('Y-m-d H:i') }}</td>
<td>{{ $batch->dispatcher?->username ?? '—' }}<br>{{ $batch->dispatched_at?->format('Y-m-d H:i') }}</td>
<td>{{ $batch->receiver?->username ?? '—' }}<br>{{ $batch->received_at?->format('Y-m-d H:i') }}</td>
<td><a class="btn btn-sm btn-outline" href="{{ route('logistics.transfers.show',$batch->id) }}">Details</a></td>
</tr>@empty<tr><td colspan="10">No transfers match these filters.</td></tr>@endforelse</tbody></table></div></div>
{{ $batches->links() }}
@endif
@endsection
