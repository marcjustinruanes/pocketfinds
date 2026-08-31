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
    ['completed',        'check',   'Completed'],
    ['cancelled',        'x',       'Cancelled'],
  ];
  @endphp
  @foreach($orderTabs as [$key, $icon, $label])
  <a href="{{ route('buyer.orders') }}?tab={{ $key }}" class="tab {{ $tab === $key ? 'active' : '' }}">
    <span style="display:inline-flex;align-items:center;gap:5px">
      @include('buyer.partials.icon', ['name' => $icon, 'size' => 14])
      {{ $label }}
      @php
        $count = $key === 'all' ? $orderCounts->sum() : (int) ($orderCounts[$key] ?? 0);
      @endphp
      @if($count)<span class="order-tab-count">{{ $count }}</span>@endif
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
    <div class="order-shop"><strong>{{ $order->seller?->business_name ?: ($order->seller?->given_names ?: 'Seller not provided') }}</strong><div class="seller-actions"><a href="{{ route('buyer.messages', ['seller' => $order->seller?->username]) }}" data-messages-trigger>Chat</a><a href="{{ route('buyer.shop', $order->seller?->username) }}">View Shop</a></div></div>
    <span class="stamp stamp-{{ $order->status === 'to_ship' ? 'new' : $order->status }}">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span>
  </div>
  <div class="order-meta"><span class="mono">{{ $order->order_number }}</span><span>{{ $order->created_at->format('M d, Y h:i A') }}</span></div>
  <div class="order-products-list">
    @foreach($order->items ?? [] as $item)
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
<div class="order-details-modal" id="orderDetails{{ $order->id }}" aria-hidden="true">
  <div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="orderDetailsTitle{{ $order->id }}">
    <div class="order-details-head"><div><span class="order-kicker">Order details</span><h3 id="orderDetailsTitle{{ $order->id }}">{{ $order->order_number }}</h3></div><button type="button" class="modal-close order-details-close" aria-label="Close order details">&times;</button></div>
    @php
      $trackingSteps = [
        ['Order placed', $order->created_at->format('M d, Y · h:i A')],
        ['Order confirmed', 'The seller received your order.'],
        ['Preparing to ship', 'The seller is packing your items.'],
        ['Package in transit', $order->shipment?->tracking_number ? 'Tracking: ' . $order->shipment->tracking_number : 'Your package is on its way.'],
        ['Out for delivery', 'Your package is arriving soon.'],
        ['Delivered', 'Order completed.'],
      ];
      $currentStep = match($order->status) {
        'in_transit' => 4, 'out_for_delivery' => 5, 'completed' => 6,
        'cancelled' => 1,
        default => $order->shipment ? 3 : 2,
      };
    @endphp
    <div class="order-tracking"><span class="order-kicker">Delivery tracking</span><div class="tracking-steps">@foreach($trackingSteps as $step => [$label, $copy])<div class="tracking-step {{ $step + 1 < $currentStep ? 'is-done' : ($step + 1 === $currentStep ? 'is-current' : '') }}"><span class="tracking-dot">{{ $step + 1 < $currentStep ? '✓' : $step + 1 }}</span><div><strong>{{ $label }}</strong><small>{{ $step + 1 === $currentStep ? $copy : $copy }}</small></div></div>@endforeach</div></div>
    <div class="order-details-list">@foreach($order->items as $item)<div class="order-item"><span>{{ $item['name'] ?: 'Product not provided' }} @if(($item['variation_value'] ?? $item['color'] ?? '') || ($item['size'] ?? ''))<small>{{ collect([$item['variation_group'] ?? null, $item['variation_value'] ?? $item['color'] ?? null, $item['size'] ?? null])->filter()->unique()->join(': ') }}</small>@endif</span><strong>{{ $item['qty'] }} × ₱{{ number_format($item['price'], 2) }}</strong></div>@endforeach</div>
    <div class="order-detail-info-grid">
      <div><span>Order status</span><strong>{{ ucwords(str_replace('_', ' ', $order->status)) }}</strong></div>
      <div><span>Payment method</span><strong>{{ $order->paymentMethod?->name ?: $order->payment_method ?: 'Payment not provided' }}</strong></div>
      <div class="order-address-info"><span>Delivery address</span><strong>{{ collect([$order->shipping_address['house_no'] ?? null, $order->shipping_address['street'] ?? null, $order->shipping_address['barangay'] ?? null, $order->shipping_address['municipality'] ?? null, $order->shipping_address['province'] ?? null])->filter(fn($part) => $part && $part !== 'Not provided')->join(', ') ?: 'Address not provided' }}</strong></div>
      @if($order->buyer_note)<div class="order-address-info"><span>Message for seller</span><strong>{{ $order->buyer_note }}</strong></div>@endif
    </div>
    <div class="order-price-breakdown">
      <div><span>Merchandise subtotal</span><strong>₱{{ number_format($order->subtotal, 2) }}</strong></div>
      <div><span>Shipping fee</span><strong>₱{{ number_format($order->shipping_amount, 2) }}</strong></div>
      @if((float)$order->discount_amount > 0)<div><span>Voucher discount</span><strong class="discount">−₱{{ number_format($order->discount_amount, 2) }}</strong></div>@endif
      <div class="total"><span>Order total</span><strong>₱{{ number_format($order->total, 2) }}</strong></div>
    </div>
    @if($order->status === 'to_ship')
      <div class="order-modal-actions"><button type="button" class="btn btn-danger order-cancel-open">Cancel Order</button></div>
      <div class="order-cancel-panel" hidden>
        <form method="POST" action="{{ route('buyer.orders.cancel', $order) }}">
          @csrf @method('PATCH')
          <strong>Why are you cancelling?</strong>
          <select name="cancellation_reason" required><option value="" selected disabled>Select a reason</option><option>Changed my mind</option><option>Found a better price</option><option>Ordered by mistake</option><option>Payment issue</option><option>Other</option></select>
          <textarea name="cancellation_note" rows="3" maxlength="500" placeholder="Add a note (optional)"></textarea>
          <div class="order-modal-actions"><button type="button" class="btn btn-outline order-cancel-back">Keep Order</button><button type="submit" class="btn btn-danger">Confirm Cancellation</button></div>
        </form>
      </div>
    @elseif($order->status === 'cancelled')
      <div class="order-cancelled-note">Cancelled: {{ $order->cancellation_reason ?: 'Reason not provided' }}</div>
    @endif
  </div>
</div>
@empty
<div class="card"><div class="card-pad"><div class="empty"><div class="ic">@include('buyer.partials.icon', ['name' => 'package', 'size' => 28])</div><h3>No orders yet</h3><p>Orders with status "{{ str_replace('_', ' ', ucfirst($tab)) }}" will appear here.</p>@if($tab === 'to_ship')<a href="{{ route('buyer.browse') }}" class="btn btn-primary" style="margin-top:14px">Start Shopping</a>@endif</div></div></div>
@endforelse
</div>
@endsection

@push('head')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const orderSearch = document.getElementById('orderSearch');
  orderSearch?.addEventListener('input', () => {
    const query = orderSearch.value.trim().toLowerCase();
    document.querySelectorAll('[data-order-search]').forEach(card => card.hidden = query && !card.dataset.orderSearch.includes(query));
  });
  document.querySelectorAll('[data-order-details]').forEach(button => {
    const modal = document.getElementById(button.dataset.orderDetails);
    const close = () => { modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true'); };
    button.addEventListener('click', () => { modal.classList.add('open'); modal.setAttribute('aria-hidden', 'false'); });
    modal.querySelector('.order-details-close').addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    const cancelOpen = modal.querySelector('.order-cancel-open');
    const cancelPanel = modal.querySelector('.order-cancel-panel');
    cancelOpen?.addEventListener('click', () => { cancelPanel.hidden = false; cancelOpen.hidden = true; });
    modal.querySelector('.order-cancel-back')?.addEventListener('click', () => { cancelPanel.hidden = true; cancelOpen.hidden = false; });
  });
});
</script>
@endpush
