@php
  $timeline=\App\Support\OrderTimeline::forOrder($order,$orderHistory->get($order->id,collect()));
  $summaryAddress=collect([$order->shipping_address['house_no']??null,$order->shipping_address['street']??null,$order->shipping_address['barangay']??null,$order->shipping_address['municipality']??null,$order->shipping_address['province']??null,$order->shipping_address['postal_code']??null])->filter(fn($part)=>filled($part)&&$part!=='Not provided')->join(', ');
@endphp
<div class="modal-overlay order-details-modal seller-order-summary-modal" data-order-summary id="sellerOrderDetails{{ $order->id }}">
  <div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="sellerOrderTitle{{ $order->id }}">
    <div class="order-details-head"><div><h3 id="sellerOrderTitle{{ $order->id }}">{{ ($order->items[0]['name']??'Your order').(count($order->items??[])>1?' + '.(count($order->items)-1).' more':'') }}</h3><p class="order-reference">Order reference: <span>{{ $order->order_number }}</span> <button type="button" class="order-copy-reference" data-seller-order-copy="{{ $order->order_number }}" aria-label="Copy order reference">Copy</button></p></div><div class="order-details-heading-actions"><button type="button" class="order-details-close-icon" data-modal-close aria-label="Close order details">@include('buyer.partials.icon', ['name'=>'x','size'=>18])</button><span class="order-kicker order-details-label">Order details</span></div></div>
    <div class="order-details-body" tabindex="0" role="region" aria-label="Scrollable order information">
      <section class="order-tracking order-tracking-horizontal" aria-label="Delivery progress"><ol class="tracking-steps">
        @foreach($timeline['rows'] as $step)
        <li class="tracking-step is-{{ $step['state'] }}" @if($step['state']==='current') aria-current="step" @endif>
          <span class="tracking-dot" aria-hidden="true">@include('buyer.partials.icon',['name'=>['bag','clipboard-check','package','map','rider-delivery',$order->status==='completed'?'shield':'package-received'][$loop->index],'size'=>21])</span>
          <div><strong>{{ $step['title'] }}</strong>@if($step['timestamp'])<time datetime="{{ $step['timestamp']->toIso8601String() }}">{{ $step['timestamp']->format('M j, Y g:i A') }}</time>@endif</div>
        </li>
        @endforeach
      </ol></section>
      @php $detail = $orderDetails->get($order->id); @endphp
      @include('orders.partials.payment')
      @include('orders.partials.settlement')
      @include('orders.partials.refund')
      <div class="order-summary-columns">
        <div class="order-quantity-summary order-checkout-summary">
          <h3>Order summary</h3>
          @include('orders.partials.costs')
        </div>
        <div class="order-detail-info-grid">
          <div><span>Customer name</span><strong>{{ $customerName }}</strong></div>
          <div><span>Contact number</span><strong>{{ $customerContact?:'Not provided' }}</strong></div>
          <div><span>Date &amp; time</span><strong>{{ $orderedAt }}</strong></div>
          <div><span>Payment method</span><strong>{{ $order->paymentMethod?->name?:$order->payment_method?:'Not provided' }}</strong></div>
          <div class="order-address-info"><span>Delivery address</span><address>{{ $summaryAddress?:'Address not provided' }}</address></div>
          @if($order->buyer_note)<div class="order-address-info"><span>Message for seller</span><strong>{{ $order->buyer_note }}</strong></div>@endif
        </div>
        <section class="order-products-summary" aria-label="Ordered products"><h3>Products</h3><table><tbody>
          @foreach($items as $item)
          @php
            $summaryVariant=collect([$item['variation_group']??null,$item['variation_value']??$item['color']??null,$item['size']??null])->filter()->unique()->join(': ');
          @endphp
          <tr><td><div class="order-summary-product"><div class="order-summary-product-image">@if(!empty($item['img']))<img src="{{ $item['img'] }}" alt="{{ $item['name']??'Product' }}" loading="lazy">@else @include('seller.partials.icon',['name'=>'package','size'=>22]) @endif</div><div><strong>{{ $item['name']??'Product not provided' }}</strong>@if($summaryVariant)<small>{{ $summaryVariant }}</small>@endif</div></div></td><td>{{ $item['qty'] }} × ₱{{ number_format($item['price'],2) }}<br><small>Line total: PHP {{ number_format($item['qty'] * $item['price'], 2) }}</small></td></tr>
          @endforeach
        </tbody></table></section>
      </div>
      @if($order->status==='cancelled')<p class="seller-order-cancel-note">{{ preg_match('/^Cancelled\b/i', $order->cancellation_reason ?? '') ? $order->cancellation_reason : 'Cancelled: '.($order->cancellation_reason ?: 'Reason not provided') }}@if($order->cancellation_note)<br>{{ $order->cancellation_note }}@endif</p>@endif
      @if($shipment)<div class="seller-order-shipment-info"><span>Tracking: <strong>{{ $shipment->tracking_number }}</strong></span><span>Logistics company: <strong>{{ $shipment->logistics_company ?: 'Not assigned' }}</strong></span>@if($shipment->pickupRider)<span>Pickup rider: {{ $shipment->pickupRider->given_names }} {{ $shipment->pickupRider->last_name }}</span>@endif @if($shipment->courier)<span>Delivery rider: {{ $shipment->courier->given_names }} {{ $shipment->courier->last_name }}</span>@endif @if($shipment->scheduled_pickup_at)<span>Scheduled pickup: {{ $shipment->scheduled_pickup_at->format('M j, Y g:i A') }}</span>@endif</div>@endif
      @include('seller.partials.order-cancel')
    </div>
    <div class="order-details-footer">
      @include('seller.partials.order-actions')
    </div>
  </div>
</div>
