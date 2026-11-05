@php
  $walletVouchers=\Illuminate\Support\Facades\DB::table('commerce_vouchers')->when(\App\Services\DeliveryGuarantee::regionalPoliciesInstalled(), fn($q)=>$q->where('code','!=',\App\Services\DeliveryGuarantee::FREE_SHIPPING_CODE))->where('is_active',true)->where('ends_at','>',now())->get();
  $walletVouchers=$walletVouchers->filter(fn($voucher)=>\App\Services\DeliveryGuarantee::voucherBelongsTo((int)$voucher->id,(int)auth()->id()));
  $walletClaims=\Illuminate\Support\Facades\DB::table('commerce_claims')->where('buyer_id',auth()->id())->pluck('voucher_id')->all();
@endphp
<div class="card voucher-wallet-card" id="my-vouchers">
 <div class="card-head"><div><h2>My Vouchers</h2><p>Claim vouchers here and select them when checking out in your cart.</p></div></div>
 <div class="card-pad">
  <div class="voucher-wallet-list">
   @forelse($walletVouchers as $voucher)
    @php($claimed = in_array($voucher->id, $walletClaims))
    @php($comingSoon = now()->lt($voucher->starts_at))
    <article class="wallet-voucher">
     <div class="wallet-voucher-mark" aria-hidden="true">%</div>
     <div class="wallet-voucher-details">
      <div class="wallet-voucher-topline"><strong class="wallet-voucher-code">{{ $voucher->code }}</strong><span class="wallet-voucher-kind">{{ ucfirst($voucher->kind) }}</span></div>
      <strong class="wallet-voucher-value">{{ $voucher->discount_type==='percent' ? number_format($voucher->amount,0).'%' : '₱'.number_format($voucher->amount,2) }} off</strong>
      <span class="wallet-voucher-terms">Minimum spend ₱{{ number_format($voucher->minimum_spend,2) }}</span>
      <span class="wallet-voucher-dates">{{ $voucher->starts_at }} – {{ $voucher->ends_at }}</span>
     </div>
     <form method="POST" action="{{ route('buyer.commerce.claim',$voucher->id) }}">
      @csrf
      <button class="btn btn-outline wallet-voucher-claim" @disabled($claimed || $comingSoon)>{{ $claimed ? 'Claimed' : ($comingSoon ? 'Coming soon' : 'Claim voucher') }}</button>
     </form>
    </article>
   @empty
    <div class="voucher-wallet-empty"><strong>No vouchers available</strong><p>New vouchers will appear here when they become available.</p></div>
   @endforelse
  </div>
 </div>
</div>
