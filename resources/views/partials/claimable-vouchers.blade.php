@if($claimableVouchers->isNotEmpty())
<section class="claim-vouchers" aria-label="Shop vouchers">
 <div class="claim-vouchers-heading"><h3>Shop vouchers</h3><span>{{ $claimableVouchers->count() }}</span></div>
 <div class="claim-vouchers-list">
 @foreach($claimableVouchers as $voucher)
  <article class="claim-voucher {{ $voucher->claimed ? 'is-claimed' : '' }}">
   <span class="claim-voucher-icon" aria-hidden="true">@if($voucher->kind==='shipping')@include('buyer.partials.icon',['name'=>'truck','size'=>24])@else @include('partials.discount-icon') @endif</span>
   <div class="claim-voucher-copy">
    <div class="claim-voucher-topline"><span>{{ $product['seller'] ?? 'Shop voucher' }}</span><b>{{ $voucher->code }}</b><em>{{ $voucher->kind==='shipping' ? 'Shipping' : 'Store' }}</em></div>
    <div class="claim-voucher-terms"><strong>{{ $voucher->discount_type==='percent' ? number_format($voucher->amount,0).'%' : '₱'.number_format($voucher->amount,2) }} {{ $voucher->kind==='shipping' ? 'off shipping' : 'off' }}</strong><small>Min. spend ₱{{ number_format($voucher->minimum_spend,2) }}@if($voucher->cap) · Up to ₱{{ number_format($voucher->cap,2) }}@endif</small><small class="claim-voucher-expiry">Use by {{ \Illuminate\Support\Carbon::parse($voucher->ends_at)->format('M j, Y') }}</small></div>
   </div>
   @if(auth()->check() && auth()->user()->account_type==='buyer')
    <form method="POST" action="{{ route('buyer.commerce.claim',$voucher->id) }}">@csrf<button type="submit" class="claim-voucher-button" aria-label="{{ $voucher->used ? 'Used' : ($voucher->claimed ? 'Claimed' : 'Claim') }} voucher {{ $voucher->code }}" @disabled($voucher->claimed || $voucher->used)>{{ $voucher->used ? 'Used' : ($voucher->claimed ? 'Claimed' : 'Claim') }}</button></form>
   @else<button type="button" class="claim-voucher-button" data-protected aria-label="Sign in to claim voucher {{ $voucher->code }}">Claim</button>@endif
  </article>
 @endforeach
 </div>
</section>
@once
<style>
.claim-vouchers{margin:12px 0;min-width:0}
.claim-vouchers-heading{display:flex;align-items:center;gap:7px;margin-bottom:8px}.claim-vouchers-heading h3{margin:0;font:650 12px/1.4 Inter,system-ui,sans-serif;color:#593346}
.claim-vouchers-heading>span{color:#947e8c;font:600 11px/1.4 Inter,system-ui,sans-serif}
.claim-vouchers-list{display:flex;flex-direction:column;gap:7px;max-height:230px;overflow-y:auto;scrollbar-width:none;padding:1px}.claim-vouchers-list::-webkit-scrollbar{display:none}
.claim-voucher{display:grid;grid-template-columns:40px minmax(0,1fr) auto;align-items:center;gap:11px;min-width:0;padding:10px 12px;border:1px solid #eadde5;border-radius:12px;background:#fff}
.claim-voucher-icon{display:grid;place-items:center;width:38px;height:38px;border:1px solid #f0d9e5;border-radius:9px;background:#fcf3f8;color:#bc387f}.claim-voucher-icon svg{max-width:25px;max-height:25px}
.claim-voucher-copy{display:flex;flex-direction:column;gap:5px;min-width:0;font-family:Inter,system-ui,sans-serif}
.claim-voucher-topline,.claim-voucher-terms{display:flex;align-items:baseline;flex-wrap:wrap;gap:3px 10px;min-width:0}
.claim-voucher-topline>span{font-size:11px;font-weight:650;color:#593346;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:45%}.claim-voucher-topline>b{font-size:11px;color:#593346;overflow-wrap:anywhere}.claim-voucher-topline>em{font-size:10px;font-style:normal;color:#8c7381;border:1px solid #f0e5eb;border-radius:999px;padding:1px 6px}
.claim-voucher-terms>strong{font-size:13px;line-height:1.4;color:#bc387f;white-space:nowrap}.claim-voucher-terms>small{font-size:10px;line-height:1.4;color:#8c7381;white-space:nowrap}
.claim-voucher form{margin:0}.claim-voucher-button{min-height:31px;padding:5px 11px;border:1px solid #e6bcd3;border-radius:8px;background:#fff;color:#a52d6e;font:600 11px/1.4 Inter,system-ui,sans-serif;cursor:pointer;white-space:nowrap}.claim-voucher-button:hover{background:#fcf1f7}.claim-voucher-button:focus-visible{outline:2px solid #bc387f;outline-offset:3px}
.claim-voucher.is-claimed .claim-voucher-button:disabled{opacity:1;border-color:#c7e9d3;border-radius:999px;background:#f2fbf5;color:#267548}.claim-voucher-button:disabled{opacity:.65;cursor:default}
@media(max-width:600px){.claim-voucher{grid-template-columns:32px minmax(0,1fr) auto;gap:8px;padding:9px}.claim-voucher-icon{width:30px;height:30px}.claim-voucher-terms>small{white-space:normal}}
</style>
@endonce
@endif
