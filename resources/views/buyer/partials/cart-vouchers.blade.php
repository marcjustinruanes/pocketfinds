@php($cartSellerIds=\App\Models\Product::whereIn('id',$items->pluck('product_id'))->pluck('seller_id')->unique())
@php($checkoutVouchers=\Illuminate\Support\Facades\DB::table('commerce_vouchers')->where('is_active',true)->where('starts_at','<=',now())->where('ends_at','>',now())->where(fn($q)=>$q->whereIn('seller_id',$cartSellerIds)->orWhereNull('seller_id'))->get())
<div class="form-row"><h3 class="cart-section-title">Vouchers & discounts</h3>
@foreach($groups as $slug=>$shopItems)
@php($voucherSeller=\App\Models\Product::whereKey($shopItems->first()['product_id'])->value('seller_id'))
<label data-voucher-group>Store voucher · {{ $shopItems->first()['seller'] }}<select class="select" name="vouchers[store_{{ $voucherSeller }}]" form="checkoutForm" data-checkout-voucher data-voucher-seller="{{ $voucherSeller }}"><option value="">None</option>@foreach($checkoutVouchers->where('kind','store')->where('seller_id',$voucherSeller) as $v)<option value="{{ $v->code }}" data-voucher-seller="{{ $v->seller_id }}">{{ $v->code }} · {{ number_format($v->amount,2) }}{{ $v->discount_type === 'percent' ? '%' : ' PHP' }} off · Min. ₱{{ number_format($v->minimum_spend,2) }}</option>@endforeach</select></label>
@endforeach
@foreach(['platform'=>'Platform voucher','shipping'=>'Shipping voucher'] as $kind=>$label)
<label data-voucher-group>{{ $label }}<select class="select" name="vouchers[{{ $kind }}]" form="checkoutForm" data-checkout-voucher><option value="">None</option>@foreach($checkoutVouchers->where('kind',$kind) as $v)<option value="{{ $v->code }}" data-voucher-seller="{{ $v->seller_id }}">{{ $v->code }} · {{ number_format($v->amount,2) }}{{ $v->discount_type === 'percent' ? '%' : ' PHP' }} off · Min. ₱{{ number_format($v->minimum_spend,2) }}</option>@endforeach</select></label>
@endforeach
<p id="shippingVoucherStatus" role="status" aria-live="polite" style="font-size:13px;margin:6px 0">Select a shipping voucher to apply its discount. Claiming it does not apply it automatically.</p>
<small>Choose one per type. A new selection replaces the previous one.</small><a href="{{ route('buyer.account') }}#my-vouchers">My Vouchers</a></div>
