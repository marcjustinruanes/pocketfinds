<section class="assignment-parcel-details" data-detail-title="{{ $s->tracking_number ?? $s->id }}">

{{-- Header summary --}}
<div class="apd-header">
  <div class="apd-header-info">
    <span class="apd-order-ref">{{ $s->order?->order_number }}</span>
    <h2 class="apd-tracking">{{ $s->tracking_number ?? $s->id }}</h2>
  </div>
  <span class="stamp stamp-{{ $s->shipping_status }}">{{ ucwords(str_replace('_',' ',$s->shipping_status)) }}</span>
</div>

{{-- Progress bar --}}
<ol class="apd-progress" aria-label="Parcel progress">
  @foreach(['picked_up_at'=>'Collected','at_sorting_center_at'=>'At hub','sorted_at'=>'Sorted','out_for_delivery_at'=>'Out for delivery','delivered_at'=>'Delivered'] as $field=>$label)
  <li class="{{ $s->$field ? 'is-done' : '' }}">
    <span class="apd-progress-bar" aria-hidden="true"></span>
    <strong>{{ $label }}</strong>
    @if($s->$field)<time>{{ \Carbon\Carbon::parse($s->$field)->format('M d, g:i A') }}</time>@endif
  </li>
  @endforeach
</ol>

{{-- Two-column info + actions --}}
<div class="apd-columns">
  <div class="apd-section">
    <h3 class="apd-section-title">Delivery information</h3>
    <dl class="apd-fields">
      <div><dt>Buyer</dt><dd>{{ trim(($s->order?->buyer?->given_names ?? '').' '.($s->order?->buyer?->last_name ?? '')) ?: 'Not recorded' }}</dd></div>
      <div><dt>Origin hub</dt><dd>{{ $s->origin_hub }}, {{ $s->origin_province }}</dd></div>
      <div><dt>Destination hub</dt><dd>{{ $s->destination_hub }}, {{ $s->destination_province }}</dd></div>
      <div><dt>Pickup rider</dt><dd>{{ $s->pickupRider?->given_names ?? 'Unassigned' }} {{ $s->pickupRider?->last_name }}</dd></div>
      <div><dt>Delivery rider</dt><dd>{{ $s->courier?->given_names ?? 'Unassigned' }} {{ $s->courier?->last_name }}</dd></div>
    </dl>
  </div>

  <div class="apd-section">
    <h3 class="apd-section-title">Actions</h3>
    @if($canOffer)
    <form method="POST" action="{{ route('logistics.assignments.assign',$s->id) }}" class="apd-action-form">
      @csrf @method('PATCH')
      <label class="apd-label">Delivery rider
        <select name="courier_id" required class="apd-select">
          <option value="">Choose an available rider</option>
          @foreach($eligibleRiders as $rider)
          <option value="{{ $rider->id }}">{{ $rider->given_names }} {{ $rider->last_name }}</option>
          @endforeach
        </select>
      </label>
      <button class="btn btn-primary apd-btn" @disabled($eligibleRiders->isEmpty())>Send offer</button>
      @if($eligibleRiders->isEmpty())<p class="apd-hint">No eligible online rider covers this destination barangay.</p>@endif
    </form>
    @elseif($s->shipping_status==='sorted')
    <p class="apd-hint">Destination Hub Staff assigns the delivery rider.</p>
    @endif
    @if(count($allowedNext))
    @include('logistics.partials.assignment-status-form')
    @elseif(!$canOffer && $s->shipping_status!=='sorted')
    <p class="apd-hint">No manual status update is available at this stage.</p>
    @endif
  </div>
</div>

{{-- Assignment timeline --}}
<div class="apd-section apd-timeline-section">
  <h3 class="apd-section-title">Assignment timeline &amp; review</h3>
  @forelse($assignments as $a)
  <details class="apd-step" data-assignment-id="{{ $a->id }}" @if(old('_assignment_review')===$a->id) open @endif>
    <summary class="apd-step-summary">
      <span class="apd-step-leg">{{ ucwords(str_replace('_',' ',$a->leg)) }}</span>
      <span class="apd-step-status">{{ ucwords(str_replace('_',' ',$a->status)) }}</span>
      <span class="apd-step-chevron" aria-hidden="true"></span>
    </summary>
    <div class="apd-step-body">
      <p class="apd-step-meta">Rider #{{ $a->courier_id }} &middot; {{ $a->accepted_at?->format('M d, Y g:i A') ?? 'Not recorded' }}</p>
      @if(isset($a->work_class))
      @include('logistics.partials.assignment-review-details')
      @else
      <p class="apd-hint">Assignment history is recorded. Review tools are not installed.</p>
      @endif
    </div>
  </details>
  @empty
  <p class="apd-hint">No assignment history yet.</p>
  @endforelse
</div>

</section>
