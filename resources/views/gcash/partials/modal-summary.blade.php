@php
 $address=$p->snapshot['shipping_address']??[];
 $deliveryAddress=collect(['house_no','street','barangay','municipality','province','postal_code'])->map(fn($key)=>$address[$key]??null)->filter(fn($value)=>filled($value)&&$value!=='Not provided')->join(', ');
 $modalItems=collect($p->snapshot['items']??[]);
@endphp
<div class="order-summary-columns gcash-modal-summary">
 <section class="order-quantity-summary order-checkout-summary"><h3>Order summary</h3>
  <div class="order-cost-row"><span>Subtotal</span><strong>₱{{ number_format($p->snapshot['subtotal']??0,2) }}</strong></div>
  <div class="order-cost-row"><span>Shipping</span><strong>₱{{ number_format($p->snapshot['shipping_amount']??0,2) }}</strong></div>
  <div class="order-cost-row order-cost-discount"><span>Discounts</span><strong>₱{{ number_format($p->snapshot['discount_amount']??0,2) }}</strong></div>
  <div class="order-cost-total"><span><strong>Total</strong><small>{{ $modalItems->sum('qty') }} items</small></span><strong>₱{{ number_format($p->amount_due,2) }}</strong></div>
 </section>
 <section class="order-detail-info-grid">
  <div><span>Customer name</span><strong>{{ $address['recipient_name']??trim(($other?->given_names??'').' '.($other?->last_name??'')) }}</strong></div>
  <div><span>Contact number</span><strong>{{ $address['contact_no']??$other?->contact_no??'Not provided' }}</strong></div>
  <div><span>Date &amp; time</span><strong>{{ $p->created_at?->format('M d, Y · g:i A') }}</strong></div>
  <div><span>Payment method</span><strong>GCash</strong></div>
  <div class="order-address-info"><span>Delivery address</span><address>{{ $deliveryAddress?:'Not provided' }}</address></div>
 </section>
 <section class="order-products-summary"><h3>Products</h3><table><tbody>
  @foreach($modalItems as $item)
  @php
   $variant=collect([$item['variation_group']??null,$item['variation_value']??null,$item['size']??null])->filter()->unique()->join(': ');
  @endphp
  <tr><td><div class="order-summary-product"><div class="order-summary-product-image">@if(!empty($item['img']))<img src="{{ $item['img'] }}" alt="{{ $item['name']??'Product' }}">@else @include('buyer.partials.icon',['name'=>'package','size'=>22]) @endif</div><div><strong>{{ $item['name']??'Product' }}</strong>@if($variant)<small>{{ $variant }}</small>@endif</div></div></td><td>{{ $item['qty']??1 }} × ₱{{ number_format($item['price']??0,2) }}</td></tr>
  @endforeach
 </tbody></table></section>
</div>
