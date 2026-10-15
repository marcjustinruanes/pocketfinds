@extends('buyer.layout')
@section('title', 'My Orders')
@section('page-title', 'My Orders')
@section('page-sub', 'Track and manage your orders')

@section('content')
<div class="orders-page">
<div class="order-tabs-shell">
<div class="tabs order-tabs">
  @php
  $orderTabs = [
    ['all',              'bag',     'All'],
    ['to_ship',          'package', 'To Ship'],
    ['in_transit',       'truck',   'In Transit'],
    ['out_for_delivery', 'bike',    'Out for Delivery'],
    ['delivered',        'home',    'Delivered'],
    ['completed',        'check',   'Completed'],
    ['cancelled',        'x',       'Cancelled'],
  ];
  @endphp
  @php
    $tabCount = fn ($key) => match ($key) {
      'all'        => $orderCounts->sum(),
      'to_ship'    => collect(\App\Models\Order::BUYER_TO_SHIP_STATUSES)->sum(fn ($s) => (int) ($orderCounts[$s] ?? 0)),
      'in_transit' => collect(\App\Models\Order::BUYER_IN_TRANSIT_STATUSES)->sum(fn ($s) => (int) ($orderCounts[$s] ?? 0)),
      default      => (int) ($orderCounts[$key] ?? 0),
    };
  @endphp
  @foreach($orderTabs as [$key, $icon, $label])
  <a href="{{ route('buyer.orders') }}?tab={{ $key }}" class="tab {{ $tab === $key ? 'active' : '' }}">
    <span style="display:inline-flex;align-items:center;gap:5px">
      @include('buyer.partials.icon', ['name' => $icon, 'size' => 14])
      {{ $label }}
      @if($tabCount($key))<span class="order-tab-count">{{ $tabCount($key) }}</span>@endif
    </span>
  </a>
  @endforeach
</div>
</div>

<div class="orders-toolbar">
  <span class="orders-search-icon">@include('buyer.partials.icon', ['name' => 'search', 'size' => 14])</span>
  <input type="search" id="orderSearch" placeholder="Search by order ID, shop, or product">
</div>

@if(session('success'))<div class="auth-success" style="margin-bottom:16px">{{ session('success') }}</div>@endif
@if(session('error'))<div class="auth-error" style="margin-bottom:16px">{{ session('error') }}</div>@endif
@forelse($orders as $order)
<div class="order-card shopee-order-card" data-order-search="{{ strtolower($order->order_number . ' ' . ($order->seller?->business_name ?? '') . ' ' . collect($order->items ?? [])->pluck('name')->join(' ')) }}">
  <div class="order-card-head">
    <div class="order-shop"><strong>{{ $order->seller?->business_name ?: ($order->seller?->given_names ?: 'Seller not provided') }}</strong><div class="seller-actions"><a href="{{ route('buyer.messages', ['seller' => $order->seller?->username, 'order' => $order->id]) }}" data-messages-trigger>Chat</a><a href="{{ route('buyer.shop', $order->seller?->username) }}">View Shop</a></div></div>
    <span class="stamp stamp-{{ $order->status === 'placed' ? 'new' : $order->status }}">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span>
  </div>
  <div class="order-meta"><span class="mono">{{ $order->order_number }}</span><span>{{ $order->created_at?->format('M d, Y h:i A') ?? '—' }}</span></div>
  <div class="order-products-list">
    @foreach($order->itemsWithImages() as $item)
    <div class="order-product-row">
      <div class="order-product-image">@if(!empty($item['img']))<img src="{{ $item['img'] }}" alt="{{ $item['name'] ?? 'Product' }}">@else<span>IMG</span>@endif</div>
      <div class="order-product-copy">
        <strong>{{ $item['name'] ?? 'Product not provided' }}</strong>
        @php
          $variation = collect([$item['variation_group'] ?? null, $item['variation_value'] ?? $item['color'] ?? null, $item['size'] ?? null])->filter()->unique()->join(': ');
        @endphp
        @if($variation)<span>Variation: {{ $variation }}</span>@endif
        <span>x{{ $item['qty'] ?? 1 }}</span>
      </div>
      <div class="order-line-end">
        <strong class="order-line-price">₱{{ number_format(($item['price'] ?? 0) * ($item['qty'] ?? 1), 2) }}</strong>
        @if($loop->last)
          <button type="button" class="btn btn-outline order-details-button" data-order-details="orderDetails{{ $order->id }}">View Order Details</button>
        @endif
      </div>
    </div>
    @endforeach
  </div>
  @if($order->status === 'out_for_delivery')
  <div class="order-actions" style="border-top:1px dashed var(--border,#eee);padding-top:10px">
    <span style="color:var(--muted,#888)">Your rider is on the way — Confirm Receipt unlocks once they mark it delivered.</span>
  </div>
  @endif
  @if($order->status === 'delivered')
  <div class="order-actions" style="border-top:1px dashed var(--border,#eee);padding-top:10px">
    <span>Already have your package?</span>
    <form method="POST" action="{{ route('buyer.orders.confirm-receipt', $order) }}" onsubmit="return confirm('Confirm that you have received this order?')">
      @csrf @method('PATCH')
      <button type="submit" class="btn btn-primary btn-sm">Confirm Receipt</button>
    </form>
  </div>
  @endif
  @if($order->status === 'completed')
  <div class="order-actions" style="border-top:1px dashed var(--border,#eee);padding-top:10px">
    @if($order->review)
      <span>You rated this order {{ $order->review->rating }}/5 ★</span>
    @else
      <button type="button" class="btn btn-primary btn-sm rate-order-open" data-order-details="rateOrder{{ $order->id }}">Rate Order</button>
    @endif
    <form method="POST" action="{{ route('buyer.orders.buy-again', $order) }}">
      @csrf
      <button type="submit" class="btn btn-outline btn-sm">Buy Again</button>
    </form>
  </div>
  @endif
</div>
@if($order->status === 'completed' && !$order->review)
<div class="order-details-modal" id="rateOrder{{ $order->id }}" aria-hidden="true">
  <div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="rateOrderTitle{{ $order->id }}">
    <div class="order-details-head"><div><span class="order-kicker">Rate your order</span><h3 id="rateOrderTitle{{ $order->id }}">{{ $order->order_number }}</h3></div><button type="button" class="modal-close order-details-close" aria-label="Close rate order">&times;</button></div>
    <form method="POST" action="{{ route('buyer.orders.review', $order) }}" style="display:flex;flex-direction:column;gap:12px;padding:4px 0">
      @csrf
      <div style="display:flex;gap:6px;font-size:26px">
        @for($star = 1; $star <= 5; $star++)
        <label style="cursor:pointer;color:var(--border,#ddd)">
          <input type="radio" name="rating" value="{{ $star }}" required style="display:none" onclick="this.closest('form').querySelectorAll('label').forEach((l,i)=>l.style.color=i<{{ $star }}?'#f59e0b':'var(--border,#ddd)')">★
        </label>
        @endfor
      </div>
      <textarea name="comment" rows="3" maxlength="1000" placeholder="Tell other buyers about this product (optional)"></textarea>
      <div class="order-modal-actions"><button type="submit" class="btn btn-primary">Submit Rating</button></div>
    </form>
  </div>
</div>
@endif
<div class="order-details-modal" data-order-summary id="orderDetails{{ $order->id }}" aria-hidden="true">
  <div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="orderDetailsTitle{{ $order->id }}">
    <div class="order-details-head"><div><span class="order-kicker">Order details</span><h3 id="orderDetailsTitle{{ $order->id }}">{{ ($order->items[0]['name'] ?? 'Your order').(count($order->items ?? []) > 1 ? ' + '.(count($order->items) - 1).' more' : '') }}</h3><p class="order-reference">Order reference: <span data-order-reference>{{ $order->order_number }}</span> <button type="button" class="order-copy-reference" aria-label="Copy order reference">Copy</button></p></div><button type="button" class="modal-close order-details-close" aria-label="Close order details">&times;</button></div>
    <div class="order-details-body" tabindex="0" role="region" aria-label="Scrollable order information">
    @php
      $timeline = \App\Support\OrderTimeline::forOrder($order, $orderHistory->get($order->id, collect()));
      $addressPart = fn ($part) => filled($part) && $part !== 'Not provided';
      $addressLine1 = collect([$order->shipping_address['house_no'] ?? null, $order->shipping_address['street'] ?? null])->filter($addressPart)->join(', ');
      $addressLine2 = collect([$order->shipping_address['barangay'] ?? null, $order->shipping_address['municipality'] ?? null, $order->shipping_address['province'] ?? null])->filter($addressPart)->join(', ');
    @endphp
    <div class="order-current-status {{ $timeline['stopped'] ? 'is-interrupted' : '' }}"><strong>{{ ucwords(str_replace('_', ' ', $order->status)) }}</strong><p>{{ $timeline['explanation'] }}</p></div>
    <details class="order-tracking"><summary>Delivery progress <span>{{ ucwords(str_replace('_', ' ', $order->status)) }}</span></summary>
     <ol class="tracking-steps" aria-label="Recorded delivery progress">
      @foreach($timeline['rows'] as $step)
       <li class="tracking-step is-{{ $step['state'] }}" @if($step['state'] === 'current') aria-current="step" @endif>
        <span class="tracking-dot" aria-hidden="true">{{ $step['state'] === 'completed' ? '✓' : $loop->iteration }}</span>
        <div><strong>{{ $step['title'] }}</strong><span class="tracking-state">{{ $step['label'] }}</span>
         @if($step['timestamp'])<time datetime="{{ $step['timestamp']->toIso8601String() }}">{{ $step['timestamp']->format('M j, Y g:i A') }} {{ config('app.timezone') }}</time>@endif
        </div>
       </li>
      @endforeach
     </ol>
    </details>
    <div class="order-details-list">@foreach($order->items as $item)<div class="order-item"><span>{{ $item['name'] ?: 'Product not provided' }} @if(($item['variation_value'] ?? $item['color'] ?? '') || ($item['size'] ?? ''))<small>{{ collect([$item['variation_group'] ?? null, $item['variation_value'] ?? $item['color'] ?? null, $item['size'] ?? null])->filter()->unique()->join(': ') }}</small>@endif</span><strong>{{ $item['qty'] }} × ₱{{ number_format($item['price'], 2) }}</strong></div>@endforeach</div>
    <div class="order-detail-info-grid">
      <div><span>Order status</span><strong>{{ ucwords(str_replace('_', ' ', $order->status)) }}</strong></div>
      <div><span>Payment method</span><strong>{{ $order->paymentMethod?->name ?: $order->payment_method ?: 'Payment not provided' }}</strong></div>
      <div class="order-address-info"><span>Delivery address</span><address>@if($addressLine1){{ $addressLine1 }}<br>@endif{{ $addressLine2 ?: ($addressLine1 ? '' : 'Address not provided') }}</address></div>
      @if($order->buyer_note)<div class="order-address-info"><span>Message for seller</span><strong>{{ $order->buyer_note }}</strong></div>@endif
    </div>
    <div class="order-price-breakdown">
      <div><span>Merchandise subtotal</span><strong>₱{{ number_format($order->subtotal, 2) }}</strong></div>
      <div><span>Shipping fee</span><strong>₱{{ number_format($order->shipping_amount, 2) }}</strong></div>
      @if((float)$order->discount_amount > 0)<div><span>Voucher discount</span><strong class="discount">−₱{{ number_format($order->discount_amount, 2) }}</strong></div>@endif
      <div class="total"><span>Order total</span><strong>₱{{ number_format($order->total, 2) }}</strong></div>
    </div>
    @if(in_array($order->status, \App\Models\Order::CANCELLABLE_STATUSES, true))
      <div class="order-modal-actions"><button type="button" class="btn btn-danger order-cancel-open">Cancel Order</button></div>
      <div class="order-cancel-panel" hidden>
        <form class="order-cancel-form" data-confirm-dialog="cancelConfirm{{ $order->id }}" method="POST" action="{{ route('buyer.orders.cancel', $order) }}">
          @csrf @method('PATCH')
          <label for="cancelReason{{ $order->id }}"><strong>Why are you cancelling?</strong></label>
          <select id="cancelReason{{ $order->id }}" name="cancellation_reason" required><option value="" selected disabled>Select a reason</option><option>Changed my mind</option><option>Found a better price</option><option>Ordered by mistake</option><option>Payment issue</option><option>Other</option></select>
          <textarea aria-label="Cancellation note (optional)" name="cancellation_note" rows="3" maxlength="500" placeholder="Add a note (optional)"></textarea>
          <p class="order-cancel-feedback" role="alert" tabindex="-1"></p><div class="order-modal-actions"><button type="button" class="btn btn-outline order-cancel-back">Keep Order</button><button type="submit" class="btn btn-danger">Review Cancellation</button></div>
        </form>
      </div>
    @elseif($order->status === 'cancelled')
      <div class="order-cancelled-note">Cancelled: {{ $order->cancellation_reason ?: 'Reason not provided' }}</div>
    @endif
    </div><div class="order-details-footer"><button type="button" class="btn btn-primary order-details-close">Done</button></div>
  </div>
</div>
@if(in_array($order->status, \App\Models\Order::CANCELLABLE_STATUSES, true))
<dialog class="order-cancel-confirm" id="cancelConfirm{{ $order->id }}" aria-labelledby="cancelTitle{{ $order->id }}" aria-describedby="cancelConsequences{{ $order->id }}">
 <h3 id="cancelTitle{{ $order->id }}">Cancel this order?</h3>
 <p><strong>{{ ($order->items[0]['name'] ?? 'Your order').(count($order->items ?? []) > 1 ? ' + '.(count($order->items) - 1).' more' : '') }}</strong></p>
 <p class="order-reference">{{ $order->order_number }}</p>
 <p id="cancelConsequences{{ $order->id }}">Cancellation stops fulfillment of this order. Stock adjustments will follow the existing order rules.
 @if($order->gcash_purchase_id) Your GCash payment is not automatically refunded. Contact the seller to reconcile any money already received. @endif</p>
 <p>Order total: <strong>₱{{ number_format($order->total, 2) }}</strong></p>
 <p data-cancel-reason></p>
 <div class="order-modal-actions"><button type="button" class="btn btn-outline" data-keep-order autofocus>Keep Order</button><button type="button" class="btn btn-danger" data-confirm-cancellation>Confirm Cancellation</button></div>
</dialog>
@endif
@empty
<div class="card"><div class="card-pad"><div class="empty"><div class="ic">@include('buyer.partials.icon', ['name' => 'package', 'size' => 28])</div><h3>No orders yet</h3><p>Orders with status "{{ str_replace('_', ' ', ucfirst($tab)) }}" will appear here.</p>@if($tab === 'to_ship')<a href="{{ route('buyer.browse') }}" class="btn btn-primary" style="margin-top:14px">Start Shopping</a>@endif</div></div></div>
@endforelse
</div>
@endsection

@push('head')
<link rel="stylesheet" href="{{ asset('css/order-details.css') }}?v={{ filemtime(public_path('css/order-details.css')) }}">
<script src="{{ asset('js/order-details.js') }}?v={{ filemtime(public_path('js/order-details.js')) }}" defer></script>
@endpush
