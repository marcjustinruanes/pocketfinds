<div class="order-cost-row"><span>Product subtotal</span><strong>PHP {{ number_format($order->subtotal, 2) }}</strong></div>
@if($detail['product_discount_cents'] !== null)
<div class="order-cost-row"><span>Product discounts</span><strong>− PHP {{ number_format($detail['product_discount_cents']/100, 2) }}</strong></div>
@endif
<div class="order-cost-row"><span>Delivery — this parcel</span><strong>PHP {{ number_format($order->shipping_amount, 2) }}</strong></div>
@if($detail['shipping_discount_cents'] !== null)
<div class="order-cost-row"><span>Shipping discounts</span><strong>− PHP {{ number_format($detail['shipping_discount_cents']/100, 2) }}</strong></div>
@else
<div class="order-cost-row"><span>Discounts (combined)</span><strong>− PHP {{ number_format($order->discount_amount, 2) }}</strong></div>
@endif
<div class="order-cost-total"><strong>Order total</strong><strong>PHP {{ number_format($order->total, 2) }}</strong></div>
