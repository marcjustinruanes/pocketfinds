@extends('buyer.layout')
@section('title', 'My Orders')
@section('page-title', 'My Orders')
@section('page-sub', 'Track and manage your orders')
@section('hide-page-heading', true)
@section('body-class', 'buyer-orders-page')

@section('content')
<div class="orders-page">
@if($errors->any())<div class="auth-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="orders-layout">
<aside class="order-tabs-shell" aria-label="Order status filters">
<nav class="tabs order-tabs">
  @php
  $orderTabs = [
    ['all',              'category-all',  'All'],
    ['to_ship',          'clipboard-check','To Ship'],
    ['in_transit',       'map',           'In Transit'],
    ['out_for_delivery', 'rider-delivery','Out for Delivery'],
    ['delivered',        'package-received','Delivered'],
    ['completed',        'shield',        'Completed'],
    ['cancelled',        'ban',     'Cancelled'],
    ... (\App\Services\BuyerCommerce::installed() ? [['refunds','wallet','Return / Refund']] : []),
  ];
  @endphp
  @php
    $tabCount = fn ($key) => match ($key) {
      'all'        => $orderCounts->sum(),
      'refunds' => $refundOrderIds->count(),
      'to_ship'    => collect(\App\Models\Order::BUYER_TO_SHIP_STATUSES)->sum(fn ($s) => (int) ($orderCounts[$s] ?? 0)),
      'in_transit' => collect(\App\Models\Order::BUYER_IN_TRANSIT_STATUSES)->sum(fn ($s) => (int) ($orderCounts[$s] ?? 0)),
      default      => (int) ($orderCounts[$key] ?? 0),
    };
  @endphp
  @foreach($orderTabs as [$key, $icon, $label])
  <a href="{{ route('buyer.orders', array_filter(['tab' => $key, 'period' => $period !== 'all' ? $period : null, 'payment' => $payment !== 'all' ? $payment : null])) }}" class="tab {{ $tab === $key ? 'active' : '' }}" aria-label="{{ $label }} ({{ $tabCount($key) }})" title="{{ $label }}" @if($tab === $key) aria-current="page" @endif>
    <span class="order-tab-icon">
      @if($key === 'all')
        @include('guest.partials.category-filter-icon', ['category' => null])
      @else
        @include('buyer.partials.icon', ['name' => $icon, 'size' => 15])
      @endif
    </span>
    <span class="order-tab-label">{{ $label }}</span>
    @if($tabCount($key))<span class="order-tab-count">{{ $tabCount($key) }}</span>@endif
  </a>
  @endforeach
</nav>
</aside>

<div class="orders-content">
<form class="orders-toolbar" method="GET" aria-label="Filter orders">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div class="orders-search-field"><span class="orders-search-icon">@include('buyer.partials.icon', ['name' => 'search', 'size' => 14])</span><input type="search" id="orderSearch" placeholder="Search orders" aria-label="Search orders"></div>
  <label class="orders-filter-field"><span>Date</span><select name="period" onchange="this.form.requestSubmit()"><option value="all" @selected($period === 'all')>All time</option><option value="30d" @selected($period === '30d')>Last 30 days</option><option value="6m" @selected($period === '6m')>Last 6 months</option><option value="year" @selected($period === 'year')>This year</option></select></label>
  <label class="orders-filter-field"><span>Payment</span><select name="payment" onchange="this.form.requestSubmit()"><option value="all" @selected($payment === 'all')>All payments</option><option value="cod" @selected($payment === 'cod')>Cash on Delivery</option><option value="gcash" @selected($payment === 'gcash')>GCash</option></select></label>
</form>
@if(session('success'))<div class="auth-success" style="margin-bottom:16px">{{ session('success') }}</div>@endif
@if(session('error'))<div class="auth-error" style="margin-bottom:16px">{{ session('error') }}</div>@endif
@forelse($orders as $order)
@php
  $adminPayment = $order->relationLoaded('gcashPurchase') && $order->gcashPurchase?->usesAdminReview();
  $paymentState = $adminPayment ? $order->gcashPurchase->payment_state : null;
  $paymentApproved = $adminPayment && $paymentState === 'order_placed';
  $cardCancellationRefund = $order->status === 'cancelled' ? $cancellationRefunds->get($order->id) : null;
  [$cardStatus, $cardStatusClass] = match (true) {
    (bool) $cardCancellationRefund => ['Return / Refund', 'returned'],
    in_array($order->status, \App\Models\Order::BUYER_TO_SHIP_STATUSES, true) => ['To Ship', 'new'],
    in_array($order->status, \App\Models\Order::BUYER_IN_TRANSIT_STATUSES, true) => ['In Transit', 'picked_up'],
    $order->status === 'out_for_delivery' => ['Out for Delivery', 'out_for_delivery'],
    $order->status === 'delivered' => ['Delivered', 'delivered'],
    $order->status === 'completed' => ['Completed', 'completed'],
    $order->status === 'cancelled' => ['Cancelled', 'cancelled'],
    $order->status === 'returned' => ['Return / Refund', 'returned'],
    default => [str_replace('_', ' ', ucfirst($order->status)), $order->status],
  };
  $cardProgress = str_replace('_', ' ', ucfirst($order->status));
  if ($adminPayment && $order->status === 'placed') {
    $cardProgress = match ($paymentState) {
      'awaiting_payment' => 'Awaiting receipt',
      'proof_submitted' => 'Admin reviewing payment',
      'underpaid' => 'Payment short',
      'verified', 'order_placed' => 'Payment approved',
      'rejected' => 'Payment rejected',
      'manual_resolution' => 'Payment review',
      default => 'Order placed',
    };
  }
  if ($order->status === 'cancelled') {
    $cardProgress = preg_replace('/^Cancelled by (?:seller|buyer):\s*/i', '', $order->cancellation_reason ?? '');
  }
  if ($cardCancellationRefund) {
    $cardProgress = $cardCancellationRefund->status === 'completed' ? 'Refunded' : 'Refund pending';
  }
  $showCardProgress = filled($cardProgress) && strcasecmp($cardStatus, $cardProgress) !== 0;
@endphp
<div class="order-card shopee-order-card" data-order-search="{{ strtolower($order->order_number . ' ' . ($order->seller?->business_name ?? '') . ' ' . collect($order->items ?? [])->pluck('name')->join(' ')) }}">
  <div class="order-card-head">
    <div class="order-shop"><strong>{{ $order->seller?->business_name ?: ($order->seller?->given_names ?: 'Seller not provided') }}</strong><div class="seller-actions"><a href="{{ route('buyer.shop', $order->seller?->username) }}" aria-label="View shop" title="View shop">@include('buyer.partials.icon',['name'=>'shop','size'=>16])</a><span aria-hidden="true">|</span><a href="{{ route('buyer.messages', ['seller' => $order->seller?->username, 'order' => $order->id]) }}" data-messages-trigger aria-label="Message about this order" title="Message seller">@include('buyer.partials.icon',['name'=>'chat','size'=>16])</a></div></div>
    <span class="stamp order-progress-capsule stamp-{{ $cardStatusClass }}"><span>{{ $cardStatus }}</span>@if($showCardProgress)<span class="order-progress-divider" aria-hidden="true">|</span><span class="order-progress-detail">{{ $cardProgress }}</span>@endif</span>
  </div>
  @if($order->delivery_guaranteed_from && $order->delivery_guaranteed_by)
    <p class="order-reference">Guaranteed to get by {{ \App\Services\DeliveryGuarantee::label($order->delivery_guaranteed_from->toDateTimeString(), $order->delivery_guaranteed_by->toDateTimeString()) }}. @if(($order->commerce_snapshot['delivery_policy']['late_voucher_amount'] ?? 40) > 0)Get a ₱{{ number_format($order->commerce_snapshot['delivery_policy']['late_voucher_amount'] ?? 40, 2) }} voucher if your order arrives late.@endif</p>
  @endif
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
      </div>
      <div class="order-line-end">
        @if($loop->last)<strong class="order-line-price" aria-label="Order total">₱{{ number_format($order->total, 2) }}</strong>@endif
        <small class="order-unit-price">{{ $item['qty'] ?? 1 }} × ₱{{ number_format($item['price'] ?? 0,2) }}</small>
        @if($loop->last)
          <button type="button" class="btn btn-outline order-details-button" data-order-details="orderDetails{{ $order->id }}">{{ $adminPayment && !$paymentApproved && $order->status !== 'cancelled' ? 'Continue payment' : 'View Order Details' }}</button>
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
  @if($order->status === 'delivered' && !$returnRequests->has($order->id))
  <div class="order-actions" style="border-top:1px dashed var(--border,#eee);padding-top:10px">
    <span>Already have your package?</span>
    <form method="POST" action="{{ route('buyer.orders.confirm-receipt', $order) }}" onsubmit="return confirm('Confirm that you have received this order?')">
      @csrf @method('PATCH')
      <button type="submit" class="btn btn-primary btn-sm">Confirm Receipt</button>
    </form>
  </div>
  @endif
  @if($order->status === 'completed')
  <div class="order-actions order-review-actions" style="border-top:1px dashed var(--border,#eee);padding-top:10px">
    @if(\App\Services\BuyerCommerce::installed() && !$returnRequests->has($order->id) && \App\Services\BuyerCommerce::returnWindowOpen($order))
     <button type="button" class="btn btn-outline btn-sm order-refund-open" data-order-details="refundOrder{{ $order->id }}">Request Return / Refund</button>
    @endif
    @if(\App\Services\BuyerCommerce::installed() && !$returnRequests->has($order->id) && !\App\Services\BuyerCommerce::returnWindowOpen($order))
     <span class="order-return-expired-inline">Return / Refund unavailable: the seven-day window after delivery has ended.</span>
    @endif
    @if(\App\Services\BuyerCommerce::installed())
      @if($order->review)<button type="button" class="btn btn-outline btn-sm" data-order-details="myReviews{{ $order->id }}">View my review</button>@endif
      @php
       $ratedItemCount=\App\Models\Review::where('order_id',$order->id)->where('buyer_id',auth()->id())->distinct('item_key')->count('item_key');
      @endphp
      <button type="button" class="btn btn-primary btn-sm rate-order-open" data-order-details="rateOrder{{ $order->id }}">{{ $ratedItemCount >= count($order->items) ? 'Follow up review' : ($ratedItemCount ? 'Review / follow up' : 'Review Items') }}</button>
    @elseif($order->review)
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
@if(\App\Services\BuyerCommerce::installed())@include('buyer.partials.order-return')@endif
</div>
@if($order->status === 'completed' && \App\Services\BuyerCommerce::installed())
@include('buyer.partials.order-reviews')
@elseif($order->status === 'completed' && !$order->review)
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
      <textarea name="comment" rows="3" maxlength="500" placeholder="Tell other buyers about this product (optional)"></textarea>
      <div class="order-modal-actions"><button type="submit" class="btn btn-primary">Submit Rating</button></div>
    </form>
  </div>
</div>
@endif
<div class="order-details-modal" data-order-summary id="orderDetails{{ $order->id }}" aria-hidden="true">
  <div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="orderDetailsTitle{{ $order->id }}">
    <div class="order-details-head"><div><h3 id="orderDetailsTitle{{ $order->id }}">{{ ($order->items[0]['name'] ?? 'Your order').(count($order->items ?? []) > 1 ? ' + '.(count($order->items) - 1).' more' : '') }}</h3><p class="order-reference">Order reference: <span data-order-reference>{{ $order->order_number }}</span> <button type="button" class="order-copy-reference" aria-label="Copy order reference">Copy</button></p></div><div class="order-details-heading-actions"><button type="button" class="order-details-close order-details-close-icon" aria-label="Close order details">@include('buyer.partials.icon', ['name'=>'x','size'=>18])</button>@if($adminPayment)
<nav class="order-details-capsules" aria-label="Order information">
 <button type="button" class="order-kicker order-details-label {{ $paymentApproved ? 'is-active' : '' }}" data-continue-order-details aria-pressed="{{ $paymentApproved ? 'true' : 'false' }}" @disabled(!$paymentApproved)>Order details</button>
 <span aria-hidden="true">|</span>
 <button type="button" class="order-kicker order-details-label {{ !$paymentApproved ? 'is-active' : '' }}" data-show-payment-progress aria-pressed="{{ !$paymentApproved ? 'true' : 'false' }}">Payment progress</button>
</nav>
@else
<span class="order-kicker order-details-label">Order details</span>
@endif</div></div>
    <div class="order-details-body" tabindex="0" role="region" aria-label="Scrollable order information">
    @php
      $timeline = \App\Support\OrderTimeline::forOrder($order, $orderHistory->get($order->id, collect()));
      $addressPart = fn ($part) => filled($part) && $part !== 'Not provided';
      $addressLine1 = collect([$order->shipping_address['house_no'] ?? null, $order->shipping_address['street'] ?? null])->filter($addressPart)->join(', ');
      $displayAddress = $order->shipping_address ?? [];
      $matchingAddress = collect(auth()->user() ? [auth()->user(), ...\App\Models\BuyerAddress::where('buyer_id', auth()->id())->get()->all()] : [])->first(function ($candidate) use ($displayAddress) {
        return collect(['barangay', 'municipality', 'province'])->every(fn ($field) => filled($displayAddress[$field] ?? null) && strcasecmp(trim($displayAddress[$field]), trim((string) $candidate->$field)) === 0);
      });
      foreach (['region', 'postal_code'] as $field) {
        if (! $addressPart($displayAddress[$field] ?? null) && $matchingAddress) $displayAddress[$field] = $matchingAddress->$field;
      }
      $addressLine2 = collect([$displayAddress['barangay'] ?? null, $displayAddress['municipality'] ?? null, $displayAddress['province'] ?? null, $displayAddress['postal_code'] ?? null])->filter($addressPart)->join(', ');
      $customerName = trim((string) ($order->shipping_address['recipient_name'] ?? ''))
        ?: trim(($order->buyer?->given_names ?? '').' '.($order->buyer?->last_name ?? ''))
        ?: 'Customer not provided';
      $customerContact = $order->shipping_address['contact_no'] ?? $order->buyer?->contact_no;
      $paymentHistory = $adminPayment ? $paymentHistories->get($order->gcashPurchase->id, collect()) : collect();
      $paymentApprovalEvent = $paymentHistory->whereIn('event', ['admin_verified', 'order_placed'])->last();
      if ($paymentApproved) {
        $placedIndex = collect($timeline['rows'])->search(fn ($step) => $step['title'] === 'Order placed');
        if ($placedIndex !== false) $timeline['rows'][$placedIndex]['state'] = 'completed';
        $sellerIndex = collect($timeline['rows'])->search(fn ($step) => $step['title'] === 'Seller confirmed');
        array_splice($timeline['rows'], $sellerIndex === false ? 1 : $sellerIndex, 0, [[
          'title' => 'Payment approved',
          'state' => $order->status === 'placed' ? 'current' : 'completed',
          'timestamp' => $paymentApprovalEvent?->created_at ? \Illuminate\Support\Carbon::parse($paymentApprovalEvent->created_at) : null,
        ]]);
      }
    @endphp
    @if($adminPayment)
      @include('buyer.partials.order-gcash-payment', [
        'order' => $order,
        'purchase' => $order->gcashPurchase,
        'paymentHistory' => $paymentHistory,
        'paymentProofList' => $paymentProofs->get($order->gcashPurchase->id, collect()),
      ])
    @endif
    <div data-approved-order-content tabindex="-1">
    @if(!$adminPayment || $paymentApproved)
    <section class="order-tracking order-tracking-horizontal" aria-label="Delivery progress">
     <ol class="tracking-steps" aria-label="Recorded delivery progress">
      @foreach($timeline['rows'] as $step)
       <li class="tracking-step is-{{ $step['state'] }}" @if($step['state'] === 'current') aria-current="step" @endif>
        <span class="tracking-dot" aria-hidden="true">
          @include('buyer.partials.icon', ['name' => match ($step['title']) { 'Order placed' => 'bag', 'Payment approved' => 'shield', 'Seller confirmed' => 'clipboard-check', 'Preparation & pickup' => 'package', 'In transit' => 'map', 'Out for delivery' => 'rider-delivery', default => $order->status === 'completed' ? 'shield' : 'package-received' }, 'size' => 18])
        </span>
        <div><strong>{{ $step['title'] }}</strong>
         @if($step['timestamp'])<time datetime="{{ $step['timestamp']->toIso8601String() }}">{{ $step['timestamp']->format('M j, Y g:i A') }}</time>@endif
        </div>
       </li>
      @endforeach
     </ol>
    </section>
    @endif
    <div class="order-summary-columns"><div class="order-quantity-summary order-checkout-summary">
      <h3>Order summary</h3>
      <div class="order-cost-row"><span>Subtotal</span><strong>₱{{ number_format($order->subtotal, 2) }}</strong></div>
      <div class="order-cost-row"><span>Shipping</span><strong>{{ (float) $order->shipping_amount > 0 ? '₱'.number_format($order->shipping_amount, 2) : 'Free' }}</strong></div>
      <div class="order-cost-row order-cost-discount"><span>Discounts</span><strong>{{ (float) $order->discount_amount > 0 ? '−' : '' }}₱{{ number_format($order->discount_amount, 2) }}</strong></div>
      <div class="order-cost-total"><span><strong>Total</strong><small>{{ $quantity = collect($order->items)->sum('qty') }} {{ $quantity === 1 ? 'item' : 'items' }}</small></span><strong>₱{{ number_format($order->total, 2) }}</strong></div>
    </div>
    <div class="order-detail-info-grid">
      <div><span>Customer name</span><strong>{{ $customerName }}</strong></div>
      <div><span>Contact number</span><strong>{{ $customerContact ?: 'Not provided' }}</strong></div>
      <div><span>Date &amp; time</span><strong>{{ $order->created_at?->format('M d, Y · g:i A') ?? '—' }}</strong></div>
      <div><span>Payment method</span><strong>{{ $order->paymentMethod?->name ?: $order->payment_method ?: 'Payment not provided' }}</strong></div>
      <div class="order-address-info"><span>Delivery address</span><address>@if($addressLine1){{ $addressLine1 }}<br>@endif{{ $addressLine2 ?: ($addressLine1 ? '' : 'Address not provided') }}</address></div>
      @if($order->buyer_note)<div class="order-address-info"><span>Message for seller</span><strong>{{ $order->buyer_note }}</strong></div>@endif
    </div>
    <section class="order-products-summary" aria-label="Ordered products">
      <h3>Products</h3>
      <table>
        <tbody>
          @foreach($order->itemsWithImages() as $item)
          @php
            $variant = collect([$item['variation_group'] ?? null, $item['variation_value'] ?? $item['color'] ?? null, $item['size'] ?? null])->filter()->unique()->join(': ');
          @endphp
          <tr>
            <td>@include('buyer.partials.order-product-summary', ['item'=>$item])</td>
            <td>{{ $item['qty'] }} × ₱{{ number_format($item['original_price'] ?? $item['price'], 2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </section>
    </div>
    @if($order->status === 'out_for_delivery' && $order->shipment?->delivery_otp_ciphertext)
      @php try { $deliveryOtp = \Illuminate\Support\Facades\Crypt::decryptString($order->shipment->delivery_otp_ciphertext); } catch (\Throwable) { $deliveryOtp = null; } @endphp
      @if($deliveryOtp)<div class="order-current-status"><strong>Delivery OTP: {{ $deliveryOtp }}</strong><p>Share this only after you receive the order.</p></div>@endif
    @endif
    @if($order->shipment?->delivery_proof_file)
      <div class="order-current-status"><strong>Proof of delivery</strong><p><a href="{{ route('delivery-proofs.show', $order->shipment) }}" target="_blank" rel="noopener">View delivery photo</a></p></div>
    @endif
    </div>
    @if(in_array($order->status, \App\Models\Order::CANCELLABLE_STATUSES, true))
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
      <div class="order-cancelled-note">{{ preg_match('/^Cancelled\b/i', $order->cancellation_reason ?? '') ? $order->cancellation_reason : 'Cancelled: '.($order->cancellation_reason ?: 'Reason not provided') }}</div>
    @endif
    </div>
    <div class="order-details-footer" data-approved-order-footer @if(!in_array($order->status, \App\Models\Order::CANCELLABLE_STATUSES, true)) hidden @endif>
      @if(in_array($order->status, \App\Models\Order::CANCELLABLE_STATUSES, true))<button type="button" class="btn btn-danger order-cancel-open">Cancel Order</button>@endif
    </div>
  </div>
</div>
@if(in_array($order->status, \App\Models\Order::CANCELLABLE_STATUSES, true))
<dialog class="order-cancel-confirm" id="cancelConfirm{{ $order->id }}" aria-labelledby="cancelTitle{{ $order->id }}" aria-describedby="cancelConsequences{{ $order->id }}">
 <h3 id="cancelTitle{{ $order->id }}">Cancel this order?</h3>
 <p id="cancelConsequences{{ $order->id }}">Cancellation stops fulfillment of this order. Stock adjustments will follow the existing order rules.
 @if($order->gcash_purchase_id) @if($adminPayment) If payment was sent, PocketFinds will review any refund through the platform payment process. @else Your GCash payment is not automatically refunded. Contact the seller to reconcile any money already received. @endif @endif</p>
 <div class="order-cancel-selected-reason"><span>Cancellation reason</span><strong data-cancel-reason></strong></div>
 <div class="order-modal-actions"><button type="button" class="btn btn-outline" data-keep-order autofocus>Keep Order</button><button type="button" class="btn btn-danger" data-confirm-cancellation>Confirm Cancellation</button></div>
</dialog>
@endif
@empty
<div class="card"><div class="card-pad"><div class="empty"><div class="ic">@include('buyer.partials.icon', ['name' => 'package', 'size' => 28])</div><h3>No orders yet</h3><p>Orders with status "{{ str_replace('_', ' ', ucfirst($tab)) }}" will appear here.</p>@if($tab === 'to_ship')<a href="{{ route('buyer.browse') }}" class="btn btn-primary" style="margin-top:14px">Start Shopping</a>@endif</div></div></div>
@endforelse
</div>
</div>
</div>
@endsection

@push('head')
<link rel="stylesheet" href="{{ asset('css/order-details.css') }}?v={{ filemtime(public_path('css/order-details.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buyer-orders.css') }}?v={{ filemtime(public_path('css/buyer-orders.css')) }}">
<script src="{{ asset('js/order-details.js') }}?v={{ filemtime(public_path('js/order-details.js')) }}" defer></script>
<script src="{{ asset('js/order-review-media.js') }}?v={{ filemtime(public_path('js/order-review-media.js')) }}" defer></script>
<script src="{{ asset('js/buyer-order-gcash.js') }}?v={{ filemtime(public_path('js/buyer-order-gcash.js')) }}" defer></script>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('orderSearch');
  if (!search) return;

  const cards = [...document.querySelectorAll('[data-order-search]')];
  search.addEventListener('input', () => {
    const query = search.value.trim().toLowerCase();
    cards.forEach((card) => {
      card.hidden = Boolean(query) && !card.dataset.orderSearch.includes(query);
    });
  });
});
</script>
@if($openOrderDetails)<script>document.addEventListener('DOMContentLoaded',()=>{const id='orderDetails'+{{ Illuminate\Support\Js::from((string) $openOrderDetails) }};[...document.querySelectorAll('[data-order-details]')].find(button=>button.dataset.orderDetails===id)?.click();});</script>@endif
@endpush
