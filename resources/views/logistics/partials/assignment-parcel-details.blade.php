<section class="assignment-parcel-details" data-detail-title="{{ $s->tracking_number ?? $s->id }}">
<div class="assignment-detail-summary"><div><span class="assignment-detail-kicker">Parcel details</span><h2>{{ $s->tracking_number ?? $s->id }}</h2><p>{{ $s->order?->order_number }}</p></div><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucwords(str_replace('_',' ',$s->shipping_status)) }}</span></div>
<ol class="assignment-progress" aria-label="Parcel progress">
@foreach(['picked_up_at'=>'Collected','at_sorting_center_at'=>'At hub','sorted_at'=>'Sorted','out_for_delivery_at'=>'Out for delivery','delivered_at'=>'Delivered'] as $field=>$label)
<li class="{{ $s->$field ? 'is-done' : '' }}"><span aria-hidden="true"></span><strong>{{ $label }}</strong>@if($s->$field)<time>{{ \Carbon\Carbon::parse($s->$field)->format('M d, g:i A') }}</time>@endif</li>
@endforeach
</ol>
<div class="assignment-detail-columns"><section class="assignment-detail-section"><h3>Delivery information</h3><dl><div><dt>Buyer</dt><dd>{{ trim(($s->order?->buyer?->given_names ?? '').' '.($s->order?->buyer?->last_name ?? '')) ?: 'Not recorded' }}</dd></div><div><dt>Origin hub</dt><dd>{{ $s->origin_hub }}, {{ $s->origin_province }}</dd></div><div><dt>Destination hub</dt><dd>{{ $s->destination_hub }}, {{ $s->destination_province }}</dd></div><div><dt>Pickup rider</dt><dd>{{ $s->pickupRider?->given_names ?? 'Unassigned' }} {{ $s->pickupRider?->last_name }}</dd></div><div><dt>Delivery rider</dt><dd>{{ $s->courier?->given_names ?? 'Unassigned' }} {{ $s->courier?->last_name }}</dd></div></dl></section>
<section class="assignment-detail-section"><h3>Available actions</h3>
@if($canOffer)<form method="POST" action="{{ route('logistics.assignments.assign',$s->id) }}">@csrf @method('PATCH')<label>Delivery rider<select name="courier_id" required><option value="">Choose an available rider</option>@foreach($eligibleRiders as $rider)<option value="{{ $rider->id }}">{{ $rider->given_names }} {{ $rider->last_name }}</option>@endforeach</select></label><button class="btn btn-primary" @disabled($eligibleRiders->isEmpty())>Send offer</button></form>@if($eligibleRiders->isEmpty())<p>No eligible online rider covers this destination barangay.</p>@endif
@elseif($s->shipping_status==='sorted')<p>Destination Hub Staff assigns the delivery rider.</p>@endif
@if(count($allowedNext))@include('logistics.partials.assignment-status-form')@elseif(!$canOffer && $s->shipping_status!=='sorted')<p>No manual status update is available at this stage.</p>@endif
</section></div>
<section class="assignment-detail-section"><h3>Assignment timeline &amp; review</h3>
@forelse($assignments as $a)<details class="assignment-detail-step" data-assignment-id="{{ $a->id }}" @if(old('_assignment_review')===$a->id) open @endif><summary><span>{{ ucwords(str_replace('_',' ',$a->leg)) }}</span><span>{{ ucwords(str_replace('_',' ',$a->status)) }}</span></summary><p>Rider #{{ $a->courier_id }} &middot; {{ $a->accepted_at?->format('M d, Y g:i A') ?? 'Not recorded' }}</p>
@if(isset($a->work_class))@include('logistics.partials.assignment-review-details')@else<p>Assignment history is recorded. Review tools are not installed.</p>@endif
</details>@empty<p>No assignment history yet.</p>@endforelse
</section></section>
