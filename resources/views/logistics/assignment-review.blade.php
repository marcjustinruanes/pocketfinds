@extends($seller ? 'seller.layout' : 'logistics.layout')
@section('title','Assignment Review')
@section('page-title','Assignment Review')
@push('head')<link rel="stylesheet" href="{{ asset('css/assignment-review.css') }}?v={{ filemtime(public_path('css/assignment-review.css')) }}">@endpush
@push('scripts')<script src="{{ asset('js/assignment-review.js') }}?v={{ filemtime(public_path('js/assignment-review.js')) }}" defer></script>@endpush
@section('content')
@php
 $parcelGroups=$assignments->groupBy('shipment_id');
 $reopenParcel=$assignments->firstWhere('id',old('_assignment_review'))?->shipment_id;
@endphp
<div class="assignment-review-page" @if($errors->any() && $reopenParcel) data-review-reopen="parcel-review-{{ $reopenParcel }}" data-review-assignment="{{ old('_assignment_review') }}" @endif>
  <p class="assignment-review-intro">Open a parcel to review its pickup, hub transfer, and delivery steps.</p>
  @if($seller && session('success'))<p class="assignment-review-notice" role="status">{{ session('success') }}</p>@endif
  @foreach($parcelGroups as $shipmentId=>$parcelAssignments)
  @php $parcel=$parcelAssignments->first()->shipment; @endphp
  <section class="assignment-review-card assignment-parcel-card">
    <div class="assignment-parcel-reference"><span class="assignment-review-label">{{ $parcel?->order?->order_number ?? 'Parcel' }}</span><h2>{{ $parcel?->tracking_number ?? $shipmentId }}</h2></div>
    <span class="assignment-parcel-status">{{ ucwords(str_replace('_',' ',$parcel?->shipping_status ?? 'Not recorded')) }}</span>
    <button type="button" class="assignment-review-open" data-assignment-review="parcel-review-{{ $shipmentId }}" aria-haspopup="dialog" aria-controls="parcel-review-{{ $shipmentId }}" aria-label="Order details for {{ $parcel?->tracking_number ?? $shipmentId }}">Order details <span aria-hidden="true">&nearr;</span></button>
  </section>
  @endforeach
  @if($assignments->isEmpty())<section class="assignment-review-empty"><h2>No assignments awaiting review</h2><p>Parcels requiring review will appear here.</p></section>@endif
  {{ $parcelPages->links() }}
</div>
@foreach($parcelGroups as $shipmentId=>$parcelAssignments)
@php
 $parcel=$parcelAssignments->first()->shipment;
 $steps=$parcelAssignments->sortBy(fn($assignment)=>match($assignment->leg){'pickup'=>0,'hub_transfer'=>1,'delivery'=>2,default=>3});
@endphp
<dialog id="parcel-review-{{ $shipmentId }}" class="assignment-review-dialog assignment-parcel-dialog" aria-labelledby="parcel-review-title-{{ $shipmentId }}">
  <header class="assignment-review-dialog-head"><div><span>Order details</span><h2 id="parcel-review-title-{{ $shipmentId }}">{{ $parcel?->tracking_number ?? $shipmentId }}</h2><p>{{ $parcel?->order?->order_number }} &middot; {{ ucwords(str_replace('_',' ',$parcel?->shipping_status ?? 'Not recorded')) }}</p></div><button type="button" data-assignment-close aria-label="Close order details">&times;</button></header>
  <div class="assignment-review-dialog-body">
    <ol class="assignment-steps" aria-label="Parcel assignment steps">
    @foreach($steps as $a)
      <li class="assignment-step is-{{ $a->work_class }}" data-assignment-id="{{ $a->id }}">
        <span class="assignment-step-number" aria-hidden="true">{{ $loop->iteration }}</span>
        <div class="assignment-step-content">
          <div class="assignment-step-heading"><h3>{{ ucwords(str_replace('_',' ',$a->leg)) }}</h3><span class="assignment-review-state {{ $a->reassignment ? 'is-pending' : 'is-'.$a->work_class }}">{{ $a->reassignment ? 'Awaiting confirmation' : ($a->work_class==='ambiguous' ? 'Needs review' : ucfirst($a->work_class)) }}</span></div>
          <p class="assignment-step-description">{{ match($a->leg){'pickup'=>'Seller to origin hub','hub_transfer'=>'Between company hubs','delivery'=>'Destination hub to buyer',default=>'Recorded transport assignment'} }}</p>
          <p class="assignment-step-meta">Rider #{{ $a->courier_id }} <span aria-hidden="true">&middot;</span> {{ ucwords(str_replace('_',' ',$a->status)) }}</p>
          <details class="assignment-step-review">
            <summary>Review this step</summary>
            @if($errors->any() && old('_assignment_review')===$a->id)<p class="assignment-review-error" role="alert">{{ $errors->first() }}</p>@endif
            <p class="assignment-review-guidance">Reassignment requires fresh possession confirmation before collection. Completed work and recorded physical handovers cannot be reassigned here.</p>
            @include('logistics.partials.assignment-review-details')
          </details>
        </div>
      </li>
    @endforeach
    </ol>
  </div>
  <footer class="assignment-review-dialog-foot"><button type="button" data-assignment-close>Close</button></footer>
</dialog>
@endforeach
@endsection
