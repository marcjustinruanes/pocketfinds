@push('head')
<link rel="stylesheet" href="{{ asset('css/seller-order-categories.css') }}?v={{ filemtime(public_path('css/seller-order-categories.css')) }}">
<link rel="stylesheet" href="{{ asset('css/seller-order-cards.css') }}?v={{ filemtime(public_path('css/seller-order-cards.css')) }}">
<link rel="stylesheet" href="{{ asset('css/order-details.css') }}?v={{ filemtime(public_path('css/order-details.css')) }}">
<link rel="stylesheet" href="{{ asset('css/gcash-order-modal.css') }}?v={{ filemtime(public_path('css/gcash-order-modal.css')) }}">
@endpush
@push('scripts')<script src="{{ asset('js/gcash-order-modal.js') }}?v={{ filemtime(public_path('js/gcash-order-modal.js')) }}" defer></script>@endpush
<div class="orders-management seller-gcash-orders">
 @include('commerce.messages')
 <div class="seller-order-content">
  <div class="filter-bar orders-filter-bar">
   <form class="search-mini" action="{{ route('gcash.index') }}" method="GET" role="search">
    <span class="ic">@include('seller.partials.icon',['name'=>'search','size'=>13])</span>
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search purchases or products" aria-label="Search GCash purchases">
    @if(request()->filled('contact'))<input type="hidden" name="contact" value="{{ request('contact') }}">@endif
   </form>
   @include('seller.partials.workspace-tabs')
  </div>
  @if(request()->filled('contact'))<a href="{{ route('gcash.index') }}">Show all GCash purchases</a>@endif
  <div class="order-cards">
   @forelse($purchases as $purchase)
   @php
    $purchaseItems=collect($purchase->snapshot['items']??[]);
    $recipient=$purchase->snapshot['shipping_address']['recipient_name']??'Customer';
    $messageUrl=route('seller.messages',['buyer'=>$purchase->buyer_id]);
   @endphp
   <article class="order-card-s" data-purchase-title="{{ $purchase->productLabel() }}" data-purchase-reference="{{ $purchase->id }}">
    <div class="order-card-s-head">
     <div class="seller-order-customer"><strong>{{ $recipient }}</strong></div>
     <span class="stamp gcash-badge {{ in_array($purchase->payment_state,['verified','order_placed'])?'gcash-badge-success':'' }}">{{ str_replace('_',' ',$purchase->payment_state) }}</span>
    </div>
    <div class="order-card-s-items">
     @forelse($purchaseItems as $item)
     @php
      $variant=collect([$item['variation_group']??null,$item['variation_value']??$item['color']??null,$item['size']??null])->filter()->unique()->join(': ');
      $image=$item['img']??null;
     @endphp
     <div class="order-card-s-item">
      <div class="order-card-s-item-thumb">@if($image)<img src="{{ $image }}" alt="{{ $item['name']??'Product' }}" loading="lazy">@else @include('seller.partials.icon',['name'=>'package','size'=>22]) @endif</div>
      <div class="order-card-s-item-info"><strong>{{ $item['name']??'Product not provided' }}</strong>@if($variant)<span>{{ $variant }}</span>@endif</div>
      <div class="order-card-s-item-qty">@if($loop->last)<strong aria-label="Order total">₱{{ number_format($purchase->amount_due,2) }}</strong>@endif<small>{{ $item['qty']??1 }} × ₱{{ number_format($item['price']??0,2) }}</small>@if($loop->last)<a class="btn btn-outline" href="{{ route('gcash.show',$purchase->id) }}">{{ $purchase->payment_state==='order_placed'?'View purchase & order':'Open payment review' }}</a>@endif</div>
     </div>
     @empty
     <div class="order-card-s-item-info seller-gcash-empty-items"><strong>GCash purchase</strong><a class="btn btn-outline" href="{{ route('gcash.show',$purchase->id) }}">View purchase</a></div>
     @endforelse
    </div>
    @if($purchase->payment_state==='verified')<div class="seller-gcash-note">Payment confirmed. Waiting for the buyer to place the order.</div>@endif
    @if(in_array($purchase->reservation_state,['expired','released'])&&$purchase->payment_state!=='order_placed')<div class="seller-gcash-note">Stock reservation {{ $purchase->reservation_state }}. If payment was sent, open the payment review for manual resolution.</div>@endif
   </article>
   @empty
   <div class="empty orders-empty"><div class="ic">@include('seller.partials.icon',['name'=>'wallet','size'=>28])</div><h3>No GCash purchases found</h3><p>Purchases matching your search will appear here.</p></div>
   @endforelse
  </div>
  {{ $purchases->withQueryString()->links() }}
 </div>
</div>
<div class="modal-overlay order-details-modal gcash-purchase-modal" data-order-summary id="gcashPurchaseModal" aria-hidden="true">
 <div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="gcashModalTitle">
  <div class="order-details-head">
   <div><h3 id="gcashModalTitle">GCash purchase</h3><p class="order-reference">Purchase reference: <span data-gcash-modal-reference></span></p></div>
   <div class="order-details-heading-actions"><button type="button" class="order-details-close-icon" data-gcash-modal-close aria-label="Close purchase details">@include('buyer.partials.icon',['name'=>'x','size'=>18])</button><span class="order-kicker order-details-label">Order details</span></div>
  </div>
  <iframe title="GCash purchase details and payment review" data-gcash-modal-frame></iframe>
 </div>
</div>
