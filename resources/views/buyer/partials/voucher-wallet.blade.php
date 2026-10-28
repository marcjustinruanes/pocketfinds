@php
  $walletVouchers=\Illuminate\Support\Facades\DB::table('commerce_vouchers')->where('is_active',true)->where('ends_at','>',now())->get();
  $walletClaims=\Illuminate\Support\Facades\DB::table('commerce_claims')->where('buyer_id',auth()->id())->pluck('voucher_id')->all();
@endphp
<div class="card" id="my-vouchers"><div class="card-head"><div><h2>My Vouchers</h2><p>Claim vouchers here and select them when checking out in your cart.</p></div></div><div class="card-pad">
@forelse($walletVouchers as $voucher)
<div class="order-product-row"><div class="order-product-copy"><strong>{{ $voucher->code }}</strong><span>{{ ucfirst($voucher->kind) }} · {{ number_format($voucher->amount,2) }}{{ $voucher->discount_type==='percent'?'%':' PHP' }} off</span><small>Minimum ₱{{ number_format($voucher->minimum_spend,2) }} · {{ $voucher->starts_at }} – {{ $voucher->ends_at }}</small></div>
<form method="POST" action="{{ route('buyer.commerce.claim',$voucher->id) }}">@csrf<button class="btn btn-outline" @disabled(in_array($voucher->id,$walletClaims)||now()->lt($voucher->starts_at))>{{ in_array($voucher->id,$walletClaims)?'Claimed':(now()->lt($voucher->starts_at)?'Coming soon':'Claim') }}</button></form></div>
@empty<p>No vouchers are available right now.</p>@endforelse
</div></div>
