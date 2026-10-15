@extends('logistics.layout')
@section('title', 'Transfer Details')
@section('page-title', 'Transfer Details')
@section('page-sub', $batch->label())
@section('content')
@if(session('success'))<div class="card card-pad">{{ session('success') }}</div>@endif
@if($errors->any())<div class="card card-pad">{{ $errors->first() }}</div>@endif
<div class="card card-pad"><p class="mono">{{ $batch->id }}</p><p><strong>{{ $batch->from_hub }}, {{ $batch->from_province }}</strong> → <strong>{{ $batch->to_hub }}, {{ $batch->to_province }}</strong></p>
<div class="logistics-form-grid">
@foreach(['requested'=>'requester','approved'=>'approver','dispatched'=>'dispatcher','received'=>'receiver'] as $action=>$relation)<div>{{ ucfirst($action) }}: {{ $batch->$relation?->username ?? 'Not recorded' }}<br>{{ $batch->{$action.'_at'}?->format('Y-m-d H:i') ?? '—' }}</div>@endforeach
<div>Rider: {{ $batch->rider?->username ?? '—' }}</div><div>Vehicle: {{ $batch->vehicle?->plate_number ?? ($batch->rider_id ? 'Personal vehicle / legacy vehicle not recorded' : '—') }}</div>
</div>
@if($batch->rejection_reason)<p>Rejection reason: {{ $batch->rejection_reason }}</p>@endif
@if($batch->cancellation_reason)<p>Cancellation reason: {{ $batch->cancellation_reason }}</p>@endif
@if($batch->legacy_import)<p>Imported legacy leg. Earlier actor details or event history may not have been recorded.</p>@endif
</div>
@if(auth()->user()->isLogisticsAdmin() && $batch->status === 'pending')
<div class="card card-pad"><form method="POST" action="{{ route('logistics.transfers.approve',$batch->id) }}">@csrf @method('PATCH')<button class="btn btn-success">Approve request</button></form>
<form method="POST" action="{{ route('logistics.transfers.reject',$batch->id) }}">@csrf @method('PATCH')<label>Rejection reason<textarea name="reason" maxlength="1000" required></textarea></label><button class="btn btn-outline">Reject request</button></form></div>
@endif
@if(auth()->user()->isLogisticsAdmin() && $batch->status === 'approved')
<form class="card card-pad logistics-form-grid" method="POST" action="{{ route('logistics.transfers.dispatch',$batch->id) }}">@csrf @method('PATCH')
<label>Transfer rider<select name="courier_id" required><option value="">Choose rider</option>@foreach($riders as $rider)<option value="{{ $rider->id }}">{{ $rider->username }} ({{ $rider->vehicle_ownership }})</option>@endforeach</select></label>
<label>Company vehicle<select name="vehicle_id"><option value="">Auto-select for company rider; leave blank for personal vehicle</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->plate_number }}</option>@endforeach</select></label>
<button class="btn btn-primary">Dispatch approved batch</button></form>
@endif
@if((auth()->user()->isLogisticsAdmin() && in_array($batch->status,['pending','approved'])) || (auth()->user()->isHubStaff() && auth()->user()->logistics_hub_id == $batch->origin_hub_id && $batch->status === 'pending'))
<form method="POST" class="card card-pad" action="{{ route('logistics.transfers.cancel',$batch->id) }}">@csrf @method('PATCH')<label>Cancellation reason<textarea name="reason" maxlength="1000" required></textarea></label><button class="btn btn-outline">Cancel request</button></form>
@endif
@if(auth()->user()->isLogisticsAdmin() || auth()->user()->logistics_hub_id == $batch->destination_hub_id)
@if($batch->status === 'in_transit')
<div class="card card-pad"><h2>Confirm arrival</h2><p>Receipt requires the assigned rider’s pickup confirmation. Partial scans leave the remaining parcels in transit.</p>
<form method="POST" action="{{ route('logistics.transfers.receive',$batch->id) }}">@csrf @method('PATCH')<label>Scan or enter tracking number<input name="tracking_number" required maxlength="255"></label><button class="btn btn-success">Receive this parcel</button></form>
<form method="POST" action="{{ route('logistics.transfers.receive',$batch->id) }}">@csrf @method('PATCH')<label><input type="checkbox" name="confirm_all" value="1" required> I confirm all remaining parcels have arrived.</label><button class="btn btn-success">Receive remaining batch</button></form>
<a class="btn btn-outline" href="{{ route('logistics.scan') }}">Open parcel scanner</a></div>
@endif
@if(in_array($batch->status,['in_transit','completed']))
<form method="POST" class="card card-pad" action="{{ route('logistics.transfers.discrepancy',$batch->id) }}">@csrf<label>Discrepancy details<textarea name="reason" maxlength="1000" required placeholder="Missing parcels, damaged items, or other arrival discrepancies"></textarea></label><button class="btn btn-outline">Record discrepancy</button></form>
@endif @endif
<div class="card"><div class="card-head"><h2>{{ $members->total() }} parcels</h2></div><div class="table-wrap"><table class="dtable"><thead><tr><th>Tracking</th><th>Leg status</th><th>Rider pickup</th><th>Received by / at</th></tr></thead><tbody>@foreach($members as $member)<tr><td>{{ $member->tracking_number ?? $member->shipment_id }}</td><td>{{ in_array($batch->status,['rejected','cancelled']) ? $batch->label() : ($member->leg?->status ?? 'Historical leg unavailable') }}</td><td>{{ $member->leg?->rider_confirmed_pickup_at?->format('Y-m-d H:i') ?? '—' }}</td><td>{{ $member->receiver?->username ?? '—' }}<br>{{ $member->received_at?->format('Y-m-d H:i') }}</td></tr>@endforeach</tbody></table></div></div>{{ $members->links() }}
<div class="card"><div class="card-head"><h2>Transfer history</h2></div><div class="table-wrap"><table class="dtable"><thead><tr><th>Time</th><th>Action</th><th>Actor</th><th>Transition</th><th>Parcel / reason</th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{ $event->created_at->format('Y-m-d H:i:s') }}</td><td>{{ $event->action }}</td><td>{{ $event->actor?->username ?? 'Not recorded' }}</td><td>{{ $event->from_status ?? '—' }} → {{ $event->to_status }}</td><td>{{ $event->shipment_id }}<br>{{ $event->reason }}</td></tr>@empty<tr><td colspan="5">No recorded events.</td></tr>@endforelse</tbody></table></div></div>{{ $events->links() }}
@endsection
