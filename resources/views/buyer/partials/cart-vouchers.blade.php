@php($cartSellerIds=\App\Models\Product::whereIn('id',$items->pluck('product_id'))->pluck('seller_id')->unique())
@php($checkoutVouchers=\Illuminate\Support\Facades\DB::table('commerce_vouchers')->when(\App\Services\DeliveryGuarantee::regionalPoliciesInstalled(), fn($q)=>$q->where('code','!=',\App\Services\DeliveryGuarantee::FREE_SHIPPING_CODE))->where('is_active',true)->where('starts_at','<=',now())->where('ends_at','>',now())->where(fn($q)=>$q->whereIn('seller_id',$cartSellerIds)->orWhereNull('seller_id'))->get()->filter(fn($voucher)=>\App\Services\DeliveryGuarantee::voucherBelongsTo((int)$voucher->id,(int)auth()->id())))
<div class="form-row"><div class="cart-vouchers-heading"><h3 class="cart-section-title">Vouchers & discounts</h3><button class="cart-view-vouchers" type="button" data-modal-open="checkoutVouchersModal">View offers</button></div>
@foreach($groups as $slug=>$shopItems)
@php($voucherSeller=\App\Models\Product::whereKey($shopItems->first()['product_id'])->value('seller_id'))
<label data-voucher-group>Store voucher · {{ $shopItems->first()['seller'] }}<select class="select" name="vouchers[store_{{ $voucherSeller }}]" form="checkoutForm" data-checkout-voucher data-voucher-seller="{{ $voucherSeller }}"><option value="">None</option>@foreach($checkoutVouchers->where('kind','store')->where('seller_id',$voucherSeller) as $v) @include('buyer.partials.checkout-voucher-option',['v'=>$v]) @endforeach</select></label>
@endforeach
@foreach(['platform'=>'Platform voucher','shipping'=>'Shipping voucher'] as $kind=>$label)
@continue($kind==='shipping' && $checkoutVouchers->where('kind','shipping')->isEmpty())
<label data-voucher-group>{{ $label }}<select class="select" name="vouchers[{{ $kind }}]" form="checkoutForm" data-checkout-voucher><option value="">None</option>@foreach($checkoutVouchers->where('kind',$kind) as $v) @include('buyer.partials.checkout-voucher-option',['v'=>$v]) @endforeach</select></label>
@endforeach
</div>
<div class="modal-overlay cart-offers-modal" id="checkoutVouchersModal" aria-hidden="true"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="checkoutVouchersTitle">
  <div class="modal-head"><div><h3 id="checkoutVouchersTitle">Vouchers & discounts</h3><p>Offers for the shops in your cart.</p></div><button type="button" class="modal-close" data-modal-close aria-label="Close offers">&times;</button></div>
  <div class="modal-body cart-offers-list">
    @forelse($checkoutVouchers as $offer)
    <article class="cart-checkout-offer" data-voucher-offer-code="{{ $offer->code }}">
      <span class="cart-offer-icon" aria-hidden="true">@if($offer->kind==='shipping')@include('buyer.partials.icon',['name'=>'package','size'=>25])@else @include('partials.discount-icon',['size'=>28]) @endif</span>
      <div class="cart-offer-copy"><strong>{{ $offer->code }}</strong><span class="cart-offer-amount">{{ $offer->discount_type==='percent' ? number_format($offer->amount,0).'%' : '₱'.number_format($offer->amount,2) }} {{ $offer->kind==='shipping' ? 'off shipping' : 'off' }}</span><small>Min. spend ₱{{ number_format($offer->minimum_spend,2) }} · Until {{ \Illuminate\Support\Carbon::parse($offer->ends_at)->format('M j, Y') }}</small><p data-voucher-offer-reason role="status">Checking selected items…</p></div>
      <button class="btn btn-outline" type="button" data-checkout-voucher-apply="{{ $offer->code }}" disabled>Apply</button>
    </article>
    @empty<p class="cart-offers-empty">No active offers for these shops.</p>@endforelse
  </div>
</section></div>
