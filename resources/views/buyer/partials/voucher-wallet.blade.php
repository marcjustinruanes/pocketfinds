<div class="card voucher-wallet-card" id="my-vouchers">
 <div class="card-head"><div><h2>My Vouchers</h2><p>Unused vouchers from shops you follow, ready to use at checkout.</p></div></div>
 <div class="card-pad">
  <div class="voucher-wallet-list">
   @forelse($walletVouchers as $voucher)
    <article class="wallet-voucher">
     <div class="wallet-voucher-mark {{ $voucher->kind==='shipping' ? '' : 'wallet-discount-icon' }}" aria-hidden="true">@if($voucher->kind==='shipping')<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h11v12H3V5Zm11 5h4l3 4v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>@else @include('partials.discount-icon',['size'=>28]) @endif</div>
     <div class="wallet-voucher-details">
      <div class="wallet-voucher-topline"><span class="wallet-voucher-shop">{{ $voucher->business_name ?: trim($voucher->given_names.' '.$voucher->last_name) }}</span><strong class="wallet-voucher-code">{{ $voucher->code }}</strong><span class="wallet-voucher-kind">{{ $voucher->kind==='shipping' ? 'Shipping' : ucfirst($voucher->kind) }}</span></div>
      <div class="wallet-voucher-summary"><strong class="wallet-voucher-value">{{ $voucher->discount_type==='percent' ? number_format($voucher->amount,0).'%' : '₱'.number_format($voucher->amount,2) }} {{ $voucher->kind==='shipping' ? 'off shipping' : 'off' }}</strong><span class="wallet-voucher-terms">Min. spend ₱{{ number_format($voucher->minimum_spend,2) }}</span><span class="wallet-voucher-dates">Use by {{ \Illuminate\Support\Carbon::parse($voucher->ends_at)->format('M j, Y') }}</span></div>
     </div>
     @if($voucher->claim_id)
      <span class="wallet-voucher-available">Saved</span>
     @else
      <form method="POST" action="{{ route('buyer.commerce.claim', $voucher->id) }}">@csrf
       <button class="btn btn-outline wallet-voucher-claim" type="submit">Claim</button>
      </form>
     @endif
    </article>
   @empty
    <div class="voucher-wallet-empty"><strong>No available vouchers</strong><p>Claim an unused voucher from a shop you follow and it will appear here while it is valid.</p></div>
   @endforelse
  </div>
 </div>
</div>
