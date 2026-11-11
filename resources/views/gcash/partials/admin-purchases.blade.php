@push('head')
<link rel="stylesheet" href="{{ asset('css/buyer-orders.css') }}?v={{ filemtime(public_path('css/buyer-orders.css')) }}">
<link rel="stylesheet" href="{{ asset('css/order-details.css') }}?v={{ filemtime(public_path('css/order-details.css')) }}">
<link rel="stylesheet" href="{{ asset('css/gcash-order-modal.css') }}?v={{ filemtime(public_path('css/gcash-order-modal.css')) }}">
<link rel="stylesheet" href="{{ asset('css/admin-gcash-orders.css') }}?v={{ filemtime(public_path('css/admin-gcash-orders.css')) }}">
@endpush
@push('scripts')<script src="{{ asset('js/gcash-order-modal.js') }}?v={{ filemtime(public_path('js/gcash-order-modal.js')) }}" defer></script>@endpush
@php
 $state = request('state','all');
 $states = [['all','category-all','All'],['proof_submitted','receipt','For Review'],['underpaid','ticket','Payment Short'],['order_placed','shield','Approved'],['rejected','ban','Rejected'],['cancelled','x','Cancelled'],['refunds','wallet','Return / Refund']];
 $labels = collect($states)->mapWithKeys(fn($row)=>[$row[0]=>$row[2]])->merge(['manual_resolution'=>'Rejected']);
 $images = \App\Models\Product::whereIn('id',$purchases->getCollection()->flatMap(fn($purchase)=>collect($purchase->snapshot['items']??[])->pluck('product_id'))->filter()->unique())->get()->keyBy('id');
 $people = \App\Models\User::whereIn('id',$purchases->getCollection()->flatMap(fn($purchase)=>[$purchase->buyer_id,$purchase->seller_id])->unique())->get()->keyBy('id');
 $capsuleOrders = \App\Models\Order::whereIn('gcash_purchase_id', $purchases->getCollection()->pluck('id'))->get(['id','gcash_purchase_id','status','total'])->keyBy('gcash_purchase_id');
 $capsuleRefunds = \Illuminate\Support\Facades\Schema::hasTable('cancellation_refunds') ? \Illuminate\Support\Facades\DB::table('cancellation_refunds')->whereIn('order_id', $capsuleOrders->pluck('id'))->get(['order_id','status'])->keyBy('order_id') : collect();
@endphp
<div class="buyer-pages buyer-orders-page admin-gcash-orders">
 <div class="orders-page"><div class="orders-layout">
  <aside class="order-tabs-shell" aria-label="Payment status filters"><nav class="tabs order-tabs">
   @foreach($states as [$key,$icon,$label])
   @php $count = $key === 'all' ? $paymentCounts->sum() : ($paymentCounts[$key]??0); @endphp
   <a class="tab {{ $state === $key ? 'active' : '' }}" href="{{ route('gcash.index',array_filter(['state'=>$key,'q'=>request('q'),'period'=>request('period')])) }}" aria-label="{{ $label }} ({{ $count }})" title="{{ $label }}" @if($state===$key) aria-current="page" @endif>
    <span class="order-tab-icon">@if($key==='all')@include('guest.partials.category-filter-icon',['category'=>null])@else @include('buyer.partials.icon',['name'=>$icon,'size'=>19])@endif</span>
    <span class="order-tab-label">{{ $label }}</span>@if($count)<span class="order-tab-count">{{ $count }}</span>@endif
   </a>
   @endforeach
  </nav></aside>
  <div class="orders-content">
   @include('commerce.messages')
   <form class="orders-toolbar" method="GET" action="{{ route('gcash.index') }}" aria-label="Filter GCash payments">
    <input type="hidden" name="state" value="{{ $state }}">
    <div class="orders-search-field"><span class="orders-search-icon">@include('buyer.partials.icon',['name'=>'search','size'=>14])</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Search payments or products" aria-label="Search payments"></div>
    <label class="orders-filter-field admin-gcash-date-field"><span>Date</span><select name="period" onchange="this.form.requestSubmit()">@foreach(['all'=>'All time','30d'=>'Last 30 days','6m'=>'Last 6 months','year'=>'This year'] as $key=>$label)<option value="{{ $key }}" @selected(request('period','all')===$key)>{{ $label }}</option>@endforeach</select></label>
    <button type="button" class="btn btn-outline admin-platform-account-button" data-platform-account-open>Platform GCash account</button>
   </form>
   @forelse($purchases as $purchase)
   @php
    $buyer=$people->get($purchase->buyer_id); $seller=$people->get($purchase->seller_id);
    $items=collect($purchase->snapshot['items']??[]);
    $buyerName=trim(($buyer?->given_names??'').' '.($buyer?->last_name??'')) ?: 'Buyer';
    $capsuleOrder = $capsuleOrders->get($purchase->id);
    $capsuleRefund = $capsuleOrder ? $capsuleRefunds->get($capsuleOrder->id) : null;
    $capsuleCategory = match ($purchase->payment_state) {
      'proof_submitted' => 'For Review',
      'underpaid' => 'Payment Short',
      'verified', 'order_placed' => 'Approved',
      'rejected', 'manual_resolution' => 'Rejected',
      default => 'For Review',
    };
    $capsuleState = $purchase->payment_state;
    $capsuleProgress = match ($purchase->payment_state) {
      'proof_submitted' => 'Proof submitted',
      'underpaid' => 'Awaiting top-up',
      'order_placed' => $capsuleOrder ? str_replace('_',' ',ucfirst($capsuleOrder->status)) : 'Payment approved',
      'rejected' => 'Awaiting updated receipt',
      'manual_resolution' => $capsuleOrder?->status === 'cancelled' ? 'Cancelled' : 'Payment review',
      default => null,
    };
    if ($capsuleOrder?->status === 'cancelled') {
      $capsuleCategory = 'Cancelled';
      $capsuleState = 'cancelled';
      $capsuleProgress = null;
    }
    if ($capsuleRefund) {
      $capsuleCategory = 'Return / Refund';
      $capsuleState = 'refunds';
      $capsuleProgress = $capsuleRefund->status === 'completed' ? 'Refunded' : 'Refund pending';
    }
   @endphp
   <article class="order-card shopee-order-card" data-purchase-title="{{ $purchase->productLabel() }}" data-purchase-reference="{{ $purchase->id }}">
    <div class="order-card-head"><div class="order-shop"><strong>{{ $seller?->business_name ?: $seller?->given_names ?: 'Shop' }}</strong><span class="admin-payment-buyer">{{ $buyerName }}</span></div><span class="gcash-badge admin-payment-status order-progress-capsule status-{{ $capsuleState }}"><span>{{ $capsuleCategory }}</span>@if(filled($capsuleProgress) && strcasecmp($capsuleCategory, $capsuleProgress) !== 0)<span class="order-progress-divider" aria-hidden="true">|</span><span class="order-progress-detail">{{ $capsuleProgress }}</span>@endif</span></div>
    <div class="order-products-list">
    @foreach($items as $item)
     @php
      $product=$images->get($item['product_id']??null);
      $image=$item['img']??null;
      if(!$image && $product?->image) $image=\Illuminate\Support\Facades\Storage::url($product->image);
      $variant=collect([$item['variation_group']??null,$item['variation_value']??$item['color']??null,$item['size']??null])->filter()->unique()->join(': ');
     @endphp
     <div class="order-product-row"><div class="order-product-image">@if($image)<img src="{{ $image }}" alt="{{ $item['name']??'Product' }}" loading="lazy">@else @include('buyer.partials.icon',['name'=>'package','size'=>24])@endif</div><div class="order-product-copy"><strong>{{ $item['name']??'Product' }}</strong>@if($variant)<span>Variation: {{ $variant }}</span>@endif</div><div class="order-line-end">@if($loop->last)<strong class="order-line-price" aria-label="Order total">₱{{ number_format($capsuleOrder?->total ?? $purchase->amount_due,2) }}</strong>@endif<small class="order-unit-price">{{ $item['qty']??1 }} × ₱{{ number_format($item['price']??0,2) }}</small>@if($loop->last)<a class="btn btn-outline order-details-button" data-gcash-payment href="{{ route('gcash.show',$purchase->id) }}">{{ $purchase->payment_state==='proof_submitted'?'Review Payment':'View Payment Details' }}</a>@endif</div></div>
    @endforeach
    @if($items->isEmpty())<div class="admin-payment-empty-item"><strong>{{ $purchase->productLabel() }}</strong><a class="btn btn-outline" data-gcash-payment href="{{ route('gcash.show',$purchase->id) }}">View Payment Details</a></div>@endif
    </div>
   </article>
   @empty<div class="admin-payment-empty"><h3>No payments found</h3><p>Payments matching these filters will appear here.</p></div>@endforelse
   {{ $purchases->withQueryString()->links() }}
  </div>
 </div></div>
 <div class="order-details-modal gcash-purchase-modal" data-order-summary id="gcashPurchaseModal" aria-hidden="true"><div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="gcashModalTitle"><div class="order-details-head"><div><h3 id="gcashModalTitle">GCash payment</h3><p class="order-reference">Purchase reference: <span data-gcash-modal-reference></span></p></div><div class="order-details-heading-actions"><button type="button" class="order-details-close-icon" data-gcash-modal-close aria-label="Close payment details">@include('buyer.partials.icon',['name'=>'x','size'=>18])</button><span class="order-kicker order-details-label">Payment details</span></div></div><iframe title="GCash payment details and admin review" data-gcash-modal-frame></iframe></div></div>
 <div class="order-details-modal admin-platform-account-modal" id="platformGcashAccountModal" aria-hidden="true"><div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="platformGcashAccountTitle"><div class="admin-platform-account-hero"><div class="admin-platform-account-icon">@include('buyer.partials.icon',['name'=>'wallet','size'=>23])</div><div><h3 id="platformGcashAccountTitle">Platform GCash account</h3></div><button type="button" class="order-details-close-icon" data-platform-account-close aria-label="Close platform GCash account settings">@include('buyer.partials.icon',['name'=>'x','size'=>18])</button></div><div class="admin-platform-account-body"><form method="POST" action="{{ route('gcash.platformAccount') }}" class="gcash-form">@csrf<div class="admin-platform-account-fields"><label><span>GCash account name</span><input name="platform_gcash_name" value="{{ old('platform_gcash_name',$platformSettings?->platform_gcash_name) }}" maxlength="150" required></label><label><span>GCash mobile number</span><span class="gcash-mobile-input"><b>+63</b><input type="tel" name="platform_gcash_number" value="{{ old('platform_gcash_number', $platformSettings?->platform_gcash_number ? substr($platformSettings->platform_gcash_number,1) : '') }}" pattern="9[0-9]{9}" maxlength="10" placeholder="9XXXXXXXXX" required></span></label></div><div class="admin-platform-account-actions"><button class="gcash-button" type="submit">Save platform account</button></div></form></div></div></div>
</div>
