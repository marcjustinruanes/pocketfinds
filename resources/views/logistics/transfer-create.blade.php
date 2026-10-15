@extends('logistics.layout')
@section('title', 'Request Hub Transfer')
@section('page-title', 'Request Hub Transfer')
@section('page-sub', 'Select a route and eligible parcels; every request requires Logistics Admin approval')
@section('content')
@if($errors->any())<div class="card card-pad">{{ $errors->first() }}</div>@endif
<form method="GET" class="card card-pad logistics-form-grid">
@if(auth()->user()->isHubStaff())<div>Origin: {{ $origin?->municipality }}, {{ $origin?->province }}</div>@else
<label>Origin<select name="origin_hub_id" required><option value="">Choose origin</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected($origin?->id === $hub->id)>{{ $hub->municipality }}, {{ $hub->province }}</option>@endforeach</select></label>@endif
<label>Destination<select name="destination_hub_id" required><option value="">Choose destination</option>@foreach($hubs as $hub)@if($origin?->id !== $hub->id)<option value="{{ $hub->id }}" @selected($destination?->id === $hub->id)>{{ $hub->municipality }}, {{ $hub->province }}</option>@endif @endforeach</select></label>
<button class="btn btn-outline">Find eligible parcels</button>
</form>
@if($origin && $destination)
<form method="POST" action="{{ route('logistics.transfers.store') }}" class="card" style="margin-top:16px">@csrf
<input type="hidden" name="origin_hub_id" value="{{ $origin->id }}"><input type="hidden" name="destination_hub_id" value="{{ $destination->id }}">
<div class="card-head"><h2>Eligible parcels</h2><span>{{ $candidates->total() }} parcel(s)</span></div>
<div class="table-wrap"><table class="dtable"><thead><tr><th>Select</th><th>Tracking</th><th>Stage</th></tr></thead><tbody>
@forelse($candidates as $leg)<tr><td><input type="checkbox" name="parcel_ids[]" value="{{ $leg->shipment_id }}"></td><td>{{ $leg->shipment?->tracking_number }}</td><td>{{ $leg->shipment?->shipping_status }}</td></tr>@empty<tr><td colspan="3">No eligible unrequested parcels for this route.</td></tr>@endforelse
</tbody></table></div><div class="card-pad"><button class="btn btn-primary" @disabled($candidates->isEmpty())>Submit for approval</button></div>
</form>{{ $candidates->links() }}
@endif
@endsection
