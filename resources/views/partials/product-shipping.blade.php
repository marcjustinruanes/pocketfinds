@if(\App\Services\DeliveryGuarantee::active())
@php($delivery = \App\Services\DeliveryGuarantee::forProduct((int) $product['seller_id'], auth()->user()))
<div class="pd-shipping" aria-label="Shipping" @if(!empty($delivery['by'])) title="Delivery estimate for your saved address" @endif>
  <svg class="pd-shipping-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h11v12H3zM14 9h4l3 4v4h-3M7 17h7"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>
  <span class="pd-delivery-guarantee">{{ $delivery['label'] }}</span>
</div>
@endif
