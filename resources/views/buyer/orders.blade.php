@extends('buyer.layout')
@section('title', 'My Orders')
@section('page-title', 'My Orders')
@section('page-sub', 'Track and manage your orders')

@section('content')
@php $tab = request('tab', 'to_ship'); @endphp

<div class="tabs">
  @php
  $orderTabs = [
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
    </span>
  </a>
  @endforeach
</div>

@if(session('success'))<div class="auth-success" style="margin-bottom:16px">{{ session('success') }}</div>@endif
@forelse($orders as $order)
@php $firstItem = collect($order->items ?? [])->first() ?? []; @endphp
<div class="order-card">
  <div class="order-card-head">
    <div><span class="order-kicker">Seller</span><strong>{{ $order->seller?->business_name ?: ($order->seller?->given_names ?: 'Seller not provided') }}</strong><div class="seller-actions"><a href="{{ route('buyer.messages', ['seller' => $order->seller?->username]) }}">Chat</a><a href="{{ route('buyer.shop', $order->seller?->username) }}">View Shop</a></div></div>
    <span class="stamp stamp-{{ $order->status === 'to_ship' ? 'new' : $order->status }}">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span>
  </div>
  <div class="order-meta"><span class="mono">{{ $order->order_number }}</span><span>{{ $order->created_at->format('M d, Y h:i A') }}</span></div>
  <div class="order-preview">
    <div class="order-product-image">@if(!empty($firstItem['img']))<img src="{{ $firstItem['img'] }}" alt="{{ $firstItem['name'] ?? 'Product' }}">@else<span>IMG</span>@endif</div>
    <div class="order-product-copy"><strong>{{ $firstItem['name'] ?? 'Product not provided' }}</strong><span>Qty {{ $firstItem['qty'] ?? 1 }} @if(count($order->items ?? []) > 1) · {{ count($order->items) - 1 }} more item(s) @endif</span></div>
    <button type="button" class="btn btn-outline order-details-button" data-order-details="orderDetails{{ $order->id }}">Order Details</button>
  </div>
  <div class="order-actions"><span>Order confirmed and waiting for seller to ship</span><div><small>Shipping PHP {{ number_format($order->shipping_amount, 2) }}</small><strong>Total PHP {{ number_format($order->total, 2) }}</strong></div></div>
</div>
<div class="order-details-modal" id="orderDetails{{ $order->id }}" aria-hidden="true">
  <div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="orderDetailsTitle{{ $order->id }}">
    <div class="order-details-head"><div><span class="order-kicker">Order details</span><h3 id="orderDetailsTitle{{ $order->id }}">{{ $order->order_number }}</h3></div><button type="button" class="modal-close order-details-close" aria-label="Close order details">&times;</button></div>
    @php
      $trackingSteps = [
        ['Order placed', 'We received your order.'],
        ['Seller is reviewing', 'The seller is checking your order.'],
        ['Seller is preparing', 'Your package is being packed.'],
        ['Package in transit', 'The rider is delivering your package.'],
        ['Delivered', 'Enjoy your purchase.'],
      ];
      $currentStep = match($order->status) { 'in_transit' => 3, 'out_for_delivery' => 4, 'completed' => 5, default => 2 };
    @endphp
    <div class="order-tracking"><span class="order-kicker">Delivery tracking</span><div class="tracking-steps">@foreach($trackingSteps as $step => [$label, $copy])<div class="tracking-step {{ $step + 1 < $currentStep ? 'is-done' : ($step + 1 === $currentStep ? 'is-current' : '') }}"><span class="tracking-dot">{{ $step + 1 < $currentStep ? '✓' : $step + 1 }}</span><div><strong>{{ $label }}</strong><small>{{ $step + 1 === $currentStep ? $copy : $copy }}</small></div></div>@endforeach</div></div>
    <div class="order-details-list">@foreach($order->items as $item)<div class="order-item"><span>{{ $item['name'] ?: 'Product not provided' }} @if(($item['color'] ?? '') || ($item['size'] ?? ''))<small>{{ collect([$item['color'] ?? null, $item['size'] ?? null])->filter()->join(', ') }}</small>@endif</span><strong>{{ $item['qty'] }} × PHP {{ number_format($item['price'], 2) }}</strong></div>@endforeach</div>
    <div class="order-details"><div><span>Deliver to</span><strong>{{ collect([$order->shipping_address['barangay'] ?? null, $order->shipping_address['municipality'] ?? null, $order->shipping_address['province'] ?? null])->filter()->join(', ') ?: 'Address not provided' }}</strong></div><div><span>Payment</span><strong>{{ $order->paymentMethod?->name ?: $order->payment_method ?: 'Payment not provided' }}</strong></div></div>
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
@endsection

@push('head')
<script>
document.addEventListener('DOMContentLoaded', () => {
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
