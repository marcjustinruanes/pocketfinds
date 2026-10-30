@extends($seller ? 'seller.layout' : 'logistics.layout')
@section('title', 'Assignment Review')
@section('page-title', 'Assignment Review')
@section('page-sub', 'Completion evidence and safe pre-collection reassignment')
@push('head')<link rel="stylesheet" href="{{ asset('css/logistics-cash-desk.css') }}">@endpush
@section('content')
<div class="cash-desk">
<section class="cash-card"><h2>Assignment review</h2><p>Reassignment requires a fresh possession confirmation. Completed work, prior physical handovers and dispatched transfers cannot be reassigned here. Historical classifications do not change financial records.</p></section>
@if($errors->any())<div class="cash-alert" role="alert">{{ $errors->first() }}</div>@endif
@foreach($assignments as $a)
<section class="cash-card"><div class="cash-header"><div><h2>{{ $a->shipment?->tracking_number ?? $a->shipment_id }}</h2><p>{{ ucfirst($a->leg) }} · {{ $a->status }} · {{ $a->work_class }} · revision {{ $a->revision }}</p></div></div>
@if($a->work_class==='ambiguous')<p>Evidence is incomplete or inconsistent. Manual review is required; no work or earnings were invented.</p>@endif
@if($a->work_class==='completed' && $a->status==='accepted')<p>Completion evidence is present. This historical assignment has not been rewritten.</p>@endif
@if($p=$a->reassignment)
<p>Requested change: rider #{{ $p['old_rider_id'] }} → rider #{{ $p['replacement_rider_id'] }}. {{ $p['reason'] }}</p>
@if(($seller && $a->leg==='pickup') || (!$seller && $actor->isHubStaff() && $a->leg==='delivery'))
<form method="POST" action="{{ route($seller?'seller.assignments.decide':'logistics.assignments.decide',$a->id) }}">@csrf
<input type="hidden" name="key" value="{{ $p['key'] }}"><input type="hidden" name="revision" value="{{ $a->revision }}"><input type="hidden" name="action" value="confirm">
<label class="cash-check" for="possession-{{ $a->id }}"><input id="possession-{{ $a->id }}" type="checkbox" name="confirmed" value="1" required><span>{{ $seller?'I still physically hold this parcel and have not handed it to the rider.':'I physically checked that this parcel remains at this destination hub and has not been released to the rider.' }}</span></label>
<button class="btn btn-primary">Confirm possession and reassign</button></form>
@else<p>{{ $a->leg==='pickup'?'Awaiting the seller’s possession confirmation.':'Awaiting destination Hub Staff’s physical no-release confirmation.' }}</p>@endif
<details><summary>Cancel this request</summary><form method="POST" action="{{ route($seller?'seller.assignments.decide':'logistics.assignments.decide',$a->id) }}">@csrf
<input type="hidden" name="key" value="{{ $p['key'] }}"><input type="hidden" name="revision" value="{{ $a->revision }}"><input type="hidden" name="action" value="cancel">
<label for="cancel-{{ $a->id }}">Cancellation reason</label><textarea id="cancel-{{ $a->id }}" name="reason" minlength="3" maxlength="1000" required></textarea><button class="btn">Cancel request</button></form></details>
@elseif(!$seller && in_array($a->leg,['pickup','delivery']) && in_array($a->status,['accepted','assigned_to_rider']) && $a->work_class==='active')
<details><summary>Request pre-collection reassignment</summary><form method="POST" action="{{ route('logistics.assignments.reassign',$a->id) }}">@csrf
<input type="hidden" name="key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="revision" value="{{ $a->revision }}"><input type="hidden" name="old_rider_id" value="{{ $a->courier_id }}">
<label for="replacement-{{ $a->id }}">Replacement rider</label><select id="replacement-{{ $a->id }}" name="replacement_rider_id" required><option value="">Select an eligible rider</option>@foreach($riders as $r)@if($r->id!=$a->courier_id && $r->logisticsHub?->is_operational && $r->logisticsHub?->municipality===($a->leg==='pickup'?$a->shipment?->origin_hub:$a->shipment?->destination_hub) && $r->logisticsHub?->province===($a->leg==='pickup'?$a->shipment?->origin_province:$a->shipment?->destination_province))<option value="{{ $r->id }}">{{ $r->username }}</option>@endif @endforeach</select>
<label for="reason-{{ $a->id }}">Reason</label><textarea id="reason-{{ $a->id }}" name="reason" minlength="3" maxlength="1000" required></textarea><button class="btn btn-primary">Request possession review</button></form></details>
@endif
<details><summary>Recorded assignment decisions</summary>
@forelse($events[$a->id] ?? [] as $e)
@php($decision=json_decode($e->details,true) ?? [])
<p>{{ $e->created_at }} &middot; {{ ucfirst(str_replace('_',' ',$e->event)) }} &middot; actor #{{ $e->actor_id }}<br>{{ $decision['reason'] ?? '' }}
@if(isset($decision['old_rider_id'],$decision['replacement_rider_id']))<br>Rider #{{ $decision['old_rider_id'] }} &rarr; rider #{{ $decision['replacement_rider_id'] }}@endif
@if(isset($decision['attested_by']))<br>Possession confirmed by #{{ $decision['attested_by'] }} at {{ $decision['attested_at'] }}@endif
@if(isset($decision['cancel_reason']))<br>{{ $decision['cancel_reason'] }}@endif</p>
@empty<p>No recorded decisions.</p>@endforelse</details>
</section>
@endforeach
@if($assignments->isEmpty())<section class="cash-card"><p>No assignments awaiting review.</p></section>@endif
{{ $assignments->links() }}
</div>
@endsection
