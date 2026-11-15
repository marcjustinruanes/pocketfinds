@extends('logistics.layout')
@section('title','Assignments')
@section('page-title','Assignments')
@push('head')<link rel="stylesheet" href="{{ asset('css/assignment-review.css') }}"><link rel="stylesheet" href="{{ asset('css/assignment-workspace.css') }}?v={{ filemtime(public_path('css/assignment-workspace.css')) }}">@endpush
@section('content')
<section class="assignment-workspace">
<div class="assignment-workspace-toolbar"><div><h2>Assignments &amp; tracking</h2><span>{{ $assignmentRows->total() }} records</span></div><a class="btn btn-primary" href="{{ route('logistics.transfers.create',[],false) }}">Request a transfer</a></div>
<div class="card assignment-workspace-table"><div class="table-wrap"><table class="dtable"><thead><tr><th>Reference</th><th>Type</th><th>Buyer / Route</th><th>Rider</th><th>Status</th><th>Last update</th><th>Details</th></tr></thead><tbody>
@forelse($assignmentRows as $row)
@php $item=$row->item; @endphp
@if($row->assignment_type==='transfer')
<tr><td class="mono">{{ substr($item->id,0,8) }}<small>{{ $item->members_count }} parcels</small></td><td>Hub transfer</td><td>{{ $item->from_hub }}<small>&rarr; {{ $item->to_hub }}</small></td><td>{{ $item->rider?->given_names ?? 'Unassigned' }} {{ $item->rider?->last_name }}</td><td><span class="stamp stamp-{{ $item->status }}">{{ $item->label() }}</span></td><td>{{ $item->updated_at?->format('M d, Y g:i A') }}</td><td><a class="assignment-details-link" href="{{ route('logistics.transfers.show',$item->id,false) }}" data-assignment-details-url="{{ route('logistics.transfers.show',$item->id,false) }}" aria-label="Details for transfer {{ substr($item->id,0,8) }}">&nearr;</a></td></tr>
@else
@php
$currentRider=match($item->shipping_status){'ready_for_pickup','picked_up'=>$item->pickupRider,'hub_transfer'=>$item->hubTransferRider,default=>$item->courier};
$leg=match($item->shipping_status){'ready_for_pickup','picked_up'=>'Pickup','at_sorting_center'=>'At hub','hub_transfer'=>'Hub transfer',default=>'Delivery'};
@endphp
<tr><td class="mono">{{ $item->tracking_number ?? substr($item->id,0,8) }}<small>{{ $item->order?->order_number }}</small></td><td>{{ $leg }}</td><td>{{ $item->order?->buyer?->given_names }} {{ $item->order?->buyer?->last_name }}<small>{{ $item->destination_hub }}</small></td><td>{{ $currentRider?->given_names ?? 'Unassigned' }} {{ $currentRider?->last_name }}</td><td><span class="stamp stamp-{{ $item->shipping_status }}">{{ ucwords(str_replace('_',' ',$item->shipping_status)) }}</span></td><td>{{ $item->updated_at?->format('M d, Y g:i A') }}</td><td><a class="assignment-details-link" href="{{ route('logistics.assignments.parcel',$item->id,false) }}" data-assignment-details-url="{{ route('logistics.assignments.parcel',$item->id,false) }}" aria-label="Details for {{ $item->tracking_number ?? $item->id }}">&nearr;</a></td></tr>
@endif
@empty<tr><td colspan="7"><div class="empty"><h3>No assignments yet</h3><p>Parcels and transfer requests will appear here.</p></div></td></tr>@endforelse
</tbody></table></div></div>{{ $assignmentRows->links() }}
</section>
<div class="modal-overlay" id="assignmentDetailsModal" data-reopen="{{ $errors->any() ? 'true' : 'false' }}"><section class="modal assignment-details-dialog" role="dialog" aria-modal="true" aria-labelledby="assignmentDetailsTitle"><header class="modal-head"><div><span class="assignment-detail-kicker">Assignment details</span><h3 id="assignmentDetailsTitle">Parcel &amp; transfer details</h3></div><button type="button" class="modal-close" data-modal-close aria-label="Close assignment details">&times;</button></header><div class="modal-body" id="assignmentDetailsContent" aria-live="polite"></div><footer class="modal-foot"><button type="button" class="btn btn-outline" data-assignment-details-back hidden>Back</button><button type="button" class="btn btn-primary" data-modal-close>Close</button></footer></section></div>
@endsection
@push('scripts')<script src="{{ asset('js/assignment-workspace.js') }}?v={{ filemtime(public_path('js/assignment-workspace.js')) }}" defer></script>@endpush
